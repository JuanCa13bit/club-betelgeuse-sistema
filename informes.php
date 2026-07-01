<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$tipo_informe = $_GET['tipo'] ?? 'especialidades';
$anio = $_GET['anio'] ?? date('Y');
$mostrar = isset($_GET['generar']);

$titulos = [
    'especialidades' => 'Especialidades por Clase',
    'clases'         => 'Estado de Clases Regulares y Avanzadas',
    'ranking'        => 'Ranking de Puntajes'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informes · Club Betelgeuse</title>
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

        .navbar-informes {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-informes .navbar-brand {
            font-weight: 700; font-size: 1.1rem; color: white !important; transition: var(--transition);
        }
        .navbar-informes .navbar-brand:hover { color: var(--accent-club) !important; }

        .informes-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .informes-header h2 { font-weight: 800; font-size: 1.8rem; color: #0f172a; }
        .informes-header p { color: #64748b; font-weight: 500; }

        .form-select, .form-control {
            border-radius: 12px; border: 1px solid #e2e8f0; padding: 10px 14px;
            font-size: 0.9rem; font-weight: 500; background: #fafbfc; transition: var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-club); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); background: white;
        }

        .btn-generar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white; border: none; font-weight: 600; padding: 8px 20px;
            border-radius: 12px; transition: var(--transition);
        }
        .btn-generar:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3); }
        .btn-imprimir {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; border: none; font-weight: 600; padding: 8px 20px;
            border-radius: 12px; transition: var(--transition);
        }
        .btn-imprimir:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        .report-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            overflow: hidden;
        }
        .report-card .card-header {
            background: #0f172a;
            color: white;
            padding: 16px 24px;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .report-card .card-body { padding: 24px; }

        .table-report {
            margin-bottom: 0;
        }
        .table-report thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
        }
        .table-report tbody td {
            padding: 10px 16px;
            vertical-align: middle;
            font-weight: 500;
        }
        .table-report tbody tr:hover { background: #f8fafc; }

        .badge-puntaje {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .report-card { border: none; box-shadow: none; }
            .table-report { font-size: 12px; }
        }

        @media (max-width: 768px) {
            .informes-card { padding: 24px 18px; border-radius: 22px; }
            .informes-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-informes no-print">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="informes-card">

            <div class="informes-header">
                <h2><i class="bi bi-file-earmark-bar-graph me-2" style="color: var(--accent-club);"></i>Informes</h2>
                <p>Genera reportes del club listos para imprimir o guardar como PDF.</p>
            </div>

            <!-- Formulario de selección -->
            <form method="get" class="row g-2 mb-4 no-print">
                <div class="col-md-4">
                    <select name="tipo" class="form-select">
                        <option value="especialidades" <?= $tipo_informe == 'especialidades' ? 'selected' : '' ?>>Especialidades por Clase</option>
                        <option value="clases" <?= $tipo_informe == 'clases' ? 'selected' : '' ?>>Estado de Clases</option>
                        <option value="ranking" <?= $tipo_informe == 'ranking' ? 'selected' : '' ?>>Ranking de Puntajes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="anio" class="form-control" value="<?= $anio ?>" min="2020" max="<?= date('Y') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="generar" class="btn btn-generar w-100">
                        <i class="bi bi-search me-1"></i> Generar
                    </button>
                </div>
                <?php if ($mostrar): ?>
                <div class="col-md-2">
                    <button type="button" class="btn btn-imprimir w-100" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimir
                    </button>
                </div>
                <?php endif; ?>
            </form>

            <?php if ($mostrar): ?>
                <div class="report-card">
                    <div class="card-header">
                        <?= $titulos[$tipo_informe] ?> - Año <?= $anio ?>
                    </div>
                    <div class="card-body">
                        <?php if ($tipo_informe == 'especialidades'): ?>
                            <!-- INFORME 1: ESPECIALIDADES POR CLASE -->
                            <?php
                            $query = $conn->query("
                                SELECT u.nombre, u.apellido, cr.nombre AS clase, cr.edad_requerida,
                                       GROUP_CONCAT(e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') AS especialidades,
                                       COUNT(le.id) AS total_esp
                                FROM miembros m
                                JOIN usuarios u ON m.usuario_id = u.id
                                JOIN clases_regulares cr ON m.clase_actual_id = cr.id
                                LEFT JOIN logros_especialidad le ON m.id = le.miembro_id AND le.anio = $anio
                                LEFT JOIN especialidades e ON le.especialidad_id = e.id
                                WHERE m.tipo = 'conquistador' AND m.activo = 1
                                GROUP BY m.id, u.nombre, u.apellido, cr.nombre, cr.edad_requerida
                                ORDER BY cr.edad_requerida, u.apellido, u.nombre
                            ");

                            $por_clase = [];
                            while ($r = $query->fetch_assoc()) {
                                $por_clase[$r['clase']][] = $r;
                            }
                            ksort($por_clase);
                            ?>
                            <?php if (!empty($por_clase)): ?>
                                <?php foreach ($por_clase as $clase => $miembros): ?>
                                    <h5 class="mt-3"><?= badge_clase($clase) ?> <small class="text-muted">(<?= count($miembros) ?> miembros)</small></h5>
                                    <table class="table table-bordered table-sm table-report">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Especialidades</th>
                                                <th>Cantidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($miembros as $m): ?>
                                                <tr>
                                                    <td><?= $m['nombre'] . ' ' . $m['apellido'] ?></td>
                                                    <td><?= $m['especialidades'] ?: '—' ?></td>
                                                    <td><?= $m['total_esp'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No hay especialidades registradas en <?= $anio ?>.</p>
                            <?php endif; ?>

                        <?php elseif ($tipo_informe == 'clases'): ?>
                            <!-- INFORME 2: ESTADO DE CLASES -->
                            <?php
                            $clases_reg = $conn->query("SELECT id, nombre, edad_requerida FROM clases_regulares ORDER BY edad_requerida");
                            $hay_datos = false;
                            while ($clase = $clases_reg->fetch_assoc()):
                                $clase_id = $clase['id'];
                                $clase_avanzada = $conn->query("SELECT id FROM clases_avanzadas WHERE clase_regular_id = $clase_id LIMIT 1")->fetch_assoc();
                                $avanzada_id = $clase_avanzada['id'] ?? 0;

                                $miembros = $conn->query("
                                    SELECT u.nombre, u.apellido, u.dni,
                                           (SELECT COUNT(*) FROM logros_clase_regular lcr WHERE lcr.miembro_id = m.id AND lcr.clase_regular_id = m.clase_actual_id AND lcr.anio = $anio) AS regular,
                                           (SELECT COUNT(*) FROM logros_clase_avanzada lca WHERE lca.miembro_id = m.id AND lca.clase_avanzada_id = (SELECT id FROM clases_avanzadas WHERE clase_regular_id = m.clase_actual_id LIMIT 1) AND lca.anio = $anio) AS avanzada
                                    FROM miembros m
                                    JOIN usuarios u ON m.usuario_id = u.id
                                    WHERE m.tipo = 'conquistador' AND m.activo = 1 AND m.clase_actual_id = $clase_id
                                    ORDER BY u.apellido, u.nombre
                                ");
                                if ($miembros->num_rows > 0):
                                    $hay_datos = true;
                            ?>
                                <h5 class="mt-3"><?= badge_clase($clase['nombre']) ?></h5>
                                <table class="table table-bordered table-sm table-report">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Regular</th>
                                            <th>Avanzada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($m = $miembros->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $m['nombre'] . ' ' . $m['apellido'] ?></td>
                                                <td><?= $m['regular'] ? '✅ Sí' : '❌ No' ?></td>
                                                <td><?= $avanzada_id ? ($m['avanzada'] ? '✅ Sí' : '❌ No') : '—' ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php endif; endwhile; ?>
                            <?php if (!$hay_datos): ?>
                                <p class="text-muted">No hay miembros para mostrar en <?= $anio ?>.</p>
                            <?php endif; ?>

                        <?php elseif ($tipo_informe == 'ranking'): ?>
                            <!-- INFORME 3: RANKING DE PUNTAJES -->
                            <?php
                            $ranking = $conn->query("
                                SELECT u.nombre, u.apellido, un.nombre AS unidad,
                                       COALESCE(SUM(pa.puntaje_total), 0) AS puntaje_total
                                FROM miembros m
                                JOIN usuarios u ON m.usuario_id = u.id
                                LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
                                LEFT JOIN unidades un ON mu.unidad_id = un.id
                                LEFT JOIN participacion_actividades pa ON m.id = pa.miembro_id
                                LEFT JOIN actividades a ON pa.actividad_id = a.id AND a.anio = $anio
                                WHERE m.tipo = 'conquistador' AND m.activo = 1
                                GROUP BY m.id, u.nombre, u.apellido, un.nombre
                                HAVING puntaje_total > 0
                                ORDER BY puntaje_total DESC
                            ");
                            $pos = 0;
                            ?>
                            <table class="table table-bordered table-sm table-report">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>Posición</th>
                                        <th>Nombre</th>
                                        <th>Unidad</th>
                                        <th>Puntaje Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($r = $ranking->fetch_assoc()): $pos++; ?>
                                        <tr>
                                            <td><?= $pos ?>°</td>
                                            <td><?= $r['nombre'] . ' ' . $r['apellido'] ?></td>
                                            <td><?= $r['unidad'] ?? '—' ?></td>
                                            <td><span class="badge-puntaje"><?= number_format($r['puntaje_total'], 1) ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if ($pos == 0): ?>
                                        <tr><td colspan="4" class="text-muted">No hay puntajes registrados en <?= $anio ?>.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>