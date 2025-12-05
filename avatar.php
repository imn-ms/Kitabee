<?php
// avatar.php — renvoie l'avatar d'un utilisateur (BLOB)
require_once __DIR__ . '/secret/database.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$stmt = $pdo->prepare("
    SELECT avatar, avatar_type
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['avatar'])) {
    http_response_code(404);
    exit('No avatar');
}

// En-tête MIME dynamique (cf. diapos images + script PHP)
header('Content-Type: ' . ($row['avatar_type'] ?: 'image/png'));
echo $row['avatar'];
