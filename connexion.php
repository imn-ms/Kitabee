<?php
/**
 * connexion.php — Page publique : formulaire de connexion (BD)
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

$pageTitle = "Connexion – Kitabee";
$error = null;
$success = null;

// Message si redirection après réinitialisation de mot de passe
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = "Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login           = trim($_POST['login'] ?? '');
    $password        = $_POST['password'] ?? '';
    $target          = $_POST['redirect'] ?? 'profil_user.php';
    $captchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if ($login === '' || $password === '') {
        $error = "Veuillez renseigner votre identifiant et votre mot de passe.";
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
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params)
            ]
        ];

        $context = stream_context_create($options);
        $result  = @file_get_contents($verifyUrl, false, $context);
        $data    = json_decode($result, true);

        if (empty($data['success'])) {
            $error = "CAPTCHA invalide, merci de réessayer.";
        } else {

            // récupérer l'utilisateur en BD
            $stmt = $pdo->prepare('
                SELECT id, login, password, is_active, avatar, avatar_type
                FROM users
                WHERE login = :login
                LIMIT 1
            ');
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                // ✅ vérifier l'activation
                if ((int)$user['is_active'] !== 1) {
                    $error = "Votre compte n’est pas encore activé. Vérifiez vos emails.";
                } else {
                    // connexion OK
                    session_regenerate_id(true);

                    $_SESSION['user']  = (int)$user['id'];
                    $_SESSION['login'] = $user['login'];

                    // 🔑 IMPORTANT : indiquer au header si un avatar existe (BLOB en BD)
                    $_SESSION['avatar_has'] = !empty($user['avatar']);

                    // (optionnel, plus utilisé par le header)
                    // $_SESSION['avatar'] = $user['avatar'];

                    header('Location: ' . $target);
                    exit;
                }

            } else {
                $error = "Identifiants invalides.";
            }
        }
    }
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
