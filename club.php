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


// CAS 1 : PAS D'ID → LISTE DES CLUBS + INVITATIONS

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

<<<<<<< HEAD
    <style>
      .club-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
        gap:16px;
        margin-top:10px;
      }
      .club-card {
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:14px;
        padding:14px 16px;
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:10px;
        box-shadow:0 4px 10px rgba(0,0,0,0.04);
        transition:transform .15s ease, box-shadow .15s ease;
      }
      .club-card:hover {
        transform:translateY(-2px);
        box-shadow:0 6px 18px rgba(0,0,0,0.08);
      }
      .club-main {
        display:flex;
        gap:10px;
      }
      .club-icon {
        width:42px;
        height:42px;
        border-radius:999px;
        background:#5f7f5f;
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:1.3rem;
      }
      .club-name {
        margin:0 0 4px;
        font-size:1rem;
        color:#111827;
      }
      .club-description {
        margin:0 0 4px;
        font-size:.9rem;
        color:#374151;
      }
      .club-description-muted {
        font-style:italic;
        color:#9ca3af;
      }
      .club-meta {
        margin:0;
        font-size:.8rem;
        color:#6b7280;
      }
      .club-actions {
        display:flex;
        align-items:center;
      }
      body.nuit .club-card {
        background:#1f2937;
        border-color:#374151;
        color:#e5e7eb;
      }
      body.nuit .club-icon {
        background:#3b82f6;
      }
      body.nuit .club-name {
        color:#f9fafb;
      }
      body.nuit .club-description {
        color:#d1d5db;
      }
      body.nuit .club-meta {
        color:#9ca3af;
      }
    </style>

=======
>>>>>>> 595321bb75d8561a72bcca7470fc1ee2ac8491ac
    <?php
    exit;
}


// CAS 2 : ID PRÉSENT → DÉTAIL DU CLUB


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

// --- Gestion des messages (submit classique, pas AJAX) ---
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
    // L'utilisateur n'est pas membre de ce club ou club inexistant
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

/* 🔔 Nombre de messages non lus pour CE club (avant de les marquer comme lus) */
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

/* 🔔 MARQUER LES NOTIFS 'club_message' COMME LUES POUR CE CLUB */
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
        (u.avatar IS NOT NULL) AS has_avatar,
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

