<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

if (isset($_GET['id'])) {
    $miembro_id = intval($_GET['id']);
    
    // Verificar que sea conquistador activo y haya completado "Guía"
    $check = $conn->query("
        SELECT m.id, m.usuario_id
        FROM miembros m
        WHERE m.id = $miembro_id AND m.tipo = 'conquistador' AND m.activo = 1
        AND EXISTS (
            SELECT 1 FROM logros_clase_regular lcr
            JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id
            WHERE lcr.miembro_id = m.id AND cr.nombre = 'Guía'
        )
    ");
    
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        
        // Actualizar tabla miembros
        $conn->query("UPDATE miembros SET tipo = 'lider', rango = 'guia' WHERE id = $miembro_id");
        
        // Actualizar tabla usuarios
        $conn->query("UPDATE usuarios SET rol = 'lider' WHERE id = {$row['usuario_id']}");
    }
}

header("Location: admin_miembros.php?estado=activos&promovido=1");
exit;
?>