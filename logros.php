<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$clase_seleccionada = $_GET['clase'] ?? $_POST['clase'] ?? '';
$miembro_id = $_GET['miembro'] ?? $_POST['miembro_id'] ?? null;
$exito = false;
$errores = [];

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $miembro_id = intval($_POST['miembro_id']);
    $tipo_miembro = $_POST['tipo_miembro'] ?? 'conquistador';

    // --- Clases regulares (conquistador: un solo checkbox, líder: array) ---
    if ($tipo_miembro === 'conquistador') {
        $completo_regular = isset($_POST['completo_regular']);
        $clase_regular_id = $_POST['clase_regular_id'] ?? null;
        if ($completo_regular && $clase_regular_id) {
            $conn->query("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES ($miembro_id, $clase_regular_id, YEAR(CURDATE()))");
        }
        // Avanzada (conquistador, un solo checkbox)
        $completo_avanzada = isset($_POST['completo_avanzada']);
        $clase_avanzada_id = $_POST['clase_avanzada_id'] ?? null;
        if ($completo_avanzada && $clase_avanzada_id) {
            $conn->query("INSERT IGNORE INTO logros_clase_avanzada (miembro_id, clase_avanzada_id, anio) VALUES ($miembro_id, $clase_avanzada_id, YEAR(CURDATE()))");
        }
    } else { // líder
        $regular_ids = $_POST['regular_ids'] ?? '';
        $avanzada_ids = $_POST['avanzada_ids'] ?? '';
        $regulares = !empty($regular_ids) ? explode(',', $regular_ids) : [];
        $avanzadas = !empty($avanzada_ids) ? explode(',', $avanzada_ids) : [];

        $insertados_reg = 0;
        foreach ($regulares as $rid) {
            $rid = intval($rid);
            if ($rid > 0) {
                $existe = $conn->query("SELECT id FROM logros_clase_regular WHERE miembro_id = $miembro_id AND clase_regular_id = $rid")->num_rows;
                if (!$existe) {
                    $conn->query("INSERT INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES ($miembro_id, $rid, YEAR(CURDATE()))");
                    $insertados_reg++;
                }
            }
        }

        $insertados_av = 0;
        foreach ($avanzadas as $aid) {
            $aid = intval($aid);
            if ($aid > 0) {
                $existe = $conn->query("SELECT id FROM logros_clase_avanzada WHERE miembro_id = $miembro_id AND clase_avanzada_id = $aid")->num_rows;
                if (!$existe) {
                    $conn->query("INSERT INTO logros_clase_avanzada (miembro_id, clase_avanzada_id, anio) VALUES ($miembro_id, $aid, YEAR(CURDATE()))");
                    $insertados_av++;
                }
            }
        }
        if ($insertados_reg == 0 && $insertados_av == 0 && (count($regulares) + count($avanzadas)) > 0) {
            $errores[] = "Todas las clases seleccionadas ya estaban registradas anteriormente.";
        }
    }

    // Especialidades (común)
    $especialidades_input = $_POST['especialidades'] ?? '';
    $especialidades = !empty($especialidades_input) ? explode(',', $especialidades_input) : [];
    foreach ($especialidades as $eid) {
        $eid = intval($eid);
        if ($eid > 0) {
            $conn->query("INSERT IGNORE INTO logros_especialidad (miembro_id, especialidad_id, anio) VALUES ($miembro_id, $eid, YEAR(CURDATE()))");
        }
    }
    if (empty($errores)) $exito = true;
}

