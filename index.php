<?php
/**
 * index.php – Page d’accueil Kitabee
 */
header('Content-Type: text/html; charset=UTF-8');
session_start();

// 1) config d'abord (clé Google + BDD)
require __DIR__ . '/secret/config.php';

$visited = isset($_COOKIE['visited']);
if (!$visited) {
  setcookie('visited', '1', 0, '/');
}

$pageTitle = "Accueil Kitabee";

// 2) header après, pour utiliser $pageTitle
include 'include/header.inc.php';

// 3) petite sélection Google Books (on le fait ici)
$googleBooks = [];
$apiUrl = "https://www.googleapis.com/books/v1/volumes?q=subject:fiction&langRestrict=fr&maxResults=6&key={$GOOGLE_API_KEY}";
$apiResponse = @file_get_contents($apiUrl);
if ($apiResponse) {
    $json = json_decode($apiResponse, true);
    if (!empty($json['items'])) {
        $googleBooks = $json['items'];
    }
}
?>

<!-- ===== HERO ===== -->
<section class="hero"
  style="
    position: relative;
    background-image: url('/images/hero.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    padding: 120px 0;
  "
>
  <!-- Overlay sombre -->
  <div style="
    position:absolute;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.45);
  "></div>

  <!-- Contenu -->
  <div class="container hero-content"
       style="position:relative; z-index:2; text-align:left;">

    <!-- Titre aligné à gauche -->
    <h1 class="hero-title" style="color:#fff; margin-left:20px;">
      Kitabee 📚
    </h1>

    <!-- Phrase alignée à gauche -->
    <p class="hero-subtitle" style="color:#fff; margin-left:20px;">
      Gérez votre bibliothèque et découvrez des lectures qui vous ressemblent.
    </p>

    <!-- Boutons -->
    <div class="hero-actions" style="margin-top:20px;">
      <a href="#catalogue" class="btn btn-primary">Parcourir les livres</a>
      <a href="#features" class="btn btn-ghost">En savoir plus</a>
    </div>

  </div>
</section>

<!-- ===== Recherche ===== -->
<section class="section">
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
      <ul id="suggestions" class="suggestions" aria-label="Suggestions de recherche"></ul>
    </div>
  </div>
</section>

<!-- ===== Features ===== -->
<section id="features" class="section">
  <div class="container">
    <h2 class="section-title">Pensé pour les lecteurs exigeants</h2>
    <div class="cards">
      <article class="card">
        <h3>Bibliothèque personnelle</h3>
        <p>Ajoutez et retrouvez vos livres en un clin d’œil.</p>
      </article>
      <article class="card">
        <h3>Wishlist</h3>
        <p>Gardez sous la main les livres à lire plus tard.</p>
      </article>
      <article class="card">
        <h3>Actualité littéraire</h3>
        <p>Tenez-vous au courant des publications et critiques.</p>
      </article>
    </div>
  </div>
</section>

<!-- ===== Catalogue / Sélection dynamique ===== -->
<?php
// === Sélection dynamique Google Books ===

$topics = [
  'subject:fiction', 'subject:romance', 'subject:history', 'subject:fantasy',
  'subject:thriller', 'subject:mystery', 'subject:biography', 'subject:poetry',
  'subject:drama', 'subject:literary', 'subject:classic',

  'subject:science', 'subject:philosophy', 'subject:psychology',
  'subject:education', 'subject:religion', 'subject:travel', 'subject:art',
  'subject:music', 'subject:architecture', 'subject:design', 'subject:photography',

  'subject:juvenile', 'subject:young adult', 'subject:children',

  'subject:comics', 'subject:graphic novels', 'subject:manga',

  'subject:cooking', 'subject:health', 'subject:wellness', 'subject:business',
  'subject:technology', 'subject:politics', 'subject:society', 'subject:law',
  'subject:economics',

  'subject:environment', 'subject:nature', 'subject:sport', 'subject:adventure',
  'subject:science fiction', 'subject:horror'
];

$topic = $topics[array_rand($topics)];
$start = rand(0, 25);

$googleBooks = [];
$apiUrl = "https://www.googleapis.com/books/v1/volumes?q={$topic}&langRestrict=fr&startIndex={$start}&maxResults=6&key={$GOOGLE_API_KEY}";
$apiResponse = @file_get_contents($apiUrl);

if ($apiResponse) {
  $json = json_decode($apiResponse, true);
  if (!empty($json['items'])) {
    $googleBooks = $json['items'];
  }
}

$topicName = ucfirst(str_replace(['subject:', '_'], ['', ' '], explode(':', $topic)[1] ?? 'inconnu'));
?>

<section id="catalogue" class="section muted">
  <div class="container">
    <h2 class="section-title">Sélection du moment : <?= htmlspecialchars($topicName) ?></h2>

    <?php if (empty($googleBooks)): ?>
      <p>Impossible de récupérer la sélection pour le moment.</p>
      <div class="grid">
        <div class="tile placeholder"><h4>Titre à venir</h4><p>—</p></div>
        <div class="tile placeholder"><h4>Titre à venir</h4><p>—</p></div>
        <div class="tile placeholder"><h4>Titre à venir</h4><p>—</p></div>
      </div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($googleBooks as $book):
          $id    = $book['id'] ?? '';
          $info  = $book['volumeInfo'] ?? [];
          $title = $info['title'] ?? 'Titre inconnu';
          $authors = isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu';
          $thumb = $info['imageLinks']['thumbnail'] ?? 'https://via.placeholder.com/128x180?text=Pas+d%27image';
        ?>
          <a class="tile" href="detail.php?id=<?= htmlspecialchars($id) ?>">
            <div class="tile-cover">
              <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>">
            </div>
            <div class="tile-meta">
              <h4><?= htmlspecialchars($title) ?></h4>
              <p><?= htmlspecialchars($authors) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Bandeau cookies ===== -->
<div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite">
  <div class="inner">
    <p>
      Nous utilisons des cookies <strong>non indispensables</strong> pour améliorer l’apparence (thème, dernière visite).
      <br>Acceptez-vous ces cookies ? (vous pouvez changer d’avis dans <a href="/cookie.php">Cookies</a>)
    </p>
    <div class="actions">
      <button class="btn btn-accept" id="cookieAccept">Accepter</button>
      <button class="btn btn-refuse" id="cookieRefuse">Refuser</button>
    </div>
  </div>
</div>

<?php if (!$visited): ?>
  <div class="first-visit">
    Première visite détectée. Rafraîchis la page pour appliquer ton thème.
  </div>
<?php endif; ?>

<script>
// ===== Autocomplétion Google Books =====
const input = document.getElementById('q');
const suggestions = document.getElementById('suggestions');
let timer;

input.addEventListener('input', () => {
  clearTimeout(timer);
  const query = input.value.trim();
  if (query.length < 2) {
    suggestions.innerHTML = '';
    return;
  }
  timer = setTimeout(() => {
    fetch(`https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(query)}&maxResults=5`)
      .then(res => res.json())
      .then(data => {
        suggestions.innerHTML = '';
        if (data.items) {
          data.items.forEach(book => {
            const title = book.volumeInfo?.title || 'Titre inconnu';
            const li = document.createElement('li');
            li.textContent = title;
            li.addEventListener('click', () => {
              input.value = title;
              suggestions.innerHTML = '';
            });
            suggestions.appendChild(li);
          });
        }
      })
      .catch(() => suggestions.innerHTML = '');
  }, 300);
});
</script>

<?php include("include/footer.inc.php"); ?>
