<?php
/**
 * cron.php – Exécution des tâches automatiques Kitabee
 * À appeler via un cron (hébergeur).
 */

require __DIR__ . '/secret/database.php'; // ton PDO

echo "Cron Kitabee lancé : " . date('Y-m-d H:i:s') . "\n";

/* =====================================================
   1) Supprimer les messages de clubs de +24h
   ===================================================== */
try {
    $pdo->exec("
        DELETE FROM book_club_messages
        WHERE created_at < NOW() - INTERVAL 1 DAY
    ");
    echo "Messages de +24h supprimés.\n";
} catch (Exception $e) {
    echo "Erreur messages : " . $e->getMessage() . "\n";
}


/* =====================================================
   2) Supprimer les comptes non activés depuis +30 jours
   ===================================================== */
try {
    $pdo->exec("
        DELETE FROM users
        WHERE is_active = 0
          AND created_at < NOW() - INTERVAL 30 DAY
    ");
    echo "Comptes inactifs supprimés.\n";
} catch (Exception $e) {
    echo "Erreur comptes : " . $e->getMessage() . "\n";
}


/* =====================================================
   3) Supprimer les fichiers tmp de +24h
   ===================================================== */
$tmpPath = __DIR__ . '/tmp';

if (is_dir($tmpPath)) {
    foreach (glob($tmpPath . '/*') as $file) {
        if (is_file($file) && filemtime($file) < time() - 86400) { // 86400 = 1 jour
            unlink($file);
        }
    }
    echo "Fichiers tmp nettoyés.\n";
}


/* =====================================================
   4) Suppression des tokens expirés
   ===================================================== */
try {
    $pdo->exec("
        UPDATE users
        SET reset_token = NULL,
            reset_token_expires = NULL
        WHERE reset_token_expires < NOW()
    ");
    echo "Tokens expirés nettoyés.\n";
} catch (Exception $e) {
    echo "Erreur tokens : " . $e->getMessage() . "\n";
}

echo "Cron terminé.\n";
