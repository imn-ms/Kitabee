<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/secret/database.php';

$pageTitle = "Mot de passe oublié – Kitabee";
$message = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = "Veuillez saisir votre adresse e-mail.";
    } else {
        // Vérifier si l'email existe
        $stmt = $pdo->prepare('SELECT id, login FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Générer un token unique et une date d’expiration
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // valable 1h

            $stmt = $pdo->prepare('UPDATE users SET reset_token = :token, reset_token_expires = :expires WHERE id = :id');
            $stmt->execute([
                ':token' => $token,
                ':expires' => $expires,
                ':id' => $user['id']
            ]);

            // Construire le lien
            $reset_link = $site_base_url . '/reset_mdp.php?token=' . urlencode($token);

            // Envoyer l'email
            $to = $email;
            $subject = "Réinitialisation de votre mot de passe Kitabee";
            $body = "Bonjour {$user['login']},\n\n".
                    "Pour réinitialiser votre mot de passe, cliquez sur ce lien :\n$reset_link\n\n".
                    "Ce lien est valable 1 heure.";
            $headers = "From: $mail_from\r\nContent-Type: text/plain; charset=UTF-8\r\n";

            @mail($to, $subject, $body, $headers);

            $message = "Un email de réinitialisation a été envoyé à votre adresse.";
        } else {
            $error = "Aucun compte trouvé avec cette adresse e-mail.";
        }
    }
}

include __DIR__ . '/include/header.inc.php';
?>
<section class="section">
  <div class="container" style="max-width:640px;">
    <article class="card" style="padding:24px;">
      <h1>Mot de passe oublié</h1>

      <?php if ($error): ?>
        <div style="padding:12px; border-left:4px solid #dc2626; margin:12px 0;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div style="padding:12px; border-left:4px solid #16a34a; margin:12px 0;"><?= htmlspecialchars($message) ?></div>
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
