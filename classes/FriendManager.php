<?php

/**
 * Class FriendManager
 *
 * Gestionnaire des relations d’amitié du projet Kitabee.
 *
 * Cette classe centralise toute la logique liée au système d’amis :
 * - envoi de demandes d’ami,
 * - acceptation / refus de demandes reçues,
 * - suppression d’un ami,
 * - récupération des listes (amis, demandes reçues, demandes envoyées).
 *
 * Elle s’appuie sur PDO pour les requêtes SQL et utilise l’utilisateur courant
 * pour filtrer les actions et sécuriser les accès.
 *
 * Auteur : Imane MOUSSAOUI
 * Projet : Kitabee
 */
class FriendManager
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
     * @var int
     */
    private int $userId;

    /**
     * Constructeur du FriendManager.
     *
     * @param PDO $pdo    Connexion PDO à la base de données.
     * @param int $userId Identifiant de l’utilisateur courant.
     */
    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    /**
     * Envoie une demande d’ami à un autre utilisateur.
     *
     * Règles :
     * - l’utilisateur ne peut pas s’ajouter lui-même,
     * - si une relation existe déjà (dans un sens ou dans l’autre),
     *   la demande est refusée.
     *
     * @param int $otherId Identifiant de l’utilisateur cible.
     * @return bool True si la demande a été créée, false sinon.
     */
    public function sendRequest(int $otherId): bool
    {
        if ($otherId === $this->userId) return false;

        // déjà une relation dans un sens ou l'autre ?
        $sql = "
            SELECT id FROM user_friends 
            WHERE (user_id = :me AND friend_id = :them)
               OR (user_id = :them AND friend_id = :me)
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':me' => $this->userId, ':them' => $otherId]);

        if ($stmt->fetch()) {
            return false; // déjà une relation ou demande
        }

        // sinon créer la demande
        $stmt = $this->pdo->prepare("
            INSERT INTO user_friends (user_id, friend_id, requested_by, status, created_at)
            VALUES (:me, :them, :me, 'pending', NOW())
        ");

        return $stmt->execute([
            ':me'   => $this->userId,
            ':them' => $otherId
        ]);
    }

    /**
     * Accepte une demande d’ami reçue.
     *
     * Ici, on accepte uniquement une demande où :
     * - l’autre utilisateur est l’émetteur (user_id = them),
     * - l’utilisateur courant est le receveur (friend_id = me),
     * - le statut est encore "pending".
     *
     * @param int $otherId Identifiant de l’utilisateur ayant envoyé la demande.
     * @return bool True si la mise à jour a réussi, false sinon.
     */
    public function acceptRequest(int $otherId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE user_friends
               SET status = 'accepted', updated_at = NOW()
             WHERE user_id = :them
               AND friend_id = :me
               AND status = 'pending'
        ");
        return $stmt->execute([
            ':me'   => $this->userId,
            ':them' => $otherId
        ]);
    }

    /**
     * Refuse (supprime) une demande d’ami reçue.
     *
     * La demande est supprimée uniquement si :
     * - l’utilisateur courant est le destinataire,
     * - le statut est "pending".
     *
     * @param int $otherId Identifiant de l’utilisateur ayant envoyé la demande.
     * @return bool True si la suppression a réussi, false sinon.
     */
    public function declineRequest(int $otherId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_friends
             WHERE user_id = :them
               AND friend_id = :me
               AND status = 'pending'
        ");
        return $stmt->execute([
            ':me'   => $this->userId,
            ':them' => $otherId
        ]);
    }

    /**
     * Supprime une relation d’amitié (ou une demande existante) entre deux utilisateurs.
     *
     * La suppression se fait dans les deux sens :
     * - (me -> them) OU (them -> me)
     *
     * @param int $otherId Identifiant de l’autre utilisateur.
     * @return bool True si la suppression a réussi, false sinon.
     */
    public function removeFriend(int $otherId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_friends
             WHERE (user_id = :me AND friend_id = :them)
                OR (user_id = :them AND friend_id = :me)
        ");
        return $stmt->execute([
            ':me'   => $this->userId,
            ':them' => $otherId
        ]);
    }

    /**
     * Récupère la liste des amis de l’utilisateur courant.
     *
     * Retourne pour chaque ami :
     * - id, login, email,
     * - avatar_choice,
     * - has_avatar (booléen : 1 si avatar défini, 0 sinon).
     *
     * @return array Liste des amis (tableau associatif).
     */
    public function getFriends(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.login,
                u.email,
                u.avatar_choice,
                (u.avatar_choice IS NOT NULL) AS has_avatar
            FROM user_friends f
            JOIN users u 
              ON (u.id = f.user_id OR u.id = f.friend_id)
            WHERE f.status = 'accepted'
              AND (f.user_id = :me OR f.friend_id = :me)
              AND u.id <> :me
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':me' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les demandes d’ami reçues par l’utilisateur courant.
     *
     * Une demande reçue est définie par :
     * - status = 'pending'
     * - friend_id = utilisateur courant
     *
     * @return array Liste des demandes reçues.
     */
    public function getIncoming(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.login,
                u.email,
                u.avatar_choice,
                (u.avatar_choice IS NOT NULL) AS has_avatar
            FROM user_friends f
            JOIN users u ON u.id = f.user_id
            WHERE f.status = 'pending'
              AND f.friend_id = :me
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':me' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les demandes d’ami envoyées par l’utilisateur courant.
     *
     * Une demande envoyée est définie par :
     * - status = 'pending'
     * - user_id = utilisateur courant
     *
     * @return array Liste des demandes envoyées.
     */
    public function getOutgoing(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.login,
                u.avatar_choice,
                (u.avatar_choice IS NOT NULL) AS has_avatar
            FROM user_friends f
            JOIN users u ON u.id = f.friend_id
            WHERE f.status = 'pending'
              AND f.user_id = :me
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':me' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
