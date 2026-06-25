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
?>