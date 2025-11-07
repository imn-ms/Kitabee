<?php
// check_login.php
header('Content-Type: text/plain; charset=UTF-8');

// Récupération du pseudo envoyé via POST
$login = trim($_POST['login'] ?? '');
if ($login === '') {
    echo 'KO';
    exit;
}

// Chargement des utilisateurs depuis le CSV
$csvFile = __DIR__ . '/secret/password.csv';
$delimiter = ';';
$users = [];

if (file_exists($csvFile) && ($handle = fopen($csvFile, 'r')) !== false) {
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($data) >= 2) {
            $u = trim($data[0]);
            if ($u !== '') $users[$u] = true;
        }
    }
    fclose($handle);
}

// Vérification
if (array_key_exists($login, $users)) {
    echo 'TAKEN'; // pseudo déjà pris
} else {
    echo 'OK'; // pseudo disponible
}
