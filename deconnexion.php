<?php
// deconnexion.php — détruit la session puis redirige
header('Content-Type: text/html; charset=UTF-8');
session_start();

// On vide et détruit la session proprement
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

// redirection directe vers la connexion
header('Location: connexion.php');
exit;
