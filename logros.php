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
    $clase_regular_id = $_POST['clase_regular_id'] ?? null;
    $clase_avanzada_id = $_POST['clase_avanzada_id'] ?? null;
    $especialidades_input = $_POST['especialidades'] ?? '';
    $especialidades = !empty($especialidades_input) ? explode(',', $especialidades_input) : [];
    $completo_regular = isset($_POST['completo_regular']);
    $completo_avanzada = isset($_POST['completo_avanzada']);
    
    if ($completo_regular && $clase_regular_id) {
        $conn->query("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES ($miembro_id, $clase_regular_id, YEAR(CURDATE()))");
    }
    if ($completo_avanzada && $clase_avanzada_id) {
        $conn->query("INSERT IGNORE INTO logros_clase_avanzada (miembro_id, clase_avanzada_id, anio) VALUES ($miembro_id, $clase_avanzada_id, YEAR(CURDATE()))");
    }
    foreach ($especialidades as $eid) {
        $eid = intval($eid);
        if ($eid > 0) {
            $conn->query("INSERT IGNORE INTO logros_especialidad (miembro_id, especialidad_id, anio) VALUES ($miembro_id, $eid, YEAR(CURDATE()))");
        }
    }
    $exito = true;
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
            JOIN miembro_unidad mu ON m.id = mu.miembro_id
            JOIN unidades un ON mu.unidad_id = un.id
            WHERE m.activo = 1 AND m.tipo = 'conquistador'
            AND un.edad_min <= (SELECT edad_requerida FROM clases_regulares WHERE id = ?)
            AND un.edad_max >= (SELECT edad_requerida FROM clases_regulares WHERE id = ?)
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->bind_param("ii", $clase_seleccionada, $clase_seleccionada);
        $stmt->execute();
    }
    $miembros_clase = $stmt->get_result();
}

