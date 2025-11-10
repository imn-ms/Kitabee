<?php
/**
 * contact.php – Page Contact / À propos
 */
header('Content-Type: text/html; charset=UTF-8');
session_start();

$pageTitle = "Contact – Kitabee";
include 'include/header.inc.php';

$success = null;
$error   = null;

/**
 * Génère un captcha "texte" (pas un calcul).
 */
function generateCaptcha(): void {
    // quelques mots liés au projet
    $words = ['kitabee', 'lecture', 'livre', 'biblio', 'auteur', 'roman', 'club'];
    $selectedWord = $words[array_rand($words)];

    // 3 types de captcha
    $types = ['copy', 'letter', 'lower'];
    $type  = $types[array_rand($types)];

    switch ($type) {
        case 'copy':
            $question = "Recopie exactement ce mot : « $selectedWord »";
            $answer   = $selectedWord; // on vérifiera en exact
            $mode     = 'exact';
            break;

        case 'letter':
            // on demande une position entre 1 et la longueur du mot
            $len = mb_strlen($selectedWord, 'UTF-8');
            $pos = random_int(1, $len); // position humaine
            $question = "Donne la {$pos}ᵉ lettre du mot « $selectedWord »";
            // extraire la lettre
            $answer = mb_substr($selectedWord, $pos - 1, 1, 'UTF-8');
            $mode   = 'lower';
            break;

        case 'lower':
        default:
            $question = "Écris ce mot en minuscules : « " . strtoupper($selectedWord) . " »";
            $answer   = $selectedWord; // on attend en minuscules
            $mode     = 'lower';
            break;
    }

    $_SESSION['captcha_question'] = $question;
    $_SESSION['captcha_answer']   = $answer;
    $_SESSION['captcha_mode']     = $mode;
}

// Générer le captcha si on arrive sur la page
if (!isset($_SESSION['captcha_question'])) {
    generateCaptcha();
}

// ==== Traitement du formulaire ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $sujet   = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    if ($nom === '' || $email === '' || $message === '' || $captcha === '') {
        $error = "Merci de remplir tous les champs obligatoires (*), y compris le captcha.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail saisie n'est pas valide.";
    } else {
        // Vérif captcha
        $expected = $_SESSION['captcha_answer'] ?? '';
        $mode     = $_SESSION['captcha_mode'] ?? 'lower';

        $isValidCaptcha = false;
        if ($mode === 'exact') {
            // on compare tel quel
            $isValidCaptcha = ($captcha === $expected);
        } else {
            // on compare en minuscules
            $isValidCaptcha = (mb_strtolower($captcha, 'UTF-8') === mb_strtolower($expected, 'UTF-8'));
        }

        if (!$isValidCaptcha) {
            $error = "Le captcha est incorrect. Réessayez.";
            generateCaptcha();
        } else {
            // tout est ok, on envoie le mail
            $to      = "kitabee@alwaysdata.net";
            $subject = $sujet !== '' ? $sujet : "Nouveau message depuis le formulaire Kitabee";

            $body  = "Message envoyé depuis le formulaire de contact Kitabee :\n\n";
            $body .= "Nom : $nom\n";
            $body .= "E-mail : $email\n";
            $body .= "Sujet : $sujet\n\n";
            $body .= "Message :\n$message\n";

            $headers  = "From: $nom <$email>\r\n";
            $headers .= "Reply-To: $email\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            if (mail($to, $subject, $body, $headers)) {
                $success = "Merci ! Votre message a bien été envoyé.";
                // régénérer un captcha pour la prochaine fois
                generateCaptcha();
                // vider les champs
                $nom = $email = $sujet = $message = '';
            } else {
                $error = "Une erreur est survenue lors de l’envoi du message.";
                generateCaptcha();
            }
        }
    }
}
?>

<section class="contact-wrapper">
  <div class="contact-grid">

    <!-- Colonne gauche -->
    <div class="contact-card">
      <h1 class="section-title">Notre histoire</h1>
      <p>
        Nous sommes deux étudiantes en 3<sup>e</sup> année de licence informatique :
        <strong>Imane Moussaoui</strong> et <strong>Odessa Triollet-Pereira</strong>.
      </p>
      <p>
      Dans le cadre de notre Licence, nous avons eu le choix entre plusieurs UE mineures, et avons choisi de suivre une UE de développement web avancé, car c’est un sujet qui nous intéresse pleinement.
Pour approfondir nos connaissances dans ce domaine, notre encadrant <strong>Marc Lemaire</strong> nous a proposé de sélectionner un thème de notre choix et d’en proposer une solution web.
Toutes deux passionnées par la lecture, et dans l’optique de retenir un thème avec peu de concurrence, nous avons jeté notre dévolu sur les livres.
Nous avions à cœur de proposer un outil utile et ergonomique, pouvant plaire à tous les lecteurs.
      <p>
        C’est ainsi qu’est né <strong>Kitabee</strong> : un projet qui nous ressemble.
      </p>

      <div class="contact-divider"></div>

      <h2 class="contact-title">Nous contacter</h2>
      <p class="contact-subtext">Écrivez-nous via ce formulaire, nous recevons le message sur l’adresse du projet.</p>

      <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="contact.php" class="contact-form" novalidate>
        <div>
          <label for="nom">Nom / Prénom *</label>
          <input
            type="text"
            id="nom"
            name="nom"
            required
            value="<?= htmlspecialchars($nom ?? ($_POST['nom'] ?? '')) ?>">
        </div>

        <div>
          <label for="email">E-mail *</label>
          <input
            type="email"
            id="email"
            name="email"
            required
            value="<?= htmlspecialchars($email ?? ($_POST['email'] ?? '')) ?>">
        </div>

        <div>
          <label for="sujet">Sujet</label>
          <input
            type="text"
            id="sujet"
            name="sujet"
            value="<?= htmlspecialchars($sujet ?? ($_POST['sujet'] ?? '')) ?>">
        </div>

        <div>
          <label for="message">Message *</label>
          <textarea
            id="message"
            name="message"
            rows="6"
            required><?= htmlspecialchars($message ?? ($_POST['message'] ?? '')) ?></textarea>
        </div>

        <!-- Captcha texte -->
        <div>
          <label for="captcha">Captcha *</label>
          <p style="margin:4px 0 8px; color:#666; font-size:.9rem;">
            <?= htmlspecialchars($_SESSION['captcha_question'] ?? '') ?>
          </p>
          <input
            type="text"
            id="captcha"
            name="captcha"
            required
            placeholder="Votre réponse">
        </div>

        <div>
          <button type="submit" class="contact-btn">Envoyer le message</button>
        </div>
      </form>
    </div>

    <!-- Colonne droite -->
    <aside class="contact-aside">
      <h2>Informations</h2>

      <div class="contact-info-block">
        <div class="contact-info-title">Étudiantes</div>
        <p>
          Imane Moussaoui<br>
          Odessa Triollet-Pereira
        </p>
      </div>

      <div class="contact-info-block">
        <div class="contact-info-title">Projet</div>
        <p>Kitabee – outil pour lecteurs</p>
      </div>

      <div class="contact-info-block">
        <div class="contact-info-title">Adresse de contact</div>
        <p>kitabee@alwaysdata.net</p>
      </div>

      <div class="contact-info-block">
        <div class="contact-info-title">Université</div>
        <p>Université de CYU - Site de Saint-Martin</p>
      </div>

      <small>Ce formulaire est réservé aux retours sur le projet Kitabee.</small>
    </aside>

  </div>
</section>

<?php include 'include/footer.inc.php'; ?>
