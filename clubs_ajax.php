<?php
// clubs_ajax.php — actions AJAX pour les clubs de lecture
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
require_once __DIR__ . '/classes/ClubManager.php';

$userId = (int) $_SESSION['user'];
$cm     = new ClubManager($pdo, $userId);

// Récupération des paramètres envoyés en POST
$action       = $_POST['action'] ?? '';
$clubId       = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
$targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$googleBookId = $_POST['google_book_id'] ?? '';

$response = ['ok' => false];

try {
    switch ($action) {
        case 'add_member':
            // Ajouter un membre au club (owner uniquement)
            $response['ok'] = $cm->addMember($clubId, $targetUserId);
            if (!$response['ok']) {
                $response['error'] = 'cannot_add_member';
            }
            break;

        case 'remove_member':
            // Retirer un membre (owner uniquement)
            $response['ok'] = $cm->removeMember($clubId, $targetUserId);
            if (!$response['ok']) {
                $response['error'] = 'cannot_remove_member';
            }
            break;

        case 'add_book':
            // Ajouter un livre par son google_book_id
            $googleBookId = trim($googleBookId);
            if ($googleBookId === '') {
                $response['ok'] = false;
                $response['error'] = 'missing_google_book_id';
            } else {
                $response['ok'] = $cm->addBook($clubId, $googleBookId);
                if (!$response['ok']) {
                    $response['error'] = 'cannot_add_book';
                }
            }
            break;

        case 'remove_book':
            // Retirer un livre du club
            $googleBookId = trim($googleBookId);
            if ($googleBookId === '') {
                $response['ok'] = false;
                $response['error'] = 'missing_google_book_id';
            } else {
                $response['ok'] = $cm->removeBook($clubId, $googleBookId);
                if (!$response['ok']) {
                    $response['error'] = 'cannot_remove_book';
                }
            }
            break;

        default:
            $response['ok']    = false;
            $response['error'] = 'unknown_action';
    }
} catch (Throwable $e) {
    $response['ok']    = false;
    $response['error'] = 'exception';

}

echo json_encode($response);
