<?php

class BadgeManager
{
    private PDO $pdo;

    /**
     * Définition des badges.
     * Tout est centralisé ici : code, nom, description, type, seuil...
     */
    private array $definitions = [
        // ==== WISHLIST ====
        'WISHLIST_1' => [
            'name'        => 'Premières envies',
            'description' => 'Tu as ajouté ton premier livre à ta wishlist.',
            'type'        => 'wishlist_count',
            'threshold'   => 1,
        ],
        'WISHLIST_10' => [
            'name'        => 'Wishlist en feu',
            'description' => 'Tu as ajouté 10 livres à ta wishlist.',
            'type'        => 'wishlist_count',
            'threshold'   => 10,
        ],
        'WISHLIST_30' => [
            'name'        => 'Collectionneur',
            'description' => 'Tu as ajouté 30 livres à ta wishlist.',
            'type'        => 'wishlist_count',
            'threshold'   => 30,
        ],

        // ==== LECTURE TOTAL ====
        'READ_1' => [
            'name'        => 'Première lecture',
            'description' => 'Tu as terminé ton premier livre.',
            'type'        => 'library_count',
            'threshold'   => 1,
        ],
        'READ_5' => [
            'name'        => 'Lecteur régulier',
            'description' => 'Tu as terminé 5 livres.',
            'type'        => 'library_count',
            'threshold'   => 5,
        ],
        'READ_10' => [
            'name'        => 'Lecteur assidu',
            'description' => 'Tu as terminé 10 livres.',
            'type'        => 'library_count',
            'threshold'   => 10,
        ],
        'READ_25' => [
            'name'        => 'Bouquineur pro',
            'description' => 'Tu as terminé 25 livres.',
            'type'        => 'library_count',
            'threshold'   => 25,
        ],

        // ==== LECTURE SUR 30 JOURS ====
        'READ_3_IN_30_DAYS' => [
            'name'        => 'Lecteur du mois',
            'description' => 'Tu as lu 3 livres sur les 30 derniers jours.',
            'type'        => 'library_30_days',
            'threshold'   => 3,
        ],

        // ==== SOCIAL ====
        'FRIEND_1' => [
            'name'        => 'Jamais seul',
            'description' => 'Tu as ajouté ton premier ami.',
            'type'        => 'friends_count',
            'threshold'   => 1,
        ],
        'FRIEND_5' => [
            'name'        => 'Cercle de lecture',
            'description' => 'Tu as 5 amis sur Kitabee.',
            'type'        => 'friends_count',
            'threshold'   => 5,
        ],

        // ==== PROFIL ====
        'PROFILE_AVATAR' => [
            'name'        => 'Photo de profil',
            'description' => 'Tu as personnalisé ton avatar.',
            'type'        => 'avatar',
        ],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * À utiliser après une action (ajout wishlist, lecture, ami, avatar...).
     * Retourne la liste des badges débloqués pendant cet appel.
     */
    public function checkAllForUser(int $userId): array
    {
        $unlockedNow = [];

        foreach ($this->definitions as $code => $def) {

            // Déjà débloqué ? on skip.
            if ($this->userHasBadge($userId, $code)) {
                continue;
            }

            if ($this->checkRule($userId, $code, $def)) {
                $badgeId = $this->unlockBadge($userId, $code, $def);
                if ($badgeId) {
                    $unlockedNow[] = [
                        'code'        => $code,
                        'name'        => $def['name'],
                        'description' => $def['description'],
                    ];
                }
            }
        }

        return $unlockedNow;
    }

    /**
     * Vérifie la règle d'un badge en fonction de son type.
     */
    private function checkRule(int $userId, string $code, array $def): bool
    {
        $type = $def['type'] ?? null;

        switch ($type) {
            case 'wishlist_count':
                return $this->checkWishlistCount($userId, (int)$def['threshold']);

            case 'library_count':
                return $this->checkLibraryCount($userId, (int)$def['threshold']);

            case 'library_30_days':
                return $this->checkLibraryLast30Days($userId, (int)$def['threshold']);

            case 'friends_count':
                return $this->checkFriendsCount($userId, (int)$def['threshold']);

            case 'avatar':
                return $this->checkAvatar($userId);

            default:
                return false;
        }
    }

    /* ======================
       RÈGLES UNITAIRES
       ====================== */

    private function checkWishlistCount(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_wishlist WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    private function checkLibraryCount(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_library WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    private function checkLibraryLast30Days(int $userId, int $required): bool
    {
        // adapte read_at / added_at selon ta structure
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_library
            WHERE user_id = :uid
              AND added_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    private function checkFriendsCount(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_friends
            WHERE (user_id = :uid OR friend_id = :uid)
              AND status = 'accepted'
        ");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    private function checkAvatar(int $userId): bool
    {
        // À adapter : avatar / avatar_has / autre colonne
        $stmt = $this->pdo->prepare("SELECT avatar FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $userId]);
        $avatar = $stmt->fetchColumn();

        return !empty($avatar);
    }

    /* ======================
       ACCÈS BDD BADGES
       ====================== */

    private function userHasBadge(int $userId, string $code): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :uid
              AND b.code = :code
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':uid'  => $userId,
            ':code' => $code,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function unlockBadge(int $userId, string $code, array $def): ?int
    {
        // on récupère / crée le badge en BDD
        $badgeId = $this->getOrCreateBadgeId($code, $def);

        if (!$badgeId) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO user_badges (user_id, badge_id)
            VALUES (:uid, :bid)
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':bid' => $badgeId,
        ]);

        return $badgeId;
    }

    private function getOrCreateBadgeId(string $code, array $def): ?int
    {
        // 1) essayer de trouver le badge
        $stmt = $this->pdo->prepare("SELECT id FROM badges WHERE code = :code");
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (int)$id;
        }

        // 2) sinon, on le crée (nom + description depuis $definitions)
        $stmt = $this->pdo->prepare("
            INSERT INTO badges (code, name, description)
            VALUES (:code, :name, :description)
        ");
        $stmt->execute([
            ':code'        => $code,
            ':name'        => $def['name'],
            ':description' => $def['description'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /* ======================
       RÉCUPÉRER LES BADGES D'UN USER
       ====================== */

    public function getUserBadges(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.code, b.name, b.description, ub.unlocked_at
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :uid
            ORDER BY ub.unlocked_at ASC
        ");
        $stmt->execute([':uid' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
