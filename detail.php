<?php
/**
 * detail.php — Détail d'un livre (Google Books)
 *
 * - Affiche les informations d'un livre via Google Books API (volume ID).
 * - Permet (si connecté) d'ajouter/retirer le livre de la wishlist ou bibliothèque.
 * - Permet (si le livre est en bibliothèque) d'enregistrer une note (1..5) et un commentaire privé.
 * - Affiche la moyenne globale des notes (tous utilisateurs Kitabee).
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

$pageTitle = "Détail du livre - Kitabee";

require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

$loggedUserId = !empty($_SESSION['user']) ? (int)$_SESSION['user'] : 0;
$login        = $_SESSION['login'] ?? null;

include __DIR__ . '/include/header.inc.php';

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (!$id) {
    echo "<p>Aucun livre sélectionné.</p>";
    include 'include/footer.inc.php';
    exit;
}

$ctx = kb_get_book_detail_context($pdo, $id, $loggedUserId, $GOOGLE_API_KEY ?? null);

if (empty($ctx['ok'])) {
    echo "<p>" . htmlspecialchars($ctx['error'] ?? 'Erreur inconnue.', ENT_QUOTES, 'UTF-8') . "</p>";
    include 'include/footer.inc.php';
    exit;
}

$title           = $ctx['title'];
$authors         = $ctx['authors'];
$description     = $ctx['description'];
$thumbnail       = $ctx['thumbnail'];
$publisher       = $ctx['publisher'];
$publishedDate   = $ctx['publishedDate'];
$pageCount       = $ctx['pageCount'];
$categories      = $ctx['categories'];
$isInWishlist    = (bool)$ctx['isInWishlist'];
$isInLibrary     = (bool)$ctx['isInLibrary'];
$personalRating  = $ctx['personalRating'];
$personalComment = $ctx['personalComment'];
$avgRating       = $ctx['avgRating'];
$avgCount        = (int)$ctx['avgCount'];
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
    <p><strong>Pages :</strong> <?= htmlspecialchars((string)$pageCount) ?></p>
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
