<?php
/**
 * signup.php — Endpoint d'inscription (réponse texte)
 *
 * Rôle :
 * - Reçoit login/email/password via POST.
 * - Crée l'utilisateur en base (vérifs + hash).
 * - Retourne une réponse en texte brut .
 */

header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

// Récupération des données POST
$login    = trim($_POST['login'] ?? '');
$password = trim($_POST['password'] ?? '');
$email    = trim($_POST['email'] ?? '');

$result = kb_signup_user($pdo, $login, $email, $password);

echo $result['message'];
exit;
