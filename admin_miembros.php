<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

// Filtro
$filtro_activo = $_GET['estado'] ?? 'activos';
if ($filtro_activo === 'inactivos') {
    $where = 'm.activo = 0';
} elseif ($filtro_activo === 'todos') {
    $where = '1';
} else {
    $where = 'm.activo = 1';
}

// Búsqueda
$buscar = trim($_GET['buscar'] ?? '');
if (!empty($buscar)) {
    $buscar = $conn->real_escape_string($buscar);
    $where .= " AND (u.nombre LIKE '%$buscar%' OR u.apellido LIKE '%$buscar%' OR u.dni LIKE '%$buscar%')";
}

// Ordenación
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'desc';
$allowed_sorts = ['nombre', 'dni', 'tipo', 'unidad', 'clases', 'id'];
if (!in_array($sort, $allowed_sorts)) $sort = 'id';
$order = ($order === 'asc') ? 'asc' : 'desc';
$next_order = ($order === 'desc') ? 'asc' : 'desc';

$sort_sql = '';
switch ($sort) {
    case 'nombre': $sort_sql = "u.apellido $order, u.nombre $order"; break;
    case 'dni':    $sort_sql = "u.dni $order"; break;
    case 'tipo':   $sort_sql = "m.tipo $order"; break;
    case 'unidad': $sort_sql = "un.nombre $order"; break;
    case 'clases': $sort_sql = "clases_regular $order"; break;
    default:       $sort_sql = "m.id $order"; break;
}

