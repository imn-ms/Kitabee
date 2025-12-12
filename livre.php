<?php
/**
 * livre.php – liste de livres
 */
header('Content-Type: text/html; charset=UTF-8');

$visited = isset($_COOKIE['visited']);
if (!$visited) {
  setcookie('visited', '1', 0, '/');
}

$pageTitle = "Résultats de recherche - Kitabee";
include 'include/header.inc.php';
include __DIR__ . '/secret/config.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!$query) {
  echo "<p>Aucune recherche effectuée.</p>";
  exit;
}

$queryEncoded = urlencode($query);
$url = "https://www.googleapis.com/books/v1/volumes?q={$queryEncoded}&maxResults=12&key={$GOOGLE_API_KEY}";
$response = file_get_contents($url);
$data = json_decode($response, true);
?>

<div class="page-livre">
      <!-- ===== Barre de recherche avec autocomplétion ===== -->
      <div class="section">
      <div class="container">
        <div class="search-wrapper">
          <form id="searchForm" action="livre.php" method="GET" class="search-bar" aria-label="Recherche de livre">
            <input 
              id="q" 
              name="q" 
              type="text" 
              placeholder="Rechercher un livre, un auteur…" 
              autocomplete="off" 
              required
            >
            <button type="submit" class="btn btn-primary">🔍</button>
          </form>
          <ul id="suggestions" class="suggestions"></ul>
        </div>
      </div>
    </div>
  <h1>Résultats pour « <?= htmlspecialchars($query) ?> »</h1>

  <?php if (!empty($data['items'])): ?>
    <div class="book-list">
      <?php foreach ($data['items'] as $book): 
        $info = $book['volumeInfo'] ?? [];
        $id = $book['id'] ?? '';
        $title = $info['title'] ?? 'Titre inconnu';
        $authors = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
        $thumbnail = $info['imageLinks']['thumbnail'] ?? 'https://via.placeholder.com/128x192?text=Pas+d\'image';
      ?>
        <div class="book-card">
          <img src="<?= htmlspecialchars($thumbnail) ?>" alt="Couverture du livre">
          <div class="book-info">
            <h2><?= htmlspecialchars($title) ?></h2>
            <p><strong>Auteur(s) :</strong> <?= htmlspecialchars($authors) ?></p>
            <a href="detail.php?id=<?= urlencode($id) ?>" class="btn-view">Voir plus</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>Aucun livre trouvé pour « <?= htmlspecialchars($query) ?> ».</p>
  <?php endif; ?>
</div>
<?php include("include/footer.inc.php"); ?>