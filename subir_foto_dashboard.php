<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario','tesorero']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $archivo = $_FILES['foto'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($extension, $permitidas) && $archivo['size'] < 2097152) {
        $nombre = 'perfil_' . $usuario_id . '_' . time() . '.' . $extension;
        $destino = 'assets/fotos/' . $nombre;
        
        if (move_uploaded_file($archivo['tmp_name'], $destino)) {
            // Eliminar foto anterior si existe
            $anterior = $conn->query("SELECT foto FROM usuarios WHERE id = $usuario_id")->fetch_assoc()['foto'];
            if ($anterior && file_exists($anterior)) {
                unlink($anterior);
            }
            
            $conn->query("UPDATE usuarios SET foto = '$destino' WHERE id = $usuario_id");
        }
    }
}

header("Location: dashboard.php");
exit;
?>