<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $id = intval($_GET['id']);
    $nuevo_estado = ($_GET['accion'] === 'activar') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE miembros SET activo = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuevo_estado, $id);
    $stmt->execute();
}

header("Location: admin_miembros.php?estado=" . ($nuevo_estado ? 'activos' : 'inactivos'));
exit;
?>