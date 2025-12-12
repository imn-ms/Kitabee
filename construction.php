<?php
/**
 * construction.php
 *
 * Page générique "En construction" pour Kitabee.
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
<?php
include 'include/footer.inc.php';
?>
