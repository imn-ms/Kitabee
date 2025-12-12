<?php
/**
 * contact.php – Page Contact / À propos (avec Google reCAPTCHA v2)
 */
header('Content-Type: text/html; charset=UTF-8');
session_start();

require __DIR__ . '/secret/config.php';

$pageTitle = "Contact – Kitabee";
include 'include/header.inc.php';

$success = null;
$error   = null;

// ==== Traitement du formulaire ====
$nom     = trim($_POST['nom'] ?? '');
$email   = trim($_POST['email'] ?? '');
$sujet   = trim($_POST['sujet'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Champs requis
    if ($nom === '' || $email === '' || $message === '') {
        $error = "Merci de remplir tous les champs obligatoires (*), y compris le reCAPTCHA.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail saisie n'est pas valide.";
    }

    // ✅ Vérification reCAPTCHA côté serveur
    if (!$error) {
        $token = $_POST['g-recaptcha-response'] ?? '';
        if ($token === '') {
            $error = "Veuillez valider le reCAPTCHA.";
        } else {
            $verifyUrl = "https://www.google.com/recaptcha/api/siteverify";
            $postData  = http_build_query([
                'secret'   => RECAPTCHA_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData,
                    'timeout' => 8,
                ]
            ];

            $context = stream_context_create($opts);
            $verify  = file_get_contents($verifyUrl, false, $context);
            $captcha = $verify ? json_decode($verify, true) : null;

            if (empty($captcha['success'])) {
                $error = "Échec reCAPTCHA. Réessayez.";
            }
        }
    }

    // ✅ Si OK, on envoie le mail
    if (!$error) {
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

        if (@mail($to, $subject, $body, $headers)) {
            $success = "Merci ! Votre message a bien été envoyé.";
            $nom = $email = $sujet = $message = '';
        } else {
            $error = "Une erreur est survenue lors de l’envoi du message.";
        }
    }
}
?>

<!-- Leaflet CSS (CDN) -->
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

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
      </p>
      <p>
        C’est ainsi qu’est né <strong>Kitabee</strong> : un projet qui nous ressemble.
      </p>

      <div class="contact-divider"></div>

      <h2 class="contact-title">Nous contacter</h2>
      <p class="contact-subtext">Écrivez-nous via ce formulaire, nous recevons le message sur l’adresse du projet.</p>

      <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="contact.php" class="contact-form" novalidate>
        <div>
          <label for="nom">Nom / Prénom *</label>
          <input
            type="text"
            id="nom"
            name="nom"
            required
            value="<?= htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div>
          <label for="email">E-mail *</label>
          <input
            type="email"
            id="email"
            name="email"
            required
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div>
          <label for="sujet">Sujet</label>
          <input
            type="text"
            id="sujet"
            name="sujet"
            value="<?= htmlspecialchars($sujet, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div>
          <label for="message">Message *</label>
          <textarea
            id="message"
            name="message"
            rows="6"
            required><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- ✅ reCAPTCHA -->
        <div style="margin-top:6px;">
          <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>
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

    <!-- Bloc carte -->
    <div class="contact-map-block" style="grid-column: 1 / -1; margin-top: 2rem;">
      <h2>Nous situer</h2>
      <p>Retrouvez-nous facilement grâce à la carte ci-dessous :</p>

      <div id="map" style="height: 400px; width: 100%; border-radius: 10px;"></div>
    </div>

  </div>
</section>

<!-- Leaflet JS (CDN) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// On attend que le DOM soit prêt
document.addEventListener('DOMContentLoaded', () => {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // Coordonnées (exemple CY Cergy)
    const latitude = 49.043;
    const longitude = 2.0845;

    // Création de la carte
    const map = L.map(mapElement).setView([latitude, longitude], 13);

    // Chargement tiles OSM
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    // Ajout d'un marqueur
    L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup('<strong>Kitabee – CYU</strong><br/>Nous sommes ici.')
        .openPopup();

    // Barre d’échelle
    L.control.scale({metric: true, imperial: false}).addTo(map);

    // Géolocalisation de l'utilisateur (optionnelle)
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(pos => {
            const userLat = pos.coords.latitude;
            const userLon = pos.coords.longitude;

            L.marker([userLat, userLon])
              .addTo(map)
              .bindPopup("Votre position")
              .openPopup();
        }, err => {
            console.log("Erreur géolocalisation :", err);
        });
    }
});
</script>

<?php include 'include/footer.inc.php'; ?>
