<?php
/**
 * reset_mdp.php — Réinitialisation du mot de passe utilisateur.
 *
 * Rôle :
 * - Vérifie la validité du token passé en GET.
 * - Affiche le formulaire de nouveau mot de passe.
 * - Met à jour le mot de passe via une fonction dédiée.
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

$pageTitle = "Réinitialisation du mot de passe – Kitabee";
$message = null;
$error   = null;

$token = $_GET['token'] ?? '';

if ($token === '') {
    $error = "Lien invalide.";
} else {

    // Vérifier existence du token
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE reset_token = :token
          AND reset_token_expires > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $userExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userExists) {
        $error = "Ce lien n’est plus valide. Veuillez recommencer la procédure.";
    }

    // Traitement du formulaire
    if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm'] ?? '');

        if ($password === '' || $confirm === '') {
            $error = "Veuillez remplir les deux champs.";
        } elseif ($password !== $confirm) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $ok = kb_reset_password($pdo, $token, $password);

            if ($ok) {
                $message = "Mot de passe mis à jour avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $error = "Impossible de mettre à jour le mot de passe.";
            }
        }
    }
}

include __DIR__ . '/include/header.inc.php';
?>

<section class="section">
  <div class="container" style="max-width:640px;">
    <article class="card" style="padding:24px;">
      <h1>Réinitialiser votre mot de passe</h1>

      <?php if ($error): ?>
        <div style="padding:12px; border-left:4px solid #dc2626; margin:12px 0;">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div style="padding:12px; border-left:4px solid #16a34a; margin:12px 0;">
          <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <p><a href="connexion.php">Se connecter</a></p>

      <?php elseif (!$error): ?>
        <form method="post" style="display:grid; gap:12px;">
          <label for="password">Nouveau mot de passe</label>
          <input id="password" type="password" name="password" required>

          <label for="confirm">Confirmer le mot de passe</label>
          <input id="confirm" type="password" name="confirm" required>

          <button class="btn btn-primary" type="submit">
            Mettre à jour
          </button>
        </form>
      <?php endif; ?>
    </article>
  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