<style>
  .club-page {
    display:flex;
    flex-direction:column;
    gap:18px;
  }

  .club-header {
    padding:18px 22px;
    border-radius:16px;
    border:1px solid #e5e7eb;
    display:flex;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }

  .club-header-main {
    flex:1;
    min-width:260px;
  }

  .club-header-title {
    margin-bottom:4px;
  }

  .club-header-description {
    margin:4px 0 6px;
    color:#4b5563;
  }

  .club-header-description-muted {
    color:#9ca3af;
    font-style:italic;
  }

  .club-header-meta {
    margin:0;
    font-size:.85rem;
    color:#6b7280;
  }

  .club-header-meta-small {
    margin:2px 0 0;
    font-size:.8rem;
    color:#9ca3af;
  }

  .club-header-alert {
    margin:6px 0 0;
    font-size:.85rem;
    color:#b91c1c;
  }

  .club-header-error {
    margin-top:8px;
    font-size:.85rem;
    color:#dc2626;
  }

  .club-header-actions {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
  }

  .club-btn-danger-soft {
    background:#fee2e2;
    color:#b91c1c;
  }

  .club-btn-danger {
    background:#dc2626;
    color:#fff;
  }

  /* Layout principal */
  .club-layout-shell {
    display:grid;
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
    gap:18px;
    align-items:start;
  }

  @media (max-width: 768px) {
    .club-layout-shell {
      grid-template-columns: 1fr;
    }
  }

  /* Sidebar */
  .club-sidebar {
    display:flex;
    flex-direction:column;
    gap:12px;
  }

  .club-sidebar-card {
    background:#f9fafb;
    border-radius:14px;
    border:1px solid #e5e7eb;
    padding:12px 14px;
  }

  .club-sidebar-header {
    display:flex;
    gap:10px;
    align-items:center;
  }

  .club-sidebar-icon-lg {
    width:40px;
    height:40px;
    border-radius:999px;
    background:#5f7f5f;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.4rem;
  }

  .club-sidebar-title {
    font-weight:600;
    font-size:.95rem;
    color:#111827;
  }

  .club-sidebar-meta {
    font-size:.8rem;
    color:#6b7280;
  }

  /* Nav */
  .club-nav {
    display:flex;
    flex-direction:column;
    gap:6px;
  }

  .club-nav-link {
    border:none;
    border-radius:999px;
    padding:8px 10px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    font-size:.9rem;
    background:transparent;
    cursor:pointer;
    color:#374151;
    transition:background .15s ease, color .15s ease, transform .1s ease;
  }

  .club-nav-link:hover {
    background:#f3f4f6;
    transform:translateX(1px);
  }

  .club-nav-link.is-active {
    background:#5f7f5f;
    color:#f9fafb;
  }

  .club-nav-label {
    font-weight:500;
  }

  .club-nav-badge {
    min-width:22px;
    padding:1px 6px;
    border-radius:999px;
    font-size:.75rem;
    text-align:center;
    background:#e5e7eb;
    color:#374151;
  }

  .club-nav-link.is-active .club-nav-badge {
    background:#facc15;
    color:#92400e;
  }

  /* Contenu à droite */
  .club-content {
    background:#fff;
    border-radius:16px;
    border:1px solid #e5e7eb;
    padding:14px 16px 18px;
  }

  .club-panel {
    display:none;
  }

  .club-panel.is-active {
    display:block;
  }

  .club-panel-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:10px;
  }

  .club-subtitle {
    margin:0;
    font-size:1.05rem;
  }

  .club-panel-counter {
    font-size:.8rem;
    color:#6b7280;
  }

  /* Listes */
  .book-list,
  .lib-list,
  .member-list {
    list-style:none;
    padding:0;
    margin:0;
  }

  .book-item,
  .lib-item,
  .member-item {
    display:flex;
    justify-content:space-between;
    gap:10px;
    padding:8px 0;
    border-bottom:1px solid #f3f4f6;
  }

  .member-main {
    display:flex;
    gap:10px;
    align-items:center;
  }
  .member-avatar {
    width:40px;
    height:40px;
    border-radius:50%;
    object-fit:cover;
  }
  .member-avatar-fallback {
    width:40px;
    height:40px;
    border-radius:50%;
    background:#5f7f5f;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .member-name {
    font-weight:600;
  }
  .member-meta {
    font-size:.8rem;
    color:#6b7280;
  }
  .badge-owner {
    display:inline-block;
    margin-left:6px;
    padding:2px 6px;
    font-size:.7rem;
    border-radius:999px;
    background:#facc15;
    color:#92400e;
  }

  /* Livres */
  .book-main,
  .lib-main {
    display:flex;
    gap:10px;
  }
  .book-cover,
  .lib-cover {
    width:42px;
    height:62px;
    object-fit:cover;
    border-radius:6px;
    background:#e5e7eb;
    flex-shrink:0;
  }
  .book-title {
    font-size:.95rem;
    font-weight:600;
    margin-bottom:2px;
  }
  .book-authors {
    font-size:.8rem;
    color:#6b7280;
  }
  .book-meta-small {
    font-size:.75rem;
    color:#9ca3af;
  }

  /* Cartes secondaires */
  .club-invite-card,
  .club-library-card,
  .club-messages-card {
    margin-top:14px;
    padding:10px 12px 12px;
    border-radius:12px;
    border:1px solid #e5e7eb;
  }

  .club-invite-card h3,
  .club-library-card h3 {
    margin-top:0;
    margin-bottom:6px;
    font-size:.98rem;
  }

  .club-invite-row {
    display:flex;
    gap:8px;
    margin-top:6px;
  }

  .club-invite-row select {
    flex:1;
  }

  .club-invite-feedback {
    margin:6px 0 0;
    font-size:.8rem;
    color:#16a34a;
    display:none;
  }

  .club-invite-empty,
  .club-library-empty,
  .club-library-helper {
    margin:0;
    font-size:.85rem;
    color:#6b7280;
  }

  .club-library-helper {
    margin-bottom:6px;
  }

  /* Messages */
  .messages-box {
    max-height:360px;
    overflow-y:auto;
    padding-right:6px;
    border-radius:10px;
    background:#f9fafb;
    border:1px solid #e5e7eb;
  }
  .message-item {
    border-bottom:1px solid #f3f4f6;
    padding:8px 10px;
  }
  .message-item:last-child {
    border-bottom:none;
  }
  .message-header {
    display:flex;
    gap:8px;
    align-items:center;
  }
  .message-avatar {
    width:32px;
    height:32px;
    border-radius:50%;
    object-fit:cover;
  }
  .message-avatar-fallback {
    width:32px;
    height:32px;
    border-radius:50%;
    background:#5f7f5f;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.85rem;
  }
  .message-meta {
    font-size:.8rem;
  }
  .message-author {
    font-weight:600;
  }
  .message-date {
    display:block;
    font-size:.75rem;
    color:#9ca3af;
  }
  .message-content {
    margin-left:40px;
    font-size:.9rem;
    margin-top:4px;
  }
  .badge-me {
    margin-left:4px;
    font-size:.7rem;
    background:#d1fae5;
    color:#047857;
    padding:1px 6px;
    border-radius:999px;
  }
  .message-me .message-content {
    background:#f0fdf4;
    border-radius:8px;
    padding:4px 8px;
  }

  .club-messages-empty {
    font-size:.9rem;
    color:#6b7280;
    padding:8px 10px;
  }

  .club-messages-error {
    margin:8px 0 0;
    font-size:.85rem;
    color:#dc2626;
  }

  .club-messages-form {
    margin-top:10px;
    display:grid;
    gap:6px;
  }

  .club-messages-form textarea {
    resize:vertical;
  }

  /* Mode nuit */
  body.nuit .club-header,
  body.nuit .club-content,
  body.nuit .club-sidebar-card {
    background:#111827;
    border-color:#374151;
    color:#e5e7eb;
  }
  body.nuit .club-header-description {
    color:#e5e7eb;
  }
  body.nuit .club-header-description-muted {
    color:#9ca3af;
  }
  body.nuit .club-sidebar-icon-lg {
    background:#3b82f6;
  }
  body.nuit .club-sidebar-title {
    color:#f9fafb;
  }
  body.nuit .club-sidebar-meta,
  body.nuit .club-header-meta,
  body.nuit .club-header-meta-small,
  body.nuit .club-panel-counter {
    color:#9ca3af;
  }
  body.nuit .club-content {
    background:#020617;
  }
  body.nuit .club-nav-link {
    color:#e5e7eb;
  }
  body.nuit .club-nav-link:hover {
    background:#1f2937;
  }
  body.nuit .club-nav-badge {
    background:#1f2937;
    color:#e5e7eb;
  }
  body.nuit .messages-box {
    background:#020617;
    border-color:#1f2937;
  }
  body.nuit .message-item {
    border-color:#111827;
  }
