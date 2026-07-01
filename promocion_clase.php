<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$mensaje = '';
$mes_actual = date('n');
$permitido = ($mes_actual == 12); // Solo diciembre

// =============================================
// LÓGICA DE PROMOCIÓN MASIVA DE NIÑOS
// =============================================
if ($permitido && isset($_POST['promover_todos']) && isset($_POST['miembros_ids'])) {
    $miembros_ids = $_POST['miembros_ids'];
    $clase_actual_id = intval($_POST['clase_actual_id']);
    $clase_siguiente_id = intval($_POST['clase_siguiente_id']);
    $promovidos = 0;
    foreach ($miembros_ids as $mid) {
        $mid = intval($mid);
        $genero = $conn->query("SELECT un.genero FROM miembro_unidad mu JOIN unidades un ON mu.unidad_id = un.id WHERE mu.miembro_id = $mid")->fetch_assoc()['genero'];
        $edad_actual = $conn->query("SELECT edad_requerida FROM clases_regulares WHERE id = $clase_actual_id")->fetch_assoc()['edad_requerida'];
        $conn->query("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES ($mid, $clase_actual_id, YEAR(CURDATE()))");
        // Actualizar clase_actual_id al siguiente nivel
        if ($clase_siguiente_id > 0) {
            $conn->query("UPDATE miembros SET clase_actual_id = $clase_siguiente_id WHERE id = $mid");
        }
        $nueva_unidad = $conn->query("SELECT id FROM unidades WHERE genero = '$genero' AND edad_min <= $edad_actual+1 AND edad_max >= $edad_actual+1 LIMIT 1")->fetch_assoc();
        if ($nueva_unidad) {
            $conn->query("UPDATE miembro_unidad SET unidad_id = {$nueva_unidad['id']}, fecha_asignacion = CURDATE() WHERE miembro_id = $mid");
        }
        $promovidos++;
    }
    $mensaje = "Se promovieron $promovidos conquistador(es) correctamente.";
}

// =============================================
// LÓGICA DE CAMBIO DE LÍDERES (manual)
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
$fecha_corte = "$anio_actual-06-30";

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

        .navbar-promo {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-promo .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-promo .navbar-brand:hover { color: var(--accent-club) !important; }

        .promo-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .promo-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .promo-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        .clase-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .clase-group h6 {
            font-weight: 700;
            margin-bottom: 12px;
        }
        .clase-group .btn-promote {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 12px;
            transition: var(--transition);
        }
        .clase-group .btn-promote:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .badge-tipo {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px -10px rgba(15, 23, 42, 0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 16px 24px;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .restriction-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .promo-card { padding: 24px 18px; border-radius: 22px; }
            .promo-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-promo">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="promo-card">

            <div class="promo-header">
                <h2><i class="bi bi-calendar-check me-2" style="color: var(--accent-club);"></i>Promoción y Líderes</h2>
                <p>Gestiona la promoción de conquistadores y la asignación de líderes.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div><?= $mensaje ?></div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- TARJETA 1: PROMOCIÓN DE NIÑOS -->
                <div class="col-md-7">
                    <?php if (!$permitido): ?>
                        <div class="restriction-box">
                            <i class="bi bi-lock-fill" style="font-size: 3rem; color: #f59e0b;"></i>
                            <h5 class="mt-3 fw-bold">Promoción no disponible</h5>
                            <p class="text-muted">La promoción de conquistadores solo está habilitada durante el mes de <strong>diciembre</strong>.</p>
                            <p class="badge bg-warning text-dark fs-6">Mes actual: <?= date('F') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-primary text-white rounded-top-4">
                                <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Promoción de Conquistadores</h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $clases->data_seek(0);
                                while ($clase = $clases->fetch_assoc()): 
                                    $clase_id = $clase['id'];
                                    $edad = $clase['edad_requerida'];
                                    
                                    // Siguiente clase
                                    $siguiente = $conn->query("SELECT id, nombre FROM clases_regulares WHERE edad_requerida = $edad + 1")->fetch_assoc();
                                    $siguiente_id = $siguiente['id'] ?? 0;
                                    $siguiente_nombre = $siguiente['nombre'] ?? '—';

                                    // Niños en esta clase según clase_actual_id
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
                                    if ($ninos->num_rows == 0) continue;
                                    
                                    $miembros_ids = [];
                                    $nombres = [];
                                    while ($n = $ninos->fetch_assoc()) {
                                        $miembros_ids[] = $n['id'];
                                        $nombres[] = $n['nombre'] . ' ' . $n['apellido'] . ' (' . $n['unidad_actual'] . ')';
                                    }
                                    $lista_nombres = implode(', ', $nombres);
                                ?>
                                    <div class="clase-group">
                                        <h6><?= badge_clase($clase['nombre']) ?> → <?= badge_clase($siguiente_nombre) ?></h6>
                                        <p class="text-muted small mb-3">
                                            <?= count($miembros_ids) ?> niño(s): <?= $lista_nombres ?>
                                        </p>
                                        <form method="post">
                                            <?php foreach ($miembros_ids as $id): ?>
                                                <input type="hidden" name="miembros_ids[]" value="<?= $id ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="clase_actual_id" value="<?= $clase_id ?>">
                                            <input type="hidden" name="clase_siguiente_id" value="<?= $siguiente_id ?>">
                                            <button type="submit" name="promover_todos" class="btn btn-promote"
                                                    onclick="return confirm('¿Promover <?= count($miembros_ids) ?> niño(s) de <?= $clase['nombre'] ?> a <?= $siguiente_nombre ?>?')">
                                                <i class="bi bi-check-circle me-1"></i> Promover todos (<?= count($miembros_ids) ?>)
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TARJETA 2: LÍDERES -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark rounded-top-4">
                            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Designación de Líderes</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr><th>Líder</th><th>Unidad</th><th>Clase</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($lider = $lideres->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($lider['nombre'] . ' ' . $lider['apellido']) ?></strong></td>
                                                <td><?= $lider['unidad_actual'] ?? '<span class="text-muted">-</span>' ?></td>
                                                <td><?= badge_clase($lider['clase_lider_nombre']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalLider<?= $lider['id'] ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <!-- Modal -->
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
                                                                        <label class="form-label fw-semibold">Unidad</label>
                                                                        <select name="nueva_unidad" class="form-select form-select-sm mb-2">
                                                                            <?php $unidades->data_seek(0); while ($u = $unidades->fetch_assoc()): ?>
                                                                                <option value="<?= $u['id'] ?>" <?= $u['id'] == $lider['unidad_id'] ? 'selected' : '' ?>>
                                                                                    <?= $u['nombre'] ?> (<?= $u['genero'] ?> <?= $u['edad_min'] ?>-<?= $u['edad_max'] ?>)
                                                                                </option>
                                                                            <?php endwhile; ?>
                                                                        </select>
                                                                        <label class="form-label fw-semibold">Clase que lidera</label>
                                                                        <select name="nueva_clase" class="form-select form-select-sm">
                                                                            <option value="0">Ninguna</option>
                                                                            <?php $clases->data_seek(0); while ($cl = $clases->fetch_assoc()): ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>