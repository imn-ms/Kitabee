<?php
header('Content-Type: text/html; charset=UTF-8');
$pageTitle = "Détail du livre - Kitabee";

include 'include/header.inc.php';           // doit définir $loggedUserId via la session
include __DIR__ . '/private/config.php';    // doit définir $pdo et $GOOGLE_API_KEY

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$id) {
    echo "<p>Aucun livre sélectionné.</p>";
    exit;
}

// --- Récupération du livre via Google Books ---
$url = "https://www.googleapis.com/books/v1/volumes/{$id}?key={$GOOGLE_API_KEY}";
$response = @file_get_contents($url);
$book = $response ? json_decode($response, true) : null;

if (empty($book['volumeInfo'])) {
    echo "<p>Livre introuvable.</p>";
    exit;
}

$info = $book['volumeInfo'];
$title        = $info['title'] ?? 'Titre inconnu';
$authors      = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
$description  = $info['description'] ?? 'Pas de description disponible.';
$thumbnail    = $info['imageLinks']['thumbnail'] ?? "https://via.placeholder.com/200x300?text=Pas+d'image";
$publisher    = $info['publisher'] ?? 'Éditeur inconnu';
$publishedDate= $info['publishedDate'] ?? 'Date inconnue';
$pageCount    = $info['pageCount'] ?? 'Non précisé';
$categories   = isset($info['categories']) ? implode(', ', $info['categories']) : 'Non classé';

// --- Déterminer si le livre est dans la wishlist / bibliothèque ---
$isInWishlist = false;
$isInLibrary  = false;

if (!empty($loggedUserId)) {
    // Wishlist
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_wishlist
        WHERE user_id = :uid AND google_book_id = :bid
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => (int)$loggedUserId,
        ':bid' => $id
    ]);
    $isInWishlist = (bool)$stmt->fetchColumn();

    // Bibliothèque
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_library
        WHERE user_id = :uid AND google_book_id = :bid
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => (int)$loggedUserId,
        ':bid' => $id
    ]);
    $isInLibrary = (bool)$stmt->fetchColumn();
}
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
    <?= nl2br(htmlspecialchars(strip_tags($description))) ?>

    <div class="livre-actions" style="margin-top:30px;">

      <?php if ($loggedUserId): ?>
        <?php if (!$isInWishlist && !$isInLibrary): ?>
          <!-- Cas 1 : pas du tout présent -->
          <form action="add_to_library.php" method="post" style="display:inline-block;">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
            <button type="submit" class="btn-favoris">📚 Je l’ai déjà lu (ajouter à ma bibliothèque)</button>
          </form>

          <form action="add_to_wishlist.php" method="post" style="display:inline-block; margin-left:10px;">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
            <button type="submit" class="btn-lire">💖 Ajouter à ma wishlist</button>
          </form>

        <?php elseif ($isInWishlist && !$isInLibrary): ?>
          <!-- Cas 2 : dans la wishlist uniquement -->
          <p>💖 Ce livre est dans ta wishlist.</p>

          <form action="remove_from_wishlist.php" method="post" style="display:inline-block;">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
            <button type="submit" class="btn-lire">❌ Retirer de ma wishlist</button>
          </form>

          <form action="mark_as_read.php" method="post" style="display:inline-block; margin-left:10px;">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
            <button type="submit" class="btn-favoris">📚 Je l’ai lu (ajouter à ma bibliothèque)</button>
          </form>

        <?php elseif ($isInLibrary): ?>
          <!-- Cas 3 : dans la bibliothèque -->
          <p>📚 Ce livre est dans ta bibliothèque (déjà lu).</p>

          <!-- Optionnel : possibilité de retirer de la bibliothèque -->
          <form action="remove_from_library.php" method="post" style="display:inline-block;">
            <input type="hidden" name="book_id" value="<?= htmlspecialchars($id) ?>">
            <button type="submit" class="btn-favoris">❌ Retirer de ma bibliothèque</button>
          </form>

        <?php endif; ?>
      <?php else: ?>
        <!-- Si non connecté -->
        <a href="connexion.php" class="btn-favoris">📚 Ajouter à ma bibliothèque</a>
        <a href="connexion.php" class="btn-lire">💖 Ajouter à ma wishlist</a>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include("include/footer.inc.php"); ?>