// Obtener miembros según selección
$miembros_clase = [];
if (!empty($clase_seleccionada)) {
    if ($clase_seleccionada === 'lideres') {
        $stmt = $conn->prepare("
            SELECT DISTINCT u.nombre, u.apellido, u.dni, m.id AS miembro_id
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.activo = 1 AND m.tipo = 'lider'
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT u.nombre, u.apellido, u.dni, m.id AS miembro_id
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.activo = 1 AND m.tipo = 'conquistador'
            AND m.clase_actual_id = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->bind_param("i", $clase_seleccionada);
        $stmt->execute();
    }
    $miembros_clase = $stmt->get_result();
}

// Datos del miembro seleccionado
$miembro_info = null;
$especialidades_ya_tiene = [];
$regulares_existentes = [];
$avanzadas_existentes = [];

if ($miembro_id) {
    $tipo_miembro = $conn->query("SELECT tipo FROM miembros WHERE id = $miembro_id")->fetch_row()[0];
    
    if ($tipo_miembro === 'conquistador') {
        $stmt = $conn->prepare("
            SELECT u.nombre, u.apellido, u.dni, u.fecha_nacimiento, m.id AS miembro_id, 
                   cr.id AS clase_regular_id, cr.nombre AS clase_regular,
                   ca.id AS clase_avanzada_id, ca.nombre AS clase_avanzada
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            JOIN clases_regulares cr ON cr.id = m.clase_actual_id
            LEFT JOIN clases_avanzadas ca ON ca.clase_regular_id = cr.id
            WHERE m.id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT u.nombre, u.apellido, u.dni, u.fecha_nacimiento, m.id AS miembro_id, 
                   NULL AS clase_regular_id, 'Líder' AS clase_regular,
                   NULL AS clase_avanzada_id, NULL AS clase_avanzada
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.id = ?
        ");
        // Obtener clases ya completadas por el líder
        $res_reg = $conn->query("SELECT clase_regular_id FROM logros_clase_regular WHERE miembro_id = $miembro_id");
        while ($r = $res_reg->fetch_assoc()) $regulares_existentes[] = (int)$r['clase_regular_id'];
        $res_av = $conn->query("SELECT clase_avanzada_id FROM logros_clase_avanzada WHERE miembro_id = $miembro_id");
        while ($r = $res_av->fetch_assoc()) $avanzadas_existentes[] = (int)$r['clase_avanzada_id'];
    }
    $stmt->bind_param("i", $miembro_id);
    $stmt->execute();
    $miembro_info = $stmt->get_result()->fetch_assoc();
    
    // Especialidades que ya tiene
    $stmt2 = $conn->prepare("SELECT especialidad_id FROM logros_especialidad WHERE miembro_id = ?");
    $stmt2->bind_param("i", $miembro_id);
    $stmt2->execute();
    $especialidades_ya_tiene = array_column($stmt2->get_result()->fetch_all(MYSQLI_ASSOC), 'especialidad_id');
}

$clases = $conn->query("SELECT id, nombre, edad_requerida FROM clases_regulares ORDER BY edad_requerida");
$categorias = $conn->query("SELECT id, nombre_categoria FROM categorias_especialidades ORDER BY nombre_categoria");
$avanzadas = $conn->query("SELECT ca.id, ca.nombre, cr.nombre AS clase_base FROM clases_avanzadas ca JOIN clases_regulares cr ON ca.clase_regular_id = cr.id ORDER BY ca.edad_min");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestionar Logros · Club Betelgeuse</title>
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

        .navbar-logros {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-logros .navbar-brand {
            font-weight: 700; font-size: 1.1rem; color: white !important; transition: var(--transition);
        }
        .navbar-logros .navbar-brand:hover { color: var(--accent-club) !important; }

        .logros-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .logros-header h2 { font-weight: 800; font-size: 1.8rem; color: #0f172a; }
        .logros-header p { color: #64748b; font-weight: 500; }

        .form-select, .form-control {
            border-radius: 12px; border: 1px solid #e2e8f0; padding: 10px 14px;
            font-size: 0.9rem; font-weight: 500; background: #fafbfc; transition: var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-club); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); background: white;
        }

        .cart-card {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 20px;
        }
        .cart-card h5 { font-weight: 700; color: #0f172a; }

        .btn-guardar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white; border: none; font-weight: 700; font-size: 1rem;
            padding: 12px 32px; border-radius: 14px; transition: var(--transition);
            box-shadow: 0 8px 20px -8px rgba(30, 64, 175, 0.3);
        }
        .btn-guardar:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -6px rgba(30, 64, 175, 0.4); }

        .alert-success { border-radius: 16px; border-left: 4px solid #10b981; background: #f0fdf4; color: #065f46; font-weight: 600; }
        .alert-danger { border-radius: 16px; border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; font-weight: 500; }
        .maestria-item { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; margin-bottom: 8px; }

        @media (max-width: 768px) {
            .logros-card { padding: 24px 18px; border-radius: 22px; }
            .logros-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-logros">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel</a>
        </div>
    </nav>

    <div class="container">
        <div class="logros-card">
            <div class="logros-header">
                <h2><i class="bi bi-journal-plus me-2" style="color: var(--accent-club);"></i>Gestionar Logros del Año <?= date('Y') ?></h2>
                <p>Registra las clases completadas y las especialidades ganadas por cada miembro.</p>
            </div>

            <?php if ($exito): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-check-circle-fill fs-4"></i> <div>Logros guardados correctamente.</div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div>
                        <?php foreach ($errores as $e): ?>
                            <?= $e ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Paso 1: Seleccionar grupo -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Paso 1: Seleccionar Grupo</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-6">
                            <select name="clase" class="form-select" onchange="this.form.submit()">
                                <option value="">Seleccionar grupo...</option>
                                <option value="lideres" <?= $clase_seleccionada=='lideres'?'selected':'' ?>>👥 Líderes</option>
                                <optgroup label="Clases Regulares">
                                    <?php while ($c = $clases->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" <?= $clase_seleccionada==$c['id']?'selected':'' ?>>
                                            <?= $c['nombre'] ?> (<?= $c['edad_requerida'] ?> años)
                                        </option>
                                    <?php endwhile; ?>
                                </optgroup>
                            </select>
                        </div>
                    </form>

                    <?php if ($miembros_clase && $miembros_clase->num_rows > 0): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>Nombre</th><th>DNI</th><th>Acción</th></tr></thead>
                                <tbody>
                                    <?php while ($m = $miembros_clase->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?></strong></td>
                                            <td class="text-secondary"><?= $m['dni'] ?></td>
                                            <td>
                                                <a href="logros.php?clase=<?= $clase_seleccionada ?>&miembro=<?= $m['miembro_id'] ?>" 
                                                   class="btn btn-sm <?= $miembro_id==$m['miembro_id']?'btn-primary':'btn-outline-primary' ?> rounded-pill">
                                                    <i class="bi bi-pencil"></i> Seleccionar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($clase_seleccionada): ?>
                        <p class="text-muted mt-3">No hay miembros activos en este grupo.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Paso 2: Registrar logros del miembro -->
            <?php if ($miembro_info): ?>
                <?php $tipo = $miembro_info['clase_regular_id'] ? 'conquistador' : 'lider'; ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success text-white rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-person-check-fill me-2"></i>
                            <?= htmlspecialchars($miembro_info['nombre'] . ' ' . $miembro_info['apellido']) ?> 
                            <small class="text-white-50">(DNI: <?= $miembro_info['dni'] ?>)</small>
                        </h5>
                        <?php if ($tipo === 'conquistador'): ?>
                            <span><?= badge_clase($miembro_info['clase_regular']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" id="formLogros">
                            <input type="hidden" name="miembro_id" value="<?= $miembro_id ?>">
                            <input type="hidden" name="clase" value="<?= $clase_seleccionada ?>">
                            <input type="hidden" name="tipo_miembro" value="<?= $tipo ?>">

                            <?php if ($tipo === 'conquistador'): ?>
                                <!-- Conquistador: checkboxes simples -->
                                <input type="hidden" name="clase_regular_id" value="<?= $miembro_info['clase_regular_id'] ?>">
                                <input type="hidden" name="clase_avanzada_id" value="<?= $miembro_info['clase_avanzada_id'] ?>">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="completo_regular" id="completo_regular">
                                            <label class="form-check-label" for="completo_regular">
                                                ✅ Completó clase regular: <strong><?= badge_clase($miembro_info['clase_regular']) ?></strong>
                                            </label>
                                        </div>
                                    </div>
                                    <?php if ($miembro_info['clase_avanzada_id']): ?>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="completo_avanzada" id="completo_avanzada">
                                                <label class="form-check-label" for="completo_avanzada">
                                                    ⭐ Completó clase avanzada: <strong><?= $miembro_info['clase_avanzada'] ?></strong>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Líder: carritos de clases regulares y avanzadas -->
                                <div class="cart-card">
                                    <h5><i class="bi bi-book-fill me-2"></i>Clases Regulares</h5>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-5">
                                            <select id="regularSelect" class="form-select">
                                                <option value="">Seleccionar clase regular</option>
                                                <?php $clases->data_seek(0); while ($c = $clases->fetch_assoc()): ?>
                                                    <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success w-100 rounded-pill" onclick="agregarRegular()">
                                                <i class="bi bi-plus-lg"></i> Agregar
                                            </button>
                                        </div>
                                    </div>
                                    <table class="table table-sm table-borderless" id="tablaRegulares">
                                        <thead><tr><th>Clase</th><th></th></tr></thead>
                                        <tbody id="tbodyRegulares">
                                            <tr id="filaVaciaRegular"><td colspan="2" class="text-muted text-center">No se han añadido clases regulares</td></tr>
                                        </tbody>
                                    </table>
                                    <input type="hidden" name="regular_ids" id="regularInput">
                                </div>

                                <div class="cart-card">
                                    <h5><i class="bi bi-star-fill me-2"></i>Clases Avanzadas</h5>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-5">
                                            <select id="avanzadaSelect" class="form-select">
                                                <option value="">Seleccionar clase avanzada</option>
                                                <?php $avanzadas->data_seek(0); while ($a = $avanzadas->fetch_assoc()): ?>
                                                    <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?> (<?= $a['clase_base'] ?>)</option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success w-100 rounded-pill" onclick="agregarAvanzada()">
                                                <i class="bi bi-plus-lg"></i> Agregar
                                            </button>
                                        </div>
                                    </div>
                                    <table class="table table-sm table-borderless" id="tablaAvanzadas">
                                        <thead><tr><th>Clase</th><th></th></tr></thead>
                                        <tbody id="tbodyAvanzadas">
                                            <tr id="filaVaciaAvanzada"><td colspan="2" class="text-muted text-center">No se han añadido clases avanzadas</td></tr>
                                        </tbody>
                                    </table>
                                    <input type="hidden" name="avanzada_ids" id="avanzadaInput">
                                </div>
                            <?php endif; ?>

                            <!-- Especialidades (común) -->
                            <div class="cart-card">
                                <h5><i class="bi bi-star-fill text-warning me-2"></i>Especialidades</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <select id="categoriaSelect" class="form-select" onchange="cargarEspecialidades()">
                                            <option value="">1. Seleccionar categoría</option>
                                            <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
                                                <option value="<?= $cat['id'] ?>"><?= $cat['nombre_categoria'] ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <select id="especialidadSelect" class="form-select" disabled onchange="habilitarAgregar()">
                                            <option value="">2. Seleccionar especialidad</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" id="btnAgregar" class="btn btn-success w-100 rounded-pill" disabled onclick="agregarEspecialidad()">
                                            <i class="bi bi-plus-lg"></i> Agregar
                                        </button>
                                    </div>
                                </div>
                                <table class="table table-sm table-borderless" id="tablaEspecialidades">
                                    <thead><tr><th>Especialidad</th><th>Categoría</th><th></th></tr></thead>
                                    <tbody id="tbodyEspecialidades">
                                        <tr id="filaVaciaEsp"><td colspan="3" class="text-muted text-center">No hay especialidades agregadas</td></tr>
                                    </tbody>
                                </table>
                                <input type="hidden" name="especialidades" id="especialidadesInput">
                            </div>

                            <!-- Maestrías -->
                            <div class="cart-card">
                                <h5><i class="bi bi-award-fill text-warning me-2"></i>Progreso de Maestrías</h5>
                                <?php
                                $maestrias = $conn->query("SELECT * FROM maestrias ORDER BY id");
                                $alguna_maestria = false;
                                while ($maestria = $maestrias->fetch_assoc()) {
                                    $requisitos = $conn->query("
                                        SELECT mr.*, c.nombre_categoria
                                        FROM maestria_requisitos mr
                                        LEFT JOIN categorias_especialidades c ON mr.categoria_id = c.id
                                        WHERE mr.maestria_id = {$maestria['id']}
                                    ");
                                    if ($requisitos->num_rows === 0) continue;
                                    $cumplido = true; $detalle = []; $total_necesario = 0; $total_actual = 0;
                                    while ($req = $requisitos->fetch_assoc()) {
                                        $necesarias = $req['cantidad_minima'];
                                        if (!empty($req['categoria_id']) && empty($req['especialidad_id'])) {
                                            $count = $conn->query("
                                                SELECT COUNT(*) as total 
                                                FROM logros_especialidad le 
                                                JOIN especialidades e ON le.especialidad_id = e.id 
                                                WHERE le.miembro_id = $miembro_id AND e.categoria_id = {$req['categoria_id']}
                                            ")->fetch_assoc()['total'];
                                            $cat_nombre = $req['nombre_categoria'] ?? 'Cat ' . $req['categoria_id'];
                                            $detalle[] = "{$cat_nombre}: {$count}/{$necesarias}";
                                            $total_necesario += $necesarias;
                                            $total_actual += min($count, $necesarias);
                                            if ($count < $necesarias) $cumplido = false;
                                        } elseif (!empty($req['especialidad_id'])) {
                                            $tiene = $conn->query("
                                                SELECT COUNT(*) as total 
                                                FROM logros_especialidad 
                                                WHERE miembro_id = $miembro_id AND especialidad_id = {$req['especialidad_id']}
                                            ")->fetch_assoc()['total'];
                                            $esp = $conn->query("SELECT nombre_especialidad FROM especialidades WHERE id = {$req['especialidad_id']}")->fetch_assoc();
                                            $esp_nombre = $esp['nombre_especialidad'] ?? 'Esp ' . $req['especialidad_id'];
                                            $detalle[] = "{$esp_nombre}: {$tiene}/{$necesarias}";
                                            $total_necesario += $necesarias;
                                            $total_actual += min($tiene, $necesarias);
                                            if ($tiene < $necesarias) $cumplido = false;
                                        }
                                    }
                                    if ($total_necesario > 0) {
                                        $alguna_maestria = true;
                                        $porcentaje = round(($total_actual / $total_necesario) * 100);
                                        $bg_color = $cumplido ? 'success' : ($porcentaje >= 50 ? 'warning' : 'secondary');
                                ?>
                                        <div class="maestria-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong><?= htmlspecialchars($maestria['nombre_maestria']) ?></strong>
                                                <?php if ($cumplido): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> ¡Completada!</span>
                                                <?php else: ?>
                                                    <span class="badge bg-<?= $bg_color ?>"><?= $porcentaje ?>%</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar bg-<?= $bg_color ?>" style="width: <?= $porcentaje ?>%"></div>
                                            </div>
                                            <small class="text-muted mt-1 d-block"><?= implode(' | ', $detalle) ?></small>
                                        </div>
                                <?php }
                                }
                                if (!$alguna_maestria) {
                                    echo '<p class="text-muted">No hay maestrías configuradas o el miembro no tiene especialidades.</p>';
                                }
                                ?>
                            </div>

                            <button type="submit" name="guardar" class="btn btn-guardar w-100 mt-3">
                                <i class="bi bi-save me-2"></i> Guardar Todos los Logros
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Precarga de datos existentes
        var regularesYaTiene = <?= json_encode($regulares_existentes) ?>;
        var avanzadasYaTiene = <?= json_encode($avanzadas_existentes) ?>;
        var especialidadesYaTiene = <?= json_encode($especialidades_ya_tiene) ?>;
        
        var regularesAgregadas = regularesYaTiene.slice();
        var avanzadasAgregadas = avanzadasYaTiene.slice();
        var especialidadesAgregadas = [];

        // Inicializar tablas con datos existentes
        window.addEventListener('DOMContentLoaded', function() {
            actualizarTabla('Regulares');
            actualizarTabla('Avanzadas');
            actualizarInputs();
        });

        // --- Funciones para clases regulares (líder) ---
        function agregarRegular() {
            var select = document.getElementById('regularSelect');
            var id = select.value;
            if (!id) return;
            if (regularesAgregadas.includes(id)) { alert('Esta clase ya está en la lista.'); return; }
            regularesAgregadas.push(id);
            actualizarTabla('Regulares');
            actualizarInputs();
            select.value = '';
        }

        function quitarRegular(id) {
            regularesAgregadas = regularesAgregadas.filter(e => e !== id);
            actualizarTabla('Regulares');
            actualizarInputs();
        }

        // --- Funciones para clases avanzadas (líder) ---
        function agregarAvanzada() {
            var select = document.getElementById('avanzadaSelect');
            var id = select.value;
            if (!id) return;
            if (avanzadasAgregadas.includes(id)) { alert('Esta clase ya está en la lista.'); return; }
            avanzadasAgregadas.push(id);
            actualizarTabla('Avanzadas');
            actualizarInputs();
            select.value = '';
        }

        function quitarAvanzada(id) {
            avanzadasAgregadas = avanzadasAgregadas.filter(e => e !== id);
            actualizarTabla('Avanzadas');
            actualizarInputs();
        }

        // --- Funciones para especialidades (común) ---
        function cargarEspecialidades() {
            var catId = document.getElementById('categoriaSelect').value;
            var espSelect = document.getElementById('especialidadSelect');
            espSelect.innerHTML = '<option value="">Cargando...</option>';
            espSelect.disabled = true;
            document.getElementById('btnAgregar').disabled = true;
            if (!catId) return;
            var excluir = especialidadesYaTiene.concat(especialidadesAgregadas);
            fetch('get_especialidades.php?categoria=' + catId + '&excluir=' + JSON.stringify(excluir))
                .then(r => r.json())
                .then(data => {
                    espSelect.innerHTML = '<option value="">2. Seleccionar especialidad</option>';
                    data.forEach(e => { espSelect.innerHTML += `<option value="${e.id}">${e.nombre_especialidad}</option>`; });
                    espSelect.disabled = false;
                });
        }

        function habilitarAgregar() {
            document.getElementById('btnAgregar').disabled = !document.getElementById('especialidadSelect').value;
        }

        function agregarEspecialidad() {
            var espSelect = document.getElementById('especialidadSelect');
            var id = espSelect.value;
            var nombre = espSelect.options[espSelect.selectedIndex].text;
            var catNombre = document.getElementById('categoriaSelect').options[document.getElementById('categoriaSelect').selectedIndex].text;
            if (!id) return;
            if (especialidadesAgregadas.includes(id)) { alert('Esta especialidad ya está en la lista.'); return; }
            especialidadesAgregadas.push(id);
            actualizarTabla('Especialidades');
            actualizarInputs();
            espSelect.value = '';
            document.getElementById('btnAgregar').disabled = true;
            cargarEspecialidades();
        }

        function quitarEspecialidad(id) {
            especialidadesAgregadas = especialidadesAgregadas.filter(e => e !== id);
            actualizarTabla('Especialidades');
            actualizarInputs();
            cargarEspecialidades();
        }

        // --- Actualización visual de tablas ---
        function actualizarTabla(tipo) {
            var tbody, filaVacia, lista;
            if (tipo === 'Regulares') {
                tbody = document.getElementById('tbodyRegulares');
                filaVacia = document.getElementById('filaVaciaRegular');
                lista = regularesAgregadas;
            } else if (tipo === 'Avanzadas') {
                tbody = document.getElementById('tbodyAvanzadas');
                filaVacia = document.getElementById('filaVaciaAvanzada');
                lista = avanzadasAgregadas;
            } else if (tipo === 'Especialidades') {
                tbody = document.getElementById('tbodyEspecialidades');
                filaVacia = document.getElementById('filaVaciaEsp');
                lista = especialidadesAgregadas;
            }

            tbody.innerHTML = '';
            if (lista.length === 0) {
                tbody.innerHTML = `<tr id="${filaVacia.id}"><td colspan="3" class="text-muted text-center">No se han añadido elementos</td></tr>`;
            } else {
                lista.forEach(id => {
                    var nombre = '';
                    if (tipo === 'Regulares') {
                        var sel = document.getElementById('regularSelect');
                        if (sel) {
                            var opt = sel.querySelector('option[value="'+id+'"]');
                            nombre = opt ? opt.text : id;
                        }
                        tbody.innerHTML += `<tr><td>${nombre}</td><td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="quitarRegular('${id}')"><i class="bi bi-trash"></i></button></td></tr>`;
                    } else if (tipo === 'Avanzadas') {
                        var selA = document.getElementById('avanzadaSelect');
                        if (selA) {
                            var optA = selA.querySelector('option[value="'+id+'"]');
                            nombre = optA ? optA.text : id;
                        }
                        tbody.innerHTML += `<tr><td>${nombre}</td><td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="quitarAvanzada('${id}')"><i class="bi bi-trash"></i></button></td></tr>`;
                    } else if (tipo === 'Especialidades') {
                        var selE = document.getElementById('especialidadSelect');
                        if (selE) {
                            var optE = selE.querySelector('option[value="'+id+'"]');
                            nombre = optE ? optE.text : id;
                        }
                        var cat = document.getElementById('categoriaSelect').options[document.getElementById('categoriaSelect').selectedIndex].text;
                        tbody.innerHTML += `<tr><td>${nombre}</td><td>${cat}</td><td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="quitarEspecialidad('${id}')"><i class="bi bi-trash"></i></button></td></tr>`;
                    }
                });
            }
        }

        function actualizarInputs() {
            document.getElementById('regularInput').value = regularesAgregadas.join(',');
            document.getElementById('avanzadaInput').value = avanzadasAgregadas.join(',');
            document.getElementById('especialidadesInput').value = especialidadesAgregadas.join(',');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>