<?php

class FriendManager
{
    private PDO $pdo;
    private int $userId;

    public function __construct(PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    /** Envoyer une demande d'ami */
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

    /** Accepter une demande reçue */
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

    /** Refuser une demande */
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

    /** Supprimer un ami */
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

    /** Liste de mes amis */
    public function getFriends(): array
    {
        $sql = "
            SELECT u.id, u.login, u.email, u.avatar
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

    /** Demandes reçues */
    public function getIncoming(): array
    {
        $sql = "
            SELECT u.id, u.login, u.avatar, u.email
            FROM user_friends f
            JOIN users u ON u.id = f.user_id
            WHERE f.status = 'pending'
              AND f.friend_id = :me
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':me' => $this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Demandes envoyées */
    public function getOutgoing(): array
    {
        $sql = "
            SELECT u.id, u.login, u.avatar
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
