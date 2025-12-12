<?php
/**
 * friends_ajax.php — Endpoint AJAX (JSON) pour la gestion des amis
 *
 * Actions supportées (POST):
 * - action=send    & user_id=ID  -> envoie une demande d'ami
 * - action=accept  & user_id=ID  -> accepte une demande reçue
 * - action=decline & user_id=ID  -> refuse une demande reçue
 * - action=remove  & user_id=ID  -> supprime un ami
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/secret/database.php';
require_once __DIR__ . '/include/functions.inc.php';

$response = kb_handle_friends_ajax($pdo);

echo json_encode($response);
