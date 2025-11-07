<?php
header('Content-Type: text/html; charset=UTF-8');
$pageTitle = "Détail du livre - Kitabee";
include 'include/header.inc.php';
include __DIR__ . '/private/config.php';

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$id) {
  echo "<p>Aucun livre sélectionné.</p>";
  exit;
}

$url = "https://www.googleapis.com/books/v1/volumes/{$id}?key={$GOOGLE_API_KEY}";
$response = file_get_contents($url);
$book = json_decode($response, true);

if (empty($book['volumeInfo'])) {
  echo "<p>Livre introuvable.</p>";
  exit;
}

$info = $book['volumeInfo'];
$title = $info['title'] ?? 'Titre inconnu';
$authors = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
$description = $info['description'] ?? 'Pas de description disponible.';
$thumbnail = $info['imageLinks']['thumbnail'] ?? 'https://via.placeholder.com/200x300?text=Pas+d\'image';
$publisher = $info['publisher'] ?? 'Éditeur inconnu';
$publishedDate = $info['publishedDate'] ?? 'Date inconnue';
$pageCount = $info['pageCount'] ?? 'Non précisé';
$categories = isset($info['categories']) ? implode(', ', $info['categories']) : 'Non classé';
?>

<div class="book-detail">
  <img src="<?= htmlspecialchars($thumbnail) ?>" alt="Couverture du livre">
  <div class="book-info">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><strong>Auteur(s) :</strong> <?= htmlspecialchars($authors) ?></p>
    <p><strong>Éditeur :</strong> <?= htmlspecialchars($publisher) ?></p>
    <p><strong>Publié le :</strong> <?= htmlspecialchars($publishedDate) ?></p>
    <p><strong>Pages :</strong> <?= htmlspecialchars($pageCount) ?></p>
    <p><strong>Catégories :</strong> <?= htmlspecialchars($categories) ?></p>
      <?= nl2br(htmlspecialchars($description)) ?>

    <div class="livre-actions" style="margin-top:30px;">
      <?php if ($loggedUserId): ?>
        <!-- Si connecté -->
        <form action="add_to_library.php" method="post" style="display:inline-block;">
          <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
          <button type="submit" class="btn-favoris">📚 Ajouter à ma bibliothèque</button>
        </form>

        <form action="add_to_wishlist.php" method="post" style="display:inline-block; margin-left:10px;">
          <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
          <button type="submit" class="btn-lire">💖 Ajouter à ma wishlist</button>
        </form>
      <?php else: ?>
        <!-- Si non connecté -->
        <a href="connexion.php" class="btn-favoris">📚 Ajouter à ma bibliothèque</a>
        <a href="connexion.php" class="btn-lire">💖 Ajouter à ma wishlist</a>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php include("include/footer.inc.php"); ?>