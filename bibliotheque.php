<?php
session_start();
require __DIR__ . '/secret/config.php'; 
$pageTitle = "Ma bibliothèque - Kitabee";

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];

include 'include/header.inc.php';

// ======= Récupérer les livres de la bibliothèque (lus) =======
$stmt = $pdo->prepare("
    SELECT google_book_id, title, authors, thumbnail
    FROM user_library
    WHERE user_id = :uid
    ORDER BY added_at DESC
");
$stmt->execute([':uid' => $userId]);
$libraryBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ======= Récupérer les livres de la wishlist (à lire) =======
$stmt = $pdo->prepare("
    SELECT google_book_id, title, authors, thumbnail
    FROM user_wishlist
    WHERE user_id = :uid
    ORDER BY added_at DESC
");
$stmt->execute([':uid' => $userId]);
$wishlistBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- ========== SECTION 1 : LIVRES LUS (BIBLIOTHÈQUE) ========== -->
    <h2 style="margin-top:30px;">Mes livres lus</h2>

    <?php if (empty($libraryBooks)): ?>
      <p>Tu n’as encore rien ajouté à ta bibliothèque.</p>
      <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:20px;">
        <?php foreach ($libraryBooks as $book): ?>
          <?php
            $googleId = $book['google_book_id'];
            $title    = $book['title'] ?: 'Titre inconnu';
            $thumb    = $book['thumbnail'] ?: "https://via.placeholder.com/128x180?text=Pas+d'image";
          ?>
          <div style="width:130px;text-align:center;">
            <a
              href="detail.php?id=<?= htmlspecialchars($googleId, ENT_QUOTES, 'UTF-8') ?>"
              style="text-decoration:none;color:inherit;"
            >
              <img
                src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                style="width:128px;display:block;margin:0 auto 10px;"
              >
              <div style="font-size:.9rem;margin-bottom:8px;">
                <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>


    <!-- ========== SECTION 2 : WISHLIST (À LIRE) ========== -->
    <h2 style="margin-top:40px;">Ma wishlist (à lire)</h2>

    <?php if (empty($wishlistBooks)): ?>
      <p>Ta wishlist est vide pour l’instant.</p>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:20px;">
        <?php foreach ($wishlistBooks as $book): ?>
          <?php
            $googleId = $book['google_book_id'];
            $title    = $book['title'] ?: 'Titre inconnu';
            $thumb    = $book['thumbnail'] ?: "https://via.placeholder.com/128x180?text=Pas+d'image";
          ?>
          <div style="width:130px;text-align:center;">
            <a
              href="detail.php?id=<?= htmlspecialchars($googleId, ENT_QUOTES, 'UTF-8') ?>"
              style="text-decoration:none;color:inherit;"
            >
              <img
                src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                style="width:128px;display:block;margin:0 auto 10px;"
              >
              <div style="font-size:.9rem;margin-bottom:8px;">
                <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
              </div>
            </a>

            <!-- Bouton : Je l’ai lu (déplace vers bibliothèque) -->
            <form action="mark_as_read.php" method="post" style="margin-bottom:4px;">
              <input type="hidden" name="book_id" value="<?= htmlspecialchars($googleId, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn-favoris" style="font-size:.75rem;padding:4px 6px;">
                📚 Je l’ai lu
              </button>
            </form>

            <!-- Bouton : Retirer de la wishlist utile ou pas ????
            <form action="remove_from_wishlist.php" method="post">
              <input type="hidden" name="book_id" value="<?= htmlspecialchars($googleId, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn-lire" style="font-size:.75rem;padding:4px 6px;">
                ❌ Retirer
              </button>-->
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>
<?php include 'include/footer.inc.php'; ?>
