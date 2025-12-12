<?php

/**
 * Class BadgeManager
 *
 * Gestionnaire des badges utilisateurs pour le projet Kitabee.
 *
 * Cette classe centralise :
 * - la définition des badges,
 * - la vérification des règles de déblocage,
 * - l’attribution automatique des badges,
 * - la récupération des badges d’un utilisateur.
 *
 * Elle est appelée après chaque action importante de l’utilisateur
 *
 * Auteur : Odessa TRIOLLET-PEREIRA
 * Projet : Kitabee
 */
class BadgeManager
{
    /**
     * Instance PDO pour l’accès à la base de données.
     *
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Définition centralisée des badges.
     *
     * Chaque badge contient :
     * - un code unique,
     * - un nom,
     * - une description,
     * - un type de règle,
     * - un seuil éventuel.
     *
     * @var array
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

    /**
     * Constructeur du BadgeManager.
     *
     * @param PDO $pdo Connexion PDO à la base de données.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie l’ensemble des badges pour un utilisateur.
     *
     * Cette méthode est appelée après une action utilisateur
     * (ajout à la wishlist, lecture terminée, ajout d’ami, avatar…).
     *
     * Elle retourne uniquement les badges débloqués lors de cet appel.
     *
     * @param int $userId Identifiant de l’utilisateur.
     * @return array Liste des badges débloqués.
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
     * Vérifie la règle d’un badge en fonction de son type.
     *
     * @param int    $userId Identifiant de l’utilisateur.
     * @param string $code   Code du badge.
     * @param array  $def    Définition du badge.
     *
     * @return bool True si la règle est validée.
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

    /**
     * Vérifie le nombre de livres dans la wishlist.
     *
     * @param int $userId   Identifiant de l’utilisateur.
     * @param int $required Nombre minimum requis.
     *
     * @return bool
     */
    private function checkWishlistCount(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_wishlist WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    /**
     * Vérifie le nombre total de livres lus.
     *
     * @param int $userId   Identifiant de l’utilisateur.
     * @param int $required Nombre minimum requis.
     *
     * @return bool
     */
    private function checkLibraryCount(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_library WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    /**
     * Vérifie les lectures sur les 30 derniers jours.
     *
     * @param int $userId   Identifiant de l’utilisateur.
     * @param int $required Nombre minimum requis.
     *
     * @return bool
     */
    private function checkLibraryLast30Days(int $userId, int $required): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_library
            WHERE user_id = :uid
              AND added_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn() >= $required;
    }

    /**
     * Vérifie le nombre d’amis acceptés.
     *
     * @param int $userId   Identifiant de l’utilisateur.
     * @param int $required Nombre minimum requis.
     *
     * @return bool
     */
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

    /**
     * Vérifie si l’utilisateur a défini un avatar.
     *
     * @param int $userId Identifiant de l’utilisateur.
     * @return bool
     */
    private function checkAvatar(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT avatar_choice FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $userId]);
        $avatar = $stmt->fetchColumn();

        return !empty($avatar);
    }

    /* ======================
       ACCÈS BDD BADGES
       ====================== */

    /**
     * Vérifie si un utilisateur possède déjà un badge.
     *
     * @param int    $userId Identifiant de l’utilisateur.
     * @param string $code   Code du badge.
     *
     * @return bool
     */
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

    /**
     * Débloque un badge pour un utilisateur.
     *
     * @param int    $userId Identifiant de l’utilisateur.
     * @param string $code   Code du badge.
     * @param array  $def    Définition du badge.
     *
     * @return int|null Identifiant du badge.
     */
    private function unlockBadge(int $userId, string $code, array $def): ?int
    {
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

    /**
     * Récupère ou crée un badge en base de données.
     *
     * @param string $code Code du badge.
     * @param array  $def  Définition du badge.
     *
     * @return int|null
     */
    private function getOrCreateBadgeId(string $code, array $def): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM badges WHERE code = :code");
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (int)$id;
        }

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

    /**
     * Récupère les badges débloqués par un utilisateur.
     *
     * @param int $userId Identifiant de l’utilisateur.
     * @return array
     */
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
