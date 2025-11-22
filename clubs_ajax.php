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
            // 🔔 On n'ajoute plus directement le membre !
            // On crée une notification d'invitation au club
            if ($clubId <= 0 || $targetUserId <= 0) {
                $response['ok']    = false;
                $response['error'] = 'invalid_parameters';
                break;
            }

            // Vérifier que l'utilisateur actuel est bien owner du club
            if (!$cm->isOwner($clubId)) {
                $response['ok']    = false;
                $response['error'] = 'not_owner';
                break;
            }

            // Récupérer les infos du club pour le nom
            $club = $cm->getClub($clubId);
            if (!$club) {
                $response['ok']    = false;
                $response['error'] = 'club_not_found';
                break;
            }

            $clubName = $club['name'] ?? 'Club de lecture';

            // Créer une notification type "club_invite"
            $stmtNotif = $pdo->prepare("
                INSERT INTO notifications (user_id, from_user_id, club_id, type, content, is_read, created_at)
                VALUES (:uid, :from_uid, :club_id, 'club_invite', :content, 0, NOW())
            ");
            $okNotif = $stmtNotif->execute([
                ':uid'      => $targetUserId,                      // destinataire
                ':from_uid' => $userId,                            // qui invite
                ':club_id'  => $clubId,
                ':content'  => "Vous avez été invité(e) à rejoindre le club : " . $clubName,
            ]);

            if ($okNotif) {
                $response['ok'] = true;
            } else {
                $response['ok']    = false;
                $response['error'] = 'cannot_create_notification';
            }
            break;

        case 'remove_member':
            // Retirer un membre (owner uniquement)
            if ($clubId <= 0 || $targetUserId <= 0) {
                $response['ok']    = false;
                $response['error'] = 'invalid_parameters';
                break;
            }

            $response['ok'] = $cm->removeMember($clubId, $targetUserId);
            if (!$response['ok']) {
                $response['error'] = 'cannot_remove_member';
            }
            break;

        case 'add_book':
            // Ajouter un livre par son google_book_id
            $googleBookId = trim($googleBookId);
            if ($clubId <= 0 || $googleBookId === '') {
                $response['ok']    = false;
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
            if ($clubId <= 0 || $googleBookId === '') {
                $response['ok']    = false;
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
