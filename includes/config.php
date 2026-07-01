<?php
session_start();
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'club_betelgeuse';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/**
 * Devuelve un badge HTML con el color representativo de cada clase.
 * @param string $nombre_clase El nombre de la clase (Amigo, Compañero, etc.)
 * @return string HTML del badge
 */
function badge_clase($nombre_clase) {
    $colores = [
        'Amigo'         => '#3b82f6', // azul
        'Compañero'     => '#ef4444', // rojo
        'Explorador'    => '#10b981', // verde
        'Pionero'       => '#6b7280', // gris
        'Excursionista' => '#8b5cf6', // morado
        'Guía'          => '#f59e0b', // amarillo
    ];
    $color = $colores[$nombre_clase] ?? '#6b7280';
    return '<span class="badge fw-semibold" style="background-color: ' . $color . '; color: white; font-size: 0.8rem;">' . htmlspecialchars($nombre_clase) . '</span>';
}
?>