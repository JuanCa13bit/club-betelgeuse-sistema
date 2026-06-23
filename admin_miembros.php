<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

// Determinar filtro
$filtro_activo = $_GET['estado'] ?? 'activos';
if ($filtro_activo === 'inactivos') {
    $where = 'm.activo = 0';
} elseif ($filtro_activo === 'todos') {
    $where = '1'; // Muestra todos sin filtrar
} else {
    $where = 'm.activo = 1'; // Por defecto, activos
}

$buscar = $_GET['buscar'] ?? '';
if (!empty($buscar)) {
    $buscar = $conn->real_escape_string($buscar);
    $where .= " AND (u.nombre LIKE '%$buscar%' OR u.apellido LIKE '%$buscar%' OR u.dni LIKE '%$buscar%')";
}

$miembros = $conn->query("
    SELECT m.id, m.activo, u.nombre AS nombre_usuario, u.apellido, u.dni,
           un.nombre AS unidad, cr.nombre AS clase_regular
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN logros_clase_regular lcr ON m.id = lcr.miembro_id
    LEFT JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id
    WHERE $where
    ORDER BY m.id DESC
");



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Miembros - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver al Panel</a>
            <span class="navbar-text"><?= $_SESSION['nombre'] ?> (<?= $_SESSION['rol'] ?>)</span>
        </div>
    </nav>

    

    <div class="container mt-4">
        <h2><i class="bi bi-people-fill"></i> Administración de Miembros</h2>

        <!-- Filtros -->
        <div class="btn-group mb-3">
            <a href="?estado=activos" class="btn btn-outline-success <?= $filtro_activo=='activos'?'active':'' ?>">Activos</a>
            <a href="?estado=inactivos" class="btn btn-outline-secondary <?= $filtro_activo=='inactivos'?'active':'' ?>">Inactivos</a>
            <a href="?estado=todos" class="btn btn-outline-primary <?= $filtro_activo=='todos'?'active':'' ?>">Todos</a>
        </div>


         <form class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="buscar" class="form-control" placeholder="Nombre o DNI" value="<?= $_GET['buscar'] ?? '' ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
    </div>
</form>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Unidad</th>
                        <th>Clase regular</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($m = $miembros->fetch_assoc()): ?>
                    <tr>
                        <td><?= $m['nombre_usuario'] . ' ' . $m['apellido'] ?></td>
                        <td><?= $m['dni'] ?></td>
                        <td><?= $m['unidad'] ?? '-' ?></td>
                        <td><?= $m['clase_regular'] ?? '-' ?></td>
                        <td>
                            <?php if ($m['activo']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['activo']): ?>
                                <a href="cambiar_estado.php?id=<?= $m['id'] ?>&accion=desactivar" class="btn btn-sm btn-outline-danger">Dar de baja</a>
                            <?php else: ?>
                                <a href="cambiar_estado.php?id=<?= $m['id'] ?>&accion=activar" class="btn btn-sm btn-outline-success">Reactivar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

   

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>