<?php
/**
 * mark_as_read.php
 *
 * Rôle :
 * - Déplacer un livre de la wishlist vers la bibliothèque utilisateur.
 * - Utilise une fonction centralisée définie dans functions.inc.php.
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

require __DIR__ . '/secret/config.php';
require __DIR__ . '/include/functions.inc.php';

/* Sécurité : utilisateur connecté */
if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

/* Validation */
if ($bookId === '') {
    header('Location: bibliotheque.php');
    exit;
}

/* Déplacement wishlist → bibliothèque */
kb_move_book_wishlist_to_library($pdo, $userId, $bookId);

/* Redirection */
header('Location: bibliotheque.php');
exit;
