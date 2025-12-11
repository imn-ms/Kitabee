<?php
session_start();
require __DIR__ . '/secret/config.php';

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

// 1. Récupérer les infos du livre depuis la wishlist
$stmt = $pdo->prepare("
    SELECT title, authors, thumbnail
    FROM user_wishlist
    WHERE user_id = :uid AND google_book_id = :bid
    LIMIT 1
");
$stmt->execute([
    ':uid' => $userId,
    ':bid' => $bookId,
]);

$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    // Si le livre n'est pas trouvé dans la wishlist, on retourne à la bibliothèque
    header('Location: bibliotheque.php');
    exit;
}

$title   = $book['title'] ?? null;
$authors = $book['authors'] ?? null;
$thumb   = $book['thumbnail'] ?? null;

// 2. Supprimer de la wishlist
$stmt = $pdo->prepare("
    DELETE FROM user_wishlist
    WHERE user_id = :uid AND google_book_id = :bid
");
$stmt->execute([
    ':uid' => $userId,
    ':bid' => $bookId,
]);

// 3. Ajouter à la bibliothèque
$stmt = $pdo->prepare("
    INSERT IGNORE INTO user_library (user_id, google_book_id, title, authors, thumbnail, added_at)
    VALUES (:uid, :bid, :title, :authors, :thumb, NOW())
");
$stmt->execute([
    ':uid'     => $userId,
    ':bid'     => $bookId,
    ':title'   => $title,
    ':authors' => $authors,
    ':thumb'   => $thumb,
]);

// 4. Retour à la page bibliothèque (qui affiche aussi la wishlist)
header('Location: bibliotheque.php');
exit;
