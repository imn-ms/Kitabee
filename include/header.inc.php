<?php
/**
 * header.inc.php – Kitabee
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========= UTILISATEUR ========= */
$loggedUserId     = $_SESSION['user']  ?? null;
$loggedLogin      = $_SESSION['login'] ?? null;
/**
 * Doit être mis à jour dans profil_user.php :
 * $_SESSION['avatar_has'] = !empty($avatarData);
 */
$loggedHasAvatar  = $_SESSION['avatar_has'] ?? false;

/* ========= DEMANDES D'AMIS EN ATTENTE ========= */
$pendingFriendRequests = isset($pendingFriendRequests) ? (int)$pendingFriendRequests : 0;
if ($loggedUserId && isset($pdo) && $pendingFriendRequests === 0) {
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

/* ========= NOTIFICATIONS CLUBS ========= */
$pendingClubInvites  = 0;
$pendingClubMessages = 0;

if ($loggedUserId && isset($pdo)) {
    try {
        // invitations de clubs
        $stmtClub = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :uid
              AND type = 'club_invite'
              AND is_read = 0
        ");
        $stmtClub->execute([':uid' => (int)$loggedUserId]);
        $pendingClubInvites = (int)$stmtClub->fetchColumn();

        // messages de clubs non lus
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

/* ========= THEME JOUR / NUIT ========= */
$cookieConsent     = $_COOKIE['cookie_consent'] ?? null;
$allowNonEssential = ($cookieConsent === 'accepted');
$isPostToggleStyle = isset($_POST['toggle_style']);

$style = $_COOKIE['style'] ?? 'jour';

if ($isPostToggleStyle) {
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
}

/* ========= DERNIÈRE VISITE ========= */
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= $pageTitle ?? 'Kitabee' ?></title>
  <link rel="icon" type="image/png" href="/images/logo.png">

  <!-- Thèmes -->
  <link rel="stylesheet" href="/css/clair.css?v=1">
  <?php if ($style === 'nuit'): ?>
    <link rel="stylesheet" href="/css/sombre.css?v=1">
  <?php endif; ?>

  <script src="/include/script.js" defer></script>

</head>
<body class="<?= htmlspecialchars($style, ENT_QUOTES) ?>">
<a id="top"></a>

<header class="site-header">
  <div class="container header-inner">

    <!-- Logo -->
    <a href="/index.php" class="brand">
      <img src="/images/logo.png" alt="Kitabee" class="logo">
      <span class="brand-text">Kitabee</span>
    </a>

    <!-- Menu burger -->
    <button class="menu-toggle" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>

    <!-- Navigation -->
    <nav class="main-nav">
      <!-- ⚠️ plus de class="chip" ici -->
      <a href="/bibliotheque.php">Bibliothèque</a>
      <a href="/actualites.php">Actualités</a>
      <a href="/recommandations.php">Recommandations</a>
      <a href="/contact.php">Contact</a>
    </nav>

    <!-- Compte utilisateur + actions droite -->
    <div class="actions-right">
      <?php if ($loggedUserId): ?>
        <div class="profile-wrapper">
          <a href="/dashboard_user.php" class="profile-badge" aria-label="Accéder à mon espace">
            <?php if ($loggedHasAvatar): ?>
              <!-- Avatar présent : affichage via script BLOB -->
              <img src="/avatar.php?id=<?= (int)$loggedUserId ?>"
                   alt="Mon avatar"
                   class="profile-avatar">
            <?php else: ?>
              <!-- Pas d’avatar : initiale -->
              <span class="profile-circle">
                <?= strtoupper(substr($loggedLogin ?? 'U', 0, 1)) ?>
              </span>
            <?php endif; ?>
          </a>

          <?php
            $totalNotifs = $pendingFriendRequests + $pendingClubInvites + $pendingClubMessages;
            if ($totalNotifs > 0):
          ?>
            <span class="notif-badge avatar-notif-badge"><?= $totalNotifs ?></span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <!-- Connexion garde le style .chip -->
        <a href="/connexion.php" class="chip">Connexion</a>
      <?php endif; ?>

      <!-- Thème -->
      <form method="post" class="theme-toggle" style="display:inline;">
        <button type="submit" name="toggle_style" class="chip">
          <?= ($style === 'jour') ? "🌙" : "☀️" ?>
        </button>
      </form>
      <!--bouton d'accessibilité-->
      <div class="accessibility-tools" aria-label="Options d’accessibilité">
  <button type="button"
          class="font-btn"
          data-font="small"
          aria-label="Diminuer la taille du texte">
    A-
  </button>

  <button type="button"
          class="font-btn"
          data-font="normal"
          aria-label="Taille de texte normale">
    A
  </button>

  <button type="button"
          class="font-btn"
          data-font="large"
          aria-label="Augmenter la taille du texte">
    A+
  </button>
</div>

      <!-- Bouton de traduction Google -->
      <button id="custom-translate-btn"
              onclick="toggleTranslate();"
              class="chip"
              style="margin-left:8px;">
        🌍
      </button>
    </div>

  </div>

  <!-- Widget Google Translate -->
  <div id="google_translate_element"
       style="position:absolute; top:10px; right:20px; display:none;">
  </div>
</header>

<!-- Scripts Google Translate -->
<script>
  function googleTranslateElementInit() {
    new google.translate.TranslateElement({
      pageLanguage: 'fr',
      includedLanguages: 'fr,en,es',
      layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
  }

  function toggleTranslate() {
    const el = document.getElementById('google_translate_element');
    el.style.display = (el.style.display === 'none' || el.style.display === "")
      ? 'block'
      : 'none';
  }
</script>

<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<main class="site-content">
