<?php
session_start();
require __DIR__ . '/private/config.php'; // donne $pdo + clé API + connexion

// 1. vérifier qu'on est connecté
if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = $_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

// 2. vérifier qu'on a bien un id de livre
if (empty($bookId)) {
    // pas d'id -> on retourne à l'accueil (ou tu mets un message)
    header('Location: index.php');
    exit;
}

// 3. insérer dans la table
$stmt = $pdo->prepare("
    INSERT IGNORE INTO user_library (user_id, google_book_id)
    VALUES (:uid, :bid)
");
$stmt->execute([
    ':uid' => $userId,
    ':bid' => $bookId
]);

// 4. revenir sur la page du livre
header('Location: detail.php?id=' . urlencode($bookId));
exit;

