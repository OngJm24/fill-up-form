<?php
require 'vendor/autoload.php';
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    echo "Starting mail test...<br>";

    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER); // Send to yourself

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Connection Test";
    $mail->Body    = "If you see this, your SMTP configuration is working!";

    if($mail->send()) {
        echo "✅ SUCCESS! The email was sent to " . SMTP_USER . ". Please check your inbox and spam folder.";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $mail->ErrorInfo;
}
?>
