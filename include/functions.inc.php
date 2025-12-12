<?php
/**
 * include/functions.php – Kitabee
 *
 * Fonctions utilitaires réutilisables dans tout le projet.
 */

/* =========================
   SESSION / UTILISATEUR
   ========================= */

/**
 * Démarre la session si elle n'est pas déjà active.
 *
 * @return void
 */
function kb_ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Récupère l'utilisateur connecté depuis la session.
 *
 * @return array{userId:int|null, login:string|null}
 */
function kb_get_logged_user_from_session(): array
{
    $loggedUserId = $_SESSION['user'] ?? null;
    $loggedLogin  = $_SESSION['login'] ?? null;

    return [
        'userId' => $loggedUserId ? (int)$loggedUserId : null,
        'login'  => $loggedLogin !== null ? (string)$loggedLogin : null,
    ];
}

/* =========================
   COOKIES / CONSENTEMENT
   ========================= */

/**
 * Indique si l'utilisateur a accepté les cookies non essentiels.
 *
 * @return bool
 */
function kb_allow_non_essential_cookies(): bool
{
    $cookieConsent = $_COOKIE['cookie_consent'] ?? null;
    return ($cookieConsent === 'accepted');
}

/* =========================
   AVATAR
   ========================= */

/**
 * Détermine si l'utilisateur connecté a choisi un avatar (avatar_choice).
 * Synchronise aussi la session avatar_has (optionnel conseillé).
 *
 * @param PDO|null $pdo
 * @param int|null $loggedUserId
 * @return bool
 */
function kb_get_logged_has_avatar(?PDO $pdo, ?int $loggedUserId): bool
{
    $loggedHasAvatar = false;

    if ($loggedUserId && $pdo) {
        try {
            $stmtAv = $pdo->prepare("SELECT avatar_choice FROM users WHERE id = :uid LIMIT 1");
            $stmtAv->execute([':uid' => (int)$loggedUserId]);
            $avatarChoice = $stmtAv->fetchColumn();

            $loggedHasAvatar = !empty($avatarChoice);

            // Synchronisation de session (comme dans ton code)
            $_SESSION['avatar_has'] = $loggedHasAvatar;
        } catch (Throwable $e) {
            $loggedHasAvatar = $_SESSION['avatar_has'] ?? false;
        }
    }

    return (bool)$loggedHasAvatar;
}

/* =========================
   NOTIFS AMIS
   ========================= */

/**
 * Récupère le nombre de demandes d'amis en attente.
 * Si $pendingFriendRequests est déjà fourni (ex: depuis une page),
 * on le respecte. Sinon, on calcule en base.
 *
 * @param PDO|null $pdo
 * @param int|null $loggedUserId
 * @param int $pendingFriendRequests
 * @return int
 */
function kb_get_pending_friend_requests(?PDO $pdo, ?int $loggedUserId, int $pendingFriendRequests = 0): int
{
    $pendingFriendRequests = (int)$pendingFriendRequests;

    if ($loggedUserId && $pdo && $pendingFriendRequests === 0) {
        try {
            $stmtHeader = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_friends
                WHERE friend_id = :uid
                  AND status = 'pending'
            ");
            $stmtHeader->execute([':uid' => (int)$loggedUserId]);
            $pendingFriendRequests = (int)$stmtHeader->fetchColumn();
        } catch (Throwable $e) {
            $pendingFriendRequests = 0;
        }
    }

    return (int)$pendingFriendRequests;
}

/* =========================
   NOTIFS CLUBS
   ========================= */

/**
 * Récupère le nombre de notifications de clubs non lues :
 * - invitations (club_invite)
 * - messages (club_message)
 *
 * @param PDO|null $pdo
 * @param int|null $loggedUserId
 * @return array{pendingClubInvites:int, pendingClubMessages:int}
 */
function kb_get_pending_club_notifications(?PDO $pdo, ?int $loggedUserId): array
{
    $pendingClubInvites  = 0;
    $pendingClubMessages = 0;

    if ($loggedUserId && $pdo) {
        try {
            // Invitations de clubs
            $stmtClub = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :uid
                  AND type = 'club_invite'
                  AND is_read = 0
            ");
            $stmtClub->execute([':uid' => (int)$loggedUserId]);
            $pendingClubInvites = (int)$stmtClub->fetchColumn();

            // Messages de clubs non lus
            $stmtMsg = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :uid
                  AND type = 'club_message'
                  AND is_read = 0
            ");
            $stmtMsg->execute([':uid' => (int)$loggedUserId]);
            $pendingClubMessages = (int)$stmtMsg->fetchColumn();

        } catch (Throwable $e) {
            $pendingClubInvites  = 0;
            $pendingClubMessages = 0;
        }
    }

    return [
        'pendingClubInvites'  => (int)$pendingClubInvites,
        'pendingClubMessages' => (int)$pendingClubMessages,
    ];
}

/**
 * Calcule le total de notifications (amis + clubs).
 *
 * @param int $pendingFriendRequests
 * @param int $pendingClubInvites
 * @param int $pendingClubMessages
 * @return int
 */
function kb_get_total_notifications(int $pendingFriendRequests, int $pendingClubInvites, int $pendingClubMessages): int
{
    return (int)$pendingFriendRequests + (int)$pendingClubInvites + (int)$pendingClubMessages;
}

/* =========================
   THEME JOUR / NUIT
   ========================= */

/**
 * Retourne le thème courant (jour/nuit) depuis cookie, par défaut "jour".
 *
 * @return string
 */
function kb_get_current_style(): string
{
    return $_COOKIE['style'] ?? 'jour';
}

/**
 * Détecte si on est sur un POST de toggle du thème.
 *
 * @return bool
 */
function kb_is_post_toggle_style(): bool
{
    return isset($_POST['toggle_style']);
}

/**
 * Applique le toggle du thème.
 *
 * - Met à jour la variable $style.
 * - Si cookies non essentiels autorisés : enregistre le cookie "style"
 *   puis redirige pour éviter le resubmit du POST.
 * - Si cookies non essentiels refusés : ne pose pas le cookie
 *   mais la variable $style change pour le rendu de la page.
 *
 * @param string $style
 * @param bool $allowNonEssential
 * @return string Nouveau style appliqué
 */
