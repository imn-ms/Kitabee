<?php
/**
 * remove_from_wishlist.php — Retire un livre de la wishlist de l'utilisateur.
 *
 * Rôle :
 * - Vérifie que l'utilisateur est connecté.
 * - Récupère le Google Book ID depuis POST.
 * - Supprime le livre de la table user_wishlist.
 * - Redirige vers la page bibliothèque.
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/secret/config.php';
require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

if (empty($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

if (trim($bookId) === '') {
    header('Location: bibliotheque.php');
    exit;
}

// Suppression via fonction dédiée
kb_remove_from_wishlist($pdo, $userId, (string)$bookId);

// Retour à la bibliothèque
header('Location: bibliotheque.php');
exit;
