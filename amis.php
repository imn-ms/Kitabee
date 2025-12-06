<?php
// amis.php — Gestion des amis (recherche, demandes, liste d'amis)
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=amis.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';

$userId    = (int)$_SESSION['user'];
$login     = $_SESSION['login'] ?? 'Utilisateur';
$pageTitle = "Mes amis – Kitabee";

$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1) Envoyer une demande d'ami
    if ($action === 'send_request') {
        $targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

        if ($targetId <= 0 || $targetId === $userId) {
            $error = "Utilisateur invalide.";
        } else {
            // Vérifier si une relation existe déjà
            $stmt = $pdo->prepare("
                SELECT id, status
                FROM user_friends
                WHERE (user_id = :me AND friend_id = :them)
                   OR (user_id = :them AND friend_id = :me)
                LIMIT 1
            ");
            $stmt->execute([
                ':me'   => $userId,
                ':them' => $targetId,
            ]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['status'] === 'pending') {
                    $error = "Une demande d'ami est déjà en attente entre vous.";
                } elseif ($existing['status'] === 'accepted') {
                    $error = "Vous êtes déjà amis avec cette personne.";
                } else {
                    $error = "Une relation existe déjà avec cette personne.";
                }
            } else {
                // Créer la demande
                $stmt = $pdo->prepare("
                    INSERT INTO user_friends (user_id, friend_id, requested_by, status, created_at)
                    VALUES (:me, :them, :me, 'pending', NOW())
                ");
                $ok = $stmt->execute([
                    ':me'   => $userId,
                    ':them' => $targetId,
                ]);

                if ($ok) {
                    $message = "Demande d'ami envoyée ✔";
                } else {
                    $error = "Impossible d'envoyer la demande d'ami.";
                }
            }
        }
    }

    // 2) Accepter une demande reçue
    if ($action === 'accept_request') {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

        if ($requestId > 0) {
            // On vérifie que la demande m'est bien adressée
            $stmt = $pdo->prepare("
                UPDATE user_friends
                SET status = 'accepted'
                WHERE id = :id
                  AND friend_id = :me
                  AND status = 'pending'
            ");
            $ok = $stmt->execute([
                ':id' => $requestId,
                ':me' => $userId,
            ]);

            if ($ok && $stmt->rowCount() === 1) {
                $message = "Demande d'ami acceptée 👍";
            } else {
                $error = "Impossible d'accepter cette demande.";
            }
        }
    }

    // 3) Refuser une demande reçue
    if ($action === 'reject_request') {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

        if ($requestId > 0) {
            // On supprime la demande si elle m'est adressée
            $stmt = $pdo->prepare("
                DELETE FROM user_friends
                WHERE id = :id
                  AND friend_id = :me
                  AND status = 'pending'
            ");
            $ok = $stmt->execute([
                ':id' => $requestId,
                ':me' => $userId,
            ]);

            if ($ok && $stmt->rowCount() === 1) {
                $message = "Demande d'ami refusée.";
            } else {
                $error = "Impossible de refuser cette demande.";
            }
        }
    }

    // 4) Supprimer un ami (relation acceptée)
    if ($action === 'remove_friend') {
        $friendId = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;

        if ($friendId > 0 && $friendId !== $userId) {
            $stmt = $pdo->prepare("
                DELETE FROM user_friends
                WHERE ((user_id = :me AND friend_id = :friend)
                    OR (user_id = :friend AND friend_id = :me))
                  AND status = 'accepted'
            ");
            $ok = $stmt->execute([
                ':me'     => $userId,
                ':friend' => $friendId,
            ]);

            if ($ok && $stmt->rowCount() > 0) {
                $message = "Cet ami a bien été supprimé.";
            } else {
                $error = "Impossible de supprimer cet ami.";
            }
        } else {
            $error = "Ami invalide.";
        }
    }
}

// RECHERCHE D'UTILISATEURS
$searchTerm    = trim($_GET['q'] ?? '');
$searchResults = [];

if ($searchTerm !== '') {
    $stmt = $pdo->prepare("
        SELECT id, login, avatar, email
        FROM users
        WHERE (login LIKE :term OR email LIKE :term)
          AND id <> :me
        ORDER BY login ASC
        LIMIT 20
    ");
    $stmt->execute([
        ':term' => '%' . $searchTerm . '%',
        ':me'   => $userId,
    ]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// DEMANDES REÇUES
$stmt = $pdo->prepare("
    SELECT uf.id, uf.user_id, uf.created_at,
           u.login, u.avatar
    FROM user_friends uf
    JOIN users u ON u.id = uf.user_id
    WHERE uf.friend_id = :me
      AND uf.status = 'pending'
    ORDER BY uf.created_at DESC
");
$stmt->execute([':me' => $userId]);
$incomingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// MES AMIS (relations acceptées)
$stmt = $pdo->prepare("
    SELECT
      CASE 
        WHEN uf.user_id = :me THEN uf.friend_id
        ELSE uf.user_id
      END AS friend_id,
      u.login,
      u.avatar
    FROM user_friends uf
    JOIN users u ON u.id = CASE 
                              WHEN uf.user_id = :me THEN uf.friend_id
                              ELSE uf.user_id
                           END
    WHERE (uf.user_id = :me OR uf.friend_id = :me)
      AND uf.status = 'accepted'
    ORDER BY u.login ASC
");
$stmt->execute([':me' => $userId]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                  <?php if (!empty($u['avatar'])): ?>
                    <!-- avatar depuis BDD via avatar.php -->
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
                <?php if (!empty($req['avatar'])): ?>
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
                <?php if (!empty($f['avatar'])): ?>
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
