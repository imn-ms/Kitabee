<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

if (empty($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
}

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/classes/FriendManager.php';

$userId = (int)$_SESSION['user'];
$fm = new FriendManager($pdo, $userId);

$action = $_POST['action'] ?? '';
$otherId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

switch ($action) {
    case 'send':
        echo json_encode(['ok' => $fm->sendRequest($otherId)]);
        break;

    case 'accept':
        echo json_encode(['ok' => $fm->acceptRequest($otherId)]);
        break;

    case 'decline':
        echo json_encode(['ok' => $fm->declineRequest($otherId)]);
        break;

    case 'remove':
        echo json_encode(['ok' => $fm->removeFriend($otherId)]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'unknown_action']);
}