function kb_handle_style_toggle(string $style, bool $allowNonEssential): string
{
    if (!kb_is_post_toggle_style()) {
        return $style;
    }

    $new   = ($style === 'jour') ? 'nuit' : 'jour';
    $style = $new;

    if ($allowNonEssential) {
        setcookie('style', $new, [
            'expires'  => time() + 5 * 24 * 60 * 60,
            'path'     => '/',
            'secure'   => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        header("Location: " . ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF']));
        exit;
    }

    return $style;
}

/* =========================
   DERNIERE VISITE
   ========================= */

/**
 * Récupère et/ou met à jour la "dernière visite" selon le consentement cookies.
 *
 * - Si non essentiels acceptés : met à jour cookie last_visit et renvoie la date actuelle.
 * - Sinon : renvoie la valeur existante si présente.
 *
 * @param bool $allowNonEssential
 * @return string|null
 */
function kb_get_and_update_last_visit(bool $allowNonEssential): ?string
{
    $last_visit = $_COOKIE['last_visit'] ?? null;

    if ($allowNonEssential) {
        $last_visit = date('d/m/Y H:i:s');
        setcookie('last_visit', $last_visit, [
            'expires'  => time() + 365 * 24 * 60 * 60,
            'path'     => '/',
            'secure'   => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    return $last_visit;
}

/* =========================
   BOOTSTRAP HEADER
   ========================= */

/**
 * Prépare toutes les variables nécessaires au header.
 *
 * Retourne un tableau associatif contenant :
 * - loggedUserId, loggedLogin, loggedHasAvatar
 * - pendingFriendRequests, pendingClubInvites, pendingClubMessages, totalNotifs
 * - allowNonEssential, style, last_visit
 *
 * @param PDO|null $pdo
 * @param int|null $pendingFriendRequests Optionnel : si la page a déjà calculé ce compteur.
 * @return array<string, mixed>
 */
function kb_header_bootstrap(?PDO $pdo, ?int $pendingFriendRequests = null): array
{
    kb_ensure_session_started();

    $user = kb_get_logged_user_from_session();
    $loggedUserId = $user['userId'];
    $loggedLogin  = $user['login'];

    $allowNonEssential = kb_allow_non_essential_cookies();

    $style = kb_get_current_style();
    $style = kb_handle_style_toggle($style, $allowNonEssential);

    $loggedHasAvatar = kb_get_logged_has_avatar($pdo, $loggedUserId);

    $pendingFriendRequests = isset($pendingFriendRequests) ? (int)$pendingFriendRequests : 0;
    $pendingFriendRequests = kb_get_pending_friend_requests($pdo, $loggedUserId, $pendingFriendRequests);

    $clubNotifs = kb_get_pending_club_notifications($pdo, $loggedUserId);
    $pendingClubInvites  = $clubNotifs['pendingClubInvites'];
    $pendingClubMessages = $clubNotifs['pendingClubMessages'];

    $totalNotifs = kb_get_total_notifications($pendingFriendRequests, $pendingClubInvites, $pendingClubMessages);

    $last_visit = kb_get_and_update_last_visit($allowNonEssential);

    return [
        'loggedUserId'           => $loggedUserId,
        'loggedLogin'            => $loggedLogin,
        'loggedHasAvatar'        => $loggedHasAvatar,

        'pendingFriendRequests'  => $pendingFriendRequests,
        'pendingClubInvites'     => $pendingClubInvites,
        'pendingClubMessages'    => $pendingClubMessages,
        'totalNotifs'            => $totalNotifs,

        'allowNonEssential'      => $allowNonEssential,
        'style'                  => $style,

        'last_visit'             => $last_visit,
    ];
}

/**
 * Active un compte utilisateur à partir d’un token d’activation.
 *
 * Cette fonction :
 * - vérifie la validité du token,
 * - vérifie que le compte n’est pas déjà activé,
 * - active le compte si possible,
 * - retourne un message de succès ou d’erreur.
 *
 * @param PDO    $pdo   Connexion PDO à la base de données.
 * @param string $token Token d’activation reçu par URL.
 *
 * @return array{
 *   success: bool,
 *   message: string,
 *   error: string
 * }
 */
function kb_activate_user_account(PDO $pdo, string $token): array
{
    $token = trim($token);

    if ($token === '') {
        return [
            'success' => false,
            'message' => '',
            'error'   => "Lien d’activation invalide."
        ];
    }

    // Recherche de l’utilisateur correspondant au token
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE activation_token = :token
          AND is_active = 0
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [
            'success' => false,
            'message' => '',
            'error'   => "Ce lien n’est plus valide ou le compte est déjà activé."
        ];
    }

    // Activation du compte
    $stmt = $pdo->prepare("
        UPDATE users
        SET is_active = 1,
            activation_token = NULL
        WHERE id = :id
    ");
    $stmt->execute([':id' => $user['id']]);

    return [
        'success' => true,
        'message' => "Votre compte a été activé ✅ Vous pouvez maintenant vous connecter.",
        'error'   => ''
    ];
}

/**
 * Ajoute un livre à la bibliothèque de l’utilisateur à partir de son Google Book ID.
 *
 * Étapes effectuées :
 * - vérifie la validité du book ID,
 * - supprime le livre de la wishlist s’il existe,
 * - récupère les informations du livre via l’API Google Books,
 * - insère le livre dans la table user_library,
 * - déclenche la vérification des badges utilisateur.
 *
 * @param PDO    $pdo      Connexion PDO à la base de données.
 * @param int    $userId   Identifiant de l’utilisateur connecté.
 * @param string $bookId   Identifiant Google Books du livre.
 * @param string $apiKey   Clé API Google Books 
 *
 * @return array{
 *   success: bool,
 *   bookId: string
 * }
 */
function kb_add_book_to_library(PDO $pdo, int $userId, string $bookId, string $apiKey = ''): array
{
    if (empty($bookId)) {
        return [
            'success' => false,
            'bookId'  => ''
        ];
    }

    /* ======================
       SUPPRESSION WISHLIST
       ====================== */
    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_wishlist
            WHERE user_id = :uid
              AND google_book_id = :bid
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':bid' => $bookId
        ]);
    } catch (Throwable $e) {
        return [
            'success' => false,
            'bookId'  => $bookId
        ];
    }

    /* ======================
       APPEL GOOGLE BOOKS
       ====================== */
    $title   = null;
    $authors = null;
    $thumb   = null;

    $url = "https://www.googleapis.com/books/v1/volumes/" . urlencode($bookId);
    if (!empty($apiKey)) {
        $url .= "?key=" . urlencode($apiKey);
    }

    $response = @file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        $info = $data['volumeInfo'] ?? [];

        $title      = $info['title'] ?? null;
        $authorsArr = $info['authors'] ?? [];
        $authors    = $authorsArr ? implode(', ', $authorsArr) : null;
        $thumb      = $info['imageLinks']['thumbnail'] ?? null;
    }

    /* ======================
       INSERTION BIBLIOTHÈQUE
       ====================== */
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_library (user_id, google_book_id, title, authors, thumbnail)
        VALUES (:uid, :bid, :title, :authors, :thumb)
    ");
    $stmt->execute([
        ':uid'     => $userId,
        ':bid'     => $bookId,
        ':title'   => $title,
        ':authors' => $authors,
        ':thumb'   => $thumb,
    ]);

    /* ======================
       BADGES
       ====================== */
    require_once __DIR__ . '/../classes/BadgeManager.php';

    $badgeManager = new BadgeManager($pdo);
    $newBadges = $badgeManager->checkAllForUser($userId);

    if (!empty($newBadges)) {
        $_SESSION['new_badges'] = $newBadges;
    }

    return [
        'success' => true,
        'bookId'  => $bookId
    ];
}

/**
 * Ajoute un livre à la wishlist de l’utilisateur à partir de son Google Book ID.
 *
 * Étapes effectuées :
 * - vérifie la validité du book ID,
 * - supprime le livre de la bibliothèque si présent (un livre ne peut pas être à la fois "lu" et "à lire"),
 * - récupère les informations du livre via l’API Google Books,
 * - insère le livre dans la table user_wishlist,
 * - déclenche la vérification des badges.
 *
 * @param PDO    $pdo    Connexion PDO à la base de données.
 * @param int    $userId Identifiant de l’utilisateur connecté.
 * @param string $bookId Identifiant Google Books du livre.
 * @param string $apiKey Clé API Google Books
 *
 * @return array{
 *   success: bool,
 *   bookId: string
 * }
 */
function kb_add_book_to_wishlist(PDO $pdo, int $userId, string $bookId, string $apiKey = ''): array
{
    $bookId = trim($bookId);

    if ($bookId === '') {
        return [
            'success' => false,
            'bookId'  => ''
        ];
    }

    /* ======================
       1) Retirer de la bibliothèque si présent
       ====================== */
    $stmt = $pdo->prepare("
        DELETE FROM user_library
        WHERE user_id = :uid
          AND google_book_id = :bid
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':bid' => $bookId
    ]);

    /* ======================
       2) Infos via Google Books API
       ====================== */
    $title   = null;
    $authors = null;
    $thumb   = null;

    $url = "https://www.googleapis.com/books/v1/volumes/" . urlencode($bookId);
    if (!empty($apiKey)) {
        $url .= "?key=" . urlencode($apiKey);
    }

    $response = @file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        $info = $data['volumeInfo'] ?? [];

        $title      = $info['title'] ?? null;
        $authorsArr = $info['authors'] ?? [];
        $authors    = $authorsArr ? implode(', ', $authorsArr) : null;
        $thumb      = $info['imageLinks']['thumbnail'] ?? null;
    }

    /* ======================
       3) Insertion wishlist
       ====================== */
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_wishlist (user_id, google_book_id, added_at, title, authors, thumbnail)
        VALUES (:uid, :bid, NOW(), :title, :authors, :thumb)
    ");
    $stmt->execute([
        ':uid'     => $userId,
        ':bid'     => $bookId,
        ':title'   => $title,
        ':authors' => $authors,
        ':thumb'   => $thumb,
    ]);

    /* ======================
       4) Badges
       ====================== */
    require_once __DIR__ . '/../classes/BadgeManager.php';

    $badgeManager = new BadgeManager($pdo);
    $newBadges = $badgeManager->checkAllForUser($userId);

    if (!empty($newBadges)) {
        $_SESSION['new_badges'] = $newBadges;
    }

    return [
        'success' => true,
        'bookId'  => $bookId
    ];
}

/**
 * Prépare le contexte de la page amis.php (traitements + données d’affichage).
 *
 * Cette fonction :
 * - traite les actions POST (send_request, accept_request, reject_request, remove_friend),
 * - exécute la recherche utilisateur via GET,
 * - récupère les demandes reçues,
 * - récupère la liste d’amis,
 * - déclenche la vérification des badges (FRIEND_1, FRIEND_5, etc.).
 *
 * @param PDO   $pdo      Connexion PDO.
 * @param int   $userId   Identifiant de l’utilisateur connecté.
 * @param array $request  Tableau request global 
 *
 * @return array{
 *   message: string|null,
 *   error: string|null,
 *   searchTerm: string,
 *   searchResults: array,
 *   incomingRequests: array,
 *   friends: array
 * }
 */
