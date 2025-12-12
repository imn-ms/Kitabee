<?php
/**
 * construction.php
 *
 * Page générique "En construction" pour Kitabee.
 *
 * Cette page permet d'afficher un message standard pour les sections
 * non encore disponibles du site.
 *
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/include/functions.inc.php';

// Préparation du contexte
$ctx = kb_prepare_construction_page($_GET);

$page      = $ctx['page'];
$title     = $ctx['title'];
$pageTitle = $ctx['pageTitle'];

include 'include/header.inc.php';
?>

<section class="section" aria-labelledby="wip-title">
  <div class="container">
    <h1 id="wip-title" class="section-title">
      <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <div class="card" style="text-align:center; padding:40px;">
      <h2>Cette section est en cours de construction</h2>

      <p>
        Nous travaillons activement sur
        <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong>
        pour vous offrir une meilleure expérience.
      </p>

      <p>Revenez bientôt pour découvrir les nouveautés !</p>

      <div style="margin-top:20px;">
        <a href="index.php" class="btn btn-primary">⬅ Retour à l'accueil</a>
      </div>
    </div>

    <div aria-hidden="true" style="height:200px"></div>
  </div>
</section>

<?php include 'include/footer.inc.php'; ?>
