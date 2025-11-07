<?php
/**
 * cookie.php

 */

header('Content-Type: text/html; charset=UTF-8');
$visited = isset($_COOKIE['visited']);
$consent = $_COOKIE['cookie_consent'] ?? null;

$pageTitle = "Gestion des cookies – Kitabee";
include 'include/header.inc.php';
?>

<section class="section" aria-labelledby="cookies-title">
  <div class="container">
    <h1 id="cookies-title" class="section-title">Gestion des cookies</h1>

    <div class="card">
      <p>
        <strong>Cookie de session "visited" présent :</strong>
        <?= $visited ? 'Oui' : 'Non' ?><br>
        <em>(Ce cookie disparaît automatiquement quand vous fermez complètement le navigateur)</em>
      </p>

      <?php if ($consent === 'accepted'): ?>
        <hr>
        <h2>Cookies enregistrés</h2>
        <ul>
          <li><strong>Style :</strong> <?= htmlspecialchars($_COOKIE['style'] ?? 'non défini', ENT_QUOTES, 'UTF-8') ?></li>
          <li><strong>Dernière visite :</strong> <?= htmlspecialchars($_COOKIE['last_visit'] ?? 'non enregistrée', ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
      <?php else: ?>
        <hr>
        <p><em>Aucun cookie persistant affiché car vous n’avez pas accepté les cookies non indispensables.</em></p>
      <?php endif; ?>

      <div style="margin-top:12px;">
        <a href="reset.php" class="btn btn-primary">Réinitialiser manuellement</a>
        <a href="index.php" class="btn btn-ghost">⬅ Retour à l'accueil</a>
      </div>
    </div>

    <div aria-hidden="true" style="height: 200px"></div>
  </div>
</section>

<!-- ====== Date & Heure dynamiques ====== -->
<section class="section" aria-labelledby="clock-title">
  <div class="container" style="text-align:center; margin:40px 0;">
    <h2 id="clock-title">Date & Heure locales</h2>
    <p id="clock" style="font-size:1.3rem; font-weight:bold; color:#333;"></p>
  </div>
</section>

<script>
  function updateClock() {
    const now = new Date();
    const formatted = now.toLocaleString("fr-FR", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit"
    });
    document.getElementById("clock").textContent = formatted;
  }
  setInterval(updateClock, 1000);
  updateClock();
</script>

<?php
include 'include/footer.inc.php';
?>