function kb_friends_page_context(PDO $pdo, int $userId, array $request = []): array
{
    $message = null;
    $error   = null;

    /* =========================
       TRAITEMENTS POST
       ========================= */
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1) Envoyer une demande d'ami
        if ($action === 'send_request') {
            $targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

            if ($targetId <= 0 || $targetId === $userId) {
                $error = "Utilisateur invalide.";
            } else {
                // Vérifier si une relation existe déjà
                $stmt = $pdo->prepare("
                    SELECT id, status
                    FROM user_friends
                    WHERE (user_id = :me AND friend_id = :them)
                       OR (user_id = :them AND friend_id = :me)
                    LIMIT 1
                ");
                $stmt->execute([
                    ':me'   => $userId,
                    ':them' => $targetId,
                ]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    if ($existing['status'] === 'pending') {
                        $error = "Une demande d'ami est déjà en attente entre vous.";
                    } elseif ($existing['status'] === 'accepted') {
                        $error = "Vous êtes déjà amis avec cette personne.";
                    } else {
                        $error = "Une relation existe déjà avec cette personne.";
                    }
                } else {
                    // Créer la demande
                    $stmt = $pdo->prepare("
                        INSERT INTO user_friends (user_id, friend_id, requested_by, status, created_at)
                        VALUES (:me, :them, :me, 'pending', NOW())
                    ");
                    $ok = $stmt->execute([
                        ':me'   => $userId,
                        ':them' => $targetId,
                    ]);

                    if ($ok) {
                        $message = "Demande d'ami envoyée ✔";
                    } else {
                        $error = "Impossible d'envoyer la demande d'ami.";
                    }
                }
            }
        }

        // 2) Accepter une demande reçue
        if ($action === 'accept_request') {
            $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

            if ($requestId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE user_friends
                    SET status = 'accepted'
                    WHERE id = :id
                      AND friend_id = :me
                      AND status = 'pending'
                ");
                $ok = $stmt->execute([
                    ':id' => $requestId,
                    ':me' => $userId,
                ]);

                if ($ok && $stmt->rowCount() === 1) {
                    $message = "Demande d'ami acceptée 👍";
                } else {
                    $error = "Impossible d'accepter cette demande.";
                }
            }
        }

        // 3) Refuser une demande reçue
        if ($action === 'reject_request') {
            $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

            if ($requestId > 0) {
                $stmt = $pdo->prepare("
                    DELETE FROM user_friends
                    WHERE id = :id
                      AND friend_id = :me
                      AND status = 'pending'
                ");
                $ok = $stmt->execute([
                    ':id' => $requestId,
                    ':me' => $userId,
                ]);

                if ($ok && $stmt->rowCount() === 1) {
                    $message = "Demande d'ami refusée.";
                } else {
                    $error = "Impossible de refuser cette demande.";
                }
            }
        }

        // 4) Supprimer un ami
        if ($action === 'remove_friend') {
            $friendId = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;

            if ($friendId > 0 && $friendId !== $userId) {
                $stmt = $pdo->prepare("
                    DELETE FROM user_friends
                    WHERE ((user_id = :me AND friend_id = :friend)
                        OR (user_id = :friend AND friend_id = :me))
                      AND status = 'accepted'
                ");
                $ok = $stmt->execute([
                    ':me'     => $userId,
                    ':friend' => $friendId,
                ]);

                if ($ok && $stmt->rowCount() > 0) {
                    $message = "Cet ami a bien été supprimé.";
                } else {
                    $error = "Impossible de supprimer cet ami.";
                }
            } else {
                $error = "Ami invalide.";
            }
        }
    }

    /* =========================
       RECHERCHE UTILISATEURS 
       ========================= */
    $searchTerm    = trim($_GET['q'] ?? '');
    $searchResults = [];

    if ($searchTerm !== '') {
        $stmt = $pdo->prepare("
            SELECT id, login, avatar_choice, email
            FROM users
            WHERE (login LIKE :term OR email LIKE :term)
              AND id <> :me
            ORDER BY login ASC
            LIMIT 20
        ");
        $stmt->execute([
            ':term' => '%' . $searchTerm . '%',
            ':me'   => $userId,
        ]);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
       DEMANDES REÇUES
       ========================= */
    $stmt = $pdo->prepare("
        SELECT uf.id, uf.user_id, uf.created_at,
               u.login, u.avatar_choice
        FROM user_friends uf
        JOIN users u ON u.id = uf.user_id
        WHERE uf.friend_id = :me
          AND uf.status = 'pending'
        ORDER BY uf.created_at DESC
    ");
    $stmt->execute([':me' => $userId]);
    $incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       LISTE DES AMIS
       ========================= */
    $stmt = $pdo->prepare("
        SELECT
          CASE 
            WHEN uf.user_id = :me THEN uf.friend_id
            ELSE uf.user_id
          END AS friend_id,
          u.login,
          u.avatar_choice
        FROM user_friends uf
        JOIN users u ON u.id = CASE 
                                  WHEN uf.user_id = :me THEN uf.friend_id
                                  ELSE uf.user_id
                               END
        WHERE (uf.user_id = :me OR uf.friend_id = :me)
          AND uf.status = 'accepted'
        ORDER BY u.login ASC
    ");
    $stmt->execute([':me' => $userId]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       BADGES
       ========================= */
    require_once __DIR__ . '/../classes/BadgeManager.php';
    $badgeManager = new BadgeManager($pdo);
    $newBadges = $badgeManager->checkAllForUser($userId);

    if (!empty($newBadges)) {
        $_SESSION['new_badges'] = $newBadges;
    }

    return [
        'message'          => $message,
        'error'            => $error,
        'searchTerm'       => $searchTerm,
        'searchResults'    => $searchResults,
        'incomingRequests' => $incomingRequests,
        'friends'          => $friends,
    ];
}

/**
 * Envoie l’avatar d’un utilisateur (image ou fallback SVG).
 *
 * Règles :
 * 1) Si avatar_choice est défini et autorisé -> renvoie le fichier /avatar/<choice>.(jpg/jpeg/JPG)
 * 2) Sinon -> renvoie un SVG avec l’initiale du login
 *
 * Cette fonction gère :
 * - les codes HTTP (400/404),
 * - le Content-Type correct (image/jpeg ou image/svg+xml),
 * - la sortie directe (readfile / echo) et exit.
 *
 * @param PDO $pdo Connexion PDO.
 * @param int $userId Identifiant de l’utilisateur.
 * @return void
 */
function kb_output_user_avatar(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        http_response_code(400);
        exit('Bad request');
    }

    // On récupère login + avatar_choice
    $stmt = $pdo->prepare("
        SELECT login, avatar_choice
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        exit('User not found');
    }

    $login        = $row['login'] ?? '';
    $avatarChoice = $row['avatar_choice'] ?? null;

    // =====================
    // 1) Avatars prédéfinis
    // =====================
    $allowedAvatars = ['candice', 'genie', 'jerry', 'snoopy', 'belle', 'naruto'];

    if ($avatarChoice && in_array($avatarChoice, $allowedAvatars, true)) {
        $baseDir = __DIR__ . '/../avatar/';

        // On tente plusieurs extensions possibles 
        $candidates = [
            $baseDir . $avatarChoice . '.JPG',
            $baseDir . $avatarChoice . '.jpg',
            $baseDir . $avatarChoice . '.jpeg',
            $baseDir . $avatarChoice . '.JPEG',
        ];

        foreach ($candidates as $filePath) {
            if (is_file($filePath)) {
                header('Content-Type: image/jpeg');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        }
    }

    // ===========================
    // 2) Fallback : initiale pseudo
    // ===========================

    if ($login === '') {
        $initial = 'U';
    } else {
        $initial = mb_strtoupper(mb_substr($login, 0, 1, 'UTF-8'), 'UTF-8');
    }

    $colors = [
        '#F97373',
        '#FACC15',
        '#4ADE80',
        '#38BDF8',
        '#A855F7',
        '#F97316',
    ];

    $bgColor = $colors[$userId % count($colors)];

    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        . '<rect width="120" height="120" rx="24" ry="24" fill="' . $bgColor . '"/>'
        . '<text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" '
        . 'font-family="system-ui, -apple-system, BlinkMacSystemFont, sans-serif" '
        . 'font-size="64" fill="#ffffff">'
        . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
        . '</text>'
        . '</svg>';

    header('Content-Type: image/svg+xml; charset=utf-8');
    echo $svg;
    exit;
}

/**
 * Récupère les livres de la bibliothèque (lus) et de la wishlist (à lire)
 * pour un utilisateur.
 *
 * @param PDO $pdo Connexion PDO.
 * @param int $userId Identifiant de l’utilisateur.
 *
 * @return array{
 *   library: array<int, array{google_book_id:string, title:?string, authors:?string, thumbnail:?string}>,
 *   wishlist: array<int, array{google_book_id:string, title:?string, authors:?string, thumbnail:?string}>
 * }
 */
function kb_get_user_library_and_wishlist(PDO $pdo, int $userId): array
{
    // Livres lus (bibliothèque)
    $stmt = $pdo->prepare("
        SELECT google_book_id, title, authors, thumbnail
        FROM user_library
        WHERE user_id = :uid
        ORDER BY added_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $libraryBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Livres à lire (wishlist)
    $stmt = $pdo->prepare("
        SELECT google_book_id, title, authors, thumbnail
        FROM user_wishlist
        WHERE user_id = :uid
        ORDER BY added_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $wishlistBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'library'  => $libraryBooks ?: [],
        'wishlist' => $wishlistBooks ?: [],
    ];
}

/**
 * Construit le contexte du mode "liste" de club.php
 * Gère :
 * - messages (left/deleted),
 * - traitement des invitations (accept/decline),
 * - création de club,
 * - récupération invitations + clubs + messages non lus par club.
 *
 * @param PDO         $pdo
 * @param int         $userId
 * @param ClubManager $cm
 *
 * @return array{
 *   pageTitle: string,
 *   message: string|null,
 *   error: string|null,
 *   clubInvites: array,
 *   clubs: array,
 *   unreadClubMessagesByClub: array<int,int>
 * }
 */
function kb_clubs_list_context(PDO $pdo, int $userId, ClubManager $cm): array
{
    $pageTitle = "Mes clubs de lecture – Kitabee";
    $message   = null;
    $error     = null;

    // Message si on revient après avoir quitté / supprimé un club
    if (isset($_GET['left']) && $_GET['left'] === '1') {
        $message = "Vous avez quitté le club.";
    }
    if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
        $message = "Le club a été supprimé avec succès.";
    }

    /* ----- 1) Traitement des invitations (Accepter / Refuser) ----- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_action'], $_POST['notif_id'])) {
        $notifAction = $_POST['notif_action'];
        $notifId     = (int)($_POST['notif_id'] ?? 0);

        try {
            // Vérifier que la notif appartient bien à l'utilisateur
            $stmtCheck = $pdo->prepare("
                SELECT id, club_id
                FROM notifications
                WHERE id = :nid
                  AND user_id = :uid
                  AND type = 'club_invite'
                  AND is_read = 0
                LIMIT 1
            ");
            $stmtCheck->execute([
                ':nid' => $notifId,
                ':uid' => $userId,
            ]);
            $notifRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$notifRow) {
                $error = "Cette invitation n'existe pas ou a déjà été traitée.";
            } else {
                $clubFromNotif = (int)$notifRow['club_id'];

                if ($notifAction === 'accept') {
                    // Ajouter l'utilisateur comme membre
                    $stmtAdd = $pdo->prepare("
                        INSERT IGNORE INTO book_club_members (club_id, user_id, role)
                        VALUES (:cid, :uid, 'member')
                    ");
                    $stmtAdd->execute([
                        ':cid' => $clubFromNotif,
                        ':uid' => $userId,
                    ]);

                    // Marquer la notif comme lue
                    $stmtRead = $pdo->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = :nid
                    ");
                    $stmtRead->execute([':nid' => $notifId]);

                    $message = "Vous avez rejoint le club avec succès 🎉";

                } elseif ($notifAction === 'decline') {
                    // Marquer la notif comme lue
                    $stmtRead = $pdo->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = :nid
                    ");
                    $stmtRead->execute([':nid' => $notifId]);

                    $message = "Invitation refusée.";
                } else {
                    $error = "Action inconnue sur l'invitation.";
                }
            }
        } catch (Throwable $e) {
            $error = "Une erreur est survenue lors du traitement de l'invitation.";
        }
    }

    /* ----- 2) Création d'un club ----- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['notif_action'])) {
        $name  = trim($_POST['name'] ?? '');
        $descr = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = "Le nom du club est obligatoire.";
        } else {
            $newClubId = $cm->createClub($name, $descr);
            if ($newClubId) {
                $message = "Le club « " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " » a été créé avec succès 🎉";
            } else {
                $error = "Impossible de créer le club. Veuillez réessayer.";
            }
        }
    }

    /* ----- 3) Invitations non lues ----- */
    $clubInvites = [];
    try {
        $stmtInv = $pdo->prepare("
            SELECT 
                n.id,
                n.content,
                n.created_at,
                n.club_id,
                u.login AS from_login,
                c.name  AS club_name
            FROM notifications n
            JOIN users      u ON u.id = n.from_user_id
            JOIN book_clubs c ON c.id = n.club_id
            WHERE n.user_id = :uid
              AND n.type    = 'club_invite'
              AND n.is_read = 0
            ORDER BY n.created_at DESC
        ");
        $stmtInv->execute([':uid' => $userId]);
        $clubInvites = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $clubInvites = [];
    }

    /* ----- 4) Mes clubs ----- */
    $clubs = $cm->getMyClubs();

    /* ----- 5) Messages non lus par club ----- */
    $unreadClubMessagesByClub = [];
    if ($clubs) {
        $clubIds      = array_column($clubs, 'id');
        $placeholders = implode(',', array_fill(0, count($clubIds), '?'));

        $sqlUnread = "
            SELECT club_id, COUNT(*) AS unread_count
            FROM notifications
            WHERE user_id = ?
              AND type = 'club_message'
              AND is_read = 0
              AND club_id IN ($placeholders)
            GROUP BY club_id
        ";

        try {
            $stmtUnread = $pdo->prepare($sqlUnread);
            $params = array_merge([$userId], $clubIds);
            $stmtUnread->execute($params);

            while ($row = $stmtUnread->fetch(PDO::FETCH_ASSOC)) {
                $unreadClubMessagesByClub[(int)$row['club_id']] = (int)$row['unread_count'];
            }
        } catch (Throwable $e) {
            $unreadClubMessagesByClub = [];
        }
    }

    return [
        'pageTitle'                => $pageTitle,
        'message'                  => $message,
        'error'                    => $error,
        'clubInvites'              => $clubInvites,
        'clubs'                    => $clubs,
        'unreadClubMessagesByClub' => $unreadClubMessagesByClub,
    ];
}

/**
 * Construit le contexte du mode "détail" de club.php 
 * Gère :
 * - suppression du club (owner),
 * - quitter le club,
 * - envoi de message + notifications,
 * - comptage / marquage des notifs "club_message" comme lues,
 * - récupération club + membres + amis invitables + livres + bibliothèque perso + messages.
 *
 * @param PDO           $pdo
 * @param int           $userId
 * @param int           $clubId
 * @param ClubManager   $cm
 * @param FriendManager $fm
 *
 * @return array{
 *   redirect: string|null,
 *   pageTitle: string,
 *   club: array|null,
 *   deleteError: string|null,
 *   leaveError: string|null,
 *   messageError: string|null,
 *   unreadMessagesThisClub: int,
 *   members: array,
 *   memberIds: array,
 *   memberCount: int,
 *   friends: array,
 *   invitableFriends: array,
 *   clubBooks: array,
 *   clubBooksCount: int,
 *   userLibrary: array,
 *   messages: array
 * }
 */
function kb_club_detail_context(PDO $pdo, int $userId, int $clubId, ClubManager $cm, FriendManager $fm): array
{
    $redirect     = null;
    $deleteError  = null;
    $leaveError   = null;
    $messageError = null;

    // --- Supprimer le club  ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club'])) {
        if ($cm->deleteClub($clubId)) {
            $redirect = 'club.php?deleted=1';
            return [
                'redirect' => $redirect,
                'pageTitle' => '',
                'club' => null,
                'deleteError' => null,
                'leaveError' => null,
                'messageError' => null,
                'unreadMessagesThisClub' => 0,
                'members' => [],
                'memberIds' => [],
                'memberCount' => 0,
                'friends' => [],
                'invitableFriends' => [],
                'clubBooks' => [],
                'clubBooksCount' => 0,
                'userLibrary' => [],
                'messages' => [],
            ];
        } else {
            $deleteError = "Impossible de supprimer ce club.";
        }
    }

    // --- Quitter le club ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_club'])) {
        if ($cm->leaveClub($clubId)) {
            $redirect = 'club.php?left=1';
            return [
                'redirect' => $redirect,
                'pageTitle' => '',
                'club' => null,
                'deleteError' => null,
                'leaveError' => null,
                'messageError' => null,
                'unreadMessagesThisClub' => 0,
                'members' => [],
                'memberIds' => [],
                'memberCount' => 0,
                'friends' => [],
                'invitableFriends' => [],
                'clubBooks' => [],
                'clubBooksCount' => 0,
                'userLibrary' => [],
                'messages' => [],
            ];
        } else {
            $leaveError = "Impossible de quitter ce club (vous en êtes peut-être le créateur).";
        }
    }

    // --- Envoi de message --
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['club_message'])) {
        $content = trim($_POST['club_message'] ?? '');
        if ($content === '') {
            $messageError = "Le message ne peut pas être vide.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO book_club_messages (club_id, user_id, content)
                VALUES (:cid, :uid, :content)
            ");
            $ok = $stmt->execute([
                ':cid'     => $clubId,
                ':uid'     => $userId,
                ':content' => $content,
            ]);

            if (!$ok) {
                $messageError = "Impossible d'envoyer le message. Réessayez.";
            } else {
                // Notifications aux autres membres
                try {
                    $stmtMembers = $pdo->prepare("
                        SELECT user_id
                        FROM book_club_members
                        WHERE club_id = :cid
                          AND user_id <> :uid
                    ");
                    $stmtMembers->execute([
                        ':cid' => $clubId,
                        ':uid' => $userId,
                    ]);
                    $membersForNotif = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);

                    if ($membersForNotif) {
                        $preview = mb_substr($content, 0, 120);
                        if (mb_strlen($content) > 120) $preview .= '…';

                        $stmtNotif = $pdo->prepare("
                            INSERT INTO notifications (user_id, from_user_id, club_id, type, content, is_read, created_at)
                            VALUES (:uid, :from_uid, :club_id, 'club_message', :content, 0, NOW())
                        ");

                        foreach ($membersForNotif as $memberId) {
                            $stmtNotif->execute([
                                ':uid'      => (int)$memberId,
                                ':from_uid' => $userId,
                                ':club_id'  => $clubId,
                                ':content'  => $preview,
                            ]);
                        }
                    }
                } catch (Throwable $e) {
                   
                }

                $redirect = "club.php?id=" . $clubId;
                return [
                    'redirect' => $redirect,
                    'pageTitle' => '',
                    'club' => null,
                    'deleteError' => null,
                    'leaveError' => null,
                    'messageError' => null,
                    'unreadMessagesThisClub' => 0,
                    'members' => [],
                    'memberIds' => [],
                    'memberCount' => 0,
                    'friends' => [],
                    'invitableFriends' => [],
                    'clubBooks' => [],
                    'clubBooksCount' => 0,
                    'userLibrary' => [],
                    'messages' => [],
                ];
            }
        }
    }

    // Accès club
    $club = $cm->getClub($clubId);

    // Compter notifs non lues pour ce club
    $unreadMessagesThisClub = 0;
    if ($club) {
        try {
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :uid
                  AND club_id = :cid
                  AND type = 'club_message'
                  AND is_read = 0
            ");
            $stmtCount->execute([
                ':uid' => $userId,
                ':cid' => $clubId,
            ]);
            $unreadMessagesThisClub = (int)$stmtCount->fetchColumn();
        } catch (Throwable $e) {
            $unreadMessagesThisClub = 0;
        }

        // Marquer comme lues
        try {
            $stmtReadMsg = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = :uid
                  AND club_id = :cid
                  AND type = 'club_message'
                  AND is_read = 0
            ");
            $stmtReadMsg->execute([
                ':uid' => $userId,
                ':cid' => $clubId,
            ]);
        } catch (Throwable $e) {
          
        }
    }

    // Données affichage 
    $members     = $club ? $cm->getMembers($clubId) : [];
    $memberIds   = $members ? array_column($members, 'id') : [];
    $memberCount = count($members);

    $friends = $fm->getFriends();
    $invitableFriends = array_filter($friends, function ($f) use ($memberIds) {
        return !in_array((int)$f['id'], $memberIds, true);
    });

    $clubBooks      = $club ? $cm->getBooks($clubId) : [];
    $clubBooksCount = count($clubBooks);

    $stmtLib = $pdo->prepare("
        SELECT id, google_book_id, title, authors, thumbnail, added_at
        FROM user_library
        WHERE user_id = :uid
        ORDER BY added_at DESC
    ");
    $stmtLib->execute([':uid' => $userId]);
    $userLibrary = $stmtLib->fetchAll(PDO::FETCH_ASSOC);

    $stmtMsg = $pdo->prepare("
        SELECT 
            m.id, 
            m.content, 
            m.created_at, 
            u.login, 
            (u.avatar_choice IS NOT NULL) AS has_avatar,
            u.id AS user_id
        FROM book_club_messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.club_id = :cid
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $stmtMsg->execute([':cid' => $clubId]);
    $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

    $pageTitle = $club ? ("Club : " . $club['name'] . " – Kitabee") : "Club introuvable – Kitabee";

    return [
        'redirect'              => null,
        'pageTitle'             => $pageTitle,
        'club'                  => $club ?: null,
        'deleteError'           => $deleteError,
        'leaveError'            => $leaveError,
        'messageError'          => $messageError,
        'unreadMessagesThisClub'=> $unreadMessagesThisClub,
        'members'               => $members,
        'memberIds'             => $memberIds,
        'memberCount'           => $memberCount,
        'friends'               => $friends,
        'invitableFriends'      => $invitableFriends,
        'clubBooks'             => $clubBooks,
        'clubBooksCount'        => $clubBooksCount,
        'userLibrary'           => $userLibrary,
        'messages'              => $messages,
    ];
}

/**
 * Gère les actions AJAX liées aux clubs de lecture.
 *
 * Actions supportées (POST):
 * - add_member    : crée une notification d’invitation (club_invite) (owner uniquement)
 * - remove_member : retire un membre (owner uniquement)
 * - add_book      : ajoute un livre au club (google_book_id)
 * - remove_book   : retire un livre du club (google_book_id)
 *
 * Format retour:
 * - ['ok' => true] en cas de succès
 * - ['ok' => false, 'error' => '...'] en cas d’erreur
 *
 * @param PDO $pdo Connexion PDO.
 * @param int $userId ID de l’utilisateur courant.
 * @param array $post Tableau de données POST.
 *
 * @return array{ok:bool, error?:string}
 */
function kb_handle_clubs_ajax(PDO $pdo, int $userId, array $post): array
{
    require_once __DIR__ . '/../classes/ClubManager.php';

    $cm = new ClubManager($pdo, $userId);

    $action       = $post['action'] ?? '';
    $clubId       = isset($post['club_id']) ? (int)$post['club_id'] : 0;
    $targetUserId = isset($post['user_id']) ? (int)$post['user_id'] : 0;
    $googleBookId = $post['google_book_id'] ?? '';

    $response = ['ok' => false];

    try {
        switch ($action) {

            case 'add_member':
                // On n'ajoute pas directement : on crée une notif d'invitation
                if ($clubId <= 0 || $targetUserId <= 0) {
                    $response['error'] = 'invalid_parameters';
                    break;
                }

                if (!$cm->isOwner($clubId)) {
                    $response['error'] = 'not_owner';
                    break;
                }

                $club = $cm->getClub($clubId);
                if (!$club) {
                    $response['error'] = 'club_not_found';
                    break;
                }

                $clubName = $club['name'] ?? 'Club de lecture';

                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, from_user_id, club_id, type, content, is_read, created_at)
                    VALUES (:uid, :from_uid, :club_id, 'club_invite', :content, 0, NOW())
                ");
                $okNotif = $stmtNotif->execute([
                    ':uid'      => $targetUserId,
                    ':from_uid' => $userId,
                    ':club_id'  => $clubId,
                    ':content'  => "Vous avez été invité(e) à rejoindre le club : " . $clubName,
                ]);

                if ($okNotif) {
                    $response['ok'] = true;
                } else {
                    $response['error'] = 'cannot_create_notification';
                }
                break;

            case 'remove_member':
                if ($clubId <= 0 || $targetUserId <= 0) {
                    $response['error'] = 'invalid_parameters';
                    break;
                }

                $response['ok'] = $cm->removeMember($clubId, $targetUserId);
                if (!$response['ok']) {
                    $response['error'] = 'cannot_remove_member';
                }
                break;

            case 'add_book':
                $googleBookId = trim((string)$googleBookId);
                if ($clubId <= 0 || $googleBookId === '') {
                    $response['error'] = 'missing_google_book_id';
                    break;
                }

                $response['ok'] = $cm->addBook($clubId, $googleBookId);
                if (!$response['ok']) {
                    $response['error'] = 'cannot_add_book';
                }
                break;

            case 'remove_book':
                $googleBookId = trim((string)$googleBookId);
                if ($clubId <= 0 || $googleBookId === '') {
                    $response['error'] = 'missing_google_book_id';
                    break;
                }

                $response['ok'] = $cm->removeBook($clubId, $googleBookId);
                if (!$response['ok']) {
                    $response['error'] = 'cannot_remove_book';
                }
                break;

            default:
                $response['error'] = 'unknown_action';
        }
    } catch (Throwable $e) {
        $response['ok']    = false;
        $response['error'] = 'exception';
    }

    return $response;
}

/**
 * Traite le formulaire de connexion avec reCAPTCHA + vérification BD.
 *
 * - Vérifie les champs (login, password)
 * - Vérifie reCAPTCHA côté serveur (siteverify)
 * - Vérifie l'utilisateur en base (password_hash, is_active)
 * - Initialise la session : user, login, avatar_has
 *
 * @param PDO   $pdo Connexion PDO.
 * @param array $config Tableau contenant au minimum les constantes reCAPTCHA
 * @param array $post Données POST (ex: $_POST).
 * @param array $get  Données GET (ex: $_GET) pour redirect/reset.
 * @param array $server Données serveur (ex: $_SERVER) pour remote IP.
 *
 * @return array{
 *   error: string|null,
 *   success: string|null,
 *   redirect: string|null
 * }
 */
function kb_handle_login(PDO $pdo, array $config, array $post, array $get, array $server): array
{
    $error   = null;
    $success = null;
    $redirectTo = null;

    // Message si redirection après réinitialisation de mot de passe
    if (isset($get['reset']) && $get['reset'] === 'success') {
        $success = "Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.";
    }

    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
        return [
            'error'    => $error,
            'success'  => $success,
            'redirect' => $redirectTo,
        ];
    }

    $login           = trim($post['login'] ?? '');
    $password        = $post['password'] ?? '';
    $target          = $post['redirect'] ?? 'profil_user.php';
    $captchaResponse = $post['g-recaptcha-response'] ?? '';

    if ($login === '' || $password === '') {
        $error = "Veuillez renseigner votre identifiant et votre mot de passe.";
        return ['error' => $error, 'success' => $success, 'redirect' => null];
    }

    if ($captchaResponse === '') {
        $error = "Veuillez valider le CAPTCHA.";
        return ['error' => $error, 'success' => $success, 'redirect' => null];
    }

    // Vérification reCAPTCHA côté Google
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $params = [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $captchaResponse,
        'remoteip' => $server['REMOTE_ADDR'] ?? null
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params)
        ]
    ];

    $context = stream_context_create($options);
    $result  = @file_get_contents($verifyUrl, false, $context);
    $data    = json_decode($result, true);

    if (empty($data['success'])) {
        $error = "CAPTCHA invalide, merci de réessayer.";
        return ['error' => $error, 'success' => $success, 'redirect' => null];
    }

    // récupérer l'utilisateur en BD
    $stmt = $pdo->prepare('
        SELECT id, login, password, is_active, avatar_choice
        FROM users
        WHERE login = :login
        LIMIT 1
    ');
    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Identifiants invalides.";
        return ['error' => $error, 'success' => $success, 'redirect' => null];
    }

    // vérifier activation
    if ((int)$user['is_active'] !== 1) {
        $error = "Votre compte n’est pas encore activé. Vérifiez vos emails.";
        return ['error' => $error, 'success' => $success, 'redirect' => null];
    }

    // connexion OK
    session_regenerate_id(true);

    $_SESSION['user']  = (int)$user['id'];
    $_SESSION['login'] = $user['login'];

    $_SESSION['avatar_has'] = !empty($user['avatar_choice']);

    $redirectTo = $target;

    return [
        'error'    => null,
        'success'  => $success,
        'redirect' => $redirectTo,
    ];
}

/**
 * Prépare le contexte d'une page "En construction".
 *
 * Cette fonction :
 * - Récupère le paramètre GET "page"
 * - Détermine un titre lisible selon la page demandée
 * - Génère le titre HTML complet pour le header
 *
 * @param array $get Tableau des paramètres GET
 *
 * @return array{
 *   page: string,
 *   title: string,
 *   pageTitle: string
 * }
 */
function kb_prepare_construction_page(array $get): array
{
    $page = $get['page'] ?? 'page';

    switch ($page) {
        case 'actualites':
            $title = "Actualités";
            break;
        default:
            $title = ucfirst($page);
    }

    $pageTitle = $title . " – En construction – Kitabee";

    return [
        'page'      => $page,
        'title'     => $title,
        'pageTitle' => $pageTitle,
    ];
}

/**
 * Traite le formulaire de contact (validation + reCAPTCHA + envoi mail).
 *
 * - Valide les champs obligatoires (nom, email, message)
 * - Valide le format email
 * - Vérifie reCAPTCHA v2 côté serveur (siteverify)
 * - Envoie un mail via mail()
 * - Retourne le contexte (success/error + valeurs de champs)
 *
 * @param array $post Données POST 
 * @param array $server Données serveur pour IP + REQUEST_METHOD.
 *
 * @return array{
 *   success: string|null,
 *   error: string|null,
 *   nom: string,
 *   email: string,
 *   sujet: string,
 *   message: string
 * }
 */
function kb_handle_contact_form(array $post, array $server): array
{
    $success = null;
    $error   = null;

    $nom     = trim($post['nom'] ?? '');
    $email   = trim($post['email'] ?? '');
    $sujet   = trim($post['sujet'] ?? '');
    $message = trim($post['message'] ?? '');

    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
        return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
    }

    // Champs requis
    if ($nom === '' || $email === '' || $message === '') {
        $error = "Merci de remplir tous les champs obligatoires (*), y compris le reCAPTCHA.";
        return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail saisie n'est pas valide.";
        return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
    }

    // Vérification reCAPTCHA côté serveur
    $token = $post['g-recaptcha-response'] ?? '';
    if ($token === '') {
        $error = "Veuillez valider le reCAPTCHA.";
        return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
    }

    $verifyUrl = "https://www.google.com/recaptcha/api/siteverify";
    $postData  = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $server['REMOTE_ADDR'] ?? null,
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 8,
        ]
    ];

    $context = stream_context_create($opts);
    $verify  = @file_get_contents($verifyUrl, false, $context);
    $captcha = $verify ? json_decode($verify, true) : null;

    if (empty($captcha['success'])) {
        $error = "Échec reCAPTCHA. Réessayez.";
        return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
    }

    // Envoi du mail
    $to      = "kitabee@alwaysdata.net";
    $subject = $sujet !== '' ? $sujet : "Nouveau message depuis le formulaire Kitabee";

    $body  = "Message envoyé depuis le formulaire de contact Kitabee :\n\n";
    $body .= "Nom : $nom\n";
    $body .= "E-mail : $email\n";
    $body .= "Sujet : $sujet\n\n";
    $body .= "Message :\n$message\n";

    $headers  = "From: $nom <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (@mail($to, $subject, $body, $headers)) {
        $success = "Merci ! Votre message a bien été envoyé.";
        $nom = $email = $sujet = $message = '';
    } else {
        $error = "Une erreur est survenue lors de l’envoi du message.";
    }

    return compact('success', 'error', 'nom', 'email', 'sujet', 'message');
}

