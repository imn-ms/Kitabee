<?php
// dashboard_user.php — Tableau de bord utilisateur
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
  header('Location: connexion.php?redirect=dashboard_user.php');
  exit;
}

require_once __DIR__ . '/secret/database.php';

$login = $_SESSION['login'] ?? 'Utilisateur';
$pageTitle = "Mon espace – Kitabee";

/** Nombre de demandes d'amis en attente pour l'utilisateur connecté */
$pendingFriendRequests = 0;
if (!empty($_SESSION['user'])) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_friends
        WHERE friend_id = :uid
          AND status = 'pending'
    ");
    $stmt->execute([':uid' => (int)$_SESSION['user']]);
    $pendingFriendRequests = (int)$stmt->fetchColumn();
}

include __DIR__ . '/include/header.inc.php';
?>

<section class="section dashboard">
  <div class="container" style="max-width:1200px;">

    <h1 class="section-title">Bienvenue, <?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
    <p class="subtitle">Voici votre tableau de bord personnel Kitabee.</p>

    <div class="dashboard-grid">

      <!-- Profil -->
      <article class="dash-card">
        <div class="dash-icon">👤</div>
        <h2>Mon profil</h2>
        <p>Gérer mes informations personnelles, e-mail et mot de passe.</p>
        <a class="btn btn-primary" href="profil_user.php">Modifier mon profil</a>
      </article>

      <!-- Bibliothèque -->
      <article class="dash-card">
        <div class="dash-icon">📚</div>
        <h2>Ma bibliothèque</h2>
        <p>Accéder à mes livres ajoutés, en découvrir de nouveaux.</p>
        <a class="btn" href="bibliotheque.php">Ouvrir ma bibliothèque</a>
      </article>

      <!-- Amis -->
      <article class="dash-card dash-card-friends">
        <?php if ($pendingFriendRequests > 0): ?>
          <span class="card-notif-badge"><?= $pendingFriendRequests ?></span>
        <?php endif; ?>
        <div class="dash-icon">🤝</div>
        <h2>Mes amis</h2>
        <p>Rechercher des utilisateurs, envoyer ou accepter des demandes d’amis.</p>
        <a class="btn" href="amis.php">Gérer mes amis</a>
      </article>

      <!-- Clubs -->
      <article class="dash-card">
        <div class="dash-icon">👥</div>
        <h2>Mes Clubs de Lecture</h2>
        <p>Consulter vos clubs de lectures ou rejoignez-en un.</p>
        <a class="btn" href="clubs.php">Gérer mes clubs de lecture</a>
      </article>

      <!-- Déconnexion -->
      <article class="dash-card">
        <div class="dash-icon">🚪</div>
        <h2>Déconnexion</h2>
        <p>Fermer ma session sur ce navigateur.</p>
        <a class="btn btn-ghost" href="deconnexion.php">Me déconnecter</a>
      </article>

    </div>
  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>

<style>
/* ==== Dashboard User ==== */
.dashboard .section-title {
  font-size: 1.8rem;
  margin-bottom: 10px;
  color: #5f7f5f;
}
.dashboard .subtitle {
  color: #555;
  margin-bottom: 24px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
}

.dash-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 20px 24px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform 0.15s ease, box-shadow 0.2s ease;
}
.dash-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}
.dash-card-friends {
  position: relative;
}

.dash-icon {
  font-size: 2rem;
  margin-bottom: 10px;
}
.dash-card h2 {
  margin: 4px 0;
  font-size: 1.1rem;
  color: #1e3a8a;
}
.dash-card p {
  font-size: .9rem;
  color: #555;
  margin-bottom: 14px;
}
.dash-card .btn {
  align-self: flex-start;
}

/* Badge rond pour la card Amis */
.card-notif-badge {
  position:absolute;
  top:10px;
  right:16px;
  display:inline-flex;
  min-width:18px;
  height:18px;
  padding:0 5px;
  border-radius:999px;
  background:#dc2626;
  color:#fff;
  font-size:0.7rem;
  font-weight:700;
  align-items:center;
  justify-content:center;
}

/* Pour thème sombre éventuel */
body.nuit .dash-card {
  background: #1f2937;
  color: #f3f4f6;
  border-color: #374151;
}
body.nuit .dash-card h2 {
  color: #93c5fd;
}
body.nuit .dash-card p {
  color: #d1d5db;
}
</style>
