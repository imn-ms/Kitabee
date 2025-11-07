<?php
// activation.php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/secret/database.php';

$pageTitle = "Activation du compte – Kitabee";
$message = '';
$error = '';
$asupprimer = "prout";

$token = $_GET['token'] ?? '';

if ($token === '') {
    $error = "Lien d’activation invalide.";
} else {
    // chercher l'utilisateur avec ce token
    $stmt = $pdo->prepare('SELECT id FROM users WHERE activation_token = :token AND is_active = 0 LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // activer le compte
        $stmt = $pdo->prepare('UPDATE users SET is_active = 1, activation_token = NULL WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $message = "Votre compte a été activé ✅ Vous pouvez maintenant vous connecter.";
    } else {
        $error = "Ce lien n’est plus valide ou le compte est déjà activé.";
    }
}

include __DIR__ . '/include/header.inc.php';
?>
<section class="section">
  <div class="container" style="max-width:640px;">
    <article class="card" style="padding:24px;">
      <?php if ($error): ?>
        <h1>Activation impossible</h1>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="connexion.php">Aller à la connexion</a></p>
      <?php else: ?>
        <h1>Compte activé</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="connexion.php">Se connecter</a></p>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php include __DIR__ . '/include/footer.inc.php'; ?>
