<?php
require __DIR__ . '/private/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = $_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

if (!$bookId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO user_wishlist (user_id, google_book_id) VALUES (:uid, :bid)");
$stmt->execute([
    ':uid' => $userId,
    ':bid' => $bookId
]);

header('Location: detail.php?id=' . urlencode($bookId));
exit;