/**
 * Récupère l'état des cookies liés à la navigation et au consentement.
 *
 * Cette fonction permet de centraliser :
 * - la présence du cookie de session "visited"
 * - le consentement aux cookies non essentiels
 * - les cookies persistants affichables (style, dernière visite)
 *
 * @return array{
 *   visited: bool,
 *   consent: string|null,
 *   style: string|null,
 *   last_visit: string|null
 * }
 */
function kb_get_cookie_status(): array
{
    $visited = isset($_COOKIE['visited']);
    $consent = $_COOKIE['cookie_consent'] ?? null;

    return [
        'visited'    => $visited,
        'consent'    => $consent,
        'style'      => $_COOKIE['style'] ?? null,
        'last_visit' => $_COOKIE['last_visit'] ?? null,
    ];
}

/**
 * Exécute les tâches automatiques de maintenance Kitabee (cron).
 *
 * Tâches :
 * 1) Supprimer les messages de clubs de +24h
 * 2) Supprimer les comptes non activés depuis +24h
 * 3) Supprimer les fichiers temporaires  de +24h
 * 4) Nettoyer les tokens de reset expirés
 *
 * Retour :
 * - Un tableau de lignes de log (strings) prêt à être affiché.
 *
 * @param PDO $pdo Connexion PDO.
 * @param string $tmpPath Chemin absolu du dossier tmp 
 *
 * @return string[] Liste des logs.
 */