// Datos del miembro seleccionado
$miembro_info = null;
$especialidades_ya_tiene = [];
if ($miembro_id) {
    $tipo_miembro = $conn->query("SELECT tipo FROM miembros WHERE id = $miembro_id")->fetch_row()[0];
    
    if ($tipo_miembro === 'conquistador') {
        $stmt = $conn->prepare("
    SELECT u.nombre, u.apellido, u.dni, u.fecha_nacimiento, m.id AS miembro_id, 
           m.clase_actual_id AS clase_regular_id, 
           cr.nombre AS clase_regular,
           ca.id AS clase_avanzada_id, ca.nombre AS clase_avanzada
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN clases_regulares cr ON m.clase_actual_id = cr.id
    LEFT JOIN clases_avanzadas ca ON ca.clase_regular_id = cr.id
    WHERE m.id = ?
");
    } else {
        $stmt = $conn->prepare("
            SELECT u.nombre, u.apellido, u.dni, u.fecha_nacimiento, m.id AS miembro_id, 
                   NULL AS clase_regular_id, 'Líder (elegir manualmente)' AS clase_regular,
                   NULL AS clase_avanzada_id, NULL AS clase_avanzada
            FROM miembros m
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.id = ?
        ");
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

// Lista de clases para el selector
$clases = $conn->query("SELECT id, nombre, edad_requerida FROM clases_regulares ORDER BY edad_requerida");

// Lista de categorías para especialidades
$categorias = $conn->query("SELECT id, nombre_categoria FROM categorias_especialidades ORDER BY nombre_categoria");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestionar Logros - Club Betelgeuse</title>
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
        <h2><i class="bi bi-journal-plus"></i> Gestionar Logros del Año <?= date('Y') ?></h2>

        <?php if ($exito): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> Logros guardados correctamente.</div>
        <?php endif; ?>

        <!-- Paso 1: Seleccionar clase -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Paso 1: Seleccionar Grupo</h5>
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
                        <table class="table table-sm table-hover">
                            <thead class="table-secondary">
                                <tr><th>Nombre</th><th>DNI</th><th>Acción</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($m = $miembros_clase->fetch_assoc()): ?>
                                    <tr class="<?= $miembro_id==$m['miembro_id']?'table-active':'' ?>">
                                        <td><?= $m['nombre'] . ' ' . $m['apellido'] ?></td>
                                        <td><?= $m['dni'] ?></td>
                                        <td>
                                            <a href="logros.php?clase=<?= $clase_seleccionada ?>&miembro=<?= $m['miembro_id'] ?>" 
                                               class="btn btn-sm <?= $miembro_id==$m['miembro_id']?'btn-primary':'btn-outline-primary' ?>">
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
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        Paso 2: Logros de <?= $miembro_info['nombre'] . ' ' . $miembro_info['apellido'] ?> 
                        (DNI: <?= $miembro_info['dni'] ?>)
                        <?php if ($miembro_info['clase_regular_id']): ?>
                            - Clase: <?= $miembro_info['clase_regular'] ?>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" id="formLogros">
                        <input type="hidden" name="miembro_id" value="<?= $miembro_id ?>">
                        <input type="hidden" name="clase" value="<?= $clase_seleccionada ?>">
                        <input type="hidden" name="clase_regular_id" value="<?= $miembro_info['clase_regular_id'] ?>">
                        <input type="hidden" name="clase_avanzada_id" value="<?= $miembro_info['clase_avanzada_id'] ?>">
                        <input type="hidden" name="especialidades" id="especialidadesInput" value="">

                        <!-- Clases -->
                        <?php if ($clase_seleccionada !== 'lideres'): ?>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="completo_regular" id="completo_regular">
                                        <label class="form-check-label" for="completo_regular">
                                            ✅ Completó clase regular: <strong><?= $miembro_info['clase_regular'] ?></strong>
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
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Clase Regular Completada</label>
                                    <select name="clase_regular_id" class="form-select">
                                        <option value="">Ninguna</option>
                                        <?php 
                                        $clases->data_seek(0);
                                        while ($c = $clases->fetch_assoc()): ?>
                                            <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Clase Avanzada Completada</label>
                                    <select name="clase_avanzada_id" class="form-select">
                                        <option value="">Ninguna</option>
                                        <?php
                                        $avanzadas = $conn->query("SELECT ca.id, ca.nombre, cr.nombre AS clase_base FROM clases_avanzadas ca JOIN clases_regulares cr ON ca.clase_regular_id = cr.id ORDER BY ca.edad_min");
                                        while ($a = $avanzadas->fetch_assoc()): ?>
                                            <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?> (<?= $a['clase_base'] ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Especialidades -->
                        <h5><i class="bi bi-star"></i> Especialidades</h5>
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <select id="categoriaSelect" class="form-select" onchange="cargarEspecialidades()">
                                    <option value="">1. Seleccionar categoría</option>
                                    <?php 
                                    $categorias->data_seek(0);
                                    while ($cat = $categorias->fetch_assoc()): ?>
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
                                <button type="button" id="btnAgregar" class="btn btn-success w-100" disabled onclick="agregarEspecialidad()">
                                    <i class="bi bi-plus-lg"></i> Agregar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de especialidades agregadas -->
                        <table class="table table-sm table-bordered" id="tablaEspecialidades">
                            <thead class="table-warning">
                                <tr><th>Especialidad</th><th>Categoría</th><th></th></tr>
                            </thead>
                            <tbody id="tbodyEspecialidades">
                                <tr id="filaVacia"><td colspan="3" class="text-muted text-center">No hay especialidades agregadas</td></tr>
                            </tbody>
                        </table>

                        <!-- Maestrías -->
                        <h5 class="mt-4"><i class="bi bi-award-fill text-warning"></i> Progreso de Maestrías</h5>
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
                            
                            $cumplido = true;
                            $detalle = [];
                            $total_necesario = 0;
                            $total_actual = 0;
                            
                            while ($req = $requisitos->fetch_assoc()) {
                                $necesarias = $req['cantidad_minima'];
                                
                                if (!empty($req['categoria_id']) && empty($req['especialidad_id'])) {
                                    // Contar especialidades del miembro en esa categoría
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
                                    // Verificar especialidad concreta
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
                                $bg_color = $cumplido ? 'bg-success' : ($porcentaje >= 50 ? 'bg-warning' : 'bg-secondary');
                            ?>
                                <div class="mb-2 p-2 border rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong><?= htmlspecialchars($maestria['nombre_maestria']) ?></strong>
                                        <?php if ($cumplido): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> ¡Completada!</span>
                                        <?php else: ?>
                                            <span class="badge <?= $bg_color ?>"><?= $porcentaje ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <div class="progress-bar <?= $bg_color ?>" style="width: <?= $porcentaje ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= implode(' | ', $detalle) ?></small>
                                </div>
                            <?php
                            }
                        }
                        
                        if (!$alguna_maestria) {
                            echo '<p class="text-muted">No hay maestrías configuradas o el miembro no tiene especialidades.</p>';
                        }
                        ?>

                        <button type="submit" name="guardar" class="btn btn-primary btn-lg w-100 mt-3">
                            <i class="bi bi-save"></i> Guardar Todos los Logros
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        var especialidadesYaTiene = <?= json_encode($especialidades_ya_tiene) ?>;
        var especialidadesAgregadas = [];
        
        function cargarEspecialidades() {
            var catId = document.getElementById('categoriaSelect').value;
            var espSelect = document.getElementById('especialidadSelect');
            espSelect.innerHTML = '<option value="">Cargando...</option>';
            espSelect.disabled = true;
            document.getElementById('btnAgregar').disabled = true;
            
            if (!catId) return;
            
            fetch('get_especialidades.php?categoria=' + catId + '&excluir=' + JSON.stringify(especialidadesYaTiene.concat(especialidadesAgregadas)))
                .then(r => r.json())
                .then(data => {
                    espSelect.innerHTML = '<option value="">2. Seleccionar especialidad</option>';
                    data.forEach(e => {
                        espSelect.innerHTML += `<option value="${e.id}">${e.nombre_especialidad}</option>`;
                    });
                    espSelect.disabled = false;
                });
        }
        
        function habilitarAgregar() {
            document.getElementById('btnAgregar').disabled = !document.getElementById('especialidadSelect').value;
        }
        
        function agregarEspecialidad() {
            var espSelect = document.getElementById('especialidadSelect');
            var espId = espSelect.value;
            var espNombre = espSelect.options[espSelect.selectedIndex].text;
            var catNombre = document.getElementById('categoriaSelect').options[document.getElementById('categoriaSelect').selectedIndex].text;
            
            if (!espId) return;
            
            if (especialidadesAgregadas.includes(espId)) {
                alert('Esta especialidad ya está en la lista.');
                return;
            }
            
            especialidadesAgregadas.push(espId);
            
            var tbody = document.getElementById('tbodyEspecialidades');
            if (document.getElementById('filaVacia')) document.getElementById('filaVacia').remove();
            
            var tr = document.createElement('tr');
            tr.id = 'esp-' + espId;
            tr.innerHTML = `
                <td>${espNombre}</td>
                <td>${catNombre}</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="quitarEspecialidad('${espId}', this)"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
            
            actualizarInput();
            espSelect.value = '';
            document.getElementById('btnAgregar').disabled = true;
            cargarEspecialidades();
        }
        
        function quitarEspecialidad(id, btn) {
            especialidadesAgregadas = especialidadesAgregadas.filter(e => e !== id);
            btn.closest('tr').remove();
            if (document.getElementById('tbodyEspecialidades').children.length === 0) {
                document.getElementById('tbodyEspecialidades').innerHTML = '<tr id="filaVacia"><td colspan="3" class="text-muted text-center">No hay especialidades agregadas</td></tr>';
            }
            actualizarInput();
            cargarEspecialidades();
        }
        
        function actualizarInput() {
            document.getElementById('especialidadesInput').value = especialidadesAgregadas.join(',');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>