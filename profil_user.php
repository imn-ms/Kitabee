<?php
/**
 * profil_user.php — Page de gestion du profil utilisateur
 *
 * Rôle :
 * - Affiche et permet de modifier les informations personnelles (login, email).
 * - Permet de choisir un avatar via avatar_choice (ou initiale si none).
 * - Permet de modifier le mot de passe.
 * - Permet la suppression définitive du compte avec confirmation par mot de passe.
 *
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=profil_user.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/BadgeManager.php';
require_once __DIR__ . '/include/functions.inc.php';

$userId = (int)$_SESSION['user'];

// Traitement centralisé
$res = kb_profile_handle($pdo, $userId);

// Si suppression compte OK -> détruire session ici puis redirect
if (!empty($res['redirect'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $res['redirect']);
    exit;
}

$user     = $res['user'] ?? null;
$message  = $res['message'] ?? null;
$error    = $res['error'] ?? null;

$hasAvatar = (bool)($res['hasAvatar'] ?? false);
$avatarUrl = $res['avatarUrl'] ?? null;

if (!$user) {
    die("Utilisateur introuvable.");
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
            <!-- fallback visuel : première lettre -->
            <div style="width:70px; height:70px; border-radius:50%; background:#0078ff;
                        display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
              <?= strtoupper(substr($user['login'], 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="flex:1;">
          <p style="margin:0 0 8px;"><strong>Choisissez votre avatar :</strong></p>

          <?php $currentAvatarChoice = $user['avatar_choice'] ?? null; ?>

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
