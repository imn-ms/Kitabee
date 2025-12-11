<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

$pageTitle = "Actualité littéraire – Kitabee";

require __DIR__ . '/secret/config.php';
include 'include/header.inc.php';

// ====== Appel de l'API The Guardian (section Books) ======
$apiKey = $GUARDIAN_API_KEY;
$url = "https://content.guardianapis.com/books?api-key={$apiKey}&page-size=10&order-by=newest&show-fields=trailText,thumbnail";

// On récupère les données
$response = @file_get_contents($url);
$data = $response ? json_decode($response, true) : null;
$articles = $data['response']['results'] ?? [];
?>

<section class="section">
  <div class="container">
    <h1>Actualité littéraire</h1>
    <p>Les dernières nouvelles du monde du livre (source : <a href="https://www.theguardian.com/books" target="_blank">The Guardian</a>).</p>

    <?php if (empty($articles)): ?>
      <p>Aucune actualité disponible pour le moment.</p>
    <?php else: ?>
      <div class="news-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-top:30px;">
        <?php foreach ($articles as $article): 
          $title = $article['webTitle'] ?? 'Sans titre';
          $url   = $article['webUrl'] ?? '#';
          $trail = $article['fields']['trailText'] ?? '';
          $thumb = $article['fields']['thumbnail'] ?? 'https://via.placeholder.com/300x200?text=Aucune+image';
          $date  = isset($article['webPublicationDate']) ? date('d/m/Y', strtotime($article['webPublicationDate'])) : '';
        ?>
          <article class="news-card" style="background:#fff; border-radius:12px; box-shadow:0 3px 8px rgba(0,0,0,0.1); overflow:hidden; transition:transform .2s;">
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="text-decoration:none; color:inherit;">
              <img src="<?= htmlspecialchars($thumb) ?>" alt="Image de l'article" style="width:100%; height:180px; object-fit:cover;">
              <div style="padding:15px;">
                <h2 style="font-size:1.1rem; margin:0 0 10px;"><?= htmlspecialchars($title) ?></h2>
                <p style="font-size:0.9rem; color:#555;"><?= $trail ?></p>
                <p style="font-size:0.8rem; color:#888;">Publié le <?= $date ?></p>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'include/footer.inc.php'; ?>
