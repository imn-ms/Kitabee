<?php
/**
 * connexion.php — Page publique : formulaire de connexion (BD)
 *
 * Cette page affiche un formulaire de connexion et traite l'authentification :
 * - Vérification reCAPTCHA (Google)
 * - Vérification des identifiants en base (password_verify)
 * - Vérification d'activation du compte (is_active)
 * - Initialisation de la session (user, login, avatar_has)
 *
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

// si déjà connecté → on redirige
if (!empty($_SESSION['user'])) {
    $target = $_GET['redirect'] ?? 'dashboard_user.php';
    header('Location: ' . $target);
    exit;
}

// connexion à la base + config (pour reCAPTCHA)
require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

$pageTitle = "Connexion – Kitabee";
$error = null;
$success = null;

// Traitement centralisé
$ctx = kb_handle_login($pdo, [], $_POST, $_GET, $_SERVER);
$error   = $ctx['error'];
$success = $ctx['success'];

if (!empty($ctx['redirect'])) {
    header('Location: ' . $ctx['redirect']);
    exit;
}

include __DIR__ . '/include/header.inc.php';
?>
<section class="section" aria-labelledby="login-title">
  <div class="container" style="max-width:640px;">
    <h1 id="login-title" class="section-title">Connexion</h1>

    <article class="card" style="padding:24px;">
      <h2 class="visually-hidden">Formulaire de connexion</h2>

      <?php if ($success): ?>
        <div id="success-message" class="card" role="status"
             style="padding:12px; border-left:4px solid #16a34a; margin:12px 0; background-color:#f0fdf4;">
          <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="card" role="alert"
             style="padding:12px; border-left:4px solid #dc2626; margin:12px 0; background-color:#fef2f2;">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" style="display:grid; gap:12px;">
        <input type="hidden" name="redirect"
               value="<?= htmlspecialchars($_GET['redirect'] ?? 'dashboard_user.php', ENT_QUOTES, 'UTF-8') ?>">

        <label for="login">Identifiant</label>
        <input id="login" type="text" name="login" required autocomplete="username">

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <!-- Widget reCAPTCHA -->
        <div class="g-recaptcha"
             data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button class="btn btn-primary" type="submit">Se connecter</button>
          <a class="btn btn-ghost" href="index.php">⬅ Retour</a>
        </div>
      </form>

      <!-- 💡 Lien mot de passe oublié -->
      <p style="margin-top:16px; text-align:center;">
        <a href="mdp_oublie.php">Mot de passe oublié ?</a>
      </p>

      <!-- 💡 Lien d'inscription -->
      <p style="margin-top:8px; text-align:center;">
        Pas de compte ? <a href="inscription.php">Inscrivez-vous !</a>
      </p>
    </article>
  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