function kb_run_cron_tasks(PDO $pdo, string $tmpPath): array
{
    $logs = [];

    $logs[] = "Cron Kitabee lancé : " . date('Y-m-d H:i:s');

    /* 1) Supprimer les messages de clubs de +24h */
    try {
        $pdo->exec("
            DELETE FROM book_club_messages
            WHERE created_at < NOW() - INTERVAL 1 DAY
        ");
        $logs[] = "Messages de +24h supprimés.";
    } catch (Throwable $e) {
        $logs[] = "Erreur messages : " . $e->getMessage();
    }

    /* 2) Supprimer les comptes non activés depuis +24h */
    try {
        $pdo->exec("
            DELETE FROM users
            WHERE is_active = 0
              AND created_at < NOW() - INTERVAL 1 DAY
        ");
        $logs[] = "Comptes inactifs supprimés.";
    } catch (Throwable $e) {
        $logs[] = "Erreur comptes : " . $e->getMessage();
    }

    /* 3) Supprimer les fichiers tmp de +24h */
    if (is_dir($tmpPath)) {
        try {
            foreach (glob(rtrim($tmpPath, '/') . '/*') as $file) {
                if (is_file($file) && filemtime($file) < time() - 86400) {
                    @unlink($file);
                }
            }
            $logs[] = "Fichiers tmp nettoyés.";
        } catch (Throwable $e) {
            $logs[] = "Erreur tmp : " . $e->getMessage();
        }
    } else {
        $logs[] = "Dossier tmp introuvable : " . $tmpPath;
    }

    /* 4) Nettoyage des tokens expirés */
    try {
        $pdo->exec("
            UPDATE users
            SET reset_token = NULL,
                reset_token_expires = NULL
            WHERE reset_token_expires < NOW()
        ");
        $logs[] = "Tokens expirés nettoyés.";
    } catch (Throwable $e) {
        $logs[] = "Erreur tokens : " . $e->getMessage();
    }

    $logs[] = "Cron terminé.";

    return $logs;
}

/**
 * Récupère les données nécessaires au tableau de bord utilisateur.
 *
 * Cette fonction centralise :
 * - la liste des badges de l'utilisateur
 * - le nombre de demandes d'amis en attente
 * - le nombre d'invitations de clubs non lues
 * - le nombre total de messages de clubs non lus
 *
 * @param PDO $pdo Connexion PDO.
 * @param int $userId ID de l'utilisateur connecté.
 *
 * @return array{
 *   userBadges: array,
 *   pendingFriendRequests: int,
 *   pendingClubInvites: int,
 *   unreadClubMessagesTotal: int
 * }
 */
function kb_get_dashboard_data(PDO $pdo, int $userId): array
{
    // Badges
    $badgeManager = new BadgeManager($pdo);
    $userBadges   = $badgeManager->getUserBadges($userId);

    // Demandes d'amis en attente
    $pendingFriendRequests = 0;
    if ($userId) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_friends
                WHERE friend_id = :uid
                  AND status = 'pending'
            ");
            $stmt->execute([':uid' => $userId]);
            $pendingFriendRequests = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $pendingFriendRequests = 0;
        }
    }

    // Invitations clubs
    $pendingClubInvites = 0;
    if ($userId) {
        try {
            $stmtClub = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :uid
                  AND type = 'club_invite'
                  AND is_read = 0
            ");
            $stmtClub->execute([':uid' => $userId]);
            $pendingClubInvites = (int)$stmtClub->fetchColumn();
        } catch (Throwable $e) {
            $pendingClubInvites = 0;
        }
    }

    // Messages clubs non lus (total)
    $unreadClubMessagesTotal = 0;
    if ($userId) {
        try {
            $stmtMsg = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :uid
                  AND type = 'club_message'
                  AND is_read = 0
            ");
            $stmtMsg->execute([':uid' => $userId]);
            $unreadClubMessagesTotal = (int)$stmtMsg->fetchColumn();
        } catch (Throwable $e) {
            $unreadClubMessagesTotal = 0;
        }
    }

    return [
        'userBadges'              => $userBadges,
        'pendingFriendRequests'   => $pendingFriendRequests,
        'pendingClubInvites'      => $pendingClubInvites,
        'unreadClubMessagesTotal' => $unreadClubMessagesTotal,
    ];
}

/**
 * Déconnecte proprement l'utilisateur courant.
 *
 * Cette fonction :
 * - vide les données de session
 * - supprime le cookie de session si présent
 * - détruit la session PHP
 *
 * @return void
 */
function kb_logout_user(): void
{
    // Vider la session
    $_SESSION = [];

    // Supprimer le cookie de session si utilisé
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Détruire la session
    session_destroy();
}

/**
 * Prépare les données de la page detail.php (détail d'un livre).
 *
 * - Récupère le livre via l'API Google Books (volume par ID).
 * - Détermine si le livre est dans la wishlist / bibliothèque de l'utilisateur.
 * - Gère l'enregistrement d'une note/commentaire personnel (si POST + livre en bibliothèque).
 * - Récupère la note/commentaire perso et les statistiques globales (moyenne + nb avis).
 *
 * @param PDO   $pdo          Connexion PDO.
 * @param string $googleBookId ID Google Books (volume ID).
 * @param int   $loggedUserId ID utilisateur connecté (0 si non connecté).
 * @param string|null $googleApiKey Clé Google Books API (peut être null/empty).
 *
 * @return array{
 *   ok: bool,
 *   error?: string,
 *   book?: array,
 *   info?: array,
 *   title?: string,
 *   authors?: string,
 *   description?: string,
 *   thumbnail?: string,
 *   publisher?: string,
 *   publishedDate?: string,
 *   pageCount?: string|int,
 *   categories?: string,
 *   isInWishlist?: bool,
 *   isInLibrary?: bool,
 *   personalRating?: ?int,
 *   personalComment?: string,
 *   avgRating?: ?float,
 *   avgCount?: int
 * }
 */
function kb_get_book_detail_context(PDO $pdo, string $googleBookId, int $loggedUserId = 0, ?string $googleApiKey = null): array
{
    $googleBookId = trim($googleBookId);
    if ($googleBookId === '') {
        return ['ok' => false, 'error' => 'Aucun livre sélectionné.'];
    }

    // --- Récupération du livre via Google Books ---
    $baseUrl = 'https://www.googleapis.com/books/v1/volumes/' . urlencode($googleBookId);
    $url     = $baseUrl . (!empty($googleApiKey) ? ('?key=' . urlencode($googleApiKey)) : '');

    $response = @file_get_contents($url);
    $book     = $response ? json_decode($response, true) : null;

    if (empty($book['volumeInfo'])) {
        return ['ok' => false, 'error' => 'Livre introuvable.'];
    }

    $info = $book['volumeInfo'];

    $title         = $info['title'] ?? 'Titre inconnu';
    $authors       = isset($info['authors']) ? implode(', ', (array)$info['authors']) : 'Auteur inconnu';
    $description   = $info['description'] ?? 'Pas de description disponible.';
    $thumbnail     = $info['imageLinks']['thumbnail'] ?? "https://via.placeholder.com/200x300?text=Pas+d'image";
    $publisher     = $info['publisher'] ?? 'Éditeur inconnu';
    $publishedDate = $info['publishedDate'] ?? 'Date inconnue';
    $pageCount     = $info['pageCount'] ?? 'Non précisé';
    $categories    = isset($info['categories']) ? implode(', ', (array)$info['categories']) : 'Non classé';

    // --- Déterminer si le livre est dans la wishlist / bibliothèque ---
    $isInWishlist = false;
    $isInLibrary  = false;

    if ($loggedUserId > 0) {
        // Wishlist
        $stmt = $pdo->prepare("
            SELECT 1
            FROM user_wishlist
            WHERE user_id = :uid AND google_book_id = :bid
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => (int)$loggedUserId,
            ':bid' => $googleBookId
        ]);
        $isInWishlist = (bool)$stmt->fetchColumn();

        // Bibliothèque
        $stmt = $pdo->prepare("
            SELECT 1
            FROM user_library
            WHERE user_id = :uid AND google_book_id = :bid
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => (int)$loggedUserId,
            ':bid' => $googleBookId
        ]);
        $isInLibrary = (bool)$stmt->fetchColumn();
    }

    // ===== Notes & commentaires =====
    $personalRating  = null;
    $personalComment = '';
    $avgRating       = null;
    $avgCount        = 0;

    // Enregistrement note/commentaire perso (si connecté + dans bibliothèque)
    if ($loggedUserId > 0 && $isInLibrary && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
        $rating  = $_POST['rating'] ?? '';
        $comment = trim($_POST['private_comment'] ?? '');

        if ($rating === '') {
            $rating = null;
        } else {
            $rating = (int)$rating;
            if ($rating < 1 || $rating > 5) {
                $rating = null;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE user_library
            SET rating = :rating,
                private_comment = :comment
            WHERE user_id = :uid
              AND google_book_id = :gid
        ");
        $stmt->execute([
            ':rating'  => $rating,
            ':comment' => ($comment !== '' ? $comment : null),
            ':uid'     => (int)$loggedUserId,
            ':gid'     => $googleBookId
        ]);

        // Mise à jour locale pour l'affichage
        $personalRating  = $rating;
        $personalComment = $comment;
    }

    // Récupérer infos perso si connecté + livre dans bibliothèque
    if ($loggedUserId > 0 && $isInLibrary) {
        $stmt = $pdo->prepare("
            SELECT rating, private_comment
            FROM user_library
            WHERE user_id = :uid
              AND google_book_id = :gid
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => (int)$loggedUserId,
            ':gid' => $googleBookId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($personalRating === null && $row['rating'] !== null) {
                $personalRating = (int)$row['rating'];
            }
            if ($personalComment === '' && $row['private_comment'] !== null) {
                $personalComment = $row['private_comment'];
            }
        }
    }

    // Moyenne globale des notes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS nb, AVG(rating) AS avg_rating
        FROM user_library
        WHERE google_book_id = :gid
          AND rating IS NOT NULL
    ");
    $stmt->execute([':gid' => $googleBookId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats && (int)$stats['nb'] > 0) {
        $avgRating = (float)$stats['avg_rating'];
        $avgCount  = (int)$stats['nb'];
    }

    return [
        'ok'              => true,
        'book'            => $book,
        'info'            => $info,
        'title'           => $title,
        'authors'         => $authors,
        'description'     => $description,
        'thumbnail'       => $thumbnail,
        'publisher'       => $publisher,
        'publishedDate'   => $publishedDate,
        'pageCount'       => $pageCount,
        'categories'      => $categories,
        'isInWishlist'    => $isInWishlist,
        'isInLibrary'     => $isInLibrary,
        'personalRating'  => $personalRating,
        'personalComment' => $personalComment,
        'avgRating'       => $avgRating,
        'avgCount'        => $avgCount,
    ];
}

/**
 * Traite une requête AJAX JSON pour la gestion des amis (FriendManager).
 *
 * Cette fonction :
 * - vérifie que l'utilisateur est authentifié (session),
 * - exécute l'action demandée (send/accept/decline/remove),
 * - renvoie un tableau prêt à être encodé en JSON.
 *
 * @param PDO $pdo Connexion PDO.
 *
 * @return array{ok: bool, error?: string}
 */
function kb_handle_friends_ajax(PDO $pdo): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        return ['ok' => false, 'error' => 'not_authenticated'];
    }

    require_once __DIR__ . '/../classes/FriendManager.php';

    $userId  = (int)$_SESSION['user'];
    $fm      = new FriendManager($pdo, $userId);

    $action  = $_POST['action'] ?? '';
    $otherId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    switch ($action) {
        case 'send':
            return ['ok' => $fm->sendRequest($otherId)];

        case 'accept':
            return ['ok' => $fm->acceptRequest($otherId)];

        case 'decline':
            return ['ok' => $fm->declineRequest($otherId)];

        case 'remove':
            return ['ok' => $fm->removeFriend($otherId)];

        default:
            return ['ok' => false, 'error' => 'unknown_action'];
    }
}

