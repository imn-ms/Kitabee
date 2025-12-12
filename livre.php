<?php
/**
 * livre.php – liste de livres (résultats de recherche)
 *
 * Rôle :
 * - Récupère le terme de recherche
 * - Interroge l'API Google Books (max 12 résultats).
 * - Affiche une liste de livres avec couverture, titre, auteurs et lien vers detail.php.
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

/* Cookie de visite */
$visited = isset($_COOKIE['visited']);
if (!$visited) {
    setcookie('visited', '1', 0, '/');
}

/* Dépendances */
require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

/* Contexte utilisateur */
$loggedUserId = !empty($_SESSION['user']) ? (int)$_SESSION['user'] : 0;
$login        = $_SESSION['login'] ?? null;

/* Titre de page */
$pageTitle = "Résultats de recherche – Kitabee";
include __DIR__ . '/include/header.inc.php';

/* Terme de recherche */
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    echo "<p>Aucune recherche effectuée.</p>";
    include __DIR__ . '/include/footer.inc.php';
    exit;
}

/* Appel API Google Books */
$data = kb_google_books_fetch_raw($query, $GOOGLE_API_KEY ?? null, 12);
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

  <h1>Résultats pour « <?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?> »</h1>

  <?php if (!empty($data['items']) && is_array($data['items'])): ?>
    <div class="book-list">
      <?php foreach ($data['items'] as $book):
        $info      = $book['volumeInfo'] ?? [];
        $id        = $book['id'] ?? '';
        $title     = $info['title'] ?? 'Titre inconnu';
        $authors   = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
        $thumbnail = $info['imageLinks']['thumbnail']
                     ?? "https://via.placeholder.com/128x192?text=Pas+d'image";
      ?>
        <div class="book-card">
          <img
            src="<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>"
            alt="Couverture du livre"
          >
          <div class="book-info">
            <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><strong>Auteur(s) :</strong> <?= htmlspecialchars($authors, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="detail.php?id=<?= urlencode($id) ?>" class="btn-view">Voir plus</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>Aucun livre trouvé pour « <?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?> ».</p>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