$miembros = $conn->query("
    SELECT m.id, m.activo, m.tipo, u.nombre AS nombre_usuario, u.apellido, u.dni, u.foto,
           un.nombre AS unidad,
           GROUP_CONCAT(DISTINCT cr.nombre ORDER BY cr.edad_requerida SEPARATOR ', ') AS clases_regular,
           MAX(CASE WHEN cr.nombre = 'Guía' THEN 1 ELSE 0 END) AS tiene_guia
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN logros_clase_regular lcr ON m.id = lcr.miembro_id
    LEFT JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id
    WHERE $where
    GROUP BY m.id, u.nombre, u.apellido, u.dni, un.nombre, u.foto
    ORDER BY $sort_sql
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Miembros · Club Betelgeuse</title>
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

        .navbar-admin {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-admin .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-admin .navbar-brand:hover { color: var(--accent-club) !important; }
        .navbar-admin .user-badge {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .admin-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .admin-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .admin-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        .btn-filter {
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 20px;
            transition: var(--transition);
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
        }
        .btn-filter:hover, .btn-filter.active {
            background: var(--primary-club);
            color: white;
            border-color: var(--primary-club);
        }
        .btn-filter.active {
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
        }

        .input-search {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            background: #fafbfc;
        }
        .input-search:focus {
            border-color: var(--accent-club);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            background: white;
            outline: none;
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
            white-space: nowrap;
        }
        .table-card thead th a {
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .table-card thead th a:hover { color: var(--primary-club); }
        .table-card thead th i { font-size: 0.7rem; opacity: 0.5; }
        .table-card thead th i.active { opacity: 1; color: var(--primary-club); }
        .table-card tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-weight: 500;
        }
        .table-card tbody tr:hover { background: #f8fafc; }

        .avatar-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-club);
        }
        .avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .badge-tipo {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .badge-estado {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .btn-action {
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 4px 14px;
            transition: var(--transition);
        }
        .btn-action:hover { transform: translateY(-1px); }

        .alert-promoted {
            border-radius: 16px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            color: #065f46;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .admin-card { padding: 24px 18px; border-radius: 22px; }
            .admin-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-admin">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
            <span class="user-badge">
                <?= htmlspecialchars($_SESSION['nombre']) ?> · <?= ucfirst($_SESSION['rol']) ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="admin-card">

            <div class="admin-header">
                <h2><i class="bi bi-people-fill me-2" style="color: var(--accent-club);"></i>Administración de Miembros</h2>
                <p>Gestiona los integrantes del club, filtralos por estado y realiza acciones rápidas.</p>
            </div>

            <?php if (isset($_GET['promovido'])): ?>
                <div class="alert alert-promoted d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div>¡Miembro promovido a Líder exitosamente!</div>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="?estado=activos&sort=<?= $sort ?>&order=<?= $order ?>&buscar=<?= urlencode($buscar) ?>" class="btn-filter <?= $filtro_activo=='activos'?'active':'' ?>">
                    <i class="bi bi-check-circle me-1"></i> Activos
                </a>
                <a href="?estado=inactivos&sort=<?= $sort ?>&order=<?= $order ?>&buscar=<?= urlencode($buscar) ?>" class="btn-filter <?= $filtro_activo=='inactivos'?'active':'' ?>">
                    <i class="bi bi-dash-circle me-1"></i> Inactivos
                </a>
                <a href="?estado=todos&sort=<?= $sort ?>&order=<?= $order ?>&buscar=<?= urlencode($buscar) ?>" class="btn-filter <?= $filtro_activo=='todos'?'active':'' ?>">
                    <i class="bi bi-grid me-1"></i> Todos
                </a>
            </div>

            <!-- Buscador -->
            <form class="row g-2 mb-4">
                <input type="hidden" name="estado" value="<?= $filtro_activo ?>">
                <input type="hidden" name="sort" value="<?= $sort ?>">
                <input type="hidden" name="order" value="<?= $order ?>">
                <div class="col-md-5 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: 12px 0 0 12px;"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="buscar" class="form-control input-search border-start-0" placeholder="Nombre o DNI" value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold" style="border-radius: 12px !important;">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>
                <?php if (!empty($buscar)): ?>
                    <div class="col-auto">
                        <a href="?estado=<?= $filtro_activo ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn btn-outline-secondary rounded-3 px-3 fw-semibold" style="border-radius: 12px !important;">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </a>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Tabla -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>
                                    <a href="?estado=<?= $filtro_activo ?>&sort=nombre&order=<?= ($sort=='nombre' && $order=='desc') ? 'asc' : 'desc' ?>&buscar=<?= urlencode($buscar) ?>">
                                        Nombre
                                        <?php if ($sort=='nombre'): ?>
                                            <i class="bi bi-caret-<?= $order=='desc' ? 'down' : 'up' ?>-fill active"></i>
                                        <?php else: ?>
                                            <i class="bi bi-caret-down"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?estado=<?= $filtro_activo ?>&sort=dni&order=<?= ($sort=='dni' && $order=='desc') ? 'asc' : 'desc' ?>&buscar=<?= urlencode($buscar) ?>">
                                        DNI
                                        <?php if ($sort=='dni'): ?>
                                            <i class="bi bi-caret-<?= $order=='desc' ? 'down' : 'up' ?>-fill active"></i>
                                        <?php else: ?>
                                            <i class="bi bi-caret-down"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?estado=<?= $filtro_activo ?>&sort=tipo&order=<?= ($sort=='tipo' && $order=='desc') ? 'asc' : 'desc' ?>&buscar=<?= urlencode($buscar) ?>">
                                        Tipo
                                        <?php if ($sort=='tipo'): ?>
                                            <i class="bi bi-caret-<?= $order=='desc' ? 'down' : 'up' ?>-fill active"></i>
                                        <?php else: ?>
                                            <i class="bi bi-caret-down"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?estado=<?= $filtro_activo ?>&sort=unidad&order=<?= ($sort=='unidad' && $order=='desc') ? 'asc' : 'desc' ?>&buscar=<?= urlencode($buscar) ?>">
                                        Unidad
                                        <?php if ($sort=='unidad'): ?>
                                            <i class="bi bi-caret-<?= $order=='desc' ? 'down' : 'up' ?>-fill active"></i>
                                        <?php else: ?>
                                            <i class="bi bi-caret-down"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?estado=<?= $filtro_activo ?>&sort=clases&order=<?= ($sort=='clases' && $order=='desc') ? 'asc' : 'desc' ?>&buscar=<?= urlencode($buscar) ?>">
                                        Clases
                                        <?php if ($sort=='clases'): ?>
                                            <i class="bi bi-caret-<?= $order=='desc' ? 'down' : 'up' ?>-fill active"></i>
                                        <?php else: ?>
                                            <i class="bi bi-caret-down"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($m = $miembros->fetch_assoc()): ?>
                            <tr>
                                <!-- Foto -->
                                <td>
                                    <?php if ($m['foto']): ?>
                                        <img src="<?= $m['foto'] ?>" alt="Foto" class="avatar-circle">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($m['nombre_usuario'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong class="text-dark"><?= htmlspecialchars($m['nombre_usuario'] . ' ' . $m['apellido']) ?></strong></td>
                                <td class="text-secondary"><?= $m['dni'] ?></td>
                                <td>
                                    <?php if ($m['tipo'] === 'lider'): ?>
                                        <span class="badge-tipo bg-warning bg-opacity-10 text-warning">👤 Líder</span>
                                    <?php else: ?>
                                        <span class="badge-tipo bg-info bg-opacity-10 text-info">🧒 Conquistador</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $m['unidad'] ?? '<span class="text-muted">-</span>' ?></td>
                                <td>
                                    <?php if (!empty($m['clases_regular'])): ?>
                                        <?php foreach (explode(', ', $m['clases_regular']) as $cl): ?>
                                            <?= badge_clase($cl) ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($m['activo']): ?>
                                        <span class="badge-estado bg-success bg-opacity-10 text-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-estado bg-secondary bg-opacity-10 text-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if ($m['activo']): ?>
                                            <a href="cambiar_estado.php?id=<?= $m['id'] ?>&accion=desactivar" class="btn btn-action btn-outline-danger">
                                                <i class="bi bi-person-x me-1"></i> Dar de baja
                                            </a>
                                            <?php if ($m['tipo'] == 'conquistador' && $m['tiene_guia'] == 1): ?>
                                                <a href="promover.php?id=<?= $m['id'] ?>" class="btn btn-action btn-outline-success"
                                                   onclick="return confirm('¿Promover a Líder? Se cambiará su rol y podrá enseñar.')">
                                                    <i class="bi bi-arrow-up-circle me-1"></i> Promover
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="cambiar_estado.php?id=<?= $m['id'] ?>&accion=activar" class="btn btn-action btn-outline-success">
                                                <i class="bi bi-person-check me-1"></i> Reactivar
                                            </a>
                                        <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>