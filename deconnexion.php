<?php
/**
 * deconnexion.php — Déconnexion utilisateur
 *
 * Page technique chargée de :
 * - détruire la session utilisateur
 * - supprimer le cookie de session
 * - rediriger vers la page de connexion
 *
 * Auteur : MOUSSAOUI Imane
 * Projet : Kitabee
 */

header('Content-Type: text/html; charset=UTF-8');
session_start();

require_once __DIR__ . '/include/functions.inc.php';

// Déconnexion propre
kb_logout_user();

// Redirection vers la page de connexion
header('Location: connexion.php');
exit;
