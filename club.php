<?php
// club.php — Détails d'un club de lecture (membres, livres, messages)
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=clubs.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/ClubManager.php';
require_once __DIR__ . '/classes/FriendManager.php';

$userId = (int)$_SESSION['user'];
$login  = $_SESSION['login'] ?? 'Utilisateur';

$clubId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($clubId <= 0) {
    header('Location: clubs.php');
    exit;
}

$cm = new ClubManager($pdo, $userId);
$fm = new FriendManager($pdo, $userId);

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
        <a class="btn btn-ghost" href="clubs.php">⬅ Retour à mes clubs</a>
      </div>
    </section>
    <?php
    include __DIR__ . '/include/footer.inc.php';
    exit;
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
            // Éviter le renvoi du formulaire en refresh
            header("Location: club.php?id=" . $clubId);
            exit;
        }
    }
}

// === Données pour l'affichage ===

// membres du club
$members = $cm->getMembers($clubId);
$memberIds = array_column($members, 'id');

// amis (pour les invitations)
$friends       = $fm->getFriends();
$invitableFriends = array_filter($friends, function ($f) use ($memberIds) {
    return !in_array((int)$f['id'], $memberIds, true);
});

// livres du club
$clubBooks = $cm->getBooks($clubId);

// bibliothèque perso de l'utilisateur (pour proposer des livres à ajouter au club)
$stmtLib = $pdo->prepare("
    SELECT id, google_book_id, added_at
    FROM user_library
    WHERE user_id = :uid
    ORDER BY added_at DESC
");
$stmtLib->execute([':uid' => $userId]);
$userLibrary = $stmtLib->fetchAll(PDO::FETCH_ASSOC);

// messages du club
$stmtMsg = $pdo->prepare("
    SELECT m.id, m.content, m.created_at, u.login, u.avatar, u.id AS user_id
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
  <div class="container" style="max-width:1100px;">

    <!-- En-tête du club -->
    <header class="card" style="padding:18px 22px; margin-bottom:20px; border-radius:14px; border:1px solid #e5e7eb;">
      <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:260px;">
          <h1 class="section-title" style="margin-bottom:4px;">
            📖 <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <?php if (!empty($club['description'])): ?>
            <p style="margin:0 0 6px; color:#4b5563;">
              <?= nl2br(htmlspecialchars($club['description'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
          <?php else: ?>
            <p style="margin:0 0 6px; color:#9ca3af; font-style:italic;">
              Ce club n'a pas encore de description.
            </p>
          <?php endif; ?>
          <p style="margin:0; font-size:.85rem; color:#6b7280;">
            Vous êtes : 
            <?php if ($club['my_role'] === 'owner'): ?>
              <strong>Créateur du club</strong>
            <?php else: ?>
              Membre
            <?php endif; ?>
            • Créé le <?= htmlspecialchars(date('d/m/Y', strtotime($club['created_at'])), ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>
        <div style="display:flex; align-items:center;">
          <a href="clubs.php" class="btn btn-ghost">⬅ Retour à mes clubs</a>
        </div>
      </div>
    </header>

    <div class="club-layout">
      <!-- Colonne membres -->
      <section class="club-column">
        <h2 class="club-subtitle">Membres du club</h2>

        <?php if (!$members): ?>
          <p>Aucun membre pour le moment (ce qui est bizarre, car vous en faites partie 😅).</p>
        <?php else: ?>
          <ul class="member-list">
            <?php foreach ($members as $m): ?>
              <li class="member-item">
                <div class="member-main">
                  <?php if (!empty($m['avatar'])): ?>
                    <img src="uploads/avatars/<?= htmlspecialchars($m['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Avatar de <?= htmlspecialchars($m['login'], ENT_QUOTES, 'UTF-8') ?>" class="member-avatar">
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
          <div class="card" style="margin-top:16px; padding:10px 12px; border-radius:12px; border:1px solid #e5e7eb;">
            <h3 style="margin:0 0 6px; font-size:.98rem;">Inviter un ami au club</h3>
            <?php if (!$invitableFriends): ?>
              <p style="margin:0; font-size:.85rem; color:#6b7280;">
                Aucun ami disponible à inviter (ils sont peut-être déjà tous membres !).
              </p>
            <?php else: ?>
              <div style="display:flex; gap:8px; margin-top:6px;">
                <select id="invite-friend-select" style="flex:1;">
                  <?php foreach ($invitableFriends as $f): ?>
                    <option value="<?= (int)$f['id'] ?>">
                      <?= htmlspecialchars($f['login'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary" id="invite-friend-btn">Inviter</button>
              </div>
              <p id="invite-friend-feedback" style="margin:6px 0 0; font-size:.8rem; color:#16a34a; display:none;">
                Invitation envoyée / ajout au club réussi.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Colonne livres -->
      <section class="club-column">
        <h2 class="club-subtitle">Livres du club</h2>

        <?php if (!$clubBooks): ?>
          <p>Aucun livre n’a encore été ajouté à ce club.</p>
        <?php else: ?>
          <ul class="book-list">
            <?php foreach ($clubBooks as $b): ?>
              <li class="book-item">
                <div>
                  <div class="book-title">
                    Livre Google ID :
                    <code><?= htmlspecialchars($b['google_book_id'], ENT_QUOTES, 'UTF-8') ?></code>
                  </div>
                  <a href="https://books.google.com/books?id=<?= urlencode($b['google_book_id']) ?>" target="_blank" rel="noopener" class="book-link">
                    Voir sur Google Books
                  </a>
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

        <div class="card" style="margin-top:16px; padding:10px 12px; border-radius:12px; border:1px solid #e5e7eb;">
          <h3 style="margin:0 0 6px; font-size:.98rem;">Ajouter un livre depuis ma bibliothèque</h3>

          <?php if (!$userLibrary): ?>
            <p style="margin:0; font-size:.85rem; color:#6b7280;">
              Vous n'avez encore aucun livre dans votre bibliothèque. Ajoutez-en d'abord pour les partager avec le club.
            </p>
          <?php else: ?>
            <p style="margin:0 0 6px; font-size:.85rem; color:#6b7280;">
              Cliquez sur « Ajouter au club » pour partager un de vos livres avec les membres.
            </p>
            <ul class="lib-list">
              <?php foreach ($userLibrary as $book): ?>
                <li class="lib-item">
                  <div>
                    <div class="book-title">
                      <code><?= htmlspecialchars($book['google_book_id'], ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <small style="color:#9ca3af;">
                      Ajouté à votre bibliothèque le <?= htmlspecialchars(date('d/m/Y', strtotime($book['added_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </small>
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
    </div>

    <!-- Section messages -->
    <section style="margin-top:26px;">
      <h2 class="club-subtitle">Messages du club</h2>

      <div class="card" style="padding:12px 14px; border-radius:14px; border:1px solid #e5e7eb;">
        <div class="messages-box" id="messages-box">
          <?php if (!$messages): ?>
            <p style="font-size:.9rem; color:#6b7280;">Aucun message pour le moment. Lancez la discussion !</p>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
              <div class="message-item <?= ($msg['user_id'] == $userId ? 'message-me' : '') ?>">
                <div class="message-header">
                  <?php if (!empty($msg['avatar'])): ?>
                    <img src="uploads/avatars/<?= htmlspecialchars($msg['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="message-avatar">
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
          <p style="margin:8px 0 0; font-size:.85rem; color:#dc2626;">
            <?= htmlspecialchars($messageError, ENT_QUOTES, 'UTF-8') ?>
          </p>
        <?php endif; ?>

        <form method="post" style="margin-top:10px; display:grid; gap:6px;">
          <label for="club_message" style="font-size:.9rem;">Écrire un message :</label>
          <textarea id="club_message" name="club_message" rows="3" required></textarea>
          <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Envoyer</button>
        </form>
      </div>
    </section>

  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>

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
  const inviteSelect = document.getElementById('invite-friend-select');
  const inviteBtn    = document.getElementById('invite-friend-btn');
  const inviteFeedback = document.getElementById('invite-friend-feedback');

  if (inviteBtn && inviteSelect) {
    inviteBtn.addEventListener('click', () => {
      const userId = inviteSelect.value;
      if (!userId) return;
      postClubAction('add_member', { userId }, (res) => {
        if (res.ok) {
          inviteFeedback.style.display = 'block';
          inviteFeedback.textContent = "Membre ajouté au club.";
          setTimeout(() => location.reload(), 800);
        } else {
          alert("Impossible d'ajouter ce membre au club.");
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
          alert("Impossible de retirer ce livre du club.");
        }
      });
    });
  });

  // Scroll en bas de la zone de messages à l'ouverture
  const box = document.getElementById('messages-box');
  if (box) {
    box.scrollTop = box.scrollHeight;
  }
})();
</script>
