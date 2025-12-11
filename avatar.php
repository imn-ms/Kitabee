<?php
// avatar.php — renvoie l'avatar d'un utilisateur
// 1) Si avatar_choice est défini -> fichier dans /avatar
// 2) Sinon -> avatar avec la première lettre du login (SVG)

require_once __DIR__ . '/secret/database.php';

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

// On récupère login + avatar_choice
$stmt = $pdo->prepare("
    SELECT login, avatar_choice
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('User not found');
}

$login         = $row['login'] ?? '';
$avatarChoice  = $row['avatar_choice'] ?? null;

// =====================
// 1) Avatars prédéfinis
// =====================
$allowedAvatars = ['candice', 'genie', 'jerry', 'snoopy', 'belle', 'naruto'];

if ($avatarChoice && in_array($avatarChoice, $allowedAvatars, true)) {
    $baseDir  = __DIR__ . '/avatar/';
    $filePath = $baseDir . $avatarChoice . '.JPG';

    if (is_file($filePath)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    // si le fichier a été supprimé par erreur, on tombera sur l'initiale
}

// ===========================
// 2) Fallback : initiale pseudo
// ===========================

// Initiale en UTF-8 (gère les pseudos type "Élodie")
if ($login === '') {
    $initial = 'U'; // U comme "User" si vraiment rien
} else {
    $initial = mb_strtoupper(mb_substr($login, 0, 1, 'UTF-8'), 'UTF-8');
}

// Couleurs de fond possibles
$colors = [
    '#F97373', // rouge léger
    '#FACC15', // jaune
    '#4ADE80', // vert
    '#38BDF8', // bleu
    '#A855F7', // violet
    '#F97316', // orange
];

// couleur stable en fonction de l'id utilisateur
$bgColor = $colors[$userId % count($colors)];

// SVG simple, carré 120x120, lettre centrée
$svg = '<?xml version="1.0" encoding="UTF-8"?>'
    . '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
    . '<rect width="120" height="120" rx="24" ry="24" fill="' . $bgColor . '"/>'
    . '<text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" '
    . 'font-family="system-ui, -apple-system, BlinkMacSystemFont, sans-serif" '
    . 'font-size="64" fill="#ffffff">'
    . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
    . '</text>'
    . '</svg>';

header('Content-Type: image/svg+xml; charset=utf-8');
echo $svg;
exit;

