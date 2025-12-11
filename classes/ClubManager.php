<?php
// ClubManager.php

/**
 * Gestion des clubs de lecture (POO)
 */
class ClubManager
{
    private PDO $pdo;
    private int $userId;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    /* ================== Clubs ================== */

    /**
     * Crée un club et ajoute l'utilisateur courant comme owner.
     * Retourne l'ID du club ou null en cas d'échec.
     */
    public function createClub(string $name, string $description = ''): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO book_clubs (owner_id, name, description)
            VALUES (:owner, :name, :descr)
        ");
        $ok = $stmt->execute([
            ':owner' => $this->userId,
            ':name'  => $name,
            ':descr' => $description,
        ]);

        if (!$ok) {
            return null;
        }

        $clubId = (int) $this->pdo->lastInsertId();

        // Le créateur devient membre "owner"
        $stmt = $this->pdo->prepare("
            INSERT INTO book_club_members (club_id, user_id, role)
            VALUES (:club, :user, 'owner')
        ");
        $stmt->execute([
            ':club' => $clubId,
            ':user' => $this->userId,
        ]);

        return $clubId;
    }

    /**
     * Retourne tous les clubs dont l'utilisateur courant est membre.
     */
    public function getMyClubs(): array
    {
        $sql = "
            SELECT c.id, c.name, c.description, c.created_at, m.role
            FROM book_club_members m
            JOIN book_clubs c ON c.id = m.club_id
            WHERE m.user_id = :uid
            ORDER BY c.created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $this->userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les infos d'un club SI l'utilisateur courant en est membre.
     * Ajoute la clé 'my_role' (owner / member) ou null si pas de droit.
     */
    public function getClub(int $clubId): ?array
    {
        $sql = "
            SELECT c.*, m.role AS my_role
            FROM book_club_members m
            JOIN book_clubs c ON c.id = m.club_id
            WHERE m.user_id = :uid
              AND c.id = :cid
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $this->userId,
            ':cid' => $clubId,
        ]);

        $club = $stmt->fetch(PDO::FETCH_ASSOC);

        return $club ?: null;
    }

    /**
     * Vérifie si l'utilisateur courant est owner du club.
     */
    public function isOwner(int $clubId): bool
    {
        $club = $this->getClub($clubId);
        return $club !== null && $club['my_role'] === 'owner';
    }

    /**
     * Supprime complètement un club (seulement si l'utilisateur courant en est le créateur).
     * Supprime aussi : membres, livres, messages, notifications liées au club.
     */
    public function deleteClub(int $clubId): bool
    {
        if (!$this->isOwner($clubId)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            // Supprimer les messages du club
            $stmt = $this->pdo->prepare("
                DELETE FROM book_club_messages
                WHERE club_id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            // Supprimer les livres du club
            $stmt = $this->pdo->prepare("
                DELETE FROM book_club_books
                WHERE club_id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            // Supprimer les membres du club
            $stmt = $this->pdo->prepare("
                DELETE FROM book_club_members
                WHERE club_id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            // Supprimer les notifications liées au club (invitations, etc.)
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications
                WHERE club_id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            // Supprimer le club lui-même
            $stmt = $this->pdo->prepare("
                DELETE FROM book_clubs
                WHERE id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /* ================== Membres ================== */

    /**
     * Liste des membres d'un club (id, login, has_avatar, role, joined_at).
     * L'utilisateur courant doit être membre du club.
     */
    public function getMembers(int $clubId): array
    {
        // On vérifie l'accès au club
        if (!$this->getClub($clubId)) {
            return [];
        }

        $sql = "
            SELECT 
                u.id,
                u.login,
                (u.avatar IS NOT NULL) AS has_avatar,
                m.role,
                m.joined_at
            FROM book_club_members m
            JOIN users u ON u.id = m.user_id
            WHERE m.club_id = :cid
            ORDER BY (m.role = 'owner') DESC, u.login
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $clubId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un membre au club (seulement si l'utilisateur courant est owner).
     */
    public function addMember(int $clubId, int $userId): bool
    {
        if (!$this->isOwner($clubId)) {
            return false;
        }

        // On évite d'ajouter deux fois le même user
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO book_club_members (club_id, user_id, role)
            VALUES (:cid, :uid, 'member')
        ");

        return $stmt->execute([
            ':cid' => $clubId,
            ':uid' => $userId,
        ]);
    }

    /**
     * Retire un membre du club (owner uniquement, et on ne supprime pas l'owner).
     */
    public function removeMember(int $clubId, int $userId): bool
    {
        if (!$this->isOwner($clubId)) {
            return false;
        }

        // On interdit de supprimer le owner via cette méthode
        $stmt = $this->pdo->prepare("
            DELETE FROM book_club_members
            WHERE club_id = :cid
              AND user_id = :uid
              AND role = 'member'
        ");

        return $stmt->execute([
            ':cid' => $clubId,
            ':uid' => $userId,
        ]);
    }

    /* ================== Livres ================== */

    /**
     * Liste des livres d'un club (google_book_id, added_by, added_at + title, authors, thumbnail).
     *
     * On joint sur user_library pour récupérer les méta-données
     * (titre, auteur(s), couverture) du livre ajouté par l'utilisateur
     * (via added_by).
     */
    public function getBooks(int $clubId): array
    {
        if (!$this->getClub($clubId)) {
            return [];
        }

        $sql = "
            SELECT
                b.id,
                b.google_book_id,
                b.added_by,
                b.added_at,
                ul.title,
                ul.authors,
                ul.thumbnail
            FROM book_club_books b
            LEFT JOIN user_library ul
              ON ul.google_book_id = b.google_book_id
             AND ul.user_id       = b.added_by
            WHERE b.club_id = :cid
            ORDER BY b.added_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $clubId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un livre (par son google_book_id) au club.
     * L'utilisateur doit être membre du club.
     */
    public function addBook(int $clubId, string $googleBookId): bool
    {
        $googleBookId = trim($googleBookId);
        if ($googleBookId === '') {
            return false;
        }

        if (!$this->getClub($clubId)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO book_club_books (club_id, google_book_id, added_by)
            VALUES (:cid, :gbid, :uid)
        ");

        return $stmt->execute([
            ':cid'  => $clubId,
            ':gbid' => $googleBookId,
            ':uid'  => $this->userId,
        ]);
    }

    /**
     * Retire un livre du club.
     */
    public function removeBook(int $clubId, string $googleBookId): bool
    {
        $googleBookId = trim($googleBookId);
        if ($googleBookId === '') {
            return false;
        }

        if (!$this->getClub($clubId)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM book_club_books
            WHERE club_id = :cid
              AND google_book_id = :gbid
        ");

        return $stmt->execute([
            ':cid'  => $clubId,
            ':gbid' => $googleBookId,
        ]);
    }
}
