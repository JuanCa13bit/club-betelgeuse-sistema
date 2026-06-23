<?php
// =============================================
// PROBAR.PHP - Prueba de envío de correo con PHPMailer + Gmail SMTP
// =============================================

// 1. Importar las clases de PHPMailer (carga manual sin Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Incluir los archivos necesarios (debes tener la carpeta PHPMailer con estos archivos)
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// 3. Crear instancia de PHPMailer y configurar
$mail = new PHPMailer(true); // true habilita excepciones

try {
    // ---- Configuración del servidor SMTP de Gmail ----
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';                 // Servidor SMTP de Gmail
    $mail->SMTPAuth   = true;                             // Autenticación SMTP activada
    $mail->Username   = 'clubbetelgeuse2017@gmail.com';   // Tu dirección de Gmail (remitente)
    $mail->Password   = 'sgvy jpaj blqg ircw';         // ⚠️ REEMPLAZA con la contraseña de aplicación generada
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // Usar TLS
    $mail->Port       = 587;                              // Puerto TLS de Gmail

    // ---- Remitente y destinatario ----
    $mail->setFrom('clubbetelgeuse2017@gmail.com', 'Club Betelgeuse');  // Debe coincidir con Username
    $mail->addAddress('clujanmeza@gmail.com', 'Carlos'); // Destinatario de prueba

    // ---- Contenido del correo ----
    $mail->isHTML(true);                                      // El correo tendrá formato HTML
    $mail->Subject = '¡Notificación de prueba!';              // Asunto
    $mail->Body    = '<h1>¡Éxito!</h1><p>Tu configuración de envío está funcionando correctamente.</p>';
    $mail->AltBody = 'Éxito: tu configuración de envío está funcionando correctamente.'; // Texto sin HTML

    // 4. Enviar
    $mail->send();
    echo '✅ Correo enviado con éxito. Revisa tu bandeja de entrada (y carpeta SPAM).';
} catch (Exception $e) {
    // Si falla, mostrará el error exacto
    echo "❌ Error al enviar: {$mail->ErrorInfo}";
}
?>