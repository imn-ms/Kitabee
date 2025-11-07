<?php
/**
 * header.php
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$loggedUserId   = $_SESSION['user'] ?? null;
$loggedLogin    = $_SESSION['login'] ?? null;
$loggedAvatar   = $_SESSION['avatar'] ?? null;

// ---- COOKIES / CONSENTEMENT ----
$cookieConsent = $_COOKIE['cookie_consent'] ?? null;
$allowNonEssential = ($cookieConsent === 'accepted');
$isPostToggleStyle = isset($_POST['toggle_style']);

// ---- STYLE (jour/nuit) ----
$style = $_COOKIE['style'] ?? 'jour';

if ($isPostToggleStyle) {
  $new = ($style === 'jour') ? 'nuit' : 'jour';
  $style = $new;

  if ($allowNonEssential) {
    setcookie('style', $new, [
      'expires'  => time() + 5 * 24 * 60 * 60,
      'path'     => '/',
      'secure'   => false,
      'httponly' => false,
      'samesite' => 'Lax',
    ]);

    $redirect = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'];
    header('Location: ' . $redirect);
    exit;
  }
}

// ---- DERNIÈRE VISITE ----
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
  <link rel="stylesheet" href="/css/clair.css">
  <?php if ($style === 'nuit'): ?>
    <link rel="stylesheet" href="/css/sombre.css">
  <?php endif; ?>

  <script src="/include/script.js" defer></script>
</head>
<body class="<?= htmlspecialchars($style, ENT_QUOTES) ?>">
<a id="top"></a>

<header class="site-header">
  <div class="container header-inner">
    <a href="/index.php" class="brand">
      <img src="/images/logo.png" alt="Kitabee" class="logo">
      <span class="brand-text">Kitabee</span>
    </a>

    <!-- bouton pour mobile -->
    <button class="menu-toggle" aria-label="Ouvrir le menu">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <nav class="main-nav">
      <a href="/bibliotheque.php" class="chip">Bibliothèque</a>
      <a href="/actualites.php" class="chip">Actualités</a>
      <a href="/cookie.php" class="chip">Cookies</a>
    </nav>

    <div class="actions-right">
      <?php if ($loggedUserId): ?>
        <a href="/dashboard_user.php" class="profile-badge" aria-label="Accéder à mon espace">
          <?php if (!empty($loggedAvatar)): ?>
            <img src="/uploads/avatars/<?= htmlspecialchars($loggedAvatar, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Mon avatar"
                 class="profile-avatar">
          <?php else: ?>
            <span class="profile-circle">
              <?= strtoupper(substr($loggedLogin ?? 'U', 0, 1)) ?>
            </span>
          <?php endif; ?>
        </a>
      <?php else: ?>
        <a href="/connexion.php" class="chip">Connexion</a>
      <?php endif; ?>

      <form method="post" class="theme-toggle" style="display:inline;">
        <button type="submit" name="toggle_style" class="chip" title="Basculer le contraste (non essentiel)">
          Mode <?= ($style === 'jour') ? 'nuit' : 'jour' ?>
        </button>
      </form>
    </div>
  </div>
</header>

<main class="site-content">

