<?php
// club.php — Clubs de lecture : liste + détail
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=club.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/ClubManager.php';
require_once __DIR__ . '/classes/FriendManager.php';

$userId = (int)$_SESSION['user'];
$login  = $_SESSION['login'] ?? 'Utilisateur';

$cm = new ClubManager($pdo, $userId);
$fm = new FriendManager($pdo, $userId);

$clubId = isset($_GET['id']) ? (int)$_GET['id'] : 0;


// =====================================================
// CAS 1 : PAS D'ID → LISTE DES CLUBS + INVITATIONS
// =====================================================
if ($clubId <= 0) {
    $pageTitle = "Mes clubs de lecture – Kitabee";
    $message = null;
    $error   = null;

    // Message si on revient après avoir quitté / supprimé un club
    if (isset($_GET['left']) && $_GET['left'] === '1') {
        $message = "Vous avez quitté le club.";
    }
    if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
        $message = "Le club a été supprimé avec succès.";
    }

    /* ----- 1) Traitement des invitations (Accepter / Refuser) ----- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_action'], $_POST['notif_id'])) {
        $notifAction = $_POST['notif_action'];
        $notifId     = (int)($_POST['notif_id'] ?? 0);
        $notifClubId = (int)($_POST['club_id'] ?? 0);

        try {
            // On commence par vérifier que la notif appartient bien à l'utilisateur
            $stmtCheck = $pdo->prepare("
                SELECT id, club_id
                FROM notifications
                WHERE id = :nid
                  AND user_id = :uid
                  AND type = 'club_invite'
                  AND is_read = 0
                LIMIT 1
            ");
            $stmtCheck->execute([
                ':nid' => $notifId,
                ':uid' => $userId,
            ]);
            $notifRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$notifRow) {
                $error = "Cette invitation n'existe pas ou a déjà été traitée.";
            } else {
                $clubFromNotif = (int)$notifRow['club_id'];

                if ($notifAction === 'accept') {
                    // On ajoute l'utilisateur comme membre du club
                    $stmtAdd = $pdo->prepare("
                        INSERT IGNORE INTO book_club_members (club_id, user_id, role)
                        VALUES (:cid, :uid, 'member')
                    ");
                    $stmtAdd->execute([
                        ':cid' => $clubFromNotif,
                        ':uid' => $userId,
                    ]);

                    // On marque la notif comme lue
                    $stmtRead = $pdo->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = :nid
                    ");
                    $stmtRead->execute([':nid' => $notifId]);

                    $message = "Vous avez rejoint le club avec succès 🎉";

                } elseif ($notifAction === 'decline') {
                    // On marque simplement la notif comme lue
                    $stmtRead = $pdo->prepare("
                        UPDATE notifications
                        SET is_read = 1
                        WHERE id = :nid
                    ");
                    $stmtRead->execute([':nid' => $notifId]);

                    $message = "Invitation refusée.";
                } else {
                    $error = "Action inconnue sur l'invitation.";
                }
            }
        } catch (Throwable $e) {
            $error = "Une erreur est survenue lors du traitement de l'invitation.";
        }
    }

    /* ----- 2) Création d'un club ----- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['notif_action'])) {
        $name  = trim($_POST['name'] ?? '');
        $descr = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = "Le nom du club est obligatoire.";
        } else {
            $newClubId = $cm->createClub($name, $descr);
            if ($newClubId) {
                $message = "Le club « " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " » a été créé avec succès 🎉";
            } else {
                $error = "Impossible de créer le club. Veuillez réessayer.";
            }
        }
    }

    /* ----- 3) Récupérer les invitations à des clubs (non lues) ----- */
    $clubInvites = [];
    try {
        $stmtInv = $pdo->prepare("
            SELECT 
                n.id,
                n.content,
                n.created_at,
                n.club_id,
                u.login AS from_login,
                c.name  AS club_name
            FROM notifications n
            JOIN users      u ON u.id = n.from_user_id
            JOIN book_clubs c ON c.id = n.club_id
            WHERE n.user_id = :uid
              AND n.type    = 'club_invite'
              AND n.is_read = 0
            ORDER BY n.created_at DESC
        ");
        $stmtInv->execute([':uid' => $userId]);
        $clubInvites = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $clubInvites = [];
    }

    /* ----- 4) Récupérer la liste des clubs dont je suis membre ----- */
    $clubs = $cm->getMyClubs();

    /* ----- 5) Nombre de messages de clubs non lus par club ----- */
    $unreadClubMessagesByClub = [];
    if ($clubs) {
        $clubIds = array_column($clubs, 'id'); // [1, 2, 3, ...]
        $placeholders = implode(',', array_fill(0, count($clubIds), '?'));

        $sqlUnread = "
            SELECT club_id, COUNT(*) AS unread_count
            FROM notifications
            WHERE user_id = ?
              AND type = 'club_message'
              AND is_read = 0
              AND club_id IN ($placeholders)
            GROUP BY club_id
        ";

        try {
            $stmtUnread = $pdo->prepare($sqlUnread);
            $params = array_merge([$userId], $clubIds);
            $stmtUnread->execute($params);

            while ($row = $stmtUnread->fetch(PDO::FETCH_ASSOC)) {
                $unreadClubMessagesByClub[(int)$row['club_id']] = (int)$row['unread_count'];
            }
        } catch (Throwable $e) {
            $unreadClubMessagesByClub = [];
        }
    }

    include __DIR__ . '/include/header.inc.php';
    ?>
    <section class="section">
      <div class="container" style="max-width:960px;">

        <h1 class="section-title">Mes clubs de lecture</h1>
        <p>Créez des clubs avec vos amis et partagez vos lectures.</p>

        <?php if (!empty($error)): ?>
          <div class="card" style="padding:10px; border-left:4px solid #dc2626; margin:10px 0;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
          <div class="card" style="padding:10px; border-left:4px solid #16a34a; margin:10px 0;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <!-- Invitations reçues à des clubs -->
        <?php if (!empty($clubInvites)): ?>
          <section class="card" style="padding:16px 18px; margin-bottom:20px; border-radius:14px; border:1px solid #e5e7eb;">
            <h2 style="margin-top:0; font-size:1.05rem;">🛎 Invitations à des clubs de lecture</h2>
            <p style="font-size:.9rem; color:#555; margin-bottom:10px;">
              Vos amis vous ont invité à rejoindre des clubs. Choisissez d’accepter ou de refuser.
            </p>

            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
              <?php foreach ($clubInvites as $inv): ?>
                <li style="border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; display:flex; justify-content:space-between; gap:10px; align-items:center;">
                  <div>
                    <div style="font-size:.9rem; margin-bottom:4px;">
                      <strong><?= htmlspecialchars($inv['from_login'], ENT_QUOTES, 'UTF-8') ?></strong>
                      vous a invité à rejoindre le club
                      <strong><?= htmlspecialchars($inv['club_name'], ENT_QUOTES, 'UTF-8') ?></strong>.
                    </div>
                    <div style="font-size:.75rem; color:#6b7280;">
                      Reçu le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($inv['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </div>
                  <div style="display:flex; gap:6px;">
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="notif_id" value="<?= (int)$inv['id'] ?>">
                      <input type="hidden" name="club_id"  value="<?= (int)$inv['club_id'] ?>">
                      <button type="submit" name="notif_action" value="accept" class="btn btn-primary">Accepter</button>
                    </form>
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="notif_id" value="<?= (int)$inv['id'] ?>">
                      <input type="hidden" name="club_id"  value="<?= (int)$inv['club_id'] ?>">
                      <button type="submit" name="notif_action" value="decline" class="btn btn-ghost">Refuser</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <!-- Création d'un club -->
        <section aria-labelledby="create-club-title" class="card" style="padding:18px 20px; margin-bottom:24px; border-radius:14px; border:1px solid #e5e7eb;">
          <h2 id="create-club-title" style="margin-top:0; font-size:1.1rem;">Créer un nouveau club</h2>
          <p style="font-size:.9rem; color:#555; margin-bottom:14px;">
            Donnez un nom à votre club et ajoutez une petite description. Vous pourrez ensuite inviter vos amis.
          </p>

          <form method="post" style="display:grid; gap:12px; max-width:520px;">
            <div>
              <label for="name">Nom du club</label>
              <input type="text" id="name" name="name" required placeholder="Ex : Club des romans historiques">
            </div>

            <div>
              <label for="description">Description (optionnel)</label>
              <textarea id="description" name="description" rows="3" placeholder="Ex : On lit un roman par mois et on en discute ensemble."></textarea>
            </div>

            <div>
              <button type="submit" class="btn btn-primary">Créer le club</button>
            </div>
          </form>
        </section>

        <!-- Liste de mes clubs -->
        <section aria-labelledby="my-clubs-title">
          <h2 id="my-clubs-title" style="font-size:1.1rem; margin-bottom:10px;">Clubs dont je fais partie</h2>

          <?php if (!$clubs): ?>
            <p>Vous ne faites encore partie d’aucun club. Créez-en un ci-dessus pour commencer 📚.</p>
          <?php else: ?>
            <div class="club-grid">
              <?php foreach ($clubs as $club): ?>
                <?php
                  $clubIdRow = (int)$club['id'];
                  $unread = $unreadClubMessagesByClub[$clubIdRow] ?? 0;
                ?>
                <article class="club-card">
                  <div class="club-main">
                    <div class="club-icon">📖</div>
                    <div>
                      <h3 class="club-name">
                        <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?>
                      </h3>
                      <?php if (!empty($club['description'])): ?>
                        <p class="club-description">
                          <?= nl2br(htmlspecialchars($club['description'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>
                      <?php else: ?>
                        <p class="club-description club-description-muted">
                          Pas de description pour ce club.
                        </p>
                      <?php endif; ?>
                      <p class="club-meta">
                        Rôle : 
                        <?php if ($club['role'] === 'owner'): ?>
                          <strong>Créateur du club</strong>
                        <?php else: ?>
                          Membre
                        <?php endif; ?>
                        • Créé le <?= htmlspecialchars(date('d/m/Y', strtotime($club['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                      </p>

                      <?php if ($unread > 0): ?>
                        <p class="club-meta" style="color:#b91c1c; font-size:.85rem; margin-top:3px;">
                          🔔 <?= $unread ?> nouveau<?= $unread > 1 ? 'x' : '' ?> message<?= $unread > 1 ? 's' : '' ?> dans ce club
                        </p>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="club-actions">
                    <a class="btn" href="club.php?id=<?= (int)$club['id'] ?>">Voir le club</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>
    </section>

    <?php include __DIR__ . '/include/footer.inc.php'; ?>
    <?php
    exit;
}


// =====================================================
// CAS 2 : ID PRÉSENT → DÉTAIL DU CLUB
// =====================================================

// --- Gestion "Supprimer le club" (owner uniquement) ---
$deleteError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club'])) {
    if ($cm->deleteClub($clubId)) {
        header('Location: club.php?deleted=1');
        exit;
    } else {
        $deleteError = "Impossible de supprimer ce club.";
    }
}

// --- Gestion "Quitter le club" ---
$leaveError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_club'])) {
    if ($cm->leaveClub($clubId)) {
        header('Location: club.php?left=1');
        exit;
    } else {
        $leaveError = "Impossible de quitter ce club (vous en êtes peut-être le créateur).";
    }
}

// --- Gestion des messages (pas AJAX) ---
$messageError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['club_message'])) {
    $content = trim($_POST['club_message'] ?? '');
    if ($content === '') {
        $messageError = "Le message ne peut pas être vide.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO book_club_messages (club_id, user_id, content)
            VALUES (:cid, :uid, :content)
        ");
        $ok = $stmt->execute([
            ':cid'     => $clubId,
            ':uid'     => $userId,
            ':content' => $content,
        ]);
        if (!$ok) {
            $messageError = "Impossible d'envoyer le message. Réessayez.";
        } else {
            // 🔔 CRÉATION DES NOTIFS 'club_message' POUR LES AUTRES MEMBRES
            try {
                // Récupérer tous les membres du club sauf l'auteur
                $stmtMembers = $pdo->prepare("
                    SELECT user_id
                    FROM book_club_members
                    WHERE club_id = :cid
                      AND user_id <> :uid
                ");
                $stmtMembers->execute([
                    ':cid' => $clubId,
                    ':uid' => $userId,
                ]);
                $membersForNotif = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);

                if ($membersForNotif) {
                    // Petit aperçu du message pour la notif
                    $preview = mb_substr($content, 0, 120);
                    if (mb_strlen($content) > 120) {
                        $preview .= '…';
                    }

                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications (user_id, from_user_id, club_id, type, content, is_read, created_at)
                        VALUES (:uid, :from_uid, :club_id, 'club_message', :content, 0, NOW())
                    ");

                    foreach ($membersForNotif as $memberId) {
                        $stmtNotif->execute([
                            ':uid'      => (int)$memberId,
                            ':from_uid' => $userId,
                            ':club_id'  => $clubId,
                            ':content'  => $preview,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                // en cas d'erreur sur les notifs, on ne bloque pas l'envoi du message
            }

            // Éviter le renvoi du formulaire en refresh
            header("Location: club.php?id=" . $clubId);
            exit;
        }
    }
}

// Récupérer les infos du club (et vérifier l'accès)
$club = $cm->getClub($clubId);
if (!$club) {
    $pageTitle = "Club introuvable – Kitabee";
    include __DIR__ . '/include/header.inc.php';
    ?>
    <section class="section">
      <div class="container" style="max-width:800px;">
        <h1 class="section-title">Club introuvable</h1>
        <p>Ce club n'existe pas ou vous n'en faites pas partie.</p>
        <a class="btn btn-ghost" href="club.php">⬅ Retour à mes clubs</a>
      </div>
    </section>
    <?php
    include __DIR__ . '/include/footer.inc.php';
    exit;
}

/*  Nombre de messages non lus pour CE club (avant de les marquer comme lus) */
$unreadMessagesThisClub = 0;
try {
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :uid
          AND club_id = :cid
          AND type = 'club_message'
          AND is_read = 0
    ");
    $stmtCount->execute([
        ':uid' => $userId,
        ':cid' => $clubId,
    ]);
    $unreadMessagesThisClub = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {
    $unreadMessagesThisClub = 0;
}

/* MARQUER LES NOTIFS 'club_message' COMME LUES POUR CE CLUB */
try {
    $stmtReadMsg = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = :uid
          AND club_id = :cid
          AND type = 'club_message'
          AND is_read = 0
    ");
    $stmtReadMsg->execute([
        ':uid' => $userId,
        ':cid' => $clubId,
    ]);
} catch (Throwable $e) {
    // si ça plante, on ignore, ce n'est pas bloquant
}

// === Données pour l'affichage ===

// membres du club
$members = $cm->getMembers($clubId);
$memberIds = array_column($members, 'id');
$memberCount = count($members);

// amis (pour les invitations)
$friends          = $fm->getFriends();
$invitableFriends = array_filter($friends, function ($f) use ($memberIds) {
    return !in_array((int)$f['id'], $memberIds, true);
});

// livres du club (avec title, authors, thumbnail si dispo)
$clubBooks = $cm->getBooks($clubId);
$clubBooksCount = count($clubBooks);

// bibliothèque perso de l'utilisateur (pour proposer des livres à ajouter au club)
$stmtLib = $pdo->prepare("
    SELECT id, google_book_id, title, authors, thumbnail, added_at
    FROM user_library
    WHERE user_id = :uid
    ORDER BY added_at DESC
");
$stmtLib->execute([':uid' => $userId]);
$userLibrary = $stmtLib->fetchAll(PDO::FETCH_ASSOC);

// messages du club
$stmtMsg = $pdo->prepare("
    SELECT 
        m.id, 
        m.content, 
        m.created_at, 
        u.login, 
        (u.avatar_choice IS NOT NULL) AS has_avatar,
        u.id AS user_id
    FROM book_club_messages m
    JOIN users u ON u.id = m.user_id
    WHERE m.club_id = :cid
    ORDER BY m.created_at ASC
    LIMIT 100
");
$stmtMsg->execute([':cid' => $clubId]);
$messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Club : " . $club['name'] . " – Kitabee";
include __DIR__ . '/include/header.inc.php';
?>

<!--  Injection du clubId pour script.js  -->
<script>
  document.documentElement.dataset.clubId = <?= (int)$clubId ?>;
</script>

<section class="section">
  <div class="container club-page" style="max-width:1100px;">

    <!-- En-tête du club -->
    <header class="card club-header">
      <div class="club-header-main">
        <div>
          <h1 class="section-title club-header-title">
            📖 <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <?php if (!empty($club['description'])): ?>
            <p class="club-header-description">
              <?= nl2br(htmlspecialchars($club['description'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
          <?php else: ?>
            <p class="club-header-description club-header-description-muted">
              Ce club n'a pas encore de description.
            </p>
          <?php endif; ?>
          <p class="club-header-meta">
            Vous êtes : 
            <?php if ($club['my_role'] === 'owner'): ?>
              <strong>Créateur du club</strong>
            <?php else: ?>
              Membre
            <?php endif; ?>
            • Créé le <?= htmlspecialchars(date('d/m/Y', strtotime($club['created_at'])), ENT_QUOTES, 'UTF-8') ?>
          </p>
          <p class="club-header-meta-small">
            <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> •
            <?= $clubBooksCount ?> livre<?= $clubBooksCount > 1 ? 's' : '' ?>
          </p>

          <?php if ($unreadMessagesThisClub > 0): ?>
            <p class="club-header-alert">
              🔔 Vous aviez <?= $unreadMessagesThisClub ?> nouveau<?= $unreadMessagesThisClub > 1 ? 'x' : '' ?>
              message<?= $unreadMessagesThisClub > 1 ? 's' : '' ?> non lu<?= $unreadMessagesThisClub > 1 ? 's' : '' ?> dans ce club.
            </p>
          <?php endif; ?>

          <?php if ($leaveError): ?>
            <p class="club-header-error">
              <?= htmlspecialchars($leaveError, ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php endif; ?>
          <?php if ($deleteError): ?>
            <p class="club-header-error">
              <?= htmlspecialchars($deleteError, ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
      <div class="club-header-actions">
        <a href="club.php" class="btn btn-ghost">⬅ Retour à mes clubs</a>

        <?php if ($club['my_role'] !== 'owner'): ?>
          <form method="post" onsubmit="return confirm('Voulez-vous vraiment quitter ce club ?');" style="margin:0;">
            <button 
              type="submit" 
              name="leave_club" 
              value="1" 
              class="btn btn-ghost club-btn-danger-soft">
              Quitter le club
            </button>
          </form>
        <?php endif; ?>

        <?php if ($club['my_role'] === 'owner'): ?>
          <form method="post"
                onsubmit="return confirm('Supprimer définitivement ce club pour tous les membres ?');"
                style="margin:0;">
            <input type="hidden" name="delete_club" value="1">
            <button type="submit" class="btn btn-ghost club-btn-danger">
              🗑️ Supprimer le club
            </button>
          </form>
        <?php endif; ?>

      </div>
    </header>

    <!-- Layout principal : bannière à gauche + contenu à droite -->
    <div class="club-layout-shell">
      <!-- BANNIÈRE / SIDEBAR -->
      <aside class="club-sidebar">
        <div class="club-sidebar-card">
          <div class="club-sidebar-header">
            <div class="club-sidebar-icon-lg">📚</div>
            <div>
              <div class="club-sidebar-title">
                <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div class="club-sidebar-meta">
                <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> •
                <?= $clubBooksCount ?> livre<?= $clubBooksCount > 1 ? 's' : '' ?>
              </div>
            </div>
          </div>
        </div>

        <nav class="club-nav" aria-label="Navigation du club">
          <button
            type="button"
            class="club-nav-link is-active"
            data-panel="members"
          >
            <span class="club-nav-label">Membres</span>
            <span class="club-nav-badge"><?= $memberCount ?></span>
          </button>

          <button
            type="button"
            class="club-nav-link"
            data-panel="books"
          >
            <span class="club-nav-label">Livres</span>
            <span class="club-nav-badge"><?= $clubBooksCount ?></span>
          </button>

          <button
            type="button"
            class="club-nav-link"
            data-panel="messages"
          >
            <span class="club-nav-label">Messages</span>
          </button>
        </nav>
      </aside>

      <!-- CONTENU VARIABLE À DROITE -->
      <div class="club-content">

        <!-- PANEL MEMBRES -->
        <section
          id="panel-members"
          class="club-panel is-active"
          aria-label="Membres du club"
        >
          <header class="club-panel-header">
            <h2 class="club-subtitle">Membres du club</h2>
            <span class="club-panel-counter">
              <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?>
            </span>
          </header>

          <?php if (!$members): ?>
            <p>Aucun membre pour le moment.</p>
          <?php else: ?>
            <ul class="member-list">
              <?php foreach ($members as $m): ?>
                <li class="member-item">
                  <div class="member-main">
                    <?php if (!empty($m['has_avatar'])): ?>
                      <img
                        src="avatar.php?id=<?= (int)$m['id'] ?>"
                        alt="Avatar de <?= htmlspecialchars($m['login'], ENT_QUOTES, 'UTF-8') ?>"
                        class="member-avatar"
                      >
                    <?php else: ?>
                      <div class="member-avatar member-avatar-fallback">
                        <?= strtoupper(substr($m['login'], 0, 1)) ?>
                      </div>
                    <?php endif; ?>
                    <div>
                      <div class="member-name">
                        <?= htmlspecialchars($m['login'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($m['role'] === 'owner'): ?>
                          <span class="badge badge-owner">Créateur</span>
                        <?php endif; ?>
                      </div>
                      <div class="member-meta">
                        Membre depuis le <?= htmlspecialchars(date('d/m/Y', strtotime($m['joined_at'])), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    </div>
                  </div>

                  <?php if ($club['my_role'] === 'owner' && $m['role'] !== 'owner' && $m['id'] !== $userId): ?>
                    <button
                      type="button"
                      class="btn btn-ghost js-remove-member"
                      data-user-id="<?= (int)$m['id'] ?>"
                    >Retirer</button>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($club['my_role'] === 'owner'): ?>
            <div class="card club-invite-card">
              <h3>Inviter un ami au club</h3>
              <?php if (!$invitableFriends): ?>
                <p class="club-invite-empty">
                  Aucun ami disponible à inviter (ils sont peut-être déjà tous membres !).
                </p>
              <?php else: ?>
                <div class="club-invite-row">
                  <select id="invite-friend-select">
                    <?php foreach ($invitableFriends as $f): ?>
                      <option value="<?= (int)$f['id'] ?>">
                        <?= htmlspecialchars($f['login'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" class="btn btn-primary" id="invite-friend-btn">Inviter</button>
                </div>
                <p id="invite-friend-feedback" class="club-invite-feedback">
                  Invitation envoyée.
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>

        <!-- PANEL LIVRES -->
        <section
          id="panel-books"
          class="club-panel"
          aria-label="Livres du club"
        >
          <header class="club-panel-header">
            <h2 class="club-subtitle">Livres du club</h2>
            <span class="club-panel-counter">
              <?= $clubBooksCount ?> livre<?= $clubBooksCount > 1 ? 's' : '' ?>
            </span>
          </header>

          <?php if (!$clubBooks): ?>
            <p>Aucun livre n’a encore été ajouté à ce club.</p>
          <?php else: ?>
            <ul class="book-list">
              <?php foreach ($clubBooks as $b): ?>
                <?php
                  $title    = $b['title'] ?: 'Titre inconnu';
                  $authors  = $b['authors'] ?: 'Auteur inconnu';
                  $thumb    = $b['thumbnail'] ?: "https://via.placeholder.com/80x120?text=Pas+d'image";
                  $addedAt  = $b['added_at'] ?? null;
                ?>
                <li class="book-item">
                  <div class="book-main">
                    <img
                      src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                      alt="Couverture du livre"
                      class="book-cover"
                    >
                    <div>
                      <div class="book-title">
                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <div class="book-authors">
                        <?= htmlspecialchars($authors, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <div class="book-meta-small">
                        <?php if ($addedAt): ?>
                          Ajouté le <?= htmlspecialchars(date('d/m/Y', strtotime($addedAt)), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        • <a
                            href="https://books.google.com/books?id=<?= urlencode($b['google_book_id']) ?>"
                            target="_blank"
                            rel="noopener"
                          >
                            Voir sur Google Books
                          </a>
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="btn btn-ghost js-remove-book"
                    data-google-book-id="<?= htmlspecialchars($b['google_book_id'], ENT_QUOTES, 'UTF-8') ?>"
                  >Retirer</button>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="card club-library-card">
            <h3>Ajouter un livre depuis ma bibliothèque</h3>

            <?php if (!$userLibrary): ?>
              <p class="club-library-empty">
                Vous n'avez encore aucun livre dans votre bibliothèque. Ajoutez-en d'abord pour les partager avec le club.
              </p>
            <?php else: ?>
              <p class="club-library-helper">
                Cliquez sur « Ajouter au club » pour partager un de vos livres avec les membres.
              </p>
              <ul class="lib-list">
                <?php foreach ($userLibrary as $book): ?>
                  <?php
                    $title   = $book['title'] ?: 'Titre inconnu';
                    $authors = $book['authors'] ?: 'Auteur inconnu';
                    $thumb   = $book['thumbnail'] ?: "https://via.placeholder.com/80x120?text=Pas+d'image";
                  ?>
                  <li class="lib-item">
                    <div class="lib-main">
                      <img
                        src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Couverture du livre"
                        class="lib-cover"
                      >
                      <div>
                        <div class="book-title">
                          <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="book-authors">
                          <?= htmlspecialchars($authors, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <small class="book-meta-small">
                          Ajouté à votre bibliothèque le <?= htmlspecialchars(date('d/m/Y', strtotime($book['added_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </small>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="btn btn-primary js-add-book"
                      data-google-book-id="<?= htmlspecialchars($book['google_book_id'], ENT_QUOTES, 'UTF-8') ?>"
                    >Ajouter au club</button>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>

        <!-- PANEL MESSAGES -->
        <section
          id="panel-messages"
          class="club-panel"
          aria-label="Messages du club"
        >
          <header class="club-panel-header">
            <h2 class="club-subtitle">Messages du club</h2>
          </header>

          <div class="card club-messages-card">
            <div class="messages-box" id="messages-box">
              <?php if (!$messages): ?>
                <p class="club-messages-empty">Aucun message pour le moment. Lancez la discussion !</p>
              <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                  <div class="message-item <?= ($msg['user_id'] == $userId ? 'message-me' : '') ?>">
                    <div class="message-header">
                      <?php if (!empty($msg['has_avatar'])): ?>
                        <img
                          src="avatar.php?id=<?= (int)$msg['user_id'] ?>"
                          alt="Avatar de <?= htmlspecialchars($msg['login'], ENT_QUOTES, 'UTF-8') ?>"
                          class="message-avatar"
                        >
                      <?php else: ?>
                        <div class="message-avatar message-avatar-fallback">
                          <?= strtoupper(substr($msg['login'], 0, 1)) ?>
                        </div>
                      <?php endif; ?>
                      <div class="message-meta">
                        <span class="message-author">
                          <?= htmlspecialchars($msg['login'], ENT_QUOTES, 'UTF-8') ?>
                          <?php if ($msg['user_id'] == $userId): ?>
                            <span class="badge badge-me">Moi</span>
                          <?php endif; ?>
                        </span>
                        <span class="message-date">
                          <?= htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      </div>
                    </div>
                    <div class="message-content">
                      <?= nl2br(htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <?php if ($messageError): ?>
              <p class="club-messages-error">
                <?= htmlspecialchars($messageError, ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>

            <form method="post" class="club-messages-form">
              <label for="club_message" style="font-size:.9rem;">Écrire un message :</label>
              <textarea id="club_message" name="club_message" rows="3" required></textarea>
              <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Envoyer</button>
            </form>
          </div>
        </section>

      </div>
    </div>

  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