/**
 * Récupère une liste de livres depuis l'API Google Books.
 *
 * Cette fonction construit l'URL de recherche Google Books, exécute l'appel HTTP
 * et retourne la liste des items (volumes) si disponible.
 *
 * @param string      $query      Requête Google Books.
 * @param string|null $apiKey     Clé API Google (optionnelle). Si null/vide, l'appel se fait sans clé.
 * @param int         $maxResults Nombre de résultats (max 40 côté Google Books, souvent 10/20/40).
 * @param int         $startIndex Index de départ (pagination).
 * @param string      $lang       Filtre de langue.
 *
 * @return array Liste des items Google Books. Vide si erreur/aucun résultat.
 */
function kb_google_books_search(
    string $query,
    ?string $apiKey = null,
    int $maxResults = 6,
    int $startIndex = 0,
    string $lang = 'fr'
): array {
    $base = "https://www.googleapis.com/books/v1/volumes";

    $url = $base
        . "?q=" . urlencode($query)
        . "&langRestrict=" . urlencode($lang)
        . "&startIndex=" . (int)$startIndex
        . "&maxResults=" . (int)$maxResults;

    if (!empty($apiKey)) {
        $url .= "&key=" . urlencode($apiKey);
    }

    $apiResponse = @file_get_contents($url);
    if (!$apiResponse) {
        return [];
    }

    $json = json_decode($apiResponse, true);
    if (empty($json['items']) || !is_array($json['items'])) {
        return [];
    }

    return $json['items'];
}

