<?php
/**
 * header.php – Kitabee
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ========= UTILISATEUR ========= */
$loggedUserId   = $_SESSION['user'] ?? null;
$loggedLogin    = $_SESSION['login'] ?? null;
$loggedAvatar   = $_SESSION['avatar'] ?? null;

/* ========= COOKIES / STYLE ========= */
$cookieConsent = $_COOKIE['cookie_consent'] ?? null;
$allowNonEssential = ($cookieConsent === 'accepted');
$isPostToggleStyle = isset($_POST['toggle_style']);

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

/* ========= LANGUES (via LibreTranslate, gratuit) ========= */
$AVAILABLE_LANGS = ['fr', 'en', 'es'];
$DEFAULT_LANG = 'fr';

if (isset($_GET['lang']) && in_array($_GET['lang'], $AVAILABLE_LANGS, true)) {
  $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? $DEFAULT_LANG;

/**
 * Appelle l’API LibreTranslate pour traduire un texte (FR -> EN/ES)
 * Documentation : https://docs.libretranslate.com/
 */
function lt_translate(string $text, string $target): string {
  if ($text === '') return '';

  if (!isset($_SESSION['trans_cache'])) {
    $_SESSION['trans_cache'] = [];
  }
  $cacheKey = md5($target . '|' . $text);
  if (isset($_SESSION['trans_cache'][$cacheKey])) {
    return $_SESSION['trans_cache'][$cacheKey];
  }

  $apiUrl = 'https://libretranslate.de/translate';
  $payload = [
    'q' => $text,
    'source' => 'fr',
    'target' => $target,
    'format' => 'text'
  ];

  // Essai via cURL (plus fiable sur hébergeurs)
  if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_TIMEOUT => 4,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($result && !$err) {
      $json = json_decode($result, true);
      if (isset($json['translatedText'])) {
        $_SESSION['trans_cache'][$cacheKey] = $json['translatedText'];
        return $json['translatedText'];
      }
    }
  }

  // fallback file_get_contents
  $ctx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/json\r\n",
      'content' => json_encode($payload),
      'timeout' => 4,
    ]
  ]);
  $result = @file_get_contents($apiUrl, false, $ctx);
  if ($result) {
    $json = json_decode($result, true);
    if (isset($json['translatedText'])) {
      $_SESSION['trans_cache'][$cacheKey] = $json['translatedText'];
      return $json['translatedText'];
    }
  }

  return $text; // fallback : texte original
}

/**
 * Fonction utilitaire à utiliser dans les pages
 * Exemple : <?= t("Bienvenue sur Kitabee") ?>
 */
function t(string $text): string {
  global $currentLang;
  if ($currentLang === 'fr') {
    return $text;
  }
  return lt_translate($text, $currentLang);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
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
  <style>
    .lang-switch {
      display: flex;
      gap: 6px;
      align-items: center;
    }
    .lang-switch a {
      text-decoration: none;
      padding: 4px 8px;
      border-radius: 8px;
      font-weight: 600;
      color: var(--ink, #1c1c1c);
      transition: background .2s;
    }
    .lang-switch a:hover {
      background: rgba(95,127,95,.1);
    }
    .lang-switch a.is-active {
      background: #5f7f5f;
      color: white;
    }
  </style>
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
      <a href="/bibliotheque.php" class="chip"><?= t("Bibliothèque") ?></a>
      <a href="/actualites.php" class="chip"><?= t("Actualités") ?></a>
      <a href="/recommandations.php" class="chip"><?= t("Recommandations") ?></a>
      <a href="/contact.php" class="chip"><?= t("Contact") ?></a>
    </nav>

    <div class="actions-right">
      <div class="lang-switch">
        <a href="?lang=fr" class="<?= $currentLang === 'fr' ? 'is-active' : '' ?>">FR</a>
        <a href="?lang=en" class="<?= $currentLang === 'en' ? 'is-active' : '' ?>">EN</a>
        <a href="?lang=es" class="<?= $currentLang === 'es' ? 'is-active' : '' ?>">ES</a>
      </div>

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
        <a href="/connexion.php" class="chip"><?= t("Connexion") ?></a>
      <?php endif; ?>

      <form method="post" class="theme-toggle" style="display:inline;">
        <button type="submit" name="toggle_style" class="chip" title="<?= t("Basculer le contraste") ?>">
          <?= ($style === 'jour') ? t("Mode nuit") : t("Mode jour") ?>
        </button>
      </form>
    </div>
  </div>
</header>

<main class="site-content">
