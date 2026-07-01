<?php
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';
require_once 'includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ClubBetelgeuse2017@gmail.com';
    $mail->Password = '3AbfXG26xn8jwQPH';  // Cambiar
    $mail->SMTPSecure = false;         // Desactivar TLS
    $mail->SMTPAutoTLS = false;        // No intentar TLS automático
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    // Desactivar verificación SSL
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mail->setFrom('ClubBetelgeuse2017@gmail.com', 'Club Betelgeuse');
    $mail->addAddress('clujanmmeza@gmail.com', 'Admin');
    $mail->isHTML(true);
    $mail->Subject = 'Prueba Brevo';
    $mail->Body = '<h1>Funciona!</h1>';
    
    $mail->send();
    echo '✅ Correo enviado! Revisa tu bandeja.';
} catch (Exception $e) {
    echo "❌ Error: {$mail->ErrorInfo}";
}
?>