/**
 * Vérifie la robustesse d'un mot de passe.
 *
 * Règles :
 * - longueur >= 6
 * - au moins 1 majuscule
 * - au moins 1 minuscule
 * - au moins 1 chiffre
 * - au moins 1 caractère spécial
 *
 * @param string $pwd Mot de passe à vérifier.
 * @return bool True si le mot de passe respecte les règles, sinon false.
 */
function kb_is_strong_password(string $pwd): bool {
    if (strlen($pwd) < 6) return false;
    if (!preg_match('/[A-Z]/', $pwd)) return false;
    if (!preg_match('/[a-z]/', $pwd)) return false;
    if (!preg_match('/[0-9]/', $pwd)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return false;
    return true;
}

/**
 * Récupère la réponse brute de l'API Google Books.
 *
 * Cette fonction effectue une requête vers l'API Google Books
 * et retourne le JSON décodé complet.
 *
 * @param string      $query      Terme de recherche (titre, auteur, ISBN, etc.).
 * @param string|null $apiKey     Clé API Google Books (optionnelle).
 * @param int         $maxResults Nombre max de résultats (1 à 40).
 *
 * @return array|null Réponse JSON complète décodée ou null en cas d’échec.
 */
function kb_google_books_fetch_raw(
    string $query,
    ?string $apiKey = null,
    int $maxResults = 12
): ?array {
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $maxResults = max(1, min(40, (int)$maxResults));
    $url = 'https://www.googleapis.com/books/v1/volumes'
         . '?q=' . urlencode($query)
         . '&maxResults=' . $maxResults;

    if (!empty($apiKey)) {
        $url .= '&key=' . urlencode($apiKey);
    }

    $json = @file_get_contents($url);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Déplace un livre de la wishlist vers la bibliothèque de l'utilisateur.
 *
 * Étapes :
 * - Récupère les informations du livre depuis la wishlist.
 * - Supprime le livre de la wishlist.
 * - Ajoute le livre dans la bibliothèque (user_library).
 *
 * @param PDO    $pdo     Instance PDO.
 * @param int    $userId  Identifiant de l'utilisateur.
 * @param string $bookId  Identifiant Google Books du livre.
 *
 * @return bool True si le déplacement a réussi, false sinon.
 */
function kb_move_book_wishlist_to_library(PDO $pdo, int $userId, string $bookId): bool
{
    if ($bookId === '') {
        return false;
    }

    // 1. Récupérer le livre depuis la wishlist
    $stmt = $pdo->prepare("
        SELECT title, authors, thumbnail
        FROM user_wishlist
        WHERE user_id = :uid AND google_book_id = :bid
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':bid' => $bookId,
    ]);

    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        return false;
    }

    // 2. Supprimer de la wishlist
    $stmt = $pdo->prepare("
        DELETE FROM user_wishlist
        WHERE user_id = :uid AND google_book_id = :bid
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':bid' => $bookId,
    ]);

    // 3. Ajouter à la bibliothèque
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_library
        (user_id, google_book_id, title, authors, thumbnail, added_at)
        VALUES (:uid, :bid, :title, :authors, :thumb, NOW())
    ");
    $stmt->execute([
        ':uid'     => $userId,
        ':bid'     => $bookId,
        ':title'   => $book['title'] ?? null,
        ':authors' => $book['authors'] ?? null,
        ':thumb'   => $book['thumbnail'] ?? null,
    ]);

    return true;
}

/**
 * Lance une procédure "mot de passe oublié" :
 * - Vérifie l'existence de l'utilisateur via l'e-mail.
 * - Génère un token de réinitialisation + date d'expiration (1h).
 * - Stocke ces informations en base.
 * - Construit un lien de reset et envoie un email.
 *
 * @param PDO    $pdo         Instance PDO.
 * @param string $email       Adresse e-mail saisie.
 * @param string $siteBaseUrl Base URL du site.
 * @param string $mailFrom    Adresse utilisée dans l'en-tête From.
 *
 * @return array Tableau contenant :
 *               - 'ok' (bool)
 *               - 'message' (string|null)
 *               - 'error' (string|null)
 */
function kb_password_reset_request(PDO $pdo, string $email, string $siteBaseUrl, string $mailFrom): array
{
    $email = trim($email);

    if ($email === '') {
        return ['ok' => false, 'message' => null, 'error' => "Veuillez saisir votre adresse e-mail."];
    }

    // Vérifier si l'email existe
    $stmt = $pdo->prepare('SELECT id, login FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['ok' => false, 'message' => null, 'error' => "Aucun compte trouvé avec cette adresse e-mail."];
    }

    // Générer token + expiration (1h)
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare('
        UPDATE users
        SET reset_token = :token, reset_token_expires = :expires
        WHERE id = :id
    ');
    $stmt->execute([
        ':token'   => $token,
        ':expires' => $expires,
        ':id'      => (int)$user['id'],
    ]);

    // Construire le lien
    $reset_link = rtrim($siteBaseUrl, '/') . '/reset_mdp.php?token=' . urlencode($token);

    // Envoyer l'email
    $to      = $email;
    $subject = "Réinitialisation de votre mot de passe Kitabee";
    $body    = "Bonjour {$user['login']},\n\n"
             . "Pour réinitialiser votre mot de passe, cliquez sur ce lien :\n$reset_link\n\n"
             . "Ce lien est valable 1 heure.";

    $headers  = "From: $mailFrom\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $body, $headers);

    return ['ok' => true, 'message' => "Un email de réinitialisation a été envoyé à votre adresse.", 'error' => null];
}

/**
 * Traite la page "profil utilisateur" (lecture + update + suppression).
 *
 * Rôle :
 * - Récupère l'utilisateur courant (login, email, avatar_choice, password).
 * - Si POST delete_account : vérifie le mot de passe puis supprime le compte + détruit la session.
 * - Si POST update : valide login/email uniques, gère avatar_choice, optionnellement change le mot de passe.
 * - Met à jour la session (login + avatar_has).
 * - Déclenche le check des badges (BadgeManager) si mise à jour OK.
 *
 * @param PDO $pdo Instance PDO.
 * @param int $userId ID utilisateur connecté.
 *
 * @return array{
 *   ok: bool,
 *   user: array|null,
 *   message: string|null,
 *   error: string|null,
 *   hasAvatar: bool,
 *   avatarUrl: string|null,
 *   redirect: string|null
 * }
 */
function kb_profile_handle(PDO $pdo, int $userId): array
{
    $result = [
        'ok'       => false,
        'user'     => null,
        'message'  => null,
        'error'    => null,
        'hasAvatar'=> false,
        'avatarUrl'=> null,
        'redirect' => null,
    ];

    /**
     * Vérifie la robustesse du mot de passe :
     * - longueur >= 6
     * - au moins 1 majuscule
     * - au moins 1 minuscule
     * - au moins 1 chiffre
     * - au moins 1 caractère spécial
     */
    $is_strong_password = function (string $pwd): bool {
        if (strlen($pwd) < 6) return false;
        if (!preg_match('/[A-Z]/', $pwd)) return false;
        if (!preg_match('/[a-z]/', $pwd)) return false;
        if (!preg_match('/[0-9]/', $pwd)) return false;
        if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return false;
        return true;
    };

    // Récupérer les infos actuelles
    $stmt = $pdo->prepare("
        SELECT login, email, avatar_choice, password
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $result['error'] = "Utilisateur introuvable.";
        return $result;
    }

    $result['user'] = $user;

    // Calcul affichage avatar
    $currentAvatarChoice = $user['avatar_choice'] ?? null;
    $hasAvatar = !empty($currentAvatarChoice);
    $avatarUrl = $hasAvatar ? 'avatar.php?id=' . urlencode((string)$userId) : null;

    $result['hasAvatar'] = $hasAvatar;
    $result['avatarUrl'] = $avatarUrl;

    /* ---------------------------------------------
       SUPPRESSION DE COMPTE
    ----------------------------------------------*/
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

        $passwordDelete = $_POST['password_delete'] ?? '';

        if ($passwordDelete === '') {
            $result['error'] = "Veuillez saisir votre mot de passe pour confirmer la suppression.";
            return $result;
        }

        if (!password_verify($passwordDelete, $user['password'])) {
            $result['error'] = "Mot de passe incorrect. Suppression annulée.";
            return $result;
        }

        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $okDel   = $stmtDel->execute([':id' => $userId]);

        if ($okDel) {
            // destruction session côté page (on renvoie une redirection)
            $result['ok'] = true;
            $result['redirect'] = 'index.php?account_deleted=1';
            return $result;
        }

        $result['error'] = "Impossible de supprimer votre compte pour le moment.";
        return $result;
    }

    /* ---------------------------------------------
       MISE À JOUR PROFIL (login, mail, avatar, mdp…)
    ----------------------------------------------*/
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {

        $newLogin           = trim($_POST['login'] ?? '');
        $newEmail           = trim($_POST['email'] ?? '');
        $newPassword        = $_POST['password'] ?? '';
        $newPasswordConfirm = $_POST['password_confirm'] ?? '';

        // Avatar
        $allowedAvatars = ['candice', 'genie', 'jerry', 'snoopy', 'belle', 'naruto'];
        $avatarChoicePost = $_POST['avatar_choice'] ?? '';

        if ($avatarChoicePost === '' || $avatarChoicePost === 'none') {
            $avatarChoice = null;
        } else {
            if (!in_array($avatarChoicePost, $allowedAvatars, true)) {
                $result['error'] = "Avatar choisi invalide.";
                $avatarChoice = null;
            } else {
                $avatarChoice = $avatarChoicePost;
            }
        }

        if (!$result['error']) {
            if ($newLogin === '' || $newEmail === '') {
                $result['error'] = "L'identifiant et l'e-mail ne peuvent pas être vides.";
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $result['error'] = "L'adresse e-mail n'est pas valide.";
            }
        }

        // login unique
        if (!$result['error']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :login AND id <> :id LIMIT 1");
            $stmt->execute([':login' => $newLogin, ':id' => $userId]);
            if ($stmt->fetch()) {
                $result['error'] = "Cet identifiant est déjà utilisé par un autre compte.";
            }
        }

        // email unique
        if (!$result['error']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
            $stmt->execute([':email' => $newEmail, ':id' => $userId]);
            if ($stmt->fetch()) {
                $result['error'] = "Cet e-mail est déjà utilisé par un autre compte.";
            }
        }

        $ok = false;

        if (!$result['error']) {

            // Avec changement de mot de passe
            if ($newPassword !== '' || $newPasswordConfirm !== '') {

                if ($newPassword !== $newPasswordConfirm) {
                    $result['error'] = "Les deux mots de passe ne correspondent pas.";
                } elseif (!$is_strong_password($newPassword)) {
                    $result['error'] = "Le mot de passe doit contenir au moins 6 caractères, avec au minimum une majuscule, une minuscule, un chiffre et un caractère spécial.";
                } else {
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET login         = :login,
                            email         = :email,
                            password      = :password,
                            avatar_choice = :avatar_choice
                        WHERE id = :id
                    ");

                    $ok = $stmt->execute([
                        ':login'         => $newLogin,
                        ':email'         => $newEmail,
                        ':password'      => $hashed,
                        ':avatar_choice' => $avatarChoice,
                        ':id'            => $userId
                    ]);
                }

            } else {
                // Sans modification du mot de passe
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET login         = :login,
                        email         = :email,
                        avatar_choice = :avatar_choice
                    WHERE id = :id
                ");

                $ok = $stmt->execute([
                    ':login'         => $newLogin,
                    ':email'         => $newEmail,
                    ':avatar_choice' => $avatarChoice,
                    ':id'            => $userId
                ]);
            }

            if (!$result['error']) {
                if ($ok) {
                    // maj session
                    $_SESSION['login']      = $newLogin;
                    $_SESSION['avatar_has'] = !empty($avatarChoice);

                    // maj structure locale
                    $user['login']         = $newLogin;
                    $user['email']         = $newEmail;
                    $user['avatar_choice'] = $avatarChoice;

                    // maj affichage avatar
                    $currentAvatarChoice = $avatarChoice;
                    $hasAvatar = !empty($currentAvatarChoice);
                    $avatarUrl = $hasAvatar ? 'avatar.php?id=' . urlencode((string)$userId) : null;

                    $result['user']      = $user;
                    $result['hasAvatar'] = $hasAvatar;
                    $result['avatarUrl'] = $avatarUrl;

                    $result['message'] = "Profil mis à jour avec succès 👍";
                    $result['ok'] = true;

                    // Badges
                    if (class_exists('BadgeManager')) {
                        $badgeManager = new BadgeManager($pdo);
                        $newBadges = $badgeManager->checkAllForUser($userId);
                        if (!empty($newBadges)) {
                            $_SESSION['new_badges'] = $newBadges;
                        }
                    }
                } else {
                    $result['error'] = "Une erreur est survenue lors de la mise à jour.";
                }
            }
        }
    }

    return $result;
}

