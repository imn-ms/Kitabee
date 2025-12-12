<?php
/**
 * mdp_oublie.php — Demande de réinitialisation de mot de passe
 *
 * Rôle :
 * - Affiche un formulaire "mot de passe oublié".
 * - À la soumission, déclenche la génération d'un token et l'envoi d'un e-mail.
 * - La logique métier est centralisée dans include/functions.inc.php.
 *
 * 
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

$pageTitle = "Mot de passe oublié – Kitabee";
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $res = kb_password_reset_request(
        $pdo,
        $email,
        $site_base_url ?? '',
        $mail_from ?? 'no-reply@kitabee.local'
    );

    $message = $res['message'] ?? null;
    $error   = $res['error'] ?? null;
}

include __DIR__ . '/include/header.inc.php';
?>
<section class="section">
  <div class="container" style="max-width:640px;">
    <article class="card" style="padding:24px;">
      <h1>Mot de passe oublié</h1>

      <?php if ($error): ?>
        <div style="padding:12px; border-left:4px solid #dc2626; margin:12px 0;">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div style="padding:12px; border-left:4px solid #16a34a; margin:12px 0;">
          <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php else: ?>
        <form method="post" style="display:grid; gap:12px;">
          <label for="email">Adresse e-mail</label>
          <input id="email" type="email" name="email" required>
          <button class="btn btn-primary" type="submit">Envoyer le lien</button>
          <a class="btn btn-ghost" href="connexion.php">⬅ Retour à la connexion</a>
        </form>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php include __DIR__ . '/include/footer.inc.php'; ?>
