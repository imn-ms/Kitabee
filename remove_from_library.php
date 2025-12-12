<?php
/**
 * remove_from_library.php — Retire un livre de la bibliothèque de l'utilisateur.
 *
 * Rôle :
 * - Page protégée (utilisateur connecté).
 * - Récupère book_id depuis POST.
 * - Supprime la ligne correspondante dans user_library.
 * - Redirige vers bibliotheque.php.
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

// Suppression
kb_remove_from_library($pdo, $userId, (string)$bookId);

// Retour à la page bibliothèque
header('Location: bibliotheque.php');
exit;
