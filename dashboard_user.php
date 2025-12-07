<?php
// dashboard_user.php — Tableau de bord utilisateur
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=dashboard_user.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/BadgeManager.php';

$userId    = (int)($_SESSION['user'] ?? 0);
$login     = $_SESSION['login'] ?? 'Utilisateur';
$pageTitle = "Mon espace – Kitabee";

// ===== Badges utilisateur =====
$badgeManager = new BadgeManager($pdo);
$userBadges   = $badgeManager->getUserBadges($userId); // <-- ici : $userId (et pas $userID)

/** Nombre de demandes d'amis en attente pour l'utilisateur connecté */
$pendingFriendRequests = 0;
if ($userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_friends
            WHERE friend_id = :uid
              AND status = 'pending'
        ");
        $stmt->execute([':uid' => $userId]);
        $pendingFriendRequests = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $pendingFriendRequests = 0;
    }
}

/** Nombre d'invitations de clubs de lecture */
$pendingClubInvites = 0;
if ($userId) {
    try {
        $stmtClub = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :uid
              AND type = 'club_invite'
              AND is_read = 0
        ");
        $stmtClub->execute([':uid' => $userId]);
        $pendingClubInvites = (int)$stmtClub->fetchColumn();
    } catch (Throwable $e) {
        $pendingClubInvites = 0;
    }
}

/** Nombre total de messages de clubs non lus */
$unreadClubMessagesTotal = 0;
if ($userId) {
    try {
        $stmtMsg = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :uid
              AND type = 'club_message'
              AND is_read = 0
        ");
        $stmtMsg->execute([':uid' => $userId]);
        $unreadClubMessagesTotal = (int)$stmtMsg->fetchColumn();
    } catch (Throwable $e) {
        $unreadClubMessagesTotal = 0;
    }
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
      <?php
        $totalClubBadge = $pendingClubInvites + $unreadClubMessagesTotal;
      ?>
      <article class="dash-card dash-card-clubs">
        <?php if ($totalClubBadge > 0): ?>
          <span class="card-notif-badge"><?= $totalClubBadge ?></span>
        <?php endif; ?>
        <div class="dash-icon">👥</div>
        <h2>Mes Clubs de Lecture</h2>
        <p>Consulter vos clubs de lecture, invitations et discussions.</p>

        <?php if ($pendingClubInvites > 0 || $unreadClubMessagesTotal > 0): ?>
          <p style="font-size:.9rem; margin-top:4px;">
            <?php if ($pendingClubInvites > 0): ?>
              🔔 <?= $pendingClubInvites ?> invitation<?= $pendingClubInvites > 1 ? 's' : '' ?> à des clubs<br>
            <?php endif; ?>
            <?php if ($unreadClubMessagesTotal > 0): ?>
              💬 <?= $unreadClubMessagesTotal ?> nouveau<?= $unreadClubMessagesTotal > 1 ? 'x' : '' ?>
              message<?= $unreadClubMessagesTotal > 1 ? 's' : '' ?> dans vos clubs
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p style="font-size:.85rem; color:#6b7280; margin-top:4px;">
            Aucun nouveau message ou invitation dans vos clubs.
          </p>
        <?php endif; ?>

        <a class="btn" href="club.php" style="margin-top:8px;">Gérer mes clubs</a>
      </article>

      <!-- Badges -->
      <article class="dash-card" style="grid-column: 1 / -1;">
        <h2>Mes badges</h2>
        <?php if (empty($userBadges)): ?>
          <p>Tu n'as pas encore débloqué de badge.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($userBadges as $badge): ?>
              <li>
                <strong><?= htmlspecialchars($badge['name']) ?></strong><br>
                <?= htmlspecialchars($badge['description']) ?><br>
                <small>Débloqué le <?= htmlspecialchars($badge['unlocked_at']) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
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

