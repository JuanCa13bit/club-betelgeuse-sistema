<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$mensaje = '';
$mes_actual = date('n');
$permitido = ($mes_actual == 12); // Solo diciembre

// =============================================
// LÓGICA DE PROMOCIÓN MASIVA DE NIÑOS (solo diciembre)
// =============================================
if ($permitido && isset($_POST['promover_todos']) && isset($_POST['miembros_ids'])) {
    $miembros_ids = $_POST['miembros_ids'];
    $clase_actual_id = intval($_POST['clase_actual_id']);
    $promovidos = 0;
    foreach ($miembros_ids as $mid) {
        $mid = intval($mid);
        // Obtener género desde la unidad actual
        $genero = $conn->query("SELECT un.genero FROM miembro_unidad mu JOIN unidades un ON mu.unidad_id = un.id WHERE mu.miembro_id = $mid")->fetch_assoc()['genero'];
        // Edad de la clase actual
        $edad_actual = $conn->query("SELECT edad_requerida FROM clases_regulares WHERE id = $clase_actual_id")->fetch_assoc()['edad_requerida'];
        // Registrar logro de clase actual
        $conn->query("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES ($mid, $clase_actual_id, YEAR(CURDATE()))");
        // Buscar unidad para la nueva clase (edad + 1)
        $nueva_unidad = $conn->query("SELECT id FROM unidades WHERE genero = '$genero' AND edad_min <= $edad_actual+1 AND edad_max >= $edad_actual+1 LIMIT 1")->fetch_assoc();
        if ($nueva_unidad) {
            $conn->query("UPDATE miembro_unidad SET unidad_id = {$nueva_unidad['id']}, fecha_asignacion = CURDATE() WHERE miembro_id = $mid");
        }
        $promovidos++;
    }
    $mensaje = "Se promovieron $promovidos conquistador(es) correctamente.";
}

// =============================================
// LÓGICA DE CAMBIO DE LÍDERES (siempre disponible)
// =============================================
if (isset($_POST['actualizar_lider'])) {
    $lider_id = intval($_POST['lider_id']);
    $nueva_unidad = intval($_POST['nueva_unidad']);
    $nueva_clase = intval($_POST['nueva_clase']);
    
    $existe = $conn->query("SELECT id FROM lider_clase WHERE miembro_id = $lider_id")->num_rows;
    if ($existe) {
        $conn->query("UPDATE lider_clase SET unidad_id = $nueva_unidad, clase_regular_id = $nueva_clase, fecha_inicio = CURDATE() WHERE miembro_id = $lider_id");
    } else {
        $conn->query("INSERT INTO lider_clase (miembro_id, unidad_id, clase_regular_id, fecha_inicio) VALUES ($lider_id, $nueva_unidad, $nueva_clase, CURDATE())");
    }
    $mensaje = "Asignación de líder actualizada.";
}

// =============================================
// DATOS COMUNES
// =============================================
$anio_actual = date('Y');
$fecha_corte = "$anio_actual-06-30"; // 30 de junio del año actual

$clases = $conn->query("SELECT * FROM clases_regulares ORDER BY edad_requerida");
$unidades = $conn->query("SELECT * FROM unidades ORDER BY genero, edad_min");