</style>

<script>
(function() {
  const CLUB_ID = <?= (int)$clubId ?>;

  function postClubAction(action, data, cb) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('club_id', CLUB_ID);
    if (data.userId) fd.append('user_id', data.userId);
    if (data.googleBookId) fd.append('google_book_id', data.googleBookId);

    fetch('clubs_ajax.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(cb)
    .catch(err => {
      console.error('Erreur AJAX club:', err);
      alert("Une erreur est survenue.");
    });
  }

  // Inviter un ami comme membre
  const inviteSelect   = document.getElementById('invite-friend-select');
  const inviteBtn      = document.getElementById('invite-friend-btn');
  const inviteFeedback = document.getElementById('invite-friend-feedback');

  if (inviteBtn && inviteSelect) {
    inviteBtn.addEventListener('click', () => {
      const userId = inviteSelect.value;
      if (!userId) return;
      postClubAction('add_member', { userId }, (res) => {
        if (res.ok) {
          inviteFeedback.style.display = 'block';
          inviteFeedback.textContent = "Invitation envoyée.";
          setTimeout(() => {
            inviteFeedback.style.display = 'none';
          }, 1500);
        } else {
          alert("Impossible d'inviter ce membre au club.");
        }
      });
    });
  }

  // Retirer un membre
  document.querySelectorAll('.js-remove-member').forEach(btn => {
    btn.addEventListener('click', () => {
      const userId = btn.dataset.userId;
      if (!confirm("Retirer ce membre du club ?")) return;
      postClubAction('remove_member', { userId }, (res) => {
        if (res.ok) {
          btn.closest('.member-item')?.remove();
        } else {
          alert("Impossible de retirer ce membre.");
        }
      });
    });
  });

  // Ajouter un livre au club
  document.querySelectorAll('.js-add-book').forEach(btn => {
    btn.addEventListener('click', () => {
      const gbid = btn.dataset.googleBookId;
      postClubAction('add_book', { googleBookId: gbid }, (res) => {
        if (res.ok) {
          btn.textContent = "Ajouté";
          btn.disabled = true;
          setTimeout(() => location.reload(), 800);
        } else {
          alert("Impossible d'ajouter ce livre au club.");
        }
      });
    });
  });

  // Retirer un livre du club
  document.querySelectorAll('.js-remove-book').forEach(btn => {
    btn.addEventListener('click', () => {
      const gbid = btn.dataset.googleBookId;
      if (!confirm("Retirer ce livre du club ?")) return;
      postClubAction('remove_book', { googleBookId: gbid }, (res) => {
        if (res.ok) {
          btn.closest('.book-item')?.remove();
        } else {
          alert("Impossible de retirer ce livre.");
        }
      });
    });
  });

  // Scroll en bas de la zone de messages à l'ouverture (si panel messages actif)
  const box = document.getElementById('messages-box');
  if (box) {
    box.scrollTop = box.scrollHeight;
  }

  // Tabs : Membres / Livres / Messages
  const tabButtons = document.querySelectorAll('.club-nav-link[data-panel]');
  const panels = document.querySelectorAll('.club-panel');

  function activatePanel(panelName) {
    panels.forEach(p => {
      if (p.id === 'panel-' + panelName) {
        p.classList.add('is-active');
      } else {
        p.classList.remove('is-active');
      }
    });

    tabButtons.forEach(btn => {
      if (btn.dataset.panel === panelName) {
        btn.classList.add('is-active');
      } else {
        btn.classList.remove('is-active');
      }
    });

    // Si on active le panel messages, scroll en bas
    if (panelName === 'messages' && box) {
      setTimeout(() => {
        box.scrollTop = box.scrollHeight;
      }, 50);
    }
  }

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.panel;
      if (!target) return;
      activatePanel(target);
    });
  });

  // Par défaut : on affiche Membres (déjà actif via classe is-active)
})();
</script>