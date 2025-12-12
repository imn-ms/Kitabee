<?php
/**
 * header.inc.php
 *
 * Fichier d’en-tête commun à l’ensemble du site Kitabee.
 *
 * Ce fichier est inclus au début de chaque page du site et a pour rôle de :
 * - initialiser le contexte global de l’interface utilisateur,
 * - récupérer les informations liées à l’utilisateur connecté,
 * - afficher le compte utilisateur (avatar ou initiale),
 * - afficher les compteurs de notifications (amis, clubs, messages),
 * - gérer le thème jour / nuit,
 * - préparer les éléments d’accessibilité et de traduction,
 * - afficher la navigation principale du site.
 *
 * Toute la logique PHP a été externalisée dans `include/functions.php`
 * afin de garantir une meilleure lisibilité, une maintenance simplifiée
 * et une séparation claire entre la logique applicative et le HTML.
 *
 * Auteur : MOUSSAOUI Imane & TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

require_once __DIR__ . '/functions.inc.php';

/**
 * Initialisation du contexte global du header.
 *
 * Cette fonction centralise :
 * - la session utilisateur,
 * - les informations de compte,
 * - les notifications,
 * - le thème actif,
 * - la date de dernière visite.
 */
$ctx = kb_header_bootstrap(isset($pdo) ? $pdo : null, $pendingFriendRequests ?? null);

/* =========================
   VARIABLES UTILISÉES DANS LE HEADER
   ========================= */

/** @var int|null Identifiant de l’utilisateur connecté */
$loggedUserId          = $ctx['loggedUserId'];

/** @var string|null Login de l’utilisateur connecté */
$loggedLogin           = $ctx['loggedLogin'];

/** @var bool Indique si l’utilisateur a choisi un avatar */
$loggedHasAvatar       = $ctx['loggedHasAvatar'];

/** @var int Nombre de demandes d’amis en attente */
$pendingFriendRequests = $ctx['pendingFriendRequests'];

/** @var int Nombre d’invitations à des clubs non lues */
$pendingClubInvites    = $ctx['pendingClubInvites'];

/** @var int Nombre de messages de clubs non lus */
$pendingClubMessages   = $ctx['pendingClubMessages'];

/** @var int Nombre total de notifications */
$totalNotifs           = $ctx['totalNotifs'];

/** @var bool Consentement aux cookies non essentiels */
$allowNonEssential     = $ctx['allowNonEssential'];

/** @var string Thème actif (jour / nuit) */
$style                 = $ctx['style'];

/** @var string|null Date de la dernière visite de l’utilisateur */
$last_visit            = $ctx['last_visit'];
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

    <!-- Logo et identité du site -->
    <a href="/index.php" class="brand">
      <img src="/images/logo.png" alt="Kitabee" class="logo">
      <span class="brand-text">Kitabee</span>
    </a>

    <!-- Menu burger (mobile) -->
    <button class="menu-toggle" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>

    <!-- Navigation principale -->
    <nav class="main-nav">
      <a href="/bibliotheque.php">Bibliothèque</a>
      <a href="/actualites.php">Actualités</a>
      <a href="/recommandations.php">Recommandations</a>
      <a href="/contact.php">Contact</a>
    </nav>

    <!-- Compte utilisateur et actions -->
    <div class="actions-right">
      <?php if ($loggedUserId): ?>
        <div class="profile-wrapper">
          <a href="/dashboard_user.php" class="profile-badge" aria-label="Accéder à mon espace">
            <?php if ($loggedHasAvatar): ?>
              <!-- Avatar personnalisé -->
              <img src="/avatar.php?id=<?= (int)$loggedUserId ?>"
                   alt="Mon avatar"
                   class="profile-avatar">
            <?php else: ?>
              <!-- Avatar par défaut : initiale -->
              <span class="profile-circle">
                <?= strtoupper(substr($loggedLogin ?? 'U', 0, 1)) ?>
              </span>
            <?php endif; ?>
          </a>

          <?php if ($totalNotifs > 0): ?>
            <span class="notif-badge avatar-notif-badge"><?= (int)$totalNotifs ?></span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <a href="/connexion.php" class="chip">Connexion</a>
      <?php endif; ?>

      <!-- Changement de thème jour / nuit -->
      <form method="post" class="theme-toggle" style="display:inline;">
        <button type="submit" name="toggle_style" class="chip">
          <?= ($style === 'jour') ? "🌙" : "☀️" ?>
        </button>
      </form>

      <div class="accessibility-tools">
        <button type="button" class="font-btn" data-font="small" aria-label="Diminuer la taille du texte">A-</button>
        <button type="button" class="font-btn" data-font="normal" aria-label="Taille de texte normale">A</button>
        <button type="button" class="font-btn" data-font="large" aria-label="Augmenter la taille du texte">A+</button>
      </div>


      <!-- Bouton Google Translate -->
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

<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<main class="site-content">
