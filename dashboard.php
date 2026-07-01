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

// Foto del usuario logueado
$foto_usuario = $conn->query("SELECT foto FROM usuarios WHERE id = {$_SESSION['usuario_id']}")->fetch_assoc()['foto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f8fafc;
            --primary-club: #1e40af;
            --accent-club: #f59e0b;
            --card-shadow: 0 8px 30px -8px rgba(15, 23, 42, 0.06), 0 4px 16px -6px rgba(15, 23, 42, 0.03);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-main); }

        /* Topbar */
        .topbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            padding: 12px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
        }
        .topbar .brand img { height: 36px; }
        .topbar .user-area {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }
        .topbar .user-area .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0f172a;
            background-color: var(--accent-club);
            cursor: pointer;
            transition: var(--transition);
        }
        .topbar .user-area .avatar:hover { transform: scale(1.05); }
        .topbar .user-area .btn-logout {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 10px;
            transition: var(--transition);
        }
        .topbar .user-area .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
        }

        /* Contenido Principal */
        .main-content {
            padding: 30px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Tarjetas de Estadísticas */
        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 26px;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.8);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px -5px rgba(15, 23, 42, 0.08); }
        .stat-card .icon-circle {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .stat-value { font-size: 2rem; font-weight: 800; color: #0f172a; margin: 8px 0 2px 0; }
        .stat-label { color: #64748b; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Accesos Rápidos */
        .quick-action {
            background: white;
            border-radius: 20px;
            padding: 24px 16px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
            text-decoration: none;
            color: #334155;
            display: block;
            height: 100%;
            border: 1px solid rgba(241, 245, 249, 1);
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1); }
        .quick-action .action-icon { font-size: 2rem; margin-bottom: 12px; display: inline-block; transition: var(--transition); }
        .quick-action:hover .action-icon { transform: scale(1.1); }
        .quick-action h5 { font-weight: 700; font-size: 0.9rem; margin: 0; color: #1e293b; }

        /* Contenedores de Tablas y Listas */
        .card-table {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.8);
        }
        .card-table h5 { font-size: 1.1rem; color: #0f172a; }

        .ranking-item {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            transition: var(--transition);
        }
        .ranking-item:hover { background: #f1f5f9; }

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Barra Superior -->
    <div class="topbar">
        <div class="brand">
            <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
            <span>BETELGEUSE</span>
        </div>
        <div class="user-area">
            <!-- Avatar con foto o inicial -->
            <div style="position: relative; cursor: pointer;" onclick="document.getElementById('inputFoto').click()">
                <?php if ($foto_usuario): ?>
                    <div class="avatar" style="background-image: url('<?= $foto_usuario ?>');"></div>
                <?php else: ?>
                    <div class="avatar"><?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?></div>
                <?php endif; ?>
                <div style="position: absolute; bottom: -2px; right: -2px; background: var(--accent-club); width: 18px; height: 18px; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 2px solid #0f172a;">
                    <i class="bi bi-camera-fill" style="font-size: 9px; color: #0f172a;"></i>
                </div>
            </div>
            <form id="formFotoDashboard" action="subir_foto_dashboard.php" method="post" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="inputFoto" name="foto" accept="image/*" onchange="document.getElementById('formFotoDashboard').submit()">
            </form>
            <div class="d-none d-sm-block">
                <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>
                <small class="d-block text-white-50"><?= ucfirst($_SESSION['rol']) ?></small>
            </div>
            <a href="logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Salir
            </a>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="main-content">
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Bienvenido de nuevo 👋</h2>
                <small class="text-muted">Resumen general del club Betelgeuse</small>
            </div>
            <div class="d-none d-sm-block">
                <span class="badge bg-light text-dark p-2 px-3 rounded-3 fs-6 fw-semibold">
                    <i class="bi bi-calendar3 me-2 text-primary"></i> <?= date('d/m/Y') ?>
                </span>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="row mb-5">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card" style="border-top: 4px solid #3b82f6;">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                    <p class="stat-label">Miembros Activos</p>
                    <h3 class="stat-value"><?= $total_miembros ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card" style="border-top: 4px solid #ef4444;">
                    <div class="icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-shield-fill"></i></div>
                    <p class="stat-label">Conquistadores</p>
                    <h3 class="stat-value"><?= $total_conquistadores ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-person-badge-fill"></i></div>
                    <p class="stat-label">Líderes</p>
                    <h3 class="stat-value"><?= $total_lideres ?></h3>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card" style="border-top: 4px solid #10b981;">
                    <div class="icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-award-fill"></i></div>
                    <p class="stat-label">Especialidades <?= date('Y') ?></p>
                    <h3 class="stat-value"><?= $logros_anio ?></h3>
                </div>
            </div>
        </div>

        <!-- Accesos Rápidos Agrupados -->
        <h6 class="fw-bold mb-3 text-uppercase text-muted" style="letter-spacing: 1px; font-size: 0.75rem;">Accesos Rápidos</h6>

        <!-- Grupo 1: Gestión Principal -->
        <div class="mb-4">
            <small class="text-muted fw-semibold d-block mb-2" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="bi bi-gear me-1"></i> Gestión Principal
            </small>
            <div class="row">
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="registrar.php" class="quick-action" style="border-top: 3px solid #3b82f6;">
                        <div class="action-icon text-primary"><i class="bi bi-person-plus"></i></div>
                        <h5>Registrar</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="logros.php" class="quick-action" style="border-top: 3px solid #10b981;">
                        <div class="action-icon text-success"><i class="bi bi-journal-plus"></i></div>
                        <h5>Logros</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="buscar.php" class="quick-action" style="border-top: 3px solid #f59e0b;">
                        <div class="action-icon text-warning"><i class="bi bi-search"></i></div>
                        <h5>Buscar</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="admin_miembros.php" class="quick-action" style="border-top: 3px solid #6366f1;">
                        <div class="action-icon text-info"><i class="bi bi-sliders"></i></div>
                        <h5>Administrar</h5>
                    </a>
                </div>
            </div>
        </div>

        <!-- Grupo 2: Actividades y Eventos -->
        <div class="mb-4">
            <small class="text-muted fw-semibold d-block mb-2" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="bi bi-calendar-event me-1"></i> Actividades y Eventos
            </small>
            <div class="row">
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="calendario.php" class="quick-action" style="border-top: 3px solid #ef4444;">
                        <div class="action-icon text-danger"><i class="bi bi-calendar-event"></i></div>
                        <h5>Calendario</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="actividades.php" class="quick-action" style="border-top: 3px solid #FFD300;">
                        <div class="action-icon text-warning"><i class="bi bi-star-fill"></i></div>
                        <h5>Actividades</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="promocion_clase.php" class="quick-action" style="border-top: 3px solid #f97316;">
                        <div class="action-icon text-orange"><i class="bi bi-arrow-up-circle"></i></div>
                        <h5>Promoción</h5>
                    </a>
                </div>
            </div>
        </div>

        <!-- Grupo 3: Reportes y Configuración -->
        <div class="mb-5">
            <small class="text-muted fw-semibold d-block mb-2" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="bi bi-graph-up me-1"></i> Reportes y Configuración
            </small>
            <div class="row">
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="informes.php" class="quick-action" style="border-top: 3px solid #6366f1;">
                        <div class="action-icon text-info"><i class="bi bi-file-earmark-bar-graph"></i></div>
                        <h5>Informes</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="consagrar.php" class="quick-action" style="border-top: 3px solid #f59e0b;">
                        <div class="action-icon text-warning"><i class="bi bi-trophy-fill"></i></div>
                        <h5>Consagrar</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="lider_especialidades.php" class="quick-action" style="border-top: 3px solid #8b5cf6;">
                        <div class="action-icon text-purple"><i class="bi bi-person-check-fill"></i></div>
                        <h5>Especialidades</h5>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-xl-3 mb-3">
                    <a href="notificaciones_config.php" class="quick-action" style="border-top: 3px solid #14b8a6;">
                        <div class="action-icon text-teal"><i class="bi bi-envelope"></i></div>
                        <h5>Destinatarios</h5>
                    </a>
                </div>
            </div>
        </div>

        <!-- Ranking y Ganadores -->
        <div class="row">
            <div class="col-xl-6 mb-4">
                <div class="card-table">
                    <h5 class="fw-bold mb-4"><i class="bi bi-trophy-fill text-warning me-2"></i>Top Especialidades <?= date('Y') ?></h5>
                    <?php if ($ranking && $ranking->num_rows > 0): ?>
                        <?php $medallas = ['🥇','🥈','🥉','4°','5°']; $i = 0; ?>
                        <?php while ($r = $ranking->fetch_assoc()): ?>
                            <div class="d-flex align-items-center mb-3 p-3 rounded-4 ranking-item">
                                <span style="font-size: 1.6rem; margin-right: 15px; width: 35px; text-align: center;"><?= $medallas[$i] ?></span>
                                <div class="flex-grow-1">
                                    <strong class="text-dark"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($r['unidad'] ?? 'Sin unidad') ?></small>
                                </div>
                                <span class="badge bg-white text-dark border p-2 px-3 rounded-pill fw-bold" style="font-size: 0.85rem;"><?= $r['total'] ?> esp.</span>
                            </div>
                        <?php $i++; endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Sin registros este año.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-6 mb-4">
                <div class="card-table">
                    <h5 class="fw-bold mb-4"><i class="bi bi-award-fill text-primary me-2"></i>Ganadores Destacados <?= date('Y') ?></h5>
                    <?php if ($ganadores_anio && $ganadores_anio->num_rows > 0): ?>
                        <?php $medallas = [1=>'🥇',2=>'🥈',3=>'🥉']; ?>
                        <?php while ($g = $ganadores_anio->fetch_assoc()): ?>
                            <div class="d-flex align-items-center mb-3 p-3 rounded-4" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0;">
                                <span style="font-size: 1.8rem; margin-right: 15px;"><?= $medallas[$g['posicion']] ?? '⭐' ?></span>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></strong>
                                    <br><small class="text-success fw-medium"><?= htmlspecialchars($g['unidad'] ?? '') ?></small>
                               </div>
                                <span class="badge bg-success p-2 px-3 rounded-pill fs-6 fw-bold"><?= number_format($g['puntaje_total'], 0) ?> pts</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Aún no se han consagrado ganadores.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Próximos Cumpleaños -->
        <div class="card-table mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-cake2-fill text-danger me-2"></i>Próximos Cumpleaños <span class="text-muted fw-normal fs-6">(Siguientes 7 días)</span></h5>
            <?php if ($cumpleanios->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.85rem;">
                                <th>INTEGRANTE</th>
                                <th>FECHA</th>
                                <th>EDAD A CUMPLIR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($c = $cumpleanios->fetch_assoc()): ?>
                                <tr>
                                    <td><strong class="text-dark"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></strong></td>
                                    <td class="text-secondary fw-semibold"><i class="bi bi-calendar-event me-1"></i> <?= $c['fecha'] ?></td>
                                    <td><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold">+<?= $c['edad'] + 1 ?> años</span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center mb-0 py-3">No hay cumpleaños próximos esta semana.</p>
            <?php endif; ?>
        </div>

        <!-- Estado del envío de correos -->
        <?php
        $envio_hoy = $conn->query("SELECT MAX(enviado_en) AS hora, COUNT(*) AS total FROM notificacion_envios WHERE fecha_envio = CURDATE()")->fetch_assoc();
        $destinatarios_activos = $conn->query("SELECT COUNT(*) AS total FROM notificacion_destinatarios WHERE activo = 1")->fetch_assoc()['total'];
        ?>
        <div class="card-table mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-envelope-paper-fill text-secondary me-2"></i>Módulo de Notificaciones Automatizadas</h5>
            <div class="p-3 rounded-4 border bg-light d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <?php if ($envio_hoy['hora']): ?>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill text-success fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Reporte Diario Enviado</h6>
                            <small class="text-muted">Procesado hoy a las <?= date('H:i', strtotime($envio_hoy['hora'])) ?> para <?= $envio_hoy['total'] ?> destinatario<?= $envio_hoy['total'] != 1 ? 's' : '' ?>.</small>
                        </div>
                    </div>
                    <a href="notificar.php?forzar=1" class="btn btn-sm btn-dark px-3 rounded-3" target="_blank">
                        <i class="bi bi-arrow-repeat me-1"></i> Forzar Reenvío
                    </a>
                <?php else: ?>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-circle-fill text-warning fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Envío Pendiente u Omisión</h6>
                            <small class="text-muted">No se registran envíos hoy (Hay <?= $destinatarios_activos ?> destinatarios en cola).</small>
                        </div>
                    </div>
                    <a href="notificar.php" class="btn btn-sm btn-primary px-3 rounded-3" target="_blank">
                        <i class="bi bi-send-fill me-1"></i> Despachar Ahora
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>