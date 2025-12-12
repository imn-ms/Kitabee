<?php
/**
 * actualites.php
 *
 * Page d’actualités littéraires du site Kitabee.
 *
 * Cette page permet d’afficher les dernières actualités liées au monde du livre
 * en s’appuyant sur une API externe : **The Guardian Content API**.
 *
 * Fonctionnalités principales :
 * - appel à l’API The Guardian (section "Books"),
 * - récupération des articles les plus récents,
 * - affichage du titre, d’un résumé, d’une image et de la date de publication,
 * - lien direct vers l’article original sur le site du Guardian.
 *
 * Les données affichées sont dynamiques et dépendent de la disponibilité
 * de l’API externe au moment du chargement de la page.
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

/** @var string Titre de la page */
$pageTitle = "Actualité littéraire – Kitabee";

require __DIR__ . '/secret/config.php';
include 'include/header.inc.php';

/* =========================================================
   APPEL DE L’API THE GUARDIAN – SECTION BOOKS
   ========================================================= */

/**
 * Clé API The Guardian (définie dans config.php).
 *
 * @var string
 */
$apiKey = $GUARDIAN_API_KEY;

/**
 * URL de l’API The Guardian :
 * - section : books
 * - tri : articles les plus récents
 * - nombre d’articles : 10
 * - champs supplémentaires : résumé  et image 
 *
 * @var string
 */
$url = "https://content.guardianapis.com/books?api-key={$apiKey}&page-size=10&order-by=newest&show-fields=trailText,thumbnail";

/**
 * Récupération des données depuis l’API.
 * L’opérateur @ permet d’éviter l’affichage d’erreurs PHP
 * en cas d’indisponibilité de l’API.
 *
 * @var array|null
 */
$response = @file_get_contents($url);
$data = $response ? json_decode($response, true) : null;

/**
 * Liste des articles retournés par l’API.
 *
 * @var array
 */
$articles = $data['response']['results'] ?? [];
?>

<section class="section">
  <div class="container">
    <h1>Actualité littéraire</h1>
    <p>
      Les dernières nouvelles du monde du livre
      (source :
      <a href="https://www.theguardian.com/books" target="_blank">
        The Guardian
      </a>).
    </p>

    <?php if (empty($articles)): ?>
      <!-- Cas où aucune actualité n’est disponible -->
      <p>Aucune actualité disponible pour le moment.</p>
    <?php else: ?>
      <!-- Grille d’affichage des articles -->
      <div class="news-grid"
           style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-top:30px;">

        <?php foreach ($articles as $article): 
          /**
           * Extraction et sécurisation des données de chaque article.
           */
          $title = $article['webTitle'] ?? 'Sans titre';
          $url   = $article['webUrl'] ?? '#';
          $trail = $article['fields']['trailText'] ?? '';
          $thumb = $article['fields']['thumbnail']
                   ?? 'https://via.placeholder.com/300x200?text=Aucune+image';
          $date  = isset($article['webPublicationDate'])
                   ? date('d/m/Y', strtotime($article['webPublicationDate']))
                   : '';
        ?>
          <article class="news-card"
                   style="border-radius:12px; box-shadow:0 3px 8px; overflow:hidden; transition:transform .2s;">
            <a href="<?= htmlspecialchars($url) ?>"
               target="_blank"
               style="text-decoration:none; color:inherit;">
              <img src="<?= htmlspecialchars($thumb) ?>"
                   alt="Image de l'article"
                   style="width:100%; height:180px; object-fit:cover;">
              <div style="padding:15px;">
                <h2 style="font-size:1.1rem; margin:0 0 10px;">
                  <?= htmlspecialchars($title) ?>
                </h2>
                <p style="font-size:0.9rem; color:#555;">
                  <?= $trail ?>
                </p>
                <p style="font-size:0.8rem; color:#888;">
                  Publié le <?= $date ?>
                </p>
              </div>
            </a>
          </article>
        <?php endforeach; ?>

      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'include/footer.inc.php'; ?>
