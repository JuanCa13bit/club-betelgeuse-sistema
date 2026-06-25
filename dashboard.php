<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario','tesorero']);

// Estadísticas
$total_miembros = $conn->query("SELECT COUNT(*) FROM miembros WHERE activo = 1")->fetch_row()[0];
$total_conquistadores = $conn->query("SELECT COUNT(*) FROM miembros WHERE tipo = 'conquistador' AND activo = 1")->fetch_row()[0];
$total_lideres = $conn->query("SELECT COUNT(*) FROM miembros WHERE tipo = 'lider' AND activo = 1")->fetch_row()[0];
$logros_anio = $conn->query("SELECT COUNT(*) FROM logros_especialidad WHERE anio = YEAR(CURDATE())")->fetch_row()[0];

// Próximos cumpleaños
$cumpleanios = $conn->query("
    SELECT nombre, apellido, fecha_nacimiento,
           TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad,
           DATE_FORMAT(fecha_nacimiento, '%d/%m') AS fecha
    FROM usuarios
    WHERE DATE_FORMAT(fecha_nacimiento, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
    ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d')
    LIMIT 10
");

// Ranking de especialidades del año
$ranking = $conn->query("
    SELECT u.nombre, u.apellido, un.nombre AS unidad, COUNT(le.id) AS total
    FROM logros_especialidad le
    JOIN miembros m ON le.miembro_id = m.id
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    WHERE le.anio = YEAR(CURDATE()) AND m.activo = 1
    GROUP BY le.miembro_id, u.nombre, u.apellido, un.nombre
    ORDER BY total DESC
    LIMIT 5
");

// Ganadores del año actual
$ganadores_anio = $conn->query("
    SELECT g.posicion, g.puntaje_total, u.nombre, u.apellido, un.nombre AS unidad
    FROM ganadores_anuales g
    JOIN miembros m ON g.miembro_id = m.id
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    WHERE g.anio = YEAR(CURDATE())
    ORDER BY g.posicion ASC
");

// Obtener foto del usuario logueado
$foto_usuario = $conn->query("SELECT foto FROM usuarios WHERE id = {$_SESSION['usuario_id']}")->fetch_assoc()['foto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Montserrat', sans-serif; }
        body { background: #f0f2f5; }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #133579 0%, #0d1b3e 100%);
            min-height: 100vh;
            position: fixed;
            width: 260px;
            padding: 0;
            z-index: 100;
            transition: 0.3s;
        }
        .sidebar .logo {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar .logo img {
            height: 60px;
            margin-bottom: 10px;
        }
        .sidebar .logo h4 {
            color: white;
            font-weight: 800;
            margin: 0;
            font-size: 1.3rem;
        }
        .sidebar .logo small {
            color: #FFD300;
            font-weight: 600;
        }
        .sidebar .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: white;
            text-align: center;
        }
        .sidebar .user-info .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.8rem;
            font-weight: 800;
            color: #133579;
            background-color: #FFD300; /* fallback */
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 14px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.3s;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white !important;
            background: rgba(255, 211, 0, 0.1);
            border-left-color: #FFD300;
        }
        .sidebar .nav-link i { font-size: 1.2rem; width: 25px; text-align: center; }

        /* Main content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            transition: 0.3s;
        }

        /* Top bar */
        .topbar {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar h2 { font-weight: 700; margin: 0; font-size: 1.5rem; color: #133579; }

        /* Cards de estadísticas */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            transition: 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .stat-card:nth-child(1) { border-left: 4px solid #133579; }
        .stat-card:nth-child(2) { border-left: 4px solid #E42613; }
        .stat-card:nth-child(3) { border-left: 4px solid #FFD300; }
        .stat-card:nth-child(4) { border-left: 4px solid #1BA1AD; }
        .stat-card .icon-circle {
            width: 55px; height: 55px; border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; margin-bottom: 15px;
        }
        .stat-value { font-size: 2.2rem; font-weight: 800; margin: 0; }
        .stat-label { color: #888; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }

        /* Accesos rápidos */
        .quick-action {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            transition: 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-decoration: none;
            color: #333;
            display: block;
            height: 100%;
        }
        .quick-action:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .quick-action .action-icon { font-size: 2.5rem; margin-bottom: 15px; }
        .quick-action h5 { font-weight: 700; margin: 0; }

        /* Tabla */
        .card-table {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="assets/img/logo.png" alt="Logo Club Betelgeuse" style="background: transparent;">
            <h4>BETELGEUSE</h4>
            <small>Panel de Control</small>
        </div>
        <div class="user-info">
            <!-- Avatar con foto o inicial, y botón para cambiar foto -->
            <div style="position: relative; display: inline-block; cursor: pointer;" onclick="document.getElementById('inputFoto').click()">
                <?php if ($foto_usuario): ?>
                    <div class="avatar" style="background-image: url('<?= $foto_usuario ?>');"></div>
                <?php else: ?>
                    <div class="avatar"><?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?></div>
                <?php endif; ?>
                <!-- Ícono de cámara -->
                <div style="position: absolute; bottom: 0; right: 0; background: #FFD300; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                    <i class="bi bi-camera-fill" style="font-size: 12px; color: #133579;"></i>
                </div>
            </div>
            <!-- Formulario oculto para subir foto -->
            <form id="formFotoDashboard" action="subir_foto_dashboard.php" method="post" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="inputFoto" name="foto" accept="image/*" onchange="document.getElementById('formFotoDashboard').submit()">
            </form>
            <strong><?= $_SESSION['nombre'] ?></strong><br>
            <small style="color: #FFD300;"><?= ucfirst($_SESSION['rol']) ?></small>
        </div>
        <ul class="nav flex-column mt-2">
            <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="registrar.php"><i class="bi bi-person-plus"></i> Registrar</a></li>
            <li class="nav-item"><a class="nav-link" href="logros.php"><i class="bi bi-journal-plus"></i> Gestionar Logros</a></li>
            <li class="nav-item"><a class="nav-link" href="buscar.php"><i class="bi bi-search"></i> Buscar Miembro</a></li>
            <li class="nav-item"><a class="nav-link" href="admin_miembros.php"><i class="bi bi-people"></i> Miembros</a></li>
            <li class="nav-item"><a class="nav-link" href="consagrar.php"><i class="bi bi-trophy"></i> Ganadores</a></li>
            <li class="nav-item"><a class="nav-link" href="informes.php"><i class="bi bi-file-earmark-bar-graph"></i> Informes</a></li>
            <li class="nav-item"><a class="nav-link" href="lider_especialidades.php"><i class="bi bi-person-check-fill"></i> Especialidades de Líderes</a></li>
            <li class="nav-item mt-4"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div>
                <h2><i class="bi bi-grid-fill me-2" style="color: #FFD300;"></i>Dashboard</h2>
                <small class="text-muted">Resumen general del club</small>
            </div>
            <div>
                <span class="text-muted"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div><p class="stat-label">Miembros Activos</p><h3 class="stat-value"><?= $total_miembros ?></h3></div></div>
            <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-fill"></i></div><p class="stat-label">Conquistadores</p><h3 class="stat-value"><?= $total_conquistadores ?></h3></div></div>
            <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-person-badge"></i></div><p class="stat-label">Líderes</p><h3 class="stat-value"><?= $total_lideres ?></h3></div></div>
            <div class="col-xl-3 col-md-6 mb-4"><div class="stat-card"><div class="icon-circle bg-info bg-opacity-10 text-info"><i class="bi bi-star-fill"></i></div><p class="stat-label">Especialidades <?= date('Y') ?></p><h3 class="stat-value"><?= $logros_anio ?></h3></div></div>
        </div>

        <!-- Quick Actions -->
        <h5 class="fw-bold mb-3 text-uppercase text-muted" style="letter-spacing: 1px;">Accesos Rápidos</h5>
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3"><a href="registrar.php" class="quick-action" style="border-top: 3px solid #E42613;"><div class="action-icon text-danger"><i class="bi bi-person-plus-fill"></i></div><h5>Registrar Integrante</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="logros.php" class="quick-action" style="border-top: 3px solid #1BA1AD;"><div class="action-icon text-info"><i class="bi bi-journal-plus"></i></div><h5>Gestionar Logros</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="buscar.php" class="quick-action" style="border-top: 3px solid #FFD300;"><div class="action-icon text-warning"><i class="bi bi-search"></i></div><h5>Buscar Miembro</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="admin_miembros.php" class="quick-action" style="border-top: 3px solid #133579;"><div class="action-icon text-primary"><i class="bi bi-gear-fill"></i></div><h5>Administrar Miembros</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="calendario.php" class="quick-action" style="border-top: 3px solid #E42613;"><div class="action-icon text-danger"><i class="bi bi-calendar-heart"></i></div><h5>Calendario</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="lider_especialidades.php" class="quick-action" style="border-top: 3px solid #1BA1AD;"><div class="action-icon text-info"><i class="bi bi-person-check-fill"></i></div><h5>Especialidades de Líderes</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="consagrar.php" class="quick-action" style="border-top: 3px solid #FFD300;"><div class="action-icon text-warning"><i class="bi bi-trophy-fill"></i></div><h5>Consagrar Ganadores</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="notificaciones_config.php" class="quick-action" style="border-top: 3px solid #133579;"><div class="action-icon text-primary"><i class="bi bi-envelope"></i></div><h5>Destinatarios</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="informes.php" class="quick-action" style="border-top: 3px solid #1BA1AD;"><div class="action-icon text-info"><i class="bi bi-file-earmark-bar-graph"></i></div><h5>Informes</h5></a></div>
            <div class="col-xl-3 col-md-6 mb-3"><a href="promocion_clase.php" class="quick-action" style="border-top: 3px solid #1BA1AD;">
            
            
            <div class="action-icon text-info"><i class="bi bi-calendar-check"></i></div><h5>Promoción y Líderes</h5></a></div>
       <div class="col-xl-3 col-md-6 mb-3">
    <a href="actividades.php" class="quick-action" style="border-top: 3px solid #FFD300;">
        <div class="action-icon text-warning"><i class="bi bi-star-fill"></i></div>
        <h5>Actividades y Puntajes</h5>
    </a>
</div>
       
       
        </div>

        <div class="row">
            <!-- Ranking de especialidades -->
            <div class="col-xl-6 mb-4">
                <div class="card-table">
                    <h5 class="fw-bold mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Top Especialidades <?= date('Y') ?></h5>
                    <?php if ($ranking && $ranking->num_rows > 0): ?>
                        <?php $medallas = ['🥇','🥈','🥉','4°','5°']; $i = 0; ?>
                        <?php while ($r = $ranking->fetch_assoc()): ?>
                            <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #f8f9fa;">
                                <span style="font-size: 2rem; margin-right: 15px;"><?= $medallas[$i] ?></span>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['unidad'] ?? 'Sin unidad') ?></small>
                                </div>
                                <span class="badge bg-warning text-dark fs-6"><?= $r['total'] ?> esp.</span>
                            </div>
                        <?php $i++; endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Sin registros este año.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ganadores del año -->
            <div class="col-xl-6 mb-4">
                <div class="card-table">
                    <h5 class="fw-bold mb-3"><i class="bi bi-award-fill text-warning me-2"></i>Ganadores <?= date('Y') ?></h5>
                    <?php if ($ganadores_anio && $ganadores_anio->num_rows > 0): ?>
                        <?php $medallas = [1=>'🥇',2=>'🥈',3=>'🥉']; ?>
                        <?php while ($g = $ganadores_anio->fetch_assoc()): ?>
                            <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: linear-gradient(135deg, #fff9e6, #fff3cc);">
                                <span style="font-size: 2.5rem; margin-right: 15px;"><?= $medallas[$g['posicion']] ?? '⭐' ?></span>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($g['unidad'] ?? '') ?></small>
                                </div>
                                <span class="badge bg-dark fs-6"><?= number_format($g['puntaje_total'], 1) ?> pts</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Aún no se han consagrado ganadores.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Próximos cumpleaños -->
        <div class="card-table mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-cake2 text-danger me-2"></i>Próximos Cumpleaños (7 días)</h5>
            <?php if ($cumpleanios->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Nombre</th><th>Fecha</th><th>Edad a cumplir</th></tr></thead>
                        <tbody>
                            <?php while ($c = $cumpleanios->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $c['nombre'] . ' ' . $c['apellido'] ?></strong></td>
                                    <td><?= $c['fecha'] ?></td>
                                    <td><span class="badge bg-danger rounded-pill"><?= $c['edad'] + 1 ?> años</span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center mb-0">No hay cumpleaños próximos.</p>
            <?php endif; ?>
        </div>

        <!-- Estado del envío de correos -->
        <?php
        $envio_hoy = $conn->query("SELECT MAX(enviado_en) AS hora, COUNT(*) AS total FROM notificacion_envios WHERE fecha_envio = CURDATE()")->fetch_assoc();
        $destinatarios_activos = $conn->query("SELECT COUNT(*) AS total FROM notificacion_destinatarios WHERE activo = 1")->fetch_assoc()['total'];
        ?>
        <div class="card-table mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Notificaciones por Correo</h5>
            <?php if ($envio_hoy['hora']): ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-check-circle-fill text-success fs-4 me-2"></i>
                    <div>
                        <strong>Enviado hoy</strong> a las <?= date('H:i', strtotime($envio_hoy['hora'])) ?> 
                        (<?= $envio_hoy['total'] ?> destinatario<?= $envio_hoy['total'] != 1 ? 's' : '' ?>)
                    </div>
                </div>
                <a href="notificar.php?forzar=1" class="btn btn-sm btn-outline-warning" target="_blank">
                    <i class="bi bi-arrow-repeat"></i> Reenviar
                </a>
            <?php else: ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-envelope-dash text-secondary fs-4 me-2"></i>
                    <div>
                        <strong>Aún no se envió hoy</strong> (<?= $destinatarios_activos ?> destinatario<?= $destinatarios_activos != 1 ? 's' : '' ?> activo<?= $destinatarios_activos != 1 ? 's' : '' ?>)
                    </div>
                </div>
                <a href="notificar.php" class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="bi bi-send"></i> Enviar ahora
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>