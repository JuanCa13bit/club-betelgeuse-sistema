<?php
require_once 'includes/config.php';

$categoria_id = intval($_GET['categoria'] ?? 0);
$excluir = json_decode($_GET['excluir'] ?? '[]', true);

$sql = "SELECT id, nombre_especialidad FROM especialidades WHERE categoria_id = ?";
if (!empty($excluir)) {
    $sql .= " AND id NOT IN (" . implode(',', array_map('intval', $excluir)) . ")";
}
$sql .= " ORDER BY nombre_especialidad";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $categoria_id);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: application/json');
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
?>