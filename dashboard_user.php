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
      <article class="dash-card">
        <div class="dash-icon">🤝</div>
        <h2>Mes amis</h2>
        <p>Rechercher des utilisateurs, envoyer ou accepter des demandes d’amis.</p>
        <a class="btn" href="amis.php">Gérer mes amis</a>
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
