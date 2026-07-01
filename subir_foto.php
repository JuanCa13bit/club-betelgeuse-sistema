<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $miembro_id = intval($_POST['miembro_id']);
    $usuario_id = $conn->query("SELECT usuario_id FROM miembros WHERE id = $miembro_id")->fetch_assoc()['usuario_id'];
    
    $archivo = $_FILES['foto'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($extension, $permitidas) && $archivo['size'] < 2097152) { // 2MB max
        $nombre = 'miembro_' . $miembro_id . '_' . time() . '.' . $extension;
        $destino = 'assets/fotos/' . $nombre;
        
        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            // Eliminar foto anterior si existe
            $anterior = $conn->query("SELECT foto FROM usuarios WHERE id = $usuario_id")->fetch_assoc()['foto'];
            if ($anterior && file_exists($anterior)) {
                unlink($anterior);
            }
            
            $conn->query("UPDATE usuarios SET foto = '$destino' WHERE id = $usuario_id");
            $mensaje = "Foto actualizada correctamente.";
        } else {
            $error = "Error al subir la foto.";
        }
    } else {
        $error = "Formato no permitido o archivo muy grande (máx. 2MB).";
    }
    
    header("Location: buscar.php?id=" . $miembro_id . ($mensaje ? "&foto_ok=1" : ""));
    exit;
}
?>