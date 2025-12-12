<?php
/**
 * avatar.php
 *
 * Endpoint d’affichage d’avatar pour Kitabee.
 *
 * Cette page renvoie directement une image (ou un SVG) en fonction
 * du profil utilisateur :
 *
 * 1) Si l’utilisateur a choisi un avatar prédéfini (avatar_choice)
 *    et que celui-ci est autorisé -> renvoie l’image stockée dans /avatar.
 * 2) Sinon -> renvoie un avatar fallback en SVG contenant l’initiale du login,
 *    avec une couleur de fond déterminée de façon stable selon l’identifiant utilisateur.
 *
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

/**
 * Identifiant utilisateur reçu via GET.
 *
 * @var int
 */
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/**
 * Renvoie l’avatar (image ou SVG) et stoppe l’exécution via exit.
 */
kb_output_user_avatar($pdo, $userId);
