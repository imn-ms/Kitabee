<?php
/**
 * reset.php – Réinitialisation manuelle de tous les cookies non indispensables
 */
header('Content-Type: text/html; charset=UTF-8');

// Liste des cookies à supprimer (ajoute ici si tu en crées d'autres)
$toDelete = [
  'visited',
  'style',
  'last_visit',
  'cookie_consent',
  'user_ip'
];

// Supprime chaque cookie
foreach ($toDelete as $name) {
  // Version moderne
  setcookie($name, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => false,   // passe à true si HTTPS
    'httponly' => false,
    'samesite' => 'Lax',
  ]);
  // Version legacy
  setcookie($name, '', time() - 3600, '/');
}

$pageTitle = "Réinitialisation Kitabee";
include 'include/header.inc.php';
?>

<main class="container section">

  <h1 class="section-title">Réinitialisation effectuée</h1>

  <div class="card">
    <p>Tous les cookies <strong>non indispensables</strong> ont été supprimés.</p>
    <p>
      Lors de votre prochaine visite, l’accueil se chargera sans cookies mémorisés
      (style, dernière visite, consentement…).  
      Le bandeau cookies réapparaîtra si vous n’avez pas encore accepté.
    </p>

    <div style="margin-top:20px;">
      <a href="index.php" class="btn btn-primary">⬅ Retour à l'accueil</a>
    </div>
  </div>
  <div aria-hidden="true" style="height: 200px"></div>
</main>

<!-- ====== Date & Heure dynamiques ====== -->
<section class="section">
  <div class="container" style="text-align:center; margin:40px 0;">
    <h2>Date & Heure locales</h2>
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

<?php include 'include/footer.inc.php'; ?>
