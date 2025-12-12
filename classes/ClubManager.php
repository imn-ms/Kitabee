<?php
// ClubManager.php

/**
 * Class ClubManager
 *
 * Gestionnaire des clubs de lecture du projet Kitabee.
 *
 * Cette classe gère l’ensemble des fonctionnalités liées aux clubs :
 * - création et suppression de clubs,
 * - gestion des membres (owner / membres),
 * - gestion des livres associés aux clubs,
 * - contrôle des droits d’accès (membre / owner).
 *
 * Elle repose sur une approche orientée objet (POO)
 * et utilise PDO pour toutes les interactions avec la base de données.
 *
 * Auteur : Imane MOUSSAOUI
 * Projet : Kitabee
 */
class ClubManager
{
    /**
     * Instance PDO pour l’accès à la base de données.
     *
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Identifiant de l’utilisateur courant.
     *
     * Utilisé pour vérifier les droits (membre / owner)
     * et pour les actions liées aux clubs.
     *
     * @var int
     */
    private int $userId;

    /**
     * Constructeur du ClubManager.
     *
     * @param PDO $pdo    Connexion PDO à la base de données.
     * @param int $userId Identifiant de l’utilisateur courant.
     */
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    /* ================== Clubs ================== */

    /**
     * Crée un nouveau club de lecture.
     *
     * L’utilisateur courant devient automatiquement :
     * - propriétaire du club (owner),
     * - membre du club.
     *
     * @param string $name        Nom du club.
     * @param string $description Description du club (optionnelle).
     *
     * @return int|null Identifiant du club créé ou null en cas d’échec.
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
     * Récupère tous les clubs dont l’utilisateur courant est membre.
     *
     * @return array Liste des clubs avec rôle de l’utilisateur.
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
     * Récupère les informations d’un club si l’utilisateur courant en est membre.
     *
     * Ajoute la clé :
     * - my_role : rôle de l’utilisateur dans le club (owner / member).
     *
     * @param int $clubId Identifiant du club.
     *
     * @return array|null Données du club ou null si accès non autorisé.
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
     * Vérifie si l’utilisateur courant est propriétaire (owner) du club.
     *
     * @param int $clubId Identifiant du club.
     * @return bool
     */
    public function isOwner(int $clubId): bool
    {
        $club = $this->getClub($clubId);
        return $club !== null && $club['my_role'] === 'owner';
    }

    /**
     * Supprime définitivement un club de lecture.
     *
     * Cette action est réservée au propriétaire du club.
     * Sont également supprimés :
     * - les messages du club,
     * - les livres associés,
     * - les membres,
     * - les notifications liées au club.
     *
     * @param int $clubId Identifiant du club.
     *
     * @return bool True si la suppression a réussi, false sinon.
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

            // Supprimer les notifications liées au club
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications
                WHERE club_id = :cid
            ");
            $stmt->execute([':cid' => $clubId]);

            // Supprimer le club
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
     * Récupère la liste des membres d’un club.
     *
     * Informations retournées :
     * - id utilisateur,
     * - login,
     * - avatar_choice,
     * - has_avatar,
     * - rôle dans le club,
     * - date d’adhésion.
     *
     * L’utilisateur courant doit être membre du club.
     *
     * @param int $clubId Identifiant du club.
     * @return array
     */
    public function getMembers(int $clubId): array
    {
        if (!$this->getClub($clubId)) {
            return [];
        }

        $sql = "
            SELECT 
                u.id,
                u.login,
                u.avatar_choice,
                (u.avatar_choice IS NOT NULL) AS has_avatar,
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
     * Ajoute un utilisateur comme membre du club.
     *
     * Action réservée au propriétaire du club.
     *
     * @param int $clubId Identifiant du club.
     * @param int $userId Identifiant de l’utilisateur à ajouter.
     *
     * @return bool
     */
    public function addMember(int $clubId, int $userId): bool
    {
        if (!$this->isOwner($clubId)) {
            return false;
        }

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
     * Retire un membre du club.
     *
     * Cette action est réservée au propriétaire
     * et ne permet pas de supprimer l’owner.
     *
     * @param int $clubId Identifiant du club.
     * @param int $userId Identifiant de l’utilisateur à retirer.
     *
     * @return bool
     */
    public function removeMember(int $clubId, int $userId): bool
    {
        if (!$this->isOwner($clubId)) {
            return false;
        }

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
     * Récupère la liste des livres associés à un club.
     *
     * Les métadonnées du livre (titre, auteurs, couverture)
     * sont récupérées depuis la bibliothèque de l’utilisateur
     * ayant ajouté le livre.
     *
     * @param int $clubId Identifiant du club.
     * @return array
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
     * Ajoute un livre à un club à partir de son identifiant Google Books.
     *
     * L’utilisateur doit être membre du club.
     *
     * @param int    $clubId       Identifiant du club.
     * @param string $googleBookId Identifiant Google Books du livre.
     *
     * @return bool
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
     *
     * @param int    $clubId       Identifiant du club.
     * @param string $googleBookId Identifiant Google Books du livre.
     *
     * @return bool
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
