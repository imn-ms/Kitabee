<?php
/**
 * amis.php
 *
 * Page de gestion des amis du projet Kitabee.
 *
 * Fonctionnalités :
 * - recherche d’utilisateurs par login ou e-mail,
 * - envoi de demandes d’ami,
 * - acceptation / refus des demandes reçues,
 * - suppression d’amis,
 * - affichage de la liste d’amis et des demandes en attente.
 * 
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=amis.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

$userId    = (int)$_SESSION['user'];
$login     = $_SESSION['login'] ?? 'Utilisateur';
$pageTitle = "Mes amis – Kitabee";

/**
 * Initialisation de toutes les données nécessaires à l’affichage :
 * - message / erreur,
 * - résultats de recherche,
 * - demandes reçues,
 * - liste d’amis.
 */
$ctx = kb_friends_page_context($pdo, $userId);

$message          = $ctx['message'];
$error            = $ctx['error'];
$searchTerm       = $ctx['searchTerm'];
$searchResults    = $ctx['searchResults'];
$incomingRequests = $ctx['incomingRequests'];
$friends          = $ctx['friends'];

include __DIR__ . '/include/header.inc.php';
?>

<section class="section">
  <div class="container" style="max-width:900px;">

    <h1 class="section-title">Mes amis</h1>
    <p>Recherchez des utilisateurs, envoyez des demandes d’ami et gérez vos relations.</p>

    <?php if ($error): ?>
      <div class="card" style="padding:10px; border-left:4px solid #dc2626; margin:10px 0;">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="card" style="padding:10px; border-left:4px solid #16a34a; margin:10px 0;">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Barre de recherche -->
    <section class="card" style="padding:16px 18px; border-radius:14px; border:1px solid #e5e7eb; margin-bottom:18px;">
      <h2 style="margin-top:0; font-size:1.05rem;">Rechercher un utilisateur</h2>
      <form method="get" style="display:flex; gap:8px; flex-wrap:wrap;">
        <input
          type="text"
          name="q"
          value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="Pseudo ou e-mail"
          style="flex:1; min-width:220px;"
        >
        <button type="submit" class="btn btn-primary">Rechercher</button>
      </form>

      <?php if ($searchTerm !== ''): ?>
        <p style="margin-top:8px; font-size:.85rem; color:#6b7280;">
          Résultats pour « <?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?> » :
        </p>

        <?php if (!$searchResults): ?>
          <p style="margin-top:4px;">Aucun utilisateur trouvé.</p>
        <?php else: ?>
          <ul style="list-style:none; padding:0; margin-top:8px; display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($searchResults as $u): ?>
              <li style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:6px 8px; border-radius:10px; background:#f9fafb;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <?php if (!empty($u['avatar_choice'])): ?>
                    <!-- Avatar depuis avatar.php -->
                    <img src="avatar.php?id=<?= (int)$u['id'] ?>"
                         alt=""
                         style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                  <?php else: ?>
                    <div style="width:36px; height:36px; border-radius:50%; background:#5f7f5f; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                      <?= strtoupper(substr($u['login'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:.8rem; color:#6b7280;"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>
                <form method="post">
                  <input type="hidden" name="action" value="send_request">
                  <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn-primary">Ajouter en ami</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- Demandes d'amis reçues -->
    <section class="card" style="padding:16px 18px; border-radius:14px; border:1px solid #e5e7eb; margin-bottom:18px;">
      <h2 style="margin-top:0; font-size:1.05rem;">Demandes d’amis reçues</h2>

      <?php if (!$incomingRequests): ?>
        <p style="font-size:.9rem; color:#6b7280;">Aucune demande d'ami en attente.</p>
      <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($incomingRequests as $req): ?>
            <li style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:6px 8px; border-radius:10px; background:#fefce8;">
              <div style="display:flex; align-items:center; gap:8px;">
                <?php if (!empty($req['avatar_choice'])): ?>
                  <img src="avatar.php?id=<?= (int)$req['user_id'] ?>"
                       alt=""
                       style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                  <div style="width:36px; height:36px; border-radius:50%; background:#f59e0b; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?= strtoupper(substr($req['login'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($req['login'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div style="font-size:.8rem; color:#6b7280;">
                    Vous a envoyé une demande d'ami le
                    <?= htmlspecialchars(date('d/m/Y', strtotime($req['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                  </div>
                </div>
              </div>
              <div style="display:flex; gap:6px;">
                <form method="post">
                  <input type="hidden" name="action" value="accept_request">
                  <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                  <button type="submit" class="btn btn-primary">Accepter</button>
                </form>
                <form method="post">
                  <input type="hidden" name="action" value="reject_request">
                  <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                  <button type="submit" class="btn btn-ghost">Refuser</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- Liste de mes amis -->
    <section class="card" style="padding:16px 18px; border-radius:14px; border:1px solid #e5e7eb;">
      <h2 style="margin-top:0; font-size:1.05rem;">Mes amis</h2>

      <?php if (!$friends): ?>
        <p style="font-size:.9rem; color:#6b7280;">Vous n'avez pas encore d'amis. Envoyez une demande via la barre de recherche.</p>
      <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
          <?php foreach ($friends as $f): ?>
            <li style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 8px; border-radius:10px; background:#f9fafb;">
              <div style="display:flex; align-items:center; gap:8px;">
                <?php if (!empty($f['avatar_choice'])): ?>
                  <img src="avatar.php?id=<?= (int)$f['friend_id'] ?>"
                       alt=""
                       style="width:36px; height:36px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                  <div style="width:36px; height:36px; border-radius:50%; background:#3b82f6; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?= strtoupper(substr($f['login'], 0, 1)) ?>
                  </div>
                <?php endif; ?>
                <div style="font-weight:600;"><?= htmlspecialchars($f['login'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>

              <!-- Bouton supprimer ami -->
              <form method="post"
                    onsubmit="return confirm('Supprimer cet ami ?');"
                    style="margin-left:auto;">
                <input type="hidden" name="action" value="remove_friend">
                <input type="hidden" name="friend_id" value="<?= (int)$f['friend_id'] ?>">
                <button type="submit" class="btn btn-ghost" style="color:#dc2626;">
                  Supprimer
                </button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
