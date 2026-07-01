<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director']);

// Umbrales configurables
define('UMBRAL_SEGUNDO', 0.20);
define('UMBRAL_TERCERO_LIDER', 0.35);
define('UMBRAL_TERCERO_SEGUNDO', 0.25);

$anio = $_POST['anio'] ?? date('Y');
$mes_actual = date('n');
$permitido = ($mes_actual == 11); // Solo noviembre
$mensaje = '';
$error = '';
$previa = null; // Para vista previa sin guardar

// Lógica de vista previa (siempre disponible)
if (isset($_POST['vista_previa'])) {
    $ranking = $conn->query("
        SELECT m.id AS miembro_id, u.nombre, u.apellido, un.nombre AS unidad,
               COALESCE(SUM(pa.puntaje_total), 0) AS puntaje_total
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
    $puntajes = [];
    while ($r = $ranking->fetch_assoc()) {
        $puntajes[] = $r;
    }
    $previa = calcular_ganadores($puntajes);
}

// Lógica de consagración (solo en noviembre)
if ($permitido && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consagrar'])) {
    $check = $conn->query("SELECT COUNT(*) as total FROM ganadores_anuales WHERE anio = $anio")->fetch_assoc()['total'];
    
    if ($check > 0) {
        $error = "Ya existen ganadores registrados para el año $anio.";
    } else {
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
        
        $ganadores = calcular_ganadores($puntajes);
        
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

/**
 * Calcula los ganadores según el algoritmo de saltos.
 * @param array $puntajes Array de arrays con 'miembro_id' y 'puntaje_total'
 * @return array Array de ganadores con 'miembro_id', 'posicion' y 'puntaje'
 */
function calcular_ganadores($puntajes) {
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
    return $ganadores;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consagrar Ganadores · Club Betelgeuse</title>
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

        .navbar-consagrar {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-consagrar .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-consagrar .navbar-brand:hover { color: var(--accent-club) !important; }

        .consagrar-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .consagrar-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .consagrar-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        .form-select, .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            background: #fafbfc;
            color: #0f172a;
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-club);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            background: white;
            outline: none;
        }

        .btn-consagrar {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 14px 36px;
            border-radius: 16px;
            transition: var(--transition);
            box-shadow: 0 8px 20px -8px rgba(245, 158, 11, 0.3);
        }
        .btn-consagrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -6px rgba(245, 158, 11, 0.4);
        }
        .btn-vista {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 10px 24px;
            border-radius: 14px;
            transition: var(--transition);
            box-shadow: 0 8px 20px -8px rgba(30, 64, 175, 0.3);
        }
        .btn-vista:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -6px rgba(30, 64, 175, 0.4);
        }

        .ganador-card {
            background: linear-gradient(135deg, #fffbeb, #fff7ed);
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 16px;
        }
        .ganador-card h5 { font-weight: 700; color: #0f172a; }
        .ganador-card .puntaje {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-club);
        }

        .alert-success {
            border-radius: 16px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            color: #065f46;
            font-weight: 600;
        }
        .alert-danger {
            border-radius: 16px;
            border-left: 4px solid #ef4444;
            background: #fef2f2;
            color: #991b1b;
            font-weight: 500;
        }
        .alert-info {
            border-radius: 16px;
            border-left: 4px solid #3b82f6;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
        }

        .restriction-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .consagrar-card { padding: 24px 18px; border-radius: 22px; }
            .consagrar-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-consagrar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="consagrar-card">

            <div class="consagrar-header">
                <h2><i class="bi bi-trophy-fill me-2" style="color: var(--accent-club);"></i>Consagrar Ganadores del Año</h2>
                <p>Calcula automáticamente los conquistadores del año usando el algoritmo de saltos.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div><?= $mensaje ?></div>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <!-- Selector de año y vista previa (siempre disponible) -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="post" class="d-flex gap-2">
                        <input type="number" name="anio" class="form-control" value="<?= $anio ?>" min="2020" max="<?= date('Y') ?>" style="max-width: 150px;">
                        <button type="submit" name="vista_previa" class="btn btn-vista">
                            <i class="bi bi-eye me-2"></i> Vista Previa
                        </button>
                    </form>
                </div>
            </div>

            <!-- Resultado de la vista previa -->
            <?php if ($previa !== null): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-eye me-2"></i>Vista Previa de Ganadores <?= $anio ?></h5>
                <?php if (!empty($previa)): ?>
                    <div class="row mb-4">
                        <?php $medallas = [1=>'🥇',2=>'🥈',3=>'🥉']; ?>
                        <?php foreach ($previa as $g): ?>
                            <div class="col-md-4">
                                <div class="ganador-card">
                                    <div style="font-size: 3rem;"><?= $medallas[$g['posicion']] ?? '⭐' ?></div>
                                    <h5><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></h5>
                                    <p class="text-muted mb-2"><?= htmlspecialchars($g['unidad'] ?? '') ?></p>
                                    <div class="puntaje"><?= number_format($g['puntaje_total'], 1) ?> pts</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No hay suficientes datos para determinar ganadores en <?= $anio ?>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Botón de consagrar (solo noviembre) -->
            <?php if (!$permitido): ?>
                <div class="restriction-box mt-4">
                    <i class="bi bi-lock-fill" style="font-size: 3rem; color: #f59e0b;"></i>
                    <h5 class="mt-3 fw-bold">Consagración no disponible</h5>
                    <p class="text-muted">La consagración oficial de ganadores solo está habilitada durante el mes de <strong>noviembre</strong>.</p>
                    <p class="badge bg-warning text-dark fs-6">Mes actual: <?= date('F') ?></p>
                </div>
            <?php else: ?>
                <form method="post" class="mt-4">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <button type="submit" name="consagrar" class="btn btn-consagrar w-100"
                            onclick="return confirm('¿Estás seguro de consagrar los ganadores de <?= $anio ?>? Esta acción no se puede deshacer.')">
                        <i class="bi bi-star-fill me-2"></i> Consagrar Ganadores Oficiales
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>