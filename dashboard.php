<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario','tesorero']);

// Consultas para estadísticas
$total_miembros = $conn->query("SELECT COUNT(*) FROM miembros WHERE activo = 1")->fetch_row()[0];
$total_conquistadores = $conn->query("SELECT COUNT(*) FROM miembros WHERE tipo = 'conquistador' AND activo = 1")->fetch_row()[0];
$total_lideres = $conn->query("SELECT COUNT(*) FROM miembros WHERE tipo = 'lider' AND activo = 1")->fetch_row()[0];
$logros_anio = $conn->query("SELECT COUNT(*) FROM logros_especialidad WHERE anio = YEAR(CURDATE())")->fetch_row()[0];

// Próximos cumpleaños (próximos 7 días)
$cumpleanios = $conn->query("
    SELECT nombre, apellido, fecha_nacimiento,
           TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad,
           DATE_FORMAT(fecha_nacimiento, '%d/%m') AS fecha
    FROM usuarios
    WHERE DATE_FORMAT(fecha_nacimiento, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d')
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .card-stats { border-left: 4px solid #0d6efd; transition: 0.3s; }
        .card-stats:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .icon-big { font-size: 2.5rem; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-speedometer2"></i> Panel de Control</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?= $_SESSION['nombre'] ?> (<?= $_SESSION['rol'] ?>)</span>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4"><i class="bi bi-house-door"></i> Bienvenido, <?= $_SESSION['nombre'] ?></h2>

        <!-- Tarjetas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stats p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Miembros activos</h6>
                            <h3><?= $total_miembros ?></h3>
                        </div>
                        <i class="bi bi-people-fill text-primary icon-big"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stats p-3" style="border-left-color: #198754;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Conquistadores</h6>
                            <h3><?= $total_conquistadores ?></h3>
                        </div>
                        <i class="bi bi-person-fill text-success icon-big"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stats p-3" style="border-left-color: #ffc107;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Líderes</h6>
                            <h3><?= $total_lideres ?></h3>
                        </div>
                        <i class="bi bi-person-badge text-warning icon-big"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stats p-3" style="border-left-color: #dc3545;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted">Especialidades <?= date('Y') ?></h6>
                            <h3><?= $logros_anio ?></h3>
                        </div>
                        <i class="bi bi-trophy-fill text-danger icon-big"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <h4 class="mb-3"><i class="bi bi-lightning-charge"></i> Accesos Rápidos</h4>
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <a href="registrar.php" class="btn btn-success w-100 p-3 text-start">
                    <i class="bi bi-person-plus-fill me-2"></i> Registrar Nuevo Integrante
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="logros.php" class="btn btn-info w-100 p-3 text-start">
                    <i class="bi bi-journal-plus me-2"></i> Gestionar Logros
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="buscar.php" class="btn btn-warning w-100 p-3 text-start">
                    <i class="bi bi-search me-2"></i> Buscar Miembro
                </a>
            </div>
        </div>

        <!-- Próximos cumpleaños -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cake2"></i> Cumpleaños próximos (7 días)</h5>
            </div>
            <div class="card-body">
                <?php if ($cumpleanios->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Fecha</th>
                                    <th>Edad a cumplir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($c = $cumpleanios->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
                                        <td><?= $c['fecha'] ?></td>
                                        <td><?= $c['edad'] + 1 ?> años</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay cumpleaños en los próximos 7 días.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ranking de logros -->
<h4 class="mb-3 mt-4"><i class="bi bi-trophy-fill"></i> Top especialidades <?= date('Y') ?></h4>
<?php
$ranking = $conn->query("
    SELECT u.nombre, u.apellido, COUNT(le.id) AS total
    FROM logros_especialidad le
    JOIN miembros m ON le.miembro_id = m.id
    JOIN usuarios u ON m.usuario_id = u.id
    WHERE le.anio = YEAR(CURDATE()) AND m.activo = 1
    GROUP BY le.miembro_id
    ORDER BY total DESC
    LIMIT 5
");
if ($ranking->num_rows > 0): ?>
    <div class="row">
        <?php $medallas = ['🥇','🥈','🥉','4º','5º']; $i = 0; ?>
        <?php while ($r = $ranking->fetch_assoc()): ?>
            <div class="col-md-4 mb-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h5><?= $medallas[$i] ?> <?= $r['nombre'] . ' ' . $r['apellido'] ?></h5>
                        <p class="display-6"><?= $r['total'] ?> esp.</p>
                    </div>
                </div>
            </div>
            <?php $i++; ?>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="text-muted">Aún no hay especialidades registradas este año.</p>
<?php endif; ?>


<div class="col-md-4 mb-3">
    <a href="admin_miembros.php" class="btn btn-secondary w-100 p-3 text-start">
        <i class="bi bi-gear-fill me-2"></i> Administrar Miembros
    </a>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>