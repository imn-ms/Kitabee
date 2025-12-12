<?php
/**
 * inscription.php — formulaire d'inscription avec reCAPTCHA + envoi via PHPMailer
 *
 * Rôle :
 * - Affiche le formulaire d'inscription (login, email, mot de passe).
 * - Vérifie la validité des champs + robustesse du mot de passe.
 * - Vérifie le reCAPTCHA côté serveur.
 * - Crée le compte en base (is_active = 0) avec un token d'activation.
 * - Envoie un e-mail d'activation via sendMail().
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

$pageTitle = "Inscription – Kitabee";
$message = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // Vérifications de base
    if ($login === '' || $email === '' || $password === '') {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } elseif (!kb_is_strong_password($password)) {
        $error = "Le mot de passe doit contenir au moins 6 caractères, avec au minimum une majuscule, une minuscule, un chiffre et un caractère spécial.";
    } elseif ($captchaResponse === '') {
        $error = "Veuillez valider le CAPTCHA.";
    } else {

        // Vérification reCAPTCHA côté Google
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $params = [
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $captchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params)
            ]
        ];

        $context  = stream_context_create($options);
        $result   = file_get_contents($verifyUrl, false, $context);
        $data     = json_decode($result, true);

        if (empty($data['success'])) {
            $error = "CAPTCHA invalide, merci de réessayer.";
        } else {

            // Vérifier si le login existe déjà
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
            $stmt->execute([':login' => $login]);
            if ($stmt->fetch()) {
                $error = "Ce pseudo est déjà utilisé.";
            } else {

                // Vérifier si l'e-mail existe déjà
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    $error = "Cet e-mail est déjà utilisé.";
                } else {

                    // Création du compte
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));

                    $stmt = $pdo->prepare('
                        INSERT INTO users (login, email, password, is_active, activation_token)
                        VALUES (:login, :email, :password, 0, :token)
                    ');

                    $ok = $stmt->execute([
                        ':login'    => $login,
                        ':email'    => $email,
                        ':password' => $hashedPassword,
                        ':token'    => $token
                    ]);

                    if ($ok) {
                        // Lien d’activation
                        $activation_link = $site_base_url . '/activation.php?token=' . urlencode($token);

                        // Email
                        $subject = "Activation de votre compte Kitabee";
                        $body = "Bonjour $login,\n\n"
                              . "Merci de vous être inscrit sur Kitabee.\n\n"
                              . "Pour activer votre compte, cliquez sur ce lien :\n$activation_link\n\n"
                              . "Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.";

                        $mailSent = sendMail($email, $subject, $body);

                        if ($mailSent) {
                            $message = "Inscription réussie ! Vérifiez votre e-mail pour activer votre compte.";
                        } else {
                            $error = "Le compte a été créé, mais l’e-mail d’activation n’a pas pu être envoyé.";
                        }
                    } else {
                        $error = "Erreur lors de la création du compte.";
                    }
                }
            }
        }
    }
}

include __DIR__ . '/include/header.inc.php';
?>

<!-- Script reCAPTCHA -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<section class="section" aria-labelledby="signup-title">
  <div class="container" style="max-width:640px;">
    <h1 id="signup-title" class="section-title">Inscription</h1>

    <article class="card" style="padding:24px;">
      <?php if ($error): ?>
        <div class="card" role="alert" style="padding:12px; border-left:4px solid #dc2626; margin:12px 0;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="card" role="status" style="padding:12px; border-left:4px solid #16a34a; margin:12px 0;">
          <?= htmlspecialchars($message) ?>
          <p style="margin-top:8px;"><a href="connexion.php">Aller à la connexion</a></p>
        </div>
      <?php endif; ?>

      <form method="post" style="display:grid; gap:12px;">

        <label for="login">Identifiant</label>
        <input id="login" name="login" type="text" required>

        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required>

        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" required>
        <small style="font-size:.8rem;color:#666;">
          Min. 6 caractères, avec au moins 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.
        </small>

        <!-- Widget reCAPTCHA -->
        <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>"></div>

        <button class="btn btn-primary" type="submit">Créer mon compte</button>
        <a class="btn btn-ghost" href="connexion.php">⬅ Déjà un compte ? Connexion</a>
      </form>
    </article>
  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
