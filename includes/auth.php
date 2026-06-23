<?php
function verificar_sesion($roles_permitidos = []) {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }
    if (!empty($roles_permitidos) && !in_array($_SESSION['rol'], $roles_permitidos)) {
        die("Acceso denegado.");
    }
}
?>