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
    <title>Especialidades de Líderes · Club Betelgeuse</title>
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

        .navbar-lider {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-lider .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-lider .navbar-brand:hover { color: var(--accent-club) !important; }
        .navbar-lider .btn-vista {
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 20px;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .navbar-lider .btn-vista:hover, .navbar-lider .btn-vista.active {
            background: var(--primary-club);
            border-color: var(--primary-club);
        }
        .navbar-lider .btn-vista.active {
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
        }

        .lider-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .lider-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .lider-header p {
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

        .list-group-item {
            border: none;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 20px;
            font-weight: 500;
            transition: var(--transition);
            color: #334155;
        }
        .list-group-item:hover, .list-group-item.active {
            background: #f0f4ff;
            color: var(--primary-club);
            font-weight: 600;
        }
        .list-group-item.active {
            border-left: 4px solid var(--accent-club);
        }

        .table-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
        }
        .table-card table { margin-bottom: 0; }
        .table-card thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 16px;
        }
        .table-card tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-weight: 500;
        }
        .table-card tbody tr:hover { background: #f8fafc; }

        .badge-categoria {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .btn-accion {
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 4px 14px;
            transition: var(--transition);
        }
        .btn-accion:hover { transform: translateY(-1px); }

        .btn-guardar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 1rem;
            padding: 12px 32px;
            border-radius: 14px;
            transition: var(--transition);
            box-shadow: 0 8px 20px -8px rgba(30, 64, 175, 0.3);
        }
        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -6px rgba(30, 64, 175, 0.4);
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }

        .cart-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .cart-card h6 { font-weight: 700; color: #0f172a; }

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

        @media (max-width: 768px) {
            .lider-card { padding: 24px 18px; border-radius: 22px; }
            .lider-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-lider">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
            <div class="btn-group">
                <a href="?vista=gestion" class="btn-vista <?= $vista=='gestion'?'active':'' ?>">
                    <i class="bi bi-gear me-1"></i> Gestionar
                </a>
                <a href="?vista=consulta" class="btn-vista <?= $vista=='consulta'?'active':'' ?>">
                    <i class="bi bi-search me-1"></i> Consultar
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="lider-card">

            <div class="lider-header">
                <h2><i class="bi bi-person-badge me-2" style="color: var(--accent-club);"></i>Especialidades de Líderes</h2>
                <p>Gestiona qué especialidades puede enseñar cada líder o consulta quién enseña qué.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div><?= $mensaje ?></div>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <?php if ($vista === 'gestion'): ?>
            <!-- ============ VISTA GESTIÓN ============ -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white rounded-top-4">
                            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Líderes</h5>
                        </div>
                        <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                            <?php $lideres->data_seek(0); ?>
                            <?php while ($l = $lideres->fetch_assoc()): ?>
                                <a href="?lider_id=<?= $l['id'] ?>&vista=gestion" 
                                   class="list-group-item <?= $lider_id==$l['id']?'active':'' ?>">
                                    <strong><?= $l['nombre'] . ' ' . $l['apellido'] ?></strong>
                                    <br><small class="text-muted"><?= $l['dni'] ?> | <?= $l['unidad'] ?? 'Sin unidad' ?></small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <?php if ($lider_info): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="fw-bold"><?= htmlspecialchars($lider_info['nombre'] . ' ' . $lider_info['apellido']) ?></h5>
                                <p class="mb-0 text-muted">
                                    <strong>DNI:</strong> <?= $lider_info['dni'] ?> | 
                                    <strong>Unidad:</strong> <?= $lider_info['unidad'] ?? 'No asignada' ?> | 
                                    <strong>Rango:</strong> <?= $lider_info['rango'] ? str_replace('_', ' ', ucfirst($lider_info['rango'])) : 'Guía' ?>
                                </p>
                            </div>
                        </div>

                        <!-- Especialidades que ya enseña -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-success text-white rounded-top-4">
                                <h6 class="mb-0"><i class="bi bi-check-circle me-2"></i>Ya puede enseñar (<?= $especialidades_lider->num_rows ?>)</h6>
                            </div>
                            <div class="table-card" style="max-height: 300px; overflow-y: auto;">
                                <?php if ($especialidades_lider->num_rows > 0): ?>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php while ($e = $especialidades_lider->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= $e['nombre_especialidad'] ?></td>
                                                    <td><span class="badge-categoria bg-secondary bg-opacity-10 text-secondary"><?= $e['nombre_categoria'] ?></span></td>
                                                    <td class="text-muted small"><?= date('d/m/Y', strtotime($e['fecha_habilitacion'])) ?></td>
                                                    <td>
                                                        <a href="?lider_id=<?= $lider_id ?>&quitar=<?= $e['id'] ?>&vista=gestion" 
                                                           class="btn btn-accion btn-outline-danger"
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
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-warning text-dark rounded-top-4">
                                <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Agregar especialidades</h6>
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
                                        <button type="button" id="btnAgregar" class="btn btn-success w-100 rounded-pill" disabled onclick="agregarALista()">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>

                                <form method="post" id="formGuardar">
                                    <input type="hidden" name="lider_id" value="<?= $lider_id ?>">
                                    <input type="hidden" name="especialidades" id="especialidadesInput" value="">
                                    
                                    <table class="table table-sm table-borderless" id="tablaAgregadas">
                                        <thead><tr><th>Especialidad</th><th>Categoría</th><th></th></tr></thead>
                                        <tbody id="tbodyAgregadas">
                                            <tr id="filaVacia"><td colspan="3" class="text-muted text-center">No hay especialidades para agregar</td></tr>
                                        </tbody>
                                    </table>

                                    <button type="submit" name="guardar" class="btn btn-guardar w-100" id="btnGuardar" disabled>
                                        <i class="bi bi-save me-2"></i> Guardar Todas
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm">
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

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaConsulta">
                        <thead>
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
                                    <td><span class="badge-categoria bg-secondary bg-opacity-10 text-secondary"><?= htmlspecialchars($esp['nombre_categoria']) ?></span></td>
                                    <td><?= htmlspecialchars($esp['instructores'] ?? 'Sin instructor') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($consulta_especialidades)): ?>
                                <tr><td colspan="3" class="text-center text-muted">No hay especialidades asignadas a ningún líder.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
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
                <td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="this.closest('tr').remove(); quitarDeLista('${espId}');"><i class="bi bi-trash"></i></button></td>
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