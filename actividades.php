<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$anio = $_GET['anio'] ?? date('Y');
$mensaje = '';

// Crear nueva actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_actividad'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'];
    $puntaje_base = floatval($_POST['puntaje_base']);
    $puntaje_extra = floatval($_POST['puntaje_extra'] ?? 0);
    $categoria = $_POST['categoria'];

    $conn->query("INSERT INTO actividades (nombre, descripcion, fecha, puntaje_base, puntaje_extra, anio, categoria) 
                  VALUES ('$nombre', '$descripcion', '$fecha', $puntaje_base, $puntaje_extra, $anio, '$categoria')");
    $mensaje = "Actividad creada correctamente.";
}

// Asignar puntos a participantes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar_puntos'])) {
    $actividad_id = intval($_POST['actividad_id']);
    $miembros_ids = $_POST['miembros_ids'] ?? [];
    $obtuvo_extra = $_POST['obtuvo_extra'] ?? [];

    // Obtener puntajes de la actividad
    $actividad = $conn->query("SELECT puntaje_base, puntaje_extra FROM actividades WHERE id = $actividad_id")->fetch_assoc();
    $base = floatval($actividad['puntaje_base']);
    $extra = floatval($actividad['puntaje_extra']);

    foreach ($miembros_ids as $mid) {
        $mid = intval($mid);
        $tiene_extra = in_array($mid, $obtuvo_extra) ? 1 : 0;
        $total = $base + ($tiene_extra ? $extra : 0);
        $conn->query("INSERT IGNORE INTO participacion_actividades (miembro_id, actividad_id, obtuvo_extra, puntaje_total) 
                      VALUES ($mid, $actividad_id, $tiene_extra, $total)");
    }
    $mensaje = "Puntos asignados correctamente.";
}

// Obtener actividades del año
$actividades = $conn->query("
    SELECT * FROM actividades 
    WHERE anio = $anio 
    ORDER BY fecha DESC
");

// Miembros activos (para asignar puntos)
$miembros = $conn->query("
    SELECT m.id, u.nombre, u.apellido, un.nombre AS unidad, m.clase_actual_id,
           COALESCE(cr.nombre, 'Sin clase') AS clase_nombre,
           cr.edad_requerida
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN clases_regulares cr ON m.clase_actual_id = cr.id
    WHERE m.activo = 1
    ORDER BY cr.edad_requerida, u.apellido, u.nombre
");

// Ranking del año
$ranking = $conn->query("
    SELECT u.nombre, u.apellido, un.nombre AS unidad,
           COALESCE(SUM(pa.puntaje_total), 0) AS puntaje_total,
           COUNT(pa.id) AS actividades_realizadas
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN participacion_actividades pa ON m.id = pa.miembro_id
    LEFT JOIN actividades a ON pa.actividad_id = a.id AND a.anio = $anio
    WHERE m.activo = 1 AND m.tipo = 'conquistador'
    GROUP BY m.id, u.nombre, u.apellido, un.nombre
    HAVING puntaje_total > 0
    ORDER BY puntaje_total DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actividades y Puntajes - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver</a>
            <span class="navbar-text">Año: <a href="?anio=<?= $anio-1 ?>" class="text-white">◀</a> <strong><?= $anio ?></strong> <a href="?anio=<?= $anio+1 ?>" class="text-white">▶</a></span>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Columna izquierda: Actividades -->
            <div class="col-md-7">
                <!-- Crear nueva actividad -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva Actividad</h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre de la actividad" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <select name="categoria" class="form-select form-select-sm">
                                        <option value="campamento">🏕️ Campamento</option>
                                        <option value="concurso">🏆 Concurso</option>
                                        <option value="servicio">🤝 Servicio</option>
                                        <option value="evento">🎉 Evento</option>
                                        <option value="otro">📌 Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Descripción (opcional)">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="puntaje_base" class="form-control form-control-sm" placeholder="Pts base" value="10" step="0.5" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="puntaje_extra" class="form-control form-control-sm" placeholder="Pts extra" value="5" step="0.5">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="crear_actividad" class="btn btn-success btn-sm w-100">Crear</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de actividades -->
                <h5>Actividades de <?= $anio ?></h5>
                <?php while ($act = $actividades->fetch_assoc()): ?>
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($act['nombre']) ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= $act['categoria'] ?></span>
                                    <br><small class="text-muted"><?= date('d/m/Y', strtotime($act['fecha'])) ?> | Base: <?= $act['puntaje_base'] ?> pts | Extra: <?= $act['puntaje_extra'] ?> pts</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPuntos<?= $act['id'] ?>">
                                    <i class="bi bi-people"></i> Asignar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal para asignar puntos -->
                    <div class="modal fade" id="modalPuntos<?= $act['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="post">
                                    <div class="modal-header">
                                        <h6 class="modal-title">Asignar puntos: <?= htmlspecialchars($act['nombre']) ?></h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="actividad_id" value="<?= $act['id'] ?>">
                                        <table class="table table-sm">
                                            <thead><tr><th>Miembro</th><th>Unidad</th><th>Participó</th><th>Extra</th></tr></thead>
                                            <tbody>
    <?php 
    $miembros->data_seek(0);
    $ultima_clase = '';
    while ($m = $miembros->fetch_assoc()): 
        if ($m['clase_nombre'] != $ultima_clase): $ultima_clase = $m['clase_nombre']; ?>
            <tr class="table-secondary"><td colspan="4"><strong>📘 <?= $ultima_clase ?></strong></td></tr>
        <?php endif; ?>
        <tr>
            <td><?= $m['nombre'] . ' ' . $m['apellido'] ?></td>
            <td><?= $m['unidad'] ?? '-' ?></td>
            <td><input type="checkbox" name="miembros_ids[]" value="<?= $m['id'] ?>"></td>
            <td><input type="checkbox" name="obtuvo_extra[]" value="<?= $m['id'] ?>"></td>
        </tr>
    <?php endwhile; ?>
</tbody>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="asignar_puntos" class="btn btn-primary btn-sm">Guardar puntos</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Columna derecha: Ranking -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-trophy-fill"></i> Ranking <?= $anio ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>#</th><th>Nombre</th><th>Pts</th></tr></thead>
                            <tbody>
                                <?php $pos = 0; while ($r = $ranking->fetch_assoc()): $pos++; ?>
                                    <tr>
                                        <td><?= $pos ?>°</td>
                                        <td><?= $r['nombre'] . ' ' . $r['apellido'] ?> <small class="text-muted">(<?= $r['unidad'] ?? '-' ?>)</small></td>
                                        <td><strong><?= number_format($r['puntaje_total'], 1) ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($pos == 0): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Sin puntajes registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>