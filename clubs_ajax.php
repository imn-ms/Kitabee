<?php
/**
 * clubs_ajax.php — actions AJAX pour les clubs de lecture
 *
 * Endpoint JSON utilisé par script.js pour exécuter des actions côté clubs :
 * - inviter un ami 
 * - retirer un membre
 * - ajouter / retirer un livre d'un club
 *
 * Accès :
 * - nécessite une session utilisateur active
 * - renvoie toujours du JSON (Content-Type: application/json)

 *
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

header('Content-Type: application/json; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    echo json_encode([
        'ok'    => false,
        'error' => 'not_authenticated'
    ]);
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

$userId = (int)$_SESSION['user'];

$response = kb_handle_clubs_ajax($pdo, $userId, $_POST);

echo json_encode($response);
