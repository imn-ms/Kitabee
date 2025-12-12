<?php
/**
 * cron.php – Exécution des tâches automatiques Kitabee
 *
 * Ce script est destiné à être exécuté via un cron (hébergeur) afin de
 * réaliser des tâches de maintenance :
 * - purge des messages de clubs (> 24h)
 * - suppression des comptes non activés (> 24h)
 * - nettoyage des fichiers temporaires (> 24h)
 * - suppression des tokens de réinitialisation expirés
 *
 * Auteur : TRIOLLET-PEREIRA Odessa
 * Projet : Kitabee
 */

require __DIR__ . '/secret/database.php';
require __DIR__ . '/include/functions.inc.php';

$tmpPath = __DIR__ . '/tmp';

$logs = kb_run_cron_tasks($pdo, $tmpPath);

foreach ($logs as $line) {
    echo $line . "\n";
}
