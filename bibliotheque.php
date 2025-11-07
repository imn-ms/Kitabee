<?php
session_start();
require __DIR__ . '/private/config.php'; // pour $pdo + clé API
$pageTitle = "Ma bibliothèque - Kitabee";

// ⚠️ ton login met l'id dans $_SESSION['user'], donc on teste ça
if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = $_SESSION['user'];

include 'include/header.inc.php';

// récupérer les livres de l'utilisateur
$stmt = $pdo->prepare("SELECT google_book_id FROM user_library WHERE user_id = :uid ORDER BY added_at DESC");
$stmt->execute([':uid' => $userId]);
$books = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<section class="section">
  <div class="container">
    <h1>Ma bibliothèque</h1>
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

    <?php if (empty($books)): ?>
      <p>Tu n’as encore rien ajouté.</p>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:20px;">
        <?php foreach ($books as $googleId): ?>
          <?php
            $url = "https://www.googleapis.com/books/v1/volumes/{$googleId}?key={$GOOGLE_API_KEY}";
            $response = @file_get_contents($url);
            $data = json_decode($response, true);
            $vinfo = $data['volumeInfo'] ?? [];
            $title = $vinfo['title'] ?? 'Titre inconnu';
            $thumb = $vinfo['imageLinks']['thumbnail'] ?? 'https://via.placeholder.com/128x180?text=Pas+d\'image';
          ?>
          <a href="detail.php?id=<?= htmlspecialchars($googleId) ?>" style="width:130px;text-align:center;text-decoration:none;color:inherit;">
            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>" style="width:128px;display:block;margin:0 auto 10px;">
            <div style="font-size:.9rem;"><?= htmlspecialchars($title) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include 'include/footer.inc.php'; ?>

