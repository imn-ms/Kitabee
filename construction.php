<?php
/**
 * construction.php
 *
 * Page générique "En construction" pour BookChooser.
 * On peut l'appeler avec ?page=features ou ?page=catalogue
 */

header('Content-Type: text/html; charset=UTF-8');

$page = $_GET['page'] ?? 'page';
switch ($page) {
    case 'actualites':
        $title = "Actualités";
        break;
    default:
        $title = ucfirst($page);
}

$pageTitle = "$title – En construction – Kitabee";

include 'include/header.inc.php';
?>

<section class="section" aria-labelledby="wip-title">
  <div class="container">
    <h1 id="wip-title" class="section-title"><?= htmlspecialchars($title) ?></h1>

    <div class="card" style="text-align:center; padding:40px;">
      <h2>Cette section est en cours de construction</h2>
      <p>Nous travaillons activement sur <strong><?= htmlspecialchars($title) ?></strong> pour vous offrir une meilleure expérience.</p>
      <p>Revenez bientôt pour découvrir les nouveautés !</p>

      <div style="margin-top:20px;">
        <a href="index.php" class="btn btn-primary">⬅ Retour à l'accueil</a>
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
