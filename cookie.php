<?php
/**
 * cookie.php – Gestion des cookies
 *
 * Cette page informe l'utilisateur sur :
 * - la présence du cookie de session ("visited"),
 * - le consentement aux cookies non essentiels,
 * - les cookies persistants utilisés par Kitabee (style, dernière visite).
 *
 * Auteur : MOUSSAUI Imane
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/include/functions.inc.php';

$cookieCtx = kb_get_cookie_status();

$visited = $cookieCtx['visited'];
$consent = $cookieCtx['consent'];
$style   = $cookieCtx['style'];
$lastVisit = $cookieCtx['last_visit'];

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
          <li>
            <strong>Style :</strong>
            <?= htmlspecialchars($style ?? 'non défini', ENT_QUOTES, 'UTF-8') ?>
          </li>
          <li>
            <strong>Dernière visite :</strong>
            <?= htmlspecialchars($lastVisit ?? 'non enregistrée', ENT_QUOTES, 'UTF-8') ?>
          </li>
        </ul>
      <?php else: ?>
        <hr>
        <p>
          <em>
            Aucun cookie persistant affiché car vous n’avez pas accepté
            les cookies non indispensables.
          </em>
        </p>
      <?php endif; ?>

      <div style="margin-top:12px;">
        <a href="reset.php" class="btn btn-primary">Réinitialiser manuellement</a>
        <a href="index.php" class="btn btn-ghost">⬅ Retour à l'accueil</a>
      </div>
    </div>

    <div aria-hidden="true" style="height: 200px"></div>
  </div>
</section>


<?php include 'include/footer.inc.php'; ?>
