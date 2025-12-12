<?php
/**
 * contact.php – Page Contact / À propos (avec Google reCAPTCHA v2)
 *
 * Cette page affiche :
 * - Une présentation du projet Kitabee
 * - Un formulaire de contact protégé par reCAPTCHA v2
 * - Une carte Leaflet (OpenStreetMap)
 * 
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

require __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

$pageTitle = "Contact – Kitabee";
include 'include/header.inc.php';

$ctx = kb_handle_contact_form($_POST, $_SERVER);

$success = $ctx['success'];
$error   = $ctx['error'];
$nom     = $ctx['nom'];
$email   = $ctx['email'];
$sujet   = $ctx['sujet'];
$message = $ctx['message'];
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

    // Coordonnées 
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

    // Géolocalisation de l'utilisateur 
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
