<?php
/**
 * add_to_library.php
 *
 * Script de traitement permettant d’ajouter un livre à la bibliothèque
 * personnelle d’un utilisateur connecté.
 *
 * Cette page :
 * - vérifie que l’utilisateur est authentifié,
 * - reçoit un identifiant Google Books via POST,
 * - supprime le livre de la wishlist si nécessaire,
 * - ajoute le livre à la bibliothèque utilisateur,
 * - déclenche l’attribution éventuelle de badges,
 * - redirige vers la page de détail du livre.
 *
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

session_start();

require __DIR__ . '/secret/config.php';
require_once __DIR__ . '/include/functions.inc.php';

/* =========================
   SÉCURITÉ : UTILISATEUR CONNECTÉ
   ========================= */
if (!isset($_SESSION['user'])) {
    header('Location: connexion.php');
    exit;
}

/** @var int Identifiant utilisateur */
$userId = (int)$_SESSION['user'];

/** @var string Identifiant Google Books */
$bookId = $_POST['book_id'] ?? '';

/* =========================
   TRAITEMENT PRINCIPAL
   ========================= */
$result = kb_add_book_to_library(
    $pdo,
    $userId,
    $bookId,
    $GOOGLE_API_KEY ?? ''
);

/* =========================
   REDIRECTION
   ========================= */
if (!$result['success']) {
    header('Location: index.php');
    exit;
}

header('Location: detail.php?id=' . urlencode($result['bookId']));
exit;
