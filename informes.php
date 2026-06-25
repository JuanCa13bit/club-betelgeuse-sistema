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
    <title>Informes - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .card { border: none; box-shadow: none; }
            .table { font-size: 12px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark no-print">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver al Panel</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-file-earmark-bar-graph"></i> Informes</h2>

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
                <button type="submit" name="generar" class="btn btn-primary w-100"><i class="bi bi-search"></i> Generar</button>
            </div>
            <?php if ($mostrar): ?>
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($mostrar): ?>
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><?= $titulos[$tipo_informe] ?> - Año <?= $anio ?></h4>
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
                            <h5 class="mt-3">📘 Clase: <?= $clase ?></h5>
                            <table class="table table-bordered table-sm">
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
                        <h5 class="mt-3">📘 Clase: <?= $clase['nombre'] ?></h5>
                        <table class="table table-bordered table-sm">
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
                    <table class="table table-bordered table-sm">
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
                                    <td><?= number_format($r['puntaje_total'], 1) ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>