<?php
// profil_user.php — page de gestion du profil
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    header('Location: connexion.php?redirect=profil_user.php');
    exit;
}

require_once __DIR__ . '/secret/database.php';

$userId = $_SESSION['user'];
$currentLogin = $_SESSION['login'] ?? '';

// Récupérer les infos actuelles de l'utilisateur 
$stmt = $pdo->prepare("SELECT login, email, avatar, password FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable.");
}

$message = null;
$error = null;

// --- Traitement suppression de compte ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $passwordDelete = $_POST['password_delete'] ?? '';

    if ($passwordDelete === '') {
        $error = "Veuillez saisir votre mot de passe pour confirmer la suppression.";
    } elseif (!password_verify($passwordDelete, $user['password'])) {
        $error = "Mot de passe incorrect. Suppression annulée.";
    } else {
        // suppression de l’avatar 
        if (!empty($user['avatar'])) {
            $avatarPath = __DIR__ . '/uploads/avatars/' . $user['avatar'];
            if (is_file($avatarPath)) {
                @unlink($avatarPath);
            }
        }

        // Suppression du compte
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $okDel = $stmtDel->execute([':id' => $userId]);

        if ($okDel) {
            // Déconnexion propre
            session_unset();
            session_destroy();
            header('Location: index.php?account_deleted=1');
            exit;
        } else {
            $error = "Impossible de supprimer votre compte pour le moment. Réessayez plus tard.";
        }
    }
}

// --- Traitement mise à jour du profil ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {
    // 1. Récupérer les champs
    $newLogin = trim($_POST['login'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newPasswordConfirm = $_POST['password_confirm'] ?? '';

    // Vérifs de base
    if ($newLogin === '' || $newEmail === '') {
        $error = "L'identifiant et l'e-mail ne peuvent pas être vides.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e-mail n'est pas valide.";
    } else {
        // Vérifier si le nouveau login est déjà pris par un autre utilisateur
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :login AND id <> :id LIMIT 1");
        $stmt->execute([':login' => $newLogin, ':id' => $userId]);
        if ($stmt->fetch()) {
            $error = "Cet identifiant est déjà utilisé par un autre compte.";
        }

        // Vérifier si le nouvel e-mail est déjà pris
        if (!$error) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
            $stmt->execute([':email' => $newEmail, ':id' => $userId]);
            if ($stmt->fetch()) {
                $error = "Cet e-mail est déjà utilisé par un autre compte.";
            }
        }
    }

    // 4. Gestion de l'avatar
    $avatarFilename = $user['avatar']; // on garde l'ancien par défaut

    if (!$error && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            // Vérifier la taille (2 Mo max)
            if ($file['size'] > 2 * 1024 * 1024) {
                $error = "L'image est trop lourde (max 2 Mo).";
            } else {
                // Types autorisés
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];

                $detectedType = mime_content_type($file['tmp_name']);
                if (!array_key_exists($detectedType, $allowedTypes)) {
                    $error = "Format d'image non autorisé. Utilisez JPG, PNG, GIF ou WebP.";
                } else {
                    // Dossiers cibles
                    $uploadRoot = __DIR__ . '/uploads';
                    $uploadDir  = __DIR__ . '/uploads/avatars';

                    // Créer /uploads si besoin
                    if (!is_dir($uploadRoot)) {
                        mkdir($uploadRoot, 0775, true);
                    }
                    // Créer /uploads/avatars si besoin
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    // Vérifier que le dossier existe bien
                    if (!is_dir($uploadDir)) {
                        $error = "Le dossier d’upload n’existe pas côté serveur : " . $uploadDir;
                    } else {
                        // Générer un nom unique
                        $ext = $allowedTypes[$detectedType];
                        $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . '/' . $newName;

                        // Vérifier qu'on peut écrire dedans
                        if (!is_writable($uploadDir)) {
                            $error = "Le dossier d’upload existe mais n’est pas accessible en écriture : " . $uploadDir;
                        } else {
                            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                                $error = "Erreur lors du téléversement de l'avatar. Chemin tenté : " . $destPath;
                            } else {
                                $avatarFilename = $newName;
                            }
                        }
                    }
                }
            }
        } else {
            $error = "Erreur lors de l'envoi du fichier (code " . $file['error'] . ").";
        }
    }

    // 5. Si tout est bon jusque-là, on met à jour
    if (!$error) {
        // Cas 1 : mot de passe changé
        if ($newPassword !== '' || $newPasswordConfirm !== '') {
            if ($newPassword !== $newPasswordConfirm) {
                $error = "Les deux mots de passe ne correspondent pas.";
            } elseif (strlen($newPassword) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } else {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET login = :login, email = :email, password = :password, avatar = :avatar
                    WHERE id = :id
                ");
                $ok = $stmt->execute([
                    ':login' => $newLogin,
                    ':email' => $newEmail,
                    ':password' => $hashed,
                    ':avatar' => $avatarFilename,
                    ':id' => $userId
                ]);
            }
        } else {
            // Cas 2 : on ne touche pas au mot de passe
            $stmt = $pdo->prepare("
                UPDATE users
                SET login = :login, email = :email, avatar = :avatar
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                ':login' => $newLogin,
                ':email' => $newEmail,
                ':avatar' => $avatarFilename,
                ':id' => $userId
            ]);
        }

        if (!$error) {
            if (!empty($ok)) {
                // mettre à jour la session login si changé
                $_SESSION['login'] = $newLogin;
                $_SESSION['avatar'] = $avatarFilename;
                $message = "Profil mis à jour avec succès 👍";

                // recharger les infos
                $user['login'] = $newLogin;
                $user['email'] = $newEmail;
                $user['avatar'] = $avatarFilename;
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

    <form method="post" enctype="multipart/form-data" style="display:grid; gap:14px; background:#fff; padding:20px; border-radius:14px; border:1px solid #e5e7eb;">
      
      <!-- Avatar -->
      <div style="display:flex; align-items:center; gap:14px;">
        <?php if (!empty($user['avatar'])): ?>
          <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" style="width:70px; height:70px; border-radius:50%; object-fit:cover;">
        <?php else: ?>
          <div style="width:70px; height:70px; border-radius:50%; background:#0078ff; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
            <?= strtoupper(substr($user['login'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <label for="avatar">Changer d’avatar :</label><br>
          <input type="file" name="avatar" id="avatar" accept="image/*">
          <p style="font-size: .8rem; color:#666;">Max 2 Mo. Formats autorisés : jpg, png, gif, webp.</p>
        </div>
      </div>

      <div>
        <label for="login">Identifiant</label>
        <input id="login" name="login" type="text" required value="<?= htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div>
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <hr>

      <p style="font-size:.85rem; color:#666;">Laissez les champs ci-dessous vides si vous ne souhaitez pas modifier le mot de passe.</p>

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

    <!--suppression du compte -->
    <div style="margin-top:30px; padding:16px; border-radius:14px; border:1px solid #fecaca; background:#fef2f2;">
      <h2 style="margin-top:0; color:#b91c1c;">Supprimer mon compte</h2>
      <p style="font-size:.9rem; color:#7f1d1d;">
        Cette action est <strong>définitive</strong> : toutes vos données liées à ce compte pourront être supprimées.
      </p>

      <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.');" style="display:grid; gap:10px; max-width:420px;">
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
