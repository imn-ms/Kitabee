<?php
/**
 * club.php — Clubs de lecture : liste + détail
 *
 * Cette page gère deux modes :
 * 1) Sans paramètre id : affichage de la liste des clubs de l’utilisateur,
 *    invitations en attente, création de club, indicateurs de messages non lus.
 * 2) Avec paramètre id : affichage du détail d’un club (membres, livres, messages),
 *    gestion quitter/supprimer, et envoi de messages.
 *
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=club.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';
require_once __DIR__ . '/classes/ClubManager.php';
require_once __DIR__ . '/classes/FriendManager.php';

$userId = (int)$_SESSION['user'];
$login  = $_SESSION['login'] ?? 'Utilisateur';

$cm = new ClubManager($pdo, $userId);
$fm = new FriendManager($pdo, $userId);

$clubId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* =====================================================
   CAS 1 : PAS D'ID → LISTE DES CLUBS + INVITATIONS
   ===================================================== */
if ($clubId <= 0) {
    $ctx = kb_clubs_list_context($pdo, $userId, $cm);

    $pageTitle                = $ctx['pageTitle'];
    $message                  = $ctx['message'];
    $error                    = $ctx['error'];
    $clubInvites              = $ctx['clubInvites'];
    $clubs                    = $ctx['clubs'];
    $unreadClubMessagesByClub = $ctx['unreadClubMessagesByClub'];

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

/* =====================================================
   CAS 2 : ID PRÉSENT → DÉTAIL DU CLUB
   ===================================================== */

$ctx = kb_club_detail_context($pdo, $userId, $clubId, $cm, $fm);

if (!empty($ctx['redirect'])) {
    header('Location: ' . $ctx['redirect']);
    exit;
}

$pageTitle             = $ctx['pageTitle'];
$club                  = $ctx['club'];
$deleteError           = $ctx['deleteError'];
$leaveError            = $ctx['leaveError'];
$messageError          = $ctx['messageError'];
$unreadMessagesThisClub= (int)$ctx['unreadMessagesThisClub'];

$members          = $ctx['members'];
$memberIds        = $ctx['memberIds'];
$memberCount      = (int)$ctx['memberCount'];
$friends          = $ctx['friends'];
$invitableFriends = $ctx['invitableFriends'];
$clubBooks        = $ctx['clubBooks'];
$clubBooksCount   = (int)$ctx['clubBooksCount'];
$userLibrary      = $ctx['userLibrary'];
$messages         = $ctx['messages'];

include __DIR__ . '/include/header.inc.php';

if (!$club) {
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
?>

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

    <!-- Layout principal -->
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
          <button type="button" class="club-nav-link is-active" data-panel="members">
            <span class="club-nav-label">Membres</span>
            <span class="club-nav-badge"><?= $memberCount ?></span>
          </button>

          <button type="button" class="club-nav-link" data-panel="books">
            <span class="club-nav-label">Livres</span>
            <span class="club-nav-badge"><?= $clubBooksCount ?></span>
          </button>

          <button type="button" class="club-nav-link" data-panel="messages">
            <span class="club-nav-label">Messages</span>
          </button>
        </nav>
      </aside>

      <!-- CONTENU VARIABLE À DROITE -->
      <div class="club-content">

        <!-- PANEL MEMBRES -->
        <section id="panel-members" class="club-panel is-active" aria-label="Membres du club">
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
        <section id="panel-books" class="club-panel" aria-label="Livres du club">
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
        <section id="panel-messages" class="club-panel" aria-label="Messages du club">
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
