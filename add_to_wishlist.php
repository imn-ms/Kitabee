<?php
/**
 * add_to_wishlist.php
 *
 * Script de traitement permettant d’ajouter un livre à la wishlist
 * personnelle d’un utilisateur connecté.
 *
 * Cette page :
 * - vérifie que l’utilisateur est authentifié,
 * - récupère l’identifiant du livre via POST,
 * - retire le livre de la bibliothèque si nécessaire,
 * - ajoute le livre à la wishlist,
 * - déclenche l’attribution éventuelle de badges,
 * - redirige vers la page de détail du livre.
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

session_start();

require __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

$userId = (int)$_SESSION['user'];
$bookId = $_POST['book_id'] ?? '';

$result = kb_add_book_to_wishlist(
    $pdo,
    $userId,
    $bookId,
    $GOOGLE_API_KEY ?? ''
);

if (!$result['success']) {
    header('Location: index.php');
    exit;
}

header('Location: detail.php?id=' . urlencode($result['bookId']));
exit;
