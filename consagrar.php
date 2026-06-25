<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director']);

// Umbrales configurables
define('UMBRAL_SEGUNDO', 0.20);
define('UMBRAL_TERCERO_LIDER', 0.35);
define('UMBRAL_TERCERO_SEGUNDO', 0.25);

$anio = $_POST['anio'] ?? date('Y');
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consagrar'])) {
    // Verificar si ya hay ganadores para ese año
    $check = $conn->query("SELECT COUNT(*) as total FROM ganadores_anuales WHERE anio = $anio")->fetch_assoc()['total'];
    
    if ($check > 0) {
        $error = "Ya existen ganadores registrados para el año $anio.";
    } else {
        // Obtener ranking del año
        $ranking = $conn->query("
            SELECT m.id AS miembro_id, u.nombre, u.apellido, 
                   COALESCE(SUM(pa.puntaje_total), 0) AS puntaje_total
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN participacion_actividades pa ON m.id = pa.miembro_id
            LEFT JOIN actividades a ON pa.actividad_id = a.id AND a.anio = $anio
            WHERE m.activo = 1 AND m.tipo = 'conquistador'
            GROUP BY m.id, u.nombre, u.apellido
            HAVING puntaje_total > 0
            ORDER BY puntaje_total DESC
        ");
        
        $puntajes = [];
        while ($r = $ranking->fetch_assoc()) {
            $puntajes[] = $r;
        }
        
        $ganadores = [];
        $n = count($puntajes);
        
        if ($n > 0) {
            $ganadores[] = ['miembro_id' => $puntajes[0]['miembro_id'], 'posicion' => 1, 'puntaje' => $puntajes[0]['puntaje_total']];
            $p1 = $puntajes[0]['puntaje_total'];
            
            if ($n > 1 && $p1 > 0) {
                $p2 = $puntajes[1]['puntaje_total'];
                if (($p1 - $p2) / $p1 < UMBRAL_SEGUNDO) {
                    $ganadores[] = ['miembro_id' => $puntajes[1]['miembro_id'], 'posicion' => 2, 'puntaje' => $p2];
                    
                    if ($n > 2) {
                        $p3 = $puntajes[2]['puntaje_total'];
                        $dif_1_3 = ($p1 - $p3) / $p1;
                        $dif_2_3 = ($p2 > 0) ? ($p2 - $p3) / $p2 : 1;
                        if ($dif_1_3 < UMBRAL_TERCERO_LIDER && $dif_2_3 < UMBRAL_TERCERO_SEGUNDO) {
                            $ganadores[] = ['miembro_id' => $puntajes[2]['miembro_id'], 'posicion' => 3, 'puntaje' => $p3];
                        }
                    }
                }
            }
        }
        
        if (!empty($ganadores)) {
            foreach ($ganadores as $g) {
                $conn->query("INSERT INTO ganadores_anuales (anio, miembro_id, posicion, puntaje_total) VALUES ($anio, {$g['miembro_id']}, {$g['posicion']}, {$g['puntaje']})");
            }
            $mensaje = "¡Ganadores del año $anio consagrados exitosamente! (" . count($ganadores) . " ganadores)";
        } else {
            $error = "No hay suficientes datos para determinar ganadores en $anio.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consagrar Ganadores - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver al Panel</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-trophy-fill text-warning"></i> Consagrar Ganadores del Año</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <p>Este proceso calculará automáticamente los ganadores del año usando el algoritmo de saltos.</p>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Año</label>
                        <input type="number" name="anio" class="form-control" value="<?= date('Y') ?>" min="2020" max="<?= date('Y') ?>">
                    </div>
                    <button type="submit" name="consagrar" class="btn btn-warning btn-lg">
                        <i class="bi bi-star-fill"></i> Consagrar Ganadores
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>