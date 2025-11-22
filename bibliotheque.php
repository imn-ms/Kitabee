<?php
session_start();
require __DIR__ . '/private/config.php'; 
$pageTitle = "Ma bibliothèque - Kitabee";

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];

include 'include/header.inc.php';

// récupérer les livres de l'utilisateur
$stmt = $pdo->prepare("
    SELECT google_book_id, title, authors, thumbnail
    FROM user_library
    WHERE user_id = :uid
    ORDER BY added_at DESC
");
$stmt->execute([':uid' => $userId]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <?php foreach ($books as $book): ?>
          <?php
            $googleId = $book['google_book_id'];
            $title    = $book['title'] ?: 'Titre inconnu';
            $thumb    = $book['thumbnail'] ?: "https://via.placeholder.com/128x180?text=Pas+d'image";
          ?>
          <a
            href="detail.php?id=<?= htmlspecialchars($googleId, ENT_QUOTES, 'UTF-8') ?>"
            style="width:130px;text-align:center;text-decoration:none;color:inherit;"
          >
            <img
              src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
              alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
              style="width:128px;display:block;margin:0 auto 10px;"
            >
            <div style="font-size:.9rem;">
              <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include 'include/footer.inc.php'; ?>
