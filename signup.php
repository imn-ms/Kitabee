<?php
// signup.php
header('Content-Type: text/plain; charset=UTF-8');

// Charger la connexion PDO
require_once __DIR__ . '/secret/database.php';

// Récupération des données POST
$login = trim($_POST['login'] ?? '');
$password = trim($_POST['password'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($login === '' || $password === '' || $email === '') {
    echo 'Veuillez remplir tous les champs';
    exit;
}

// Vérifier que l'email est valide
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'Email invalide';
    exit;
}

// Vérifier si le login existe déjà
$stmt = $pdo->prepare('SELECT id FROM users WHERE login = :login LIMIT 1');
$stmt->execute([':login' => $login]);
if ($stmt->fetch()) {
    echo 'Pseudo déjà pris';
    exit;
}

// Vérifier si l'email existe déjà
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    echo 'Email déjà utilisé';
    exit;
}

// Hacher le mot de passe
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insérer l'utilisateur
$stmt = $pdo->prepare('INSERT INTO users (login, email, password) VALUES (:login, :email, :password)');
$ok = $stmt->execute([
    ':login' => $login,
    ':email' => $email,
    ':password' => $hashedPassword
]);

if ($ok) {
    echo 'Inscription réussie !';
} else {
    echo 'Impossible d\'enregistrer le compte';
}