// Líderes activos
$lideres = $conn->query("
    SELECT m.id, u.nombre, u.apellido, un.nombre AS unidad_actual, un.id AS unidad_id,
           COALESCE(lc.clase_regular_id, 0) AS clase_lider_id,
           COALESCE(cr.nombre, 'Sin asignar') AS clase_lider_nombre
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN lider_clase lc ON m.id = lc.miembro_id
    LEFT JOIN clases_regulares cr ON lc.clase_regular_id = cr.id
    WHERE m.tipo = 'lider' AND m.activo = 1
    ORDER BY u.apellido, u.nombre
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promoción y Líderes - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- TARJETA 1: PROMOCIÓN DE NIÑOS (solo diciembre) -->
            <?php if ($permitido): ?>
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Promoción de Conquistadores (Diciembre)</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $clases->data_seek(0);
                        while ($clase = $clases->fetch_assoc()): 
                            $clase_id = $clase['id'];
                            $edad = $clase['edad_requerida'];
                            
                            // Niños que están en esta clase según fecha de corte (30 junio)
                            $ninos = $conn->query("
    SELECT m.id, u.nombre, u.apellido, un.nombre AS unidad_actual
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    JOIN miembro_unidad mu ON m.id = mu.miembro_id
    JOIN unidades un ON mu.unidad_id = un.id
    WHERE m.tipo = 'conquistador' AND m.activo = 1
    AND m.clase_actual_id = $clase_id
    ORDER BY u.apellido, u.nombre
");

// Actualizar clase actual al siguiente nivel
if ($clase_siguiente_id > 0) {
    $conn->query("UPDATE miembros SET clase_actual_id = $clase_siguiente_id WHERE id = $mid");
}
                            if ($ninos->num_rows == 0) continue;
                            
                            // Guardar los IDs para el formulario masivo
                            $miembros_ids = [];
                            $nombres = [];
                            while ($n = $ninos->fetch_assoc()) {
                                $miembros_ids[] = $n['id'];
                                $nombres[] = $n['nombre'] . ' ' . $n['apellido'] . ' (' . $n['unidad_actual'] . ')';
                            }
                            $lista_nombres = implode(', ', $nombres);
                        ?>
                            <h6 class="mt-3">📘 Clase <?= $clase['nombre'] ?> (<?= $edad ?> años) — <?= count($miembros_ids) ?> niños</h6>
                            <form method="post" style="display:inline;">
                                <?php foreach ($miembros_ids as $id): ?>
                                    <input type="hidden" name="miembros_ids[]" value="<?= $id ?>">
                                <?php endforeach; ?>
                                <input type="hidden" name="clase_actual_id" value="<?= $clase_id ?>">
                                <div class="mb-2">
                                    <small class="text-muted">Niños a promover: <?= $lista_nombres ?></small>
                                </div>
                                <button type="submit" name="promover_todos" class="btn btn-sm btn-success"
                                        onclick="return confirm('¿Confirma promover a todos los niños de <?= $clase['nombre'] ?>?\n\nNiños: <?= $lista_nombres ?>')">
                                    <i class="bi bi-check-circle"></i> Promover todos (<?= count($miembros_ids) ?>)
                                </button>
                            </form>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-lock-fill"></i> Promoción de Conquistadores</h5>
                    </div>
                    <div class="card-body text-center text-muted">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <h5>Promoción no disponible</h5>
                        <p>La promoción de conquistadores solo está habilitada durante el mes de <strong>diciembre</strong>.</p>
                        <p>Mes actual: <strong><?= date('F') ?></strong></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TARJETA 2: LÍDERES (siempre disponible) -->
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Designación de Líderes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr><th>Líder</th><th>Unidad Actual</th><th>Clase Actual</th><th>Editar</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($lider = $lideres->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $lider['nombre'] . ' ' . $lider['apellido'] ?></td>
                                            <td><?= $lider['unidad_actual'] ?? 'Sin unidad' ?></td>
                                            <td><?= $lider['clase_lider_nombre'] ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalLider<?= $lider['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <!-- Modal para editar -->
                                                <div class="modal fade" id="modalLider<?= $lider['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-sm">
                                                        <div class="modal-content">
                                                            <form method="post">
                                                                <div class="modal-header">
                                                                    <h6 class="modal-title">Cambiar asignación</h6>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="lider_id" value="<?= $lider['id'] ?>">
                                                                    <label class="form-label">Unidad</label>
                                                                    <select name="nueva_unidad" class="form-select form-select-sm mb-2">
                                                                        <?php $unidades->data_seek(0); while ($u = $unidades->fetch_assoc()): ?>
                                                                            <option value="<?= $u['id'] ?>" <?= $u['id'] == $lider['unidad_id'] ? 'selected' : '' ?>>
                                                                                <?= $u['nombre'] ?> (<?= $u['genero'] ?> <?= $u['edad_min'] ?>-<?= $u['edad_max'] ?>)
                                                                            </option>
                                                                        <?php endwhile; ?>
                                                                    </select>
                                                                    <label class="form-label">Clase que lidera</label>
                                                                    <select name="nueva_clase" class="form-select form-select-sm">
                                                                        <option value="0">Ninguna</option>
                                                                        <?php 
                                                                        $clases->data_seek(0);
                                                                        while ($cl = $clases->fetch_assoc()): 
                                                                        ?>
                                                                            <option value="<?= $cl['id'] ?>" <?= $cl['id'] == $lider['clase_lider_id'] ? 'selected' : '' ?>>
                                                                                <?= $cl['nombre'] ?>
                                                                            </option>
                                                                        <?php endwhile; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="submit" name="actualizar_lider" class="btn btn-primary btn-sm">Guardar</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>