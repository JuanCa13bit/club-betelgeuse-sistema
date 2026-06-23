<?php
session_start();
$host = 'localhost';
$user = 'root';         // por defecto en Laragon
$pass = '';             // sin contraseña en Laragon
$db   = 'club_betelgeuse';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>