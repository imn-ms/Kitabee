<?php
/**
 * activation.php
 *
 * Page d’activation de compte utilisateur du site Kitabee.
 *
 * Cette page est accessible via un lien envoyé par e-mail lors de l’inscription.
 * Le lien contient un token unique permettant :
 * - de vérifier l’identité de l’utilisateur,
 * - de confirmer que le compte n’est pas déjà activé,
 * - d’activer définitivement le compte en base de données.
 *
 * La logique métier liée à l’activation est centralisée dans la fonction
 * `kb_activate_user_account()` située dans `include/functions.php`,
 * afin de garantir une meilleure séparation entre la logique applicative
 * et l’affichage.
 *
 * Auteur : MOUSSAOUI Imane 
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

/** @var string Titre de la page */
$pageTitle = "Activation du compte – Kitabee";

/**
 * Récupération du token d’activation transmis en paramètre GET.
 *
 * @var string
 */
$token = $_GET['token'] ?? '';

/**
 * Tentative d’activation du compte à partir du token.
 *
 * La fonction retourne un tableau contenant :
 * - success : bool
 * - message : string (message de succès)
 * - error   : string (message d’erreur)
 */
$result = kb_activate_user_account($pdo, $token);

/** @var string Message de succès */
$message = $result['message'];

/** @var string Message d’erreur */
$error   = $result['error'];

include __DIR__ . '/include/header.inc.php';
?>
<section class="section">
  <div class="container" style="max-width:640px;">
    <article class="card" style="padding:24px;">

      <?php if ($error): ?>
        <!-- Cas d’erreur : lien invalide ou compte déjà activé -->
        <h1>Activation impossible</h1>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="connexion.php">Aller à la connexion</a></p>

      <?php else: ?>
        <!-- Cas de succès : compte activé -->
        <h1>Compte activé</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="connexion.php">Se connecter</a></p>
      <?php endif; ?>

    </article>
  </div>
</section>

<?php include __DIR__ . '/include/footer.inc.php'; ?>
