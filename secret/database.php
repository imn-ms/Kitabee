<?php
// ======= CONNEXION À LA BASE DE DONNÉES =======
$host = 'mysql-kitabee.alwaysdata.net';
$dbname = 'kitabee_db';
$username = 'kitabee';
$password = 'Imane2005';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// ======= CONFIGURATION SMTP (AlwaysData) =======
$mail_host = 'smtp-kitabee.alwaysdata.net';
$mail_port = 587;
$mail_username = 'kitabee@alwaysdata.net';
$mail_from = 'kitabee@alwaysdata.net';
$mail_from_name = 'Kitabee';
$site_base_url = 'https://kitabee.alwaysdata.net';

// ======= IMPORT DE PHPMailer =======
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

/**
 * Envoi d’un e-mail via PHPMailer (SMTP AlwaysData)
 * @param string $to - destinataire
 * @param string $subject - sujet du mail
 * @param string $body - contenu du message
 * @param string $fromName - nom d’expéditeur (par défaut : Kitabee)
 * @return bool
 */
function sendMail($to, $subject, $body, $fromName = 'Kitabee') {
    global $mail_host, $mail_port, $mail_username, $mail_password, $mail_from;

    $mail = new PHPMailer(true);
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = $mail_host;
        $mail->SMTPAuth = true;
        $mail->Username = $mail_username;
        $mail->Password = $mail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mail_port;

        // Expéditeur
        $mail->setFrom($mail_from, $fromName);
        $mail->addReplyTo($mail_from, $fromName);

        // Destinataire
        $mail->addAddress($to);

        // Contenu du message
        $mail->isHTML(false); // texte brut
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
        return false;
    }
}
?>
