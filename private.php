<?php
// private.php — Page privée
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
  // pas connecté → on renvoie vers la connexion
  header('Location: connexion.php?redirect=private.php');
  exit;
}

$login = $_SESSION['login'] ?? 'utilisateur';
$pageTitle = "Espace privé – Kitabee";
include __DIR__ . '/include/header.inc.php';
?>
<section class="section">
  <div class="container" style="max-width:900px;">
    <article class="card" style="padding:24px;">
      <h1 class="section-title">Espace privé</h1>
      <p>Bonjour, <strong><?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?></strong> 👋</p>

      <div style="display:flex; gap:8px; margin-top:12px;">
        <a class="btn btn-primary" href="bibliotheque.php">Aller à ma bibliothèque</a>
        <a class="btn" href="deconnexion.php">Se déconnecter</a>
      </div>
    </article>
  </div>
</section>
<?php include __DIR__ . '/include/footer.inc.php'; ?>
