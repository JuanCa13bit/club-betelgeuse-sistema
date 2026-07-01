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
$actividades = $conn->query("SELECT * FROM actividades WHERE anio = $anio ORDER BY fecha DESC");

// Miembros activos (para asignar puntos)
$miembros = $conn->query("
    SELECT m.id, u.nombre, u.apellido, un.nombre AS unidad, cr.nombre AS clase, cr.edad_requerida
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
    <title>Actividades y Puntajes · Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-club: #1e40af;
            --accent-club: #f59e0b;
            --bg-main: #f8fafc;
            --card-shadow: 0 8px 30px -8px rgba(15, 23, 42, 0.06), 0 4px 16px -6px rgba(15, 23, 42, 0.03);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: linear-gradient(165deg, #f0f4ff 0%, #f8fafc 40%, #fff 100%); min-height: 100vh; }

        .navbar-act {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-act .navbar-brand {
            font-weight: 700; font-size: 1.1rem; color: white !important; transition: var(--transition);
        }
        .navbar-act .navbar-brand:hover { color: var(--accent-club) !important; }

        .act-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .act-header h2 { font-weight: 800; font-size: 1.8rem; color: #0f172a; }
        .act-header p { color: #64748b; font-weight: 500; }

        .form-select, .form-control {
            border-radius: 12px; border: 1px solid #e2e8f0; padding: 10px 14px;
            font-size: 0.9rem; font-weight: 500; background: #fafbfc; transition: var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-club); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); background: white;
        }

        .btn-crear {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; border: none; font-weight: 600; padding: 8px 20px;
            border-radius: 12px; transition: var(--transition);
        }
        .btn-crear:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        .btn-asignar {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white; border: none; font-weight: 600; padding: 6px 16px;
            border-radius: 12px; font-size: 0.85rem; transition: var(--transition);
        }
        .btn-asignar:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3); }

        .actividad-item {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; margin-bottom: 12px;
        }
        .actividad-item h6 { font-weight: 700; color: #0f172a; }

        .modal-content {
            border-radius: 20px; border: none; box-shadow: 0 20px 60px -10px rgba(15, 23, 42, 0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white; border-radius: 20px 20px 0 0; padding: 16px 24px; border-bottom: none;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); }

        .table-card {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: var(--card-shadow); border: 1px solid rgba(241, 245, 249, 0.9);
        }
        .table-card table { margin-bottom: 0; }
        .table-card thead th {
            background: #f8fafc; font-weight: 700; font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 12px 16px;
        }
        .table-card tbody td { padding: 10px 16px; vertical-align: middle; font-weight: 500; }
        .table-card tbody tr:hover { background: #f8fafc; }

        .badge-puntaje {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white; font-weight: 600; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem;
        }

        .alert-success {
            border-radius: 16px; border-left: 4px solid #10b981; background: #f0fdf4;
            color: #065f46; font-weight: 600; padding: 12px 16px; margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .act-card { padding: 24px 18px; border-radius: 22px; }
            .act-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-act">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
            <span class="text-white fw-semibold">
                Año: 
                <a href="?anio=<?= $anio-1 ?>" class="text-white-50">◀</a> 
                <strong><?= $anio ?></strong> 
                <a href="?anio=<?= $anio+1 ?>" class="text-white-50">▶</a>
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="act-card">

            <div class="act-header">
                <h2><i class="bi bi-star-fill me-2" style="color: var(--accent-club);"></i>Actividades y Puntajes</h2>
                <p>Crea actividades y asigna puntos a los participantes para el ranking anual.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5"></i> <div><?= $mensaje ?></div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Columna izquierda: Crear actividad y lista -->
                <div class="col-md-7">
                    <!-- Crear nueva actividad -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white rounded-top-4">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Actividad</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" name="nombre" class="form-control" placeholder="Nombre de la actividad" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="categoria" class="form-select">
                                            <option value="campamento">🏕️ Campamento</option>
                                            <option value="concurso">🏆 Concurso</option>
                                            <option value="servicio">🤝 Servicio</option>
                                            <option value="evento">🎉 Evento</option>
                                            <option value="otro">📌 Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="descripcion" class="form-control" placeholder="Descripción (opcional)">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="puntaje_base" class="form-control" placeholder="Pts base" value="10" step="0.5" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="puntaje_extra" class="form-control" placeholder="Pts extra" value="5" step="0.5">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="crear_actividad" class="btn btn-crear w-100">Crear</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de actividades -->
                    <h5 class="fw-bold mb-3">Actividades de <?= $anio ?></h5>
                    <?php while ($act = $actividades->fetch_assoc()): ?>
                        <div class="actividad-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <?= htmlspecialchars($act['nombre']) ?>
                                        <span class="badge bg-secondary ms-2"><?= $act['categoria'] ?></span>
                                    </h6>
                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($act['fecha'])) ?> | 
                                        Base: <?= $act['puntaje_base'] ?> pts | Extra: <?= $act['puntaje_extra'] ?> pts
                                    </small>
                                </div>
                                <button class="btn btn-asignar" data-bs-toggle="modal" data-bs-target="#modalPuntos<?= $act['id'] ?>">
                                    <i class="bi bi-people me-1"></i> Asignar
                                </button>
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
                                            <table class="table table-sm table-hover">
                                                <thead><tr><th>Miembro</th><th>Unidad</th><th>Participó</th><th>Extra</th></tr></thead>
                                                <tbody>
                                                    <?php $miembros->data_seek(0); $ultima_clase = ''; while ($m = $miembros->fetch_assoc()): ?>
                                                        <?php if ($m['clase'] != $ultima_clase): $ultima_clase = $m['clase']; ?>
                                                            <tr class="table-secondary"><td colspan="4"><strong>📘 <?= $m['clase'] ?? 'Sin clase' ?></strong></td></tr>
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
                                            <button type="submit" name="asignar_puntos" class="btn btn-primary rounded-pill">Guardar puntos</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Columna derecha: Ranking -->
                <div class="col-md-5">
                    <div class="table-card">
                        <div class="card-header bg-warning text-dark rounded-top-4">
                            <h6 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Ranking <?= $anio ?></h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead><tr><th>#</th><th>Nombre</th><th>Pts</th></tr></thead>
                                <tbody>
                                    <?php $pos = 0; while ($r = $ranking->fetch_assoc()): $pos++; ?>
                                        <tr>
                                            <td><?= $pos ?>°</td>
                                            <td><?= $r['nombre'] . ' ' . $r['apellido'] ?> <small class="text-muted">(<?= $r['unidad'] ?? '-' ?>)</small></td>
                                            <td><span class="badge-puntaje"><?= number_format($r['puntaje_total'], 1) ?></span></td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>