/**
 * Prépare les données de la page recommandations (variants + émotions + appels au service).
 *
 * Rôle :
 * - Récupère l'action du POST et l'émotion sélectionnée.
 * - Maintient ou régénère les "variants" pour varier les recommandations.
 * - Instancie RecommendationService puis récupère :
 *   - recommandations par genres
 *   - recommandations par auteurs
 *   - recommandations par émotion
 *
 * @param PDO         $pdo     Instance PDO.
 * @param int         $userId  ID utilisateur connecté.
 * @param string|null $apiKey  Clé Google Books.
 *
 * @return array{
 *   action: string|null,
 *   selectedEmotion: string|null,
 *   variantGenres: int,
 *   variantAuthors: int,
 *   variantEmotion: int,
 *   recoGenres: array,
 *   recoAuthors: array,
 *   recoEmotion: array
 * }
 */
function kb_recommendations_prepare(PDO $pdo, int $userId, ?string $apiKey = null): array
{
    if (!class_exists('RecommendationService')) {
        throw new RuntimeException('RecommendationService introuvable.');
    }

    // Données du formulaire
    $action          = $_POST['action'] ?? null;
    $selectedEmotion = $_POST['emotion'] ?? ($_POST['emotion_keep'] ?? null);

    // Variants actuels ou nouveaux
    $variantGenres  = isset($_POST['variant_genres'])  ? (int)$_POST['variant_genres']  : rand(1, 999999);
    $variantAuthors = isset($_POST['variant_authors']) ? (int)$_POST['variant_authors'] : rand(1, 999999);
    $variantEmotion = isset($_POST['variant_emotion']) ? (int)$_POST['variant_emotion'] : rand(1, 999999);

    // Refresh ciblé : on régénère uniquement le variant concerné
    if ($action === 'refresh_genres') {
        $variantGenres = rand(1, 999999);
    }
    if ($action === 'refresh_authors') {
        $variantAuthors = rand(1, 999999);
    }
    if ($action === 'refresh_emotion') {
        $variantEmotion = rand(1, 999999);
    }

    $service = new RecommendationService($pdo, $userId, $apiKey);

    // Recos
    $recoGenres  = $service->getByUserGenres(6, $variantGenres);
    $recoAuthors = $service->getByUserAuthors(6, $variantAuthors);
    $recoEmotion = $selectedEmotion ? $service->getByEmotion($selectedEmotion, 6, $variantEmotion) : [];

    return [
        'action'          => $action,
        'selectedEmotion' => $selectedEmotion,
        'variantGenres'   => $variantGenres,
        'variantAuthors'  => $variantAuthors,
        'variantEmotion'  => $variantEmotion,
        'recoGenres'      => is_array($recoGenres) ? $recoGenres : [],
        'recoAuthors'     => is_array($recoAuthors) ? $recoAuthors : [],
        'recoEmotion'     => is_array($recoEmotion) ? $recoEmotion : [],
    ];
}

/**
 * Affiche une carte HTML "Google Book" (item Google Books API).
 *
 * @param array $item Item (volume) Google Books.
 * @return void
 */
function kb_render_google_book_card(array $item): void
{
    $info    = $item['volumeInfo'] ?? [];
    $title   = $info['title'] ?? 'Sans titre';
    $authors = isset($info['authors']) ? implode(', ', (array)$info['authors']) : 'Auteur inconnu';
    $thumb   = $info['imageLinks']['thumbnail'] ?? null;
    $id      = $item['id'] ?? '';
    ?>
    <article class="book-card">
        <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Couverture de <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="muted"><?= htmlspecialchars($authors, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($id): ?>
            <p><a href="detail.php?id=<?= urlencode((string)$id) ?>" class="link-more">Voir plus</a></p>
        <?php endif; ?>
    </article>
    <?php
}

/**
 * Supprime un livre de la bibliothèque d'un utilisateur.
 *
 * @param PDO    $pdo    Instance PDO.
 * @param int    $userId ID de l'utilisateur connecté.
 * @param string $bookId Google Book ID du livre à supprimer.
 * @return bool True si la requête s'est exécutée, false sinon.
 */
function kb_remove_from_library(PDO $pdo, int $userId, string $bookId): bool
{
    $bookId = trim($bookId);
    if ($userId <= 0 || $bookId === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_library
            WHERE user_id = :uid AND google_book_id = :bid
        ");
        return $stmt->execute([
            ':uid' => $userId,
            ':bid' => $bookId,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Supprime un livre de la wishlist d'un utilisateur.
 *
 * @param PDO    $pdo    Instance PDO.
 * @param int    $userId ID de l'utilisateur connecté.
 * @param string $bookId Google Book ID du livre à supprimer.
 *
 * @return bool True si la requête s'est exécutée, false sinon.
 */
function kb_remove_from_wishlist(PDO $pdo, int $userId, string $bookId): bool
{
    $bookId = trim($bookId);

    if ($userId <= 0 || $bookId === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_wishlist
            WHERE user_id = :uid
              AND google_book_id = :bid
        ");

        return $stmt->execute([
            ':uid' => $userId,
            ':bid' => $bookId,
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Réinitialise le mot de passe d'un utilisateur via un token valide.
 *
 * Rôle :
 * - Vérifie que le token existe et n'est pas expiré.
 * - Met à jour le mot de passe.
 * - Supprime le token et sa date d'expiration.
 *
 * @param PDO    $pdo      Instance PDO.
 * @param string $token    Token de réinitialisation.
 * @param string $password Nouveau mot de passe en clair.
 *
 * @return bool True si la réinitialisation a réussi, false sinon.
 */
function kb_reset_password(PDO $pdo, string $token, string $password): bool
{
    $token = trim($token);
    $password = trim($password);

    if ($token === '' || $password === '') {
        return false;
    }

    try {
        // Vérifier token valide
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE reset_token = :token
              AND reset_token_expires > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // Mise à jour du mot de passe
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = :password,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE id = :id
        ");

        return $stmt->execute([
            ':password' => $hash,
            ':id'       => (int)$user['id'],
        ]);

    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Crée un compte utilisateur en base de données.
 *
 * Rôle :
 * - Valide les champs (login, email, password).
 * - Vérifie l'unicité du login et de l'email.
 * - Hash le mot de passe.
 * - Insère l'utilisateur en base.
 *
 * @param PDO    $pdo      Instance PDO.
 * @param string $login    Identifiant utilisateur.
 * @param string $email    Adresse email.
 * @param string $password Mot de passe en clair.
 *
 * @return array Résultat standardisé :
 *               - ok (bool)
 *               - message (string) message utilisateur
 */
function kb_signup_user(PDO $pdo, string $login, string $email, string $password): array
{
    $login    = trim($login);
    $email    = trim($email);
    $password = trim($password);

    if ($login === '' || $email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Veuillez remplir tous les champs'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Email invalide'];
    }

    try {
        // Login déjà pris ?
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Pseudo déjà pris'];
        }

        // Email déjà utilisé ?
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Email déjà utilisé'];
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO users (login, email, password)
            VALUES (:login, :email, :password)
        ');

        $ok = $stmt->execute([
            ':login'    => $login,
            ':email'    => $email,
            ':password' => $hashedPassword
        ]);

        if ($ok) {
            return ['ok' => true, 'message' => 'Inscription réussie !'];
        }

        return ['ok' => false, 'message' => 'Impossible d\'enregistrer le compte'];

    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Erreur serveur'];
    }
}
