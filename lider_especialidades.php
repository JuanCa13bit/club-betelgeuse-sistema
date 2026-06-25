<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$mensaje = '';
$error = '';
$lider_id = $_GET['lider_id'] ?? null;
$lider_info = null;
$especialidades_lider = [];
$vista = $_GET['vista'] ?? 'gestion';

// Guardar especialidades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $lider_id = intval($_POST['lider_id']);
    $especialidades_input = $_POST['especialidades'] ?? '';
    $especialidades = !empty($especialidades_input) ? explode(',', $especialidades_input) : [];
    
    if (empty($especialidades)) {
        $error = "No se seleccionó ninguna especialidad.";
    } else {
        $conn->begin_transaction();
        try {
            foreach ($especialidades as $eid) {
                $eid = intval($eid);
                if ($eid > 0) {
                    $conn->query("INSERT IGNORE INTO instructor_especialidad (miembro_id, especialidad_id, fecha_habilitacion) VALUES ($lider_id, $eid, CURDATE())");
                }
            }
            $conn->commit();
            $mensaje = count($especialidades) . " especialidad(es) asignada(s) correctamente.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al guardar.";
        }
    }
}

// ... el resto del código sigue igual

// Quitar especialidad
if (isset($_GET['quitar']) && isset($_GET['lider_id'])) {
    $quitar_id = intval($_GET['quitar']);
    $lider_id = intval($_GET['lider_id']);
    $conn->query("DELETE FROM instructor_especialidad WHERE id = $quitar_id");
    $mensaje = "Especialidad removida.";
}

