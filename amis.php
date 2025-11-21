<?php
// amis.php — Gestion des amis (recherche + liste)
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=amis.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/FriendManager.php';

$userId = (int)$_SESSION['user'];
$pageTitle = "Mes amis – Kitabee";

$fm = new FriendManager($pdo, $userId);

// --- Récupérer les listes pour l'affichage ---
$friends      = $fm->getFriends();   // amis acceptés
$incomingReqs = $fm->getIncoming();  // demandes reçues
$outgoingReqs = $fm->getOutgoing();  // demandes envoyées

// Pour marquer l'état des gens dans les résultats de recherche
$friendIds   = array_column($friends, 'id');
$incomingIds = array_column($incomingReqs, 'id');
$outgoingIds = array_column($outgoingReqs, 'id');

// --- Recherche d'utilisateurs ---
$q = trim($_GET['q'] ?? '');
$searchResults = [];

if ($q !== '') {
    $sql = "
        SELECT id, login, email, avatar
        FROM users
        WHERE (login LIKE :q OR email LIKE :q)
          AND id <> :me
        ORDER BY login
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q'  => '%' . $q . '%',
        ':me' => $userId
    ]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/include/header.inc.php';
?>

<section class="section">
  <div class="container" style="max-width:900px;">
    <h1 class="section-title">Mes amis</h1>
    <p>Recherchez des utilisateurs Kitabee pour les ajouter en amis, et gérez vos relations.</p>

    <!-- ====== Recherche d'amis ====== -->
    <form method="get" class="card" style="padding:16px 20px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
      <label for="search" style="font-weight:600;">Rechercher un utilisateur :</label>
      <input
        id="search"
        name="q"
        type="text"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Pseudo ou e-mail"
        style="flex:1; min-width:180px;"
      >
      <button class="btn btn-primary" type="submit">Rechercher</button>
    </form>

    <?php if ($q !== ''): ?>
      <section aria-labelledby="search-results-title" style="margin-bottom:32px;">
        <h2 id="search-results-title" style="font-size:1.1rem; margin-bottom:10px;">
          Résultats pour « <?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?> »
        </h2>

        <?php if (!$searchResults): ?>
          <p>Aucun utilisateur trouvé pour cette recherche.</p>
        <?php else: ?>
          <div class="friend-grid">
            <?php foreach ($searchResults as $user): 
              $uid = (int)$user['id'];
              $isFriend   = in_array($uid, $friendIds, true);
              $isIncoming = in_array($uid, $incomingIds, true);
              $isOutgoing = in_array($uid, $outgoingIds, true);
            ?>
              <article class="friend-card">
                <div class="friend-main">
                  <?php if (!empty($user['avatar'])): ?>
                    <img class="friend-avatar" src="uploads/avatars/<?= htmlspecialchars($user['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Avatar de <?= htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') ?>">
                  <?php else: ?>
                    <div class="friend-avatar friend-avatar-fallback">
                      <?= strtoupper(substr($user['login'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="friend-name"><?= htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="friend-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>

                <div class="friend-actions">
                  <?php if ($isFriend): ?>
                    <span class="badge badge-success">Déjà ami</span>
                  <?php elseif ($isOutgoing): ?>
                    <span class="badge badge-soft">Demande envoyée</span>
                  <?php elseif ($isIncoming): ?>
                    <span class="badge badge-soft">Vous a envoyé une demande</span>
                  <?php else: ?>
                    <button
                      class="btn btn-primary js-add-friend"
                      data-user-id="<?= $uid ?>"
                      type="button"
                    >
                      Ajouter en ami
                    </button>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- ====== Mes amis actuels ====== -->
    <section aria-labelledby="friends-list-title">
      <h2 id="friends-list-title" style="font-size:1.1rem; margin-bottom:10px;">Mes amis</h2>

      <?php if (!$friends): ?>
        <p>Aucun ami pour le moment. Utilisez la barre de recherche ci-dessus pour trouver des lecteurs 📚.</p>
      <?php else: ?>
        <div class="friend-grid">
          <?php foreach ($friends as $f): $fid = (int)$f['id']; ?>
            <article class="friend-card friend-item">
              <div class="friend-main">
                <?php if (!empty($f['avatar'])): ?>
                  <img class="friend-avatar" src="uploads/avatars/<?= htmlspecialchars($f['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Avatar de <?= htmlspecialchars($f['login'], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                  <div class="friend-avatar friend-avatar-fallback">
                    <?= strtoupper(substr($f['login'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div class="friend-name"><?= htmlspecialchars($f['login'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="friend-email"><?= htmlspecialchars($f['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>

              <div class="friend-actions">
                <button
                  class="btn btn-ghost js-remove-friend"
                  data-user-id="<?= $fid ?>"
                  type="button"
                >
                  Retirer des amis
                </button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>

<style>
.friend-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}
.friend-card {
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:14px;
  padding:12px 14px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  box-shadow:0 3px 8px rgba(0,0,0,0.04);
}
.friend-main {
  display:flex;
  align-items:center;
  gap:10px;
}
.friend-avatar {
  width:48px;
  height:48px;
  border-radius:999px;
  object-fit:cover;
}
.friend-avatar-fallback {
  background:#5f7f5f;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:600;
}
.friend-name {
  font-weight:600;
}
.friend-email {
  font-size:.8rem;
  color:#6b7280;
}
.friend-actions {
  display:flex;
  flex-wrap:wrap;
  gap:6px;
  align-items:center;
}
.badge {
  display:inline-block;
  padding:3px 8px;
  border-radius:999px;
  font-size:.78rem;
}
.badge-success {
  background:#dcfce7;
  color:#166534;
}
.badge-soft {
  background:#e5e7eb;
  color:#374151;
}
</style>

<script>
// Petit JS AJAX pour ajouter / supprimer des amis
(function() {
  function postFriend(action, userId, onDone) {
    const form = new FormData();
    form.append('action', action);
    form.append('user_id', userId);

    fetch('friends_ajax.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
      if (onDone) onDone(data);
    })
    .catch(err => {
      console.error('Erreur AJAX amis:', err);
      alert("Une erreur est survenue.");
    });
  }

  // Ajouter un ami
  document.querySelectorAll('.js-add-friend').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.userId;
      postFriend('send', id, res => {
        if (res.ok) {
          this.textContent = 'Demande envoyée';
          this.disabled = true;
        } else {
          alert("Impossible d'envoyer la demande d'ami.");
        }
      });
    });
  });

  // Supprimer un ami
  document.querySelectorAll('.js-remove-friend').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.userId;
      if (!confirm('Voulez-vous vraiment retirer cet ami ?')) return;
      const card = this.closest('.friend-item');
      postFriend('remove', id, res => {
        if (res.ok) {
          if (card) card.remove();
        } else {
          alert("Impossible de supprimer cet ami.");
        }
      });
    });
  });
})();
</script>
