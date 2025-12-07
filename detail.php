<?php
header('Content-Type: text/html; charset=UTF-8');
$pageTitle = "Détail du livre - Kitabee";

include 'include/header.inc.php';           // définit $loggedUserId via la session
include __DIR__ . '/private/config.php';    // définit $pdo et $GOOGLE_API_KEY

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$id) {
    echo "<p>Aucun livre sélectionné.</p>";
    include 'include/footer.inc.php';
    exit;
}

// --- Récupération du livre via Google Books ---
$url      = "https://www.googleapis.com/books/v1/volumes/{$id}?key={$GOOGLE_API_KEY}";
$response = @file_get_contents($url);
$book     = $response ? json_decode($response, true) : null;

if (empty($book['volumeInfo'])) {
    echo "<p>Livre introuvable.</p>";
    include 'include/footer.inc.php';
    exit;
}

$info = $book['volumeInfo'];
$title         = $info['title'] ?? 'Titre inconnu';
$authors       = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
$description   = $info['description'] ?? 'Pas de description disponible.';
$thumbnail     = $info['imageLinks']['thumbnail'] ?? "https://via.placeholder.com/200x300?text=Pas+d'image";
$publisher     = $info['publisher'] ?? 'Éditeur inconnu';
$publishedDate = $info['publishedDate'] ?? 'Date inconnue';
$pageCount     = $info['pageCount'] ?? 'Non précisé';
$categories    = isset($info['categories']) ? implode(', ', $info['categories']) : 'Non classé';

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

// ===== Notes & commentaires =====
$personalRating   = null;
$personalComment  = '';
$avgRating        = null;
$avgCount         = 0;

// Traitement du POST pour note/commentaire perso
if (!empty($loggedUserId) && $isInLibrary && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $rating  = $_POST['rating'] ?? '';
    $comment = trim($_POST['private_comment'] ?? '');

    if ($rating === '') {
        $rating = null;
    } else {
        $rating = (int)$rating;
        if ($rating < 1 || $rating > 5) {
            $rating = null; // sécurité
        }
    }

    $stmt = $pdo->prepare("
        UPDATE user_library
        SET rating = :rating,
            private_comment = :comment
        WHERE user_id = :uid
          AND google_book_id = :gid
    ");
    $stmt->execute([
        ':rating'  => $rating,
        ':comment' => ($comment !== '' ? $comment : null),
        ':uid'     => (int)$loggedUserId,
        ':gid'     => $id
    ]);

    // on met à jour les variables locales pour l'affichage
    $personalRating  = $rating;
    $personalComment = $comment;
}

// Récupération des infos perso si l'utilisateur est connecté et que le livre est dans sa bibliothèque
if (!empty($loggedUserId) && $isInLibrary) {
    $stmt = $pdo->prepare("
        SELECT rating, private_comment
        FROM user_library
        WHERE user_id = :uid
          AND google_book_id = :gid
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => (int)$loggedUserId,
        ':gid' => $id
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($personalRating === null && $row['rating'] !== null) {
            $personalRating = (int)$row['rating'];
        }
        if ($personalComment === '' && $row['private_comment'] !== null) {
            $personalComment = $row['private_comment'];
        }
    }
}

// Moyenne globale des notes tous utilisateurs
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS nb, AVG(rating) AS avg_rating
    FROM user_library
    WHERE google_book_id = :gid
      AND rating IS NOT NULL
");
$stmt->execute([':gid' => $id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if ($stats && $stats['nb'] > 0) {
    $avgRating = (float)$stats['avg_rating'];
    $avgCount  = (int)$stats['nb'];
}
?>

<div class="book-detail">
  <img src="<?= htmlspecialchars($thumbnail) ?>" alt="Couverture du livre">
  <div class="book-info">
    <h1><?= htmlspecialchars($title) ?></h1>

    <?php if (!is_null($avgRating) && $avgCount > 0): ?>
      <div class="rating-avg">
        <span class="rating-stars">
          <?php
            $rounded = (int) round($avgRating);
            for ($i = 1; $i <= 5; $i++) {
                echo ($i <= $rounded) ? '★' : '☆';
            }
          ?>
        </span>
        <span class="rating-avg-text">
          <?= number_format($avgRating, 1, ',', ' ') ?>/5
        </span>
        <span class="rating-count">
          (<?= $avgCount ?> avis)
        </span>
      </div>
    <?php else: ?>
      <div class="rating-avg rating-avg-empty">
        Aucun avis pour le moment.
      </div>
    <?php endif; ?>

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

    <?php if ($loggedUserId && $isInLibrary): ?>
      <section class="personal-note">
        <h3>Ma note et mon commentaire</h3>

        <form method="post">
        <div class="rating-input">
      <span class="rating-label">Ma note :</span>
      <div class="rating-widget">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input
            type="radio"
            id="rating-<?= $i ?>"
            name="rating"
            value="<?= $i ?>"
            <?= (!is_null($personalRating) && (int)$personalRating === $i) ? 'checked' : '' ?>
          >
          <label for="rating-<?= $i ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <path pathLength="360"
                d="M12,17.27L18.18,21L16.54,13.97L22,9.24
                   L14.81,8.62L12,2L9.19,8.62L2,9.24
                   L7.45,13.97L5.82,21L12,17.27Z">
              </path>
            </svg>
          </label>
        <?php endfor; ?>
      </div>
    </div>


          <div class="comment-input">
            <label for="private_comment">Mon commentaire (privé) :</label>
            <textarea name="private_comment" id="private_comment" rows="4"><?= htmlspecialchars($personalComment ?? '', ENT_QUOTES) ?></textarea>
            <p class="comment-help">Ce commentaire est uniquement visible par vous.</p>
          </div>

          <button type="submit" name="save_note" class="btn btn-primary">
            Enregistrer
          </button>
        </form>
      </section>
    <?php elseif ($loggedUserId): ?>
      <p class="note-info">
        Ajoutez ce livre à votre bibliothèque pour pouvoir lui attribuer une note et un commentaire privé.
      </p>
    <?php endif; ?>

  </div>
</div>

<?php include("include/footer.inc.php"); ?>