// Obtener líderes
$lideres = $conn->query("
    SELECT m.id, u.nombre, u.apellido, u.dni, un.nombre AS unidad
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    WHERE m.tipo = 'lider' AND m.activo = 1
    ORDER BY u.apellido, u.nombre
");

// Si hay líder seleccionado
if ($lider_id) {
    $stmt = $conn->prepare("
        SELECT u.nombre, u.apellido, u.dni, m.id AS miembro_id, un.nombre AS unidad, m.rango
        FROM miembros m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
        LEFT JOIN unidades un ON mu.unidad_id = un.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $lider_id);
    $stmt->execute();
    $lider_info = $stmt->get_result()->fetch_assoc();
    
    $especialidades_lider = $conn->query("
        SELECT ie.id, ie.fecha_habilitacion, e.nombre_especialidad, cat.nombre_categoria
        FROM instructor_especialidad ie
        JOIN especialidades e ON ie.especialidad_id = e.id
        JOIN categorias_especialidades cat ON e.categoria_id = cat.id
        WHERE ie.miembro_id = $lider_id
        ORDER BY cat.nombre_categoria, e.nombre_especialidad
    ");
}

// Consulta: todas las especialidades con sus instructores
$consulta_especialidades = [];
if ($vista === 'consulta') {
    $result = $conn->query("
        SELECT e.id AS esp_id, e.nombre_especialidad, cat.nombre_categoria,
               GROUP_CONCAT(CONCAT(u.nombre, ' ', u.apellido) ORDER BY u.apellido SEPARATOR ', ') AS instructores,
               COUNT(ie.id) AS total_instructores
        FROM especialidades e
        JOIN categorias_especialidades cat ON e.categoria_id = cat.id
        LEFT JOIN instructor_especialidad ie ON e.id = ie.especialidad_id
        LEFT JOIN miembros m ON ie.miembro_id = m.id AND m.activo = 1
        LEFT JOIN usuarios u ON m.usuario_id = u.id
        GROUP BY e.id, e.nombre_especialidad, cat.nombre_categoria
        HAVING total_instructores > 0
        ORDER BY cat.nombre_categoria, e.nombre_especialidad
    ");
    while ($row = $result->fetch_assoc()) {
        $consulta_especialidades[] = $row;
    }
}

$categorias = $conn->query("SELECT id, nombre_categoria FROM categorias_especialidades ORDER BY nombre_categoria");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Especialidades de Líderes - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver al Panel</a>
            <div class="btn-group">
                <a href="?vista=gestion" class="btn btn-outline-light <?= $vista=='gestion'?'active':'' ?>">
                    <i class="bi bi-gear"></i> Gestionar
                </a>
                <a href="?vista=consulta" class="btn btn-outline-light <?= $vista=='consulta'?'active':'' ?>">
                    <i class="bi bi-search"></i> Consultar
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if ($vista === 'gestion'): ?>
        <!-- ============ VISTA GESTIÓN ============ -->
        <h2><i class="bi bi-person-badge"></i> Gestionar Especialidades de Líderes</h2>

        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Líderes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                            <?php $lideres->data_seek(0); ?>
                            <?php while ($l = $lideres->fetch_assoc()): ?>
                                <a href="?lider_id=<?= $l['id'] ?>&vista=gestion" 
                                   class="list-group-item list-group-item-action <?= $lider_id==$l['id']?'active':'' ?>">
                                    <strong><?= $l['nombre'] . ' ' . $l['apellido'] ?></strong>
                                    <br><small><?= $l['dni'] ?> | <?= $l['unidad'] ?? 'Sin unidad' ?></small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($lider_info): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5><?= $lider_info['nombre'] . ' ' . $lider_info['apellido'] ?></h5>
                            <p class="mb-1"><strong>DNI:</strong> <?= $lider_info['dni'] ?> | <strong>Unidad:</strong> <?= $lider_info['unidad'] ?? 'No asignada' ?> | <strong>Rango:</strong> <?= $lider_info['rango'] ? str_replace('_', ' ', ucfirst($lider_info['rango'])) : 'Guía' ?></p>
                        </div>
                    </div>

                    <!-- Especialidades que ya enseña -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check-circle"></i> Ya puede enseñar (<?= $especialidades_lider->num_rows ?>)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                            <?php if ($especialidades_lider->num_rows > 0): ?>
                                <table class="table table-sm table-striped mb-0">
                                    <tbody>
                                        <?php while ($e = $especialidades_lider->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $e['nombre_especialidad'] ?></td>
                                                <td><span class="badge bg-secondary"><?= $e['nombre_categoria'] ?></span></td>
                                                <td><?= date('d/m/Y', strtotime($e['fecha_habilitacion'])) ?></td>
                                                <td>
                                                    <a href="?lider_id=<?= $lider_id ?>&quitar=<?= $e['id'] ?>&vista=gestion" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('¿Quitar esta especialidad?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted p-3 mb-0">No tiene especialidades asignadas.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Agregar especialidades -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar especialidades</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-3">
                                <div class="col-md-5">
                                    <select id="categoriaSelect" class="form-select" onchange="cargarEspecialidades()">
                                        <option value="">1. Categoría</option>
                                        <?php while ($cat = $categorias->fetch_assoc()): ?>
                                            <option value="<?= $cat['id'] ?>"><?= $cat['nombre_categoria'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select id="especialidadSelect" class="form-select" disabled onchange="habilitarAgregar()">
                                        <option value="">2. Especialidad</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" id="btnAgregar" class="btn btn-success w-100" disabled onclick="agregarALista()">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <form method="post" id="formGuardar">
                                <input type="hidden" name="lider_id" value="<?= $lider_id ?>">
                                <input type="hidden" name="especialidades" id="especialidadesInput" value="">
                                
                                <table class="table table-sm table-bordered" id="tablaAgregadas">
                                    <thead class="table-warning">
                                        <tr><th>Especialidad</th><th>Categoría</th><th></th></tr>
                                    </thead>
                                    <tbody id="tbodyAgregadas">
                                        <tr id="filaVacia"><td colspan="3" class="text-muted text-center">No hay especialidades para agregar</td></tr>
                                    </tbody>
                                </table>

                                <button type="submit" name="guardar" class="btn btn-primary w-100" id="btnGuardar" disabled>
                                    <i class="bi bi-save"></i> Guardar Todas
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            <i class="bi bi-arrow-left" style="font-size: 3rem;"></i>
                            <h5>Selecciona un líder para gestionar sus especialidades</h5>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- ============ VISTA CONSULTA ============ -->
        <h2><i class="bi bi-search"></i> ¿Quién puede enseñar qué?</h2>
        
        <div class="row mt-3">
            <div class="col-md-3 mb-3">
                <input type="text" id="buscarEspecialidad" class="form-control" placeholder="Buscar especialidad..." onkeyup="filtrarTabla()">
            </div>
            <div class="col-md-3 mb-3">
                <select id="filtroCategoria" class="form-select" onchange="filtrarTabla()">
                    <option value="">Todas las categorías</option>
                    <?php $categorias->data_seek(0); ?>
                    <?php while ($cat = $categorias->fetch_assoc()): ?>
                        <option value="<?= $cat['nombre_categoria'] ?>"><?= $cat['nombre_categoria'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="tablaConsulta">
                <thead class="table-dark">
                    <tr>
                        <th>Especialidad</th>
                        <th>Categoría</th>
                        <th>Instructores</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consulta_especialidades as $esp): ?>
                        <tr data-categoria="<?= htmlspecialchars($esp['nombre_categoria']) ?>">
                            <td><strong><?= htmlspecialchars($esp['nombre_especialidad']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($esp['nombre_categoria']) ?></span></td>
                            <td><?= htmlspecialchars($esp['instructores'] ?? 'Sin instructor') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($consulta_especialidades)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No hay especialidades asignadas a ningún líder.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        var especialidadesAgregadas = [];
        
        function cargarEspecialidades() {
            var catId = document.getElementById('categoriaSelect').value;
            var espSelect = document.getElementById('especialidadSelect');
            espSelect.innerHTML = '<option value="">Cargando...</option>';
            espSelect.disabled = true;
            document.getElementById('btnAgregar').disabled = true;
            
            if (!catId) return;
            
            fetch('get_especialidades.php?categoria=' + catId + '&excluir=[]')
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
        
        function agregarALista() {
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
            
            var tbody = document.getElementById('tbodyAgregadas');
            if (document.getElementById('filaVacia')) document.getElementById('filaVacia').remove();
            
            var tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${espNombre}</td>
                <td>${catNombre}</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); quitarDeLista('${espId}');"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
            
            actualizarInput();
            espSelect.value = '';
            document.getElementById('btnAgregar').disabled = true;
        }
        
        function quitarDeLista(id) {
            especialidadesAgregadas = especialidadesAgregadas.filter(e => e !== id);
            if (document.getElementById('tbodyAgregadas').children.length === 0) {
                document.getElementById('tbodyAgregadas').innerHTML = '<tr id="filaVacia"><td colspan="3" class="text-muted text-center">No hay especialidades para agregar</td></tr>';
            }
            actualizarInput();
        }
        
        function actualizarInput() {
            document.getElementById('especialidadesInput').value = especialidadesAgregadas.join(',');
            document.getElementById('btnGuardar').disabled = especialidadesAgregadas.length === 0;
        }
        
        function filtrarTabla() {
            var texto = document.getElementById('buscarEspecialidad').value.toLowerCase();
            var categoria = document.getElementById('filtroCategoria').value;
            var filas = document.querySelectorAll('#tablaConsulta tbody tr');
            
            filas.forEach(fila => {
                var nombre = fila.textContent.toLowerCase();
                var cat = fila.getAttribute('data-categoria');
                var mostrar = nombre.includes(texto) && (!categoria || cat === categoria);
                fila.style.display = mostrar ? '' : 'none';
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>