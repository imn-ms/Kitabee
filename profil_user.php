<?php
// profil_user.php — page de gestion du profil (avatar basé sur avatar_choice dans la BD)
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=profil_user.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/BadgeManager.php';

$userId        = (int)$_SESSION['user'];
$currentLogin  = $_SESSION['login'] ?? '';

/**
 * Vérifie la robustesse du mot de passe :
 * - longueur >= 6
 * - au moins 1 majuscule
 * - au moins 1 minuscule
 * - au moins 1 chiffre
 * - au moins 1 caractère spécial
 */
function is_strong_password(string $pwd): bool {
    if (strlen($pwd) < 6) return false;
    if (!preg_match('/[A-Z]/', $pwd)) return false;
    if (!preg_match('/[a-z]/', $pwd)) return false;
    if (!preg_match('/[0-9]/', $pwd)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return false;
    return true;
}

// Récupérer les infos actuelles de l'utilisateur
$stmt = $pdo->prepare("
    SELECT login, email, avatar_choice, password
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$message = null;
$error   = null;

// Pour l'affichage de l'avatar (script dynamique)
$currentAvatarChoice = $user['avatar_choice'] ?? null;
$hasAvatar           = !empty($currentAvatarChoice); // true si un des 6 avatars est choisi
$avatarUrl           = $hasAvatar ? 'avatar.php?id=' . urlencode($userId) : null;

/* ---------------------------------------------
   SUPPRESSION DE COMPTE
----------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

    $passwordDelete = $_POST['password_delete'] ?? '';

    if ($passwordDelete === '') {
        $error = "Veuillez saisir votre mot de passe pour confirmer la suppression.";
    } elseif (!password_verify($passwordDelete, $user['password'])) {
        $error = "Mot de passe incorrect. Suppression annulée.";
    } else {

        // Suppression du compte
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $okDel   = $stmtDel->execute([':id' => $userId]);

        if ($okDel) {
            session_unset();
            session_destroy();
            header('Location: index.php?account_deleted=1');
            exit;
        } else {
            $error = "Impossible de supprimer votre compte pour le moment.";
        }
    }
}

/* ---------------------------------------------
   MISE À JOUR PROFIL (login, mail, avatar, mdp…)
----------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {

    $newLogin           = trim($_POST['login'] ?? '');
    $newEmail           = trim($_POST['email'] ?? '');
    $newPassword        = $_POST['password'] ?? '';
    $newPasswordConfirm = $_POST['password_confirm'] ?? '';

    // Gestion avatar_choice : un des 6 avatars, ou aucun (= initiale)
    $allowedAvatars = ['candice', 'genie', 'jerry', 'snoopy', 'belle', 'naruto'];

    $avatarChoicePost = $_POST['avatar_choice'] ?? ''; // peut être '', 'none' ou un des 6

    if ($avatarChoicePost === '' || $avatarChoicePost === 'none') {
        $avatarChoice = null; // utiliser la première lettre du pseudo
    } else {
        if (!in_array($avatarChoicePost, $allowedAvatars, true)) {
            $error = "Avatar choisi invalide.";
            $avatarChoice = null;
        } else {
            $avatarChoice = $avatarChoicePost;
        }
    }

    if ($newLogin === '' || $newEmail === '') {
        $error = "L'identifiant et l'e-mail ne peuvent pas être vides.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail n'est pas valide.";
    }

    // vérifier login unique
    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :login AND id <> :id LIMIT 1");
        $stmt->execute([':login' => $newLogin, ':id' => $userId]);
        if ($stmt->fetch()) {
            $error = "Cet identifiant est déjà utilisé par un autre compte.";
        }
    }

    // vérifier email unique
    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
        $stmt->execute([':email' => $newEmail, ':id' => $userId]);
        if ($stmt->fetch()) {
            $error = "Cet e-mail est déjà utilisé par un autre compte.";
        }
    }

    /* ---------------------------------------------------------
       MISE À JOUR SQL
    -----------------------------------------------------------*/
    if (!$error) {

        // Avec changement de mot de passe
        if ($newPassword !== '' || $newPasswordConfirm !== '') {

            if ($newPassword !== $newPasswordConfirm) {
                $error = "Les deux mots de passe ne correspondent pas.";
            } elseif (!is_strong_password($newPassword)) {
                $error = "Le mot de passe doit contenir au moins 6 caractères, avec au minimum une majuscule, une minuscule, un chiffre et un caractère spécial.";
            } else {

                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET login         = :login,
                        email         = :email,
                        password      = :password,
                        avatar_choice = :avatar_choice
                    WHERE id = :id
                ");

                $ok = $stmt->execute([
                    ':login'         => $newLogin,
                    ':email'         => $newEmail,
                    ':password'      => $hashed,
                    ':avatar_choice' => $avatarChoice,
                    ':id'            => $userId
                ]);
            }

        } else {
            // Sans modification du mot de passe
            $stmt = $pdo->prepare("
                UPDATE users
                SET login         = :login,
                    email         = :email,
                    avatar_choice = :avatar_choice
                WHERE id = :id
            ");

            $ok = $stmt->execute([
                ':login'         => $newLogin,
                ':email'         => $newEmail,
                ':avatar_choice' => $avatarChoice,
                ':id'            => $userId
            ]);
        }

        if (!$error) {
            if (!empty($ok)) {
                // maj session
                $_SESSION['login']      = $newLogin;
                $_SESSION['avatar_has'] = !empty($avatarChoice);

                // maj structure locale
                $user['login']         = $newLogin;
                $user['email']         = $newEmail;
                $user['avatar_choice'] = $avatarChoice;

                $currentAvatarChoice = $avatarChoice;
                $hasAvatar           = !empty($currentAvatarChoice);
                $avatarUrl           = $hasAvatar ? 'avatar.php?id=' . urlencode($userId) : null;

                $message = "Profil mis à jour avec succès 👍";

                // 🔥 Badges (profil / avatar, etc.)
                $badgeManager = new BadgeManager($pdo);
                $newBadges = $badgeManager->checkAllForUser($userId);

                if (!empty($newBadges)) {
                    $_SESSION['new_badges'] = $newBadges;
                }
            } else {
                $error = "Une erreur est survenue lors de la mise à jour.";
            }
        }
    }
}

$pageTitle = "Mon profil – Kitabee";
include __DIR__ . '/include/header.inc.php';
?>

<section class="section">
  <div class="container" style="max-width:800px;">
    <h1 class="section-title">Mon profil</h1>
    <p>Modifiez vos informations personnelles, votre mot de passe et votre avatar.</p>

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

    <form method="post"
          style="display:grid; gap:14px; background:#fff; padding:20px; border-radius:14px; border:1px solid #e5e7eb;">

      <!-- Avatar : aperçu + choix -->
      <div style="display:flex; align-items:flex-start; gap:14px; flex-wrap:wrap;">
        <div>
          <?php if ($hasAvatar && $avatarUrl): ?>
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Avatar"
                 style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
          <?php else: ?>
            <!-- fallback visuel : première lettre (même logique que avatar.php mais en CSS) -->
            <div style="width:70px; height:70px; border-radius:50%; background:#0078ff;
                        display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
              <?= strtoupper(substr($user['login'], 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="flex:1;">
          <p style="margin:0 0 8px;"><strong>Choisissez votre avatar :</strong></p>

          <?php
          $currentAvatarChoice = $user['avatar_choice'] ?? null;
          ?>

          <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
            <input type="radio" name="avatar_choice" value="none"
                   <?= $currentAvatarChoice === null ? 'checked' : '' ?>>
            <span>Utiliser la première lettre de mon pseudo</span>
          </label>

          <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:8px;">
            <?php
            $avatarOptions = [
                'candice' => 'Candice',
                'genie'   => 'Génie',
                'jerry'   => 'Jerry',
                'snoopy'  => 'Snoopy',
                'belle'   => 'Belle',
                'naruto'  => 'Naruto',
            ];

            foreach ($avatarOptions as $value => $label):
                $checked = ($currentAvatarChoice === $value) ? 'checked' : '';
            ?>
              <label style="display:flex; flex-direction:column; align-items:center; gap:4px;
                            cursor:pointer; font-size:.85rem;">
                <input type="radio" name="avatar_choice" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $checked ?>>
                <img src="avatar/<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>.JPG"
                     alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                     style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #e5e7eb;">
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <p style="font-size:.8rem; color:#666; margin-top:8px;">
            Si vous ne choisissez aucun avatar, une bulle avec la première lettre de votre identifiant sera utilisée.
          </p>
        </div>
      </div>

      <div>
        <label for="login">Identifiant</label>
        <input id="login" name="login" type="text" required
               value="<?= htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div>
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required
               value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <hr>

      <p style="font-size:.85rem; color:#666;">
        Laissez les champs ci-dessous vides si vous ne souhaitez pas modifier le mot de passe.<br>
        Le nouveau mot de passe doit contenir au moins 6 caractères, avec au minimum une majuscule, une minuscule, un chiffre et un caractère spécial.
      </p>

      <div>
        <label for="password">Nouveau mot de passe</label>
        <input id="password" name="password" type="password" autocomplete="new-password">
      </div>

      <div>
        <label for="password_confirm">Confirmer le mot de passe</label>
        <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password">
      </div>

      <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" type="submit">Enregistrer</button>
        <a class="btn btn-ghost" href="dashboard_user.php">⬅ Retour au tableau de bord</a>
      </div>
    </form>

    <!-- suppression du compte -->
    <div style="margin-top:30px; padding:16px; border-radius:14px; border:1px solid #fecaca; background:#fef2f2;">
      <h2 style="margin-top:0; color:#b91c1c;">Supprimer mon compte</h2>
      <p style="font-size:.9rem; color:#7f1d1d;">
        Cette action est <strong>définitive</strong> : toutes vos données liées à ce compte seront supprimées.
      </p>

      <form method="post"
            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.');"
            style="display:grid; gap:10px; max-width:420px;">
        <label for="password_delete">Pour confirmer, entrez votre mot de passe :</label>
        <input type="password" name="password_delete" id="password_delete" autocomplete="current-password" required>

        <button type="submit" name="delete_account" value="1"
                class="btn"
                style="background:#dc2626; color:#fff; border-color:#b91c1c;">
          Supprimer définitivement mon compte
        </button>
      </form>
    </div>

  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
