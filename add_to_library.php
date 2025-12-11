<?php
session_start();
require __DIR__ . '/secret/config.php'; 
require_once __DIR__ . '/classes/BadgeManager.php';

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

// 1. vérifier qu'on a bien un id de livre
if (empty($bookId)) {
    header('Location: index.php');
    exit;
}

// 1bis. S'il est dans la wishlist, on le retire
try {
    $stmt = $pdo->prepare("
        DELETE FROM user_wishlist
        WHERE user_id = :uid AND google_book_id = :bid
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':bid' => $bookId
    ]);
} catch (Throwable $e) {
    // tu peux loguer l'erreur si tu as un système de logs
}

// 2. Récupérer les infos du livre via l'API Google Books
$title    = null;
$authors  = null;
$thumb    = null;

try {
    if (!empty($GOOGLE_API_KEY)) {
        $url = "https://www.googleapis.com/books/v1/volumes/" . urlencode($bookId) . "?key=" . urlencode($GOOGLE_API_KEY);
    } else {
        $url = "https://www.googleapis.com/books/v1/volumes/" . urlencode($bookId);
    }

    $response = @file_get_contents($url);
    if ($response !== false) {
        $data  = json_decode($response, true);
        $info  = $data['volumeInfo'] ?? [];

        $title       = $info['title'] ?? null;
        $authorsArr  = $info['authors'] ?? [];
        $authors     = $authorsArr ? implode(', ', $authorsArr) : null;
        $thumb       = $info['imageLinks']['thumbnail'] ?? null;
    }
} catch (Throwable $e) {
    // silencieux, tu peux aussi loguer
}

// 3. insérer dans la table user_library
$stmt = $pdo->prepare("
    INSERT IGNORE INTO user_library (user_id, google_book_id, title, authors, thumbnail)
    VALUES (:uid, :bid, :title, :authors, :thumb)
");
$stmt->execute([
    ':uid'     => $userId,
    ':bid'     => $bookId,
    ':title'   => $title,
    ':authors' => $authors,
    ':thumb'   => $thumb,
]);

$badgeManager = new BadgeManager($pdo);
$newBadges = $badgeManager->checkAllForUser($userId);

if (!empty($newBadges)) {
    $_SESSION['new_badges'] = $newBadges;
}

// 4. revenir sur la page du livre
header('Location: detail.php?id=' . urlencode($bookId));
exit;
