<?php
session_start();
require __DIR__ . '/private/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

if (empty($bookId)) {
    header('Location: bibliotheque.php');
    exit;
}

// Supprimer le livre de la bibliothèque
$stmt = $pdo->prepare("
    DELETE FROM user_library
    WHERE user_id = :uid AND google_book_id = :bid
");
$stmt->execute([
    ':uid' => $userId,
    ':bid' => $bookId,
]);

// Retour à la page bibliothèque
header('Location: bibliotheque.php');
exit;
