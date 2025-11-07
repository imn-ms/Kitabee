<?php
// inscription.php — formulaire d'inscription avec CAPTCHA + envoi via PHPMailer
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once __DIR__ . '/secret/database.php';

$pageTitle = "Inscription – Kitabee";
$message = $error = null;

/**
 * Génère un CAPTCHA simple (addition)
 */
function generateCaptcha(): void {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    $_SESSION['captcha_text'] = "$a + $b = ?";
}

// Génération initiale du CAPTCHA
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generateCaptcha();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    // Vérifications de base
    if ($login === '' || $email === '' || $password === '' || $captcha === '') {
        $error = "Veuillez remplir tous les champs.";
        generateCaptcha();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
        generateCaptcha();
    } elseif (!isset($_SESSION['captcha_answer']) || (int)$captcha !== (int)$_SESSION['captcha_answer']) {
        $error = "CAPTCHA incorrect.";
        generateCaptcha();
    } else {
        // Vérifier si le login existe déjà
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);
        if ($stmt->fetch()) {
            $error = "Ce pseudo est déjà utilisé.";
            generateCaptcha();
        } else {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = "Cet e-mail est déjà utilisé.";
                generateCaptcha();
            } else {
                // Création du compte
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32)); // token unique

                $stmt = $pdo->prepare('
                    INSERT INTO users (login, email, password, is_active, activation_token)
                    VALUES (:login, :email, :password, 0, :token)
                ');
                $ok = $stmt->execute([
                    ':login' => $login,
                    ':email' => $email,
                    ':password' => $hashedPassword,
                    ':token' => $token
                ]);

                if ($ok) {
                    // Lien d'activation
                    $activation_link = $site_base_url . '/activation.php?token=' . urlencode($token);

                    // Contenu du mail
                    $subject = "Activation de votre compte Kitabee";
                    $body = "Bonjour $login,\n\n"
                          . "Merci de vous être inscrit sur Kitabee.\n\n"
                          . "Pour activer votre compte, cliquez sur ce lien :\n$activation_link\n\n"
                          . "Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.";

                    // Envoi via PHPMailer (fonction dans secret/database.php)
                    $mailSent = sendMail($email, $subject, $body);

                    if ($mailSent) {
                        unset($_SESSION['captcha_answer'], $_SESSION['captcha_text']);
                        $message = "Inscription réussie ! Vérifiez votre e-mail pour activer votre compte.";
                    } else {
                        $error = "Le compte a été créé, mais l’e-mail d’activation n’a pas pu être envoyé. Contactez l’administrateur.";
                    }
                } else {
                    $error = "Erreur lors de la création du compte.";
                    generateCaptcha();
                }
            }
        }
    }
}

include __DIR__ . '/include/header.inc.php';
?>
<section class="section" aria-labelledby="signup-title">
  <div class="container" style="max-width:640px;">
    <h1 id="signup-title" class="section-title">Inscription</h1>

    <article class="card" style="padding:24px;">
      <?php if ($error): ?>
        <div class="card" role="alert" style="padding:12px; border-left:4px solid #dc2626; margin:12px 0;">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="card" role="status" style="padding:12px; border-left:4px solid #16a34a; margin:12px 0;">
          <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
          <p style="margin-top:8px;"><a href="connexion.php">Aller à la connexion</a></p>
        </div>
      <?php endif; ?>

      <?php if (!$message): ?>
      <form method="post" style="display:grid; gap:12px;">
        <label for="login">Identifiant</label>
        <input id="login" name="login" type="text" required>

        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required>

        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" required>

        <label for="captcha">
          CAPTCHA : <?= htmlspecialchars($_SESSION['captcha_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </label>
        <input id="captcha" name="captcha" type="text" required>

        <button class="btn btn-primary" type="submit">Créer mon compte</button>
        <a class="btn btn-ghost" href="connexion.php">⬅ Déjà un compte ? Connexion</a>
      </form>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php include __DIR__ . '/include/footer.inc.php'; ?>
