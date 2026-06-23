<?php
require_once 'includes/config.php';

// Cargar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'includes/PHPMailer/src/Exception.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';

$forzar = isset($_GET['forzar']);
$hoy = date('Y-m-d');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Notificaciones</title>
    <link href='assets/css/bootstrap.min.css' rel='stylesheet'></head><body class='container mt-4'>";

// Verificar si ya se envió hoy
$check = $conn->query("SELECT COUNT(*) as total FROM notificacion_envios WHERE fecha_envio = '$hoy'");
if ($check->fetch_assoc()['total'] > 0 && !$forzar) {
    echo "<div class='alert alert-info'>
        <h4>✅ Notificaciones ya enviadas hoy (" . date('d/m/Y') . ")</h4>
        <a href='?forzar=1' class='btn btn-warning btn-sm'>Forzar reenvío</a>
        <a href='dashboard.php' class='btn btn-secondary btn-sm'>Volver</a>
    </div></body></html>";
    exit;
}

if ($forzar) {
    $conn->query("DELETE FROM notificacion_envios WHERE fecha_envio = '$hoy'");
}

// Cumpleaños de hoy
$cumpleanios = $conn->query("
    SELECT nombre, apellido, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad
    FROM usuarios WHERE DATE_FORMAT(fecha_nacimiento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
");

// Cumpleaños próximos 7 días
$cumples_proximos = $conn->query("
    SELECT nombre, apellido, DATE_FORMAT(fecha_nacimiento, '%d/%m') AS fecha,
           TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad
    FROM usuarios WHERE DATE_FORMAT(fecha_nacimiento, '%m-%d') BETWEEN 
    DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d')
");

// Eventos
$hoy_eventos = $conn->query("SELECT titulo, descripcion, hora FROM eventos_calendario WHERE fecha = CURDATE() ORDER BY hora");
$manana = date('Y-m-d', strtotime('+1 day'));
$eventos_manana = $conn->query("SELECT titulo, descripcion, hora FROM eventos_calendario WHERE fecha = '$manana' ORDER BY hora");
$eventos_semana = $conn->query("SELECT titulo, fecha, hora FROM eventos_calendario WHERE fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY fecha, hora");

// Si no hay nada, no enviar
$hay_noticias = ($cumpleanios->num_rows > 0 || $eventos_manana->num_rows > 0 || $cumples_proximos->num_rows > 0 || $hoy_eventos->num_rows > 0 || $eventos_semana->num_rows > 0);

if (!$hay_noticias) {
    echo "<div class='alert alert-info'><h4>😊 Sin novedades hoy</h4><a href='dashboard.php' class='btn btn-secondary'>Volver</a></div></body></html>";
    exit;
}

// Construir mensaje HTML
$mensaje = "<html><head><meta charset='UTF-8'><style>
    body{font-family:Arial;color:#333}
    .header{background:#0d1b3e;color:white;padding:20px;text-align:center}
    .section{margin:20px 0;padding:15px;background:#f8f9fa;border-radius:10px}
    .section h3{color:#0d1b3e}
    .item{padding:8px 0;border-bottom:1px solid #eee}
    .footer{text-align:center;color:#999;font-size:12px;margin-top:20px}
</style></head><body>
<div class='header'><h2>⭐ Club Betelgeuse</h2><p>Notificaciones " . date('d/m/Y') . "</p></div>";

if ($cumpleanios->num_rows > 0) {
    $mensaje .= "<div class='section'><h3>🎂 Cumpleaños de Hoy</h3>";
    while ($c = $cumpleanios->fetch_assoc()) {
        $mensaje .= "<div class='item'>🎂 <strong>{$c['nombre']} {$c['apellido']}</strong> ({$c['edad']} años)</div>";
    }
    $mensaje .= "</div>";
}

if ($hoy_eventos->num_rows > 0) {
    $mensaje .= "<div class='section'><h3>📅 Eventos de Hoy</h3>";
    while ($e = $hoy_eventos->fetch_assoc()) {
        $h = $e['hora'] ? substr($e['hora'],0,5) : '';
        $mensaje .= "<div class='item'>📅 <strong>{$e['titulo']}</strong> $h</div>";
    }
    $mensaje .= "</div>";
}

if ($eventos_manana->num_rows > 0) {
    $mensaje .= "<div class='section'><h3>⚠️ Mañana</h3>";
    while ($e = $eventos_manana->fetch_assoc()) {
        $h = $e['hora'] ? substr($e['hora'],0,5) : '';
        $mensaje .= "<div class='item'>📅 <strong>{$e['titulo']}</strong> $h</div>";
    }
    $mensaje .= "</div>";
}

if ($cumples_proximos->num_rows > 0) {
    $mensaje .= "<div class='section'><h3>🎉 Próximos Cumpleaños</h3>";
    while ($c = $cumples_proximos->fetch_assoc()) {
        $mensaje .= "<div class='item'><strong>{$c['nombre']} {$c['apellido']}</strong> - {$c['fecha']} (" . ($c['edad']+1) . " años)</div>";
    }
    $mensaje .= "</div>";
}

if ($eventos_semana->num_rows > 0) {
    $mensaje .= "<div class='section'><h3>📅 Esta Semana</h3>";
    while ($e = $eventos_semana->fetch_assoc()) {
        $f = date('d/m', strtotime($e['fecha']));
        $h = $e['hora'] ? substr($e['hora'],0,5) : '';
        $mensaje .= "<div class='item'><strong>$f</strong> - {$e['titulo']} $h</div>";
    }
    $mensaje .= "</div>";
}

$mensaje .= "<div class='footer'>Club Betelgeuse © " . date('Y') . "</div></body></html>";

// Enviar a cada destinatario
$destinatarios = $conn->query("SELECT id, email, nombre FROM notificacion_destinatarios WHERE activo = 1");
$enviados = 0;
$errores = 0;

while ($d = $destinatarios->fetch_assoc()) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'clubbetelgeuse2017@gmail.com';
        $mail->Password = 'sgvy jpaj blqg ircw';  // ⚠️ Cambiar por la real
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom('clubbetelgeuse2017@gmail.com', 'Club Betelgeuse');
        $mail->addAddress($d['email'], $d['nombre']);
        $mail->isHTML(true);
        $mail->Subject = '📅 Notificaciones Club Betelgeuse - ' . date('d/m/Y');
        $mail->Body = $mensaje;
        
        $mail->send();
        $conn->query("INSERT IGNORE INTO notificacion_envios (destinatario_id, fecha_envio) VALUES ({$d['id']}, '$hoy')");
        $enviados++;
    } catch (Exception $e) {
        $errores++;
    }
}

echo "<div class='card'><div class='card-header bg-success text-white'><h4>✅ Enviados: $enviados</h4></div>";
if ($errores > 0) echo "<div class='alert alert-danger'>❌ Errores: $errores</div>";
echo "<div class='card-body'><a href='dashboard.php' class='btn btn-primary'>Volver</a></div></div>";
echo "<hr><h4>Vista previa:</h4><div class='card'><div class='card-body'>$mensaje</div></div></body></html>";
?>