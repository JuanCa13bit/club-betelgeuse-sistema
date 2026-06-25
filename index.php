<?php
require_once 'includes/config.php';

// Obtener ganadores por año
$ganadores = $conn->query("
    SELECT g.anio, g.posicion, g.puntaje_total, u.nombre, u.apellido, un.nombre AS unidad
    FROM ganadores_anuales g
    JOIN miembros m ON g.miembro_id = m.id
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    ORDER BY g.anio DESC, g.posicion ASC
");

$por_anio = [];
while ($g = $ganadores->fetch_assoc()) {
    $por_anio[$g['anio']][] = $g;
}

// Top 10 del año actual
$top10 = $conn->query("
    SELECT u.nombre, u.apellido, un.nombre AS unidad, COUNT(le.id) AS total_especialidades
    FROM miembros m
    JOIN usuarios u ON m.usuario_id = u.id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN logros_especialidad le ON m.id = le.miembro_id AND le.anio = YEAR(CURDATE())
    WHERE m.activo = 1 AND m.tipo = 'conquistador'
    GROUP BY m.id, u.nombre, u.apellido, un.nombre
    ORDER BY total_especialidades DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Club de Conquistadores Betelgeuse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #133579;
            --secondary-cyan: #1BA1AD;
            --accent-yellow: #FFD300;
            --accent-red: #E42613;
            --bg-light: #f4f6f9;
            --text-dark: #2d3748;
        }

        * { font-family: 'Montserrat', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); }

        /* Navbar */
        .navbar-custom {
            background: rgba(19, 53, 121, 0.96);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--accent-yellow) !important;
            letter-spacing: 0.5px;
        }
        .navbar-custom .nav-link {
            font-weight: 600;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.85) !important;
            transition: color 0.2s;
        }
        .navbar-custom .nav-link:hover {
            color: var(--accent-yellow) !important;
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0d2353 100%);
            position: relative;
            padding: 170px 0 120px;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at 80% 20%, rgba(27, 161, 173, 0.15) 0%, transparent 50%);
        }
        .hero h1 {
            font-weight: 800;
            font-size: 3.8rem;
            color: #ffffff;
        }
        .hero .btn-cta {
            background-color: var(--accent-red);
            color: white;
            border: none;
            font-weight: 700;
            padding: 14px 38px;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(228, 38, 19, 0.3);
        }
        .hero .btn-cta:hover {
            background-color: #c41e10;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(228, 38, 19, 0.4);
        }

        /* Secciones */
        .section-title {
            font-weight: 800;
            font-size: 2.3rem;
            text-align: center;
            margin-bottom: 45px;
            color: var(--primary-blue);
        }
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 5px;
            background: var(--accent-yellow);
            margin: 15px auto 0;
            border-radius: 10px;
        }

        /* Salón de la Fama / Podio */
        .year-header {
            margin-bottom: 40px;
        }
        .year-badge {
            background: #ffffff;
            color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.2rem;
            border: 1px solid rgba(19, 53, 121, 0.1);
        }
        .winner-card {
            background: white;
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.02);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .winner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(19, 53, 121, 0.08);
        }
        
        /* Variaciones del podio */
        .winner-card.gold { border-top: 6px solid var(--accent-yellow); background: linear-gradient(to bottom, #fffcf0, #ffffff); }
        .winner-card.silver { border-top: 6px solid #CBD5E1; }
        .winner-card.bronze { border-top: 6px solid #ED8936; }
        
        .medal-icon {
            font-size: 3.5rem;
            line-height: 1;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }
        .winner-name {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-blue);
            margin-bottom: 4px;
        }
        .winner-unit {
            color: #718096;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 18px;
        }
        .winner-score {
            align-self: center;
            background: var(--primary-blue);
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .winner-card.gold .winner-score {
            background: var(--accent-yellow);
            color: #000;
        }

        /* Tabla Top 10 */
        .top-table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.01);
            overflow: hidden;
        }
        .table-custom {
            margin-bottom: 0;
        }
        .table-custom th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 24px;
            border-bottom: 1px solid #edf2f7;
        }
        .table-custom td {
            padding: 18px 24px;
            vertical-align: middle;
            color: var(--text-dark);
            border-bottom: 1px solid #f1f5f9;
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }
        .table-custom tr {
            transition: background 0.2s;
        }
        .table-custom tr:hover {
            background-color: #f8fafc;
        }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.9rem;
            background: #edf2f7;
            color: #4a5568;
        }
        .rank-1 { background: #FFF9E6; color: #D69E2E; }
        .rank-2 { background: #EDF2F7; color: #4A5568; }
        .rank-3 { background: #FFFAF0; color: #DD6B20; }

        .avatar-placeholder {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(27, 161, 173, 0.1);
            color: var(--secondary-cyan);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Nosotros */
        .nosotros-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0a1d43 100%);
            color: white;
            position: relative;
        }
        .nosotros-section .icon-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 40px 30px;
            transition: transform 0.3s;
            height: 100%;
        }
        .nosotros-section .icon-box:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.06);
        }
        .nosotros-section .icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 1.8rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Footer */
        .footer {
            background: #06132d;
            color: #a0aec0;
            padding: 35px 0;
            font-size: 0.9rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero { padding: 140px 0 90px; }
            .section-title { font-size: 1.8rem; }
            /* Deshacer orden del podio en móvil */
            .podium-row { display: flex; flex-direction: column !important; }
            .podium-order-1 { order: 1 !important; }
            .podium-order-2 { order: 2 !important; }
            .podium-order-3 { order: 3 !important; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="assets/img/logo.png" alt="Logo" style="height: 35px; background: transparent;">
                <span>CLUB BETELGEUSE</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto gap-2">
                    <li class="nav-item"><a class="nav-link" href="#ganadores">🏆 Hall de la Fama</a></li>
                    <li class="nav-item"><a class="nav-link" href="#top10">📊 Top 10</a></li>
                    <li class="nav-item"><a class="nav-link" href="#nosotros">ℹ️ Nosotros</a></li>
                </ul>
                <a href="login.php" class="btn btn-outline-warning ms-lg-4 rounded-pill px-4 fw-bold shadow-sm btn-sm py-2">
                    <i class="bi bi-lock-fill me-1"></i> Acceso
                </a>
            </div>
        </div>
    </nav>

    <section class="hero text-center d-flex align-items-center">
        <div class="container position-relative">
            <img src="assets/img/logo.png" alt="Logo Club Betelgeuse" style="height: 140px; margin-bottom: 25px; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3)); background: transparent;">
            <h1 class="mb-2">Club de Conquistadores</h1>
            <h2 class="display-4 mb-4" style="color: var(--accent-yellow); font-weight: 800;">BETELGEUSE</h2>
            <p class="lead text-white-50 max-w-2xl mx-auto mb-5" style="font-size: 1.2rem; font-weight: 500;">Formando líderes para el servicio de Dios y la comunidad</p>
            <a href="#ganadores" class="btn btn-cta rounded-pill">
                <i class="bi bi-trophy-fill me-2"></i> Explorar Ganadores
            </a>
        </div>
    </section>

    <section id="ganadores" class="py-5">
        <div class="container py-4">
            <h2 class="section-title">🏆 Salón de la Fama</h2>
            
            <?php if (!empty($por_anio)): ?>
                <?php foreach ($por_anio as $anio => $ganadores_anio): ?>
                    
                    <div class="text-center year-header">
                        <span class="year-badge"><i class="bi bi-calendar3 me-2"></i>Edición <?= $anio ?></span>
                    </div>

                    <?php 
                    // Mapeo rápido para ordenar el array visualmente como podio: [2° Lugar, 1° Lugar, 3° Lugar]
                    $podio = [1 => null, 2 => null, 3 => null];
                    foreach ($ganadores_anio as $g) {
                        if (in_array($g['posicion'], [1, 2, 3])) {
                            $podio[$g['posicion']] = $g;
                        }
                    }
                    ?>

                    <div class="row g-4 justify-content-center align-items-end podium-row mb-5">
                        <?php if ($podio[2]): $g = $podio[2]; ?>
                        <div class="col-md-4 podium-order-2">
                            <div class="winner-card silver">
                                <div class="medal-icon">🥈</div>
                                <div class="winner-name"><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></div>
                                <div class="winner-unit"><i class="bi bi-shield-fill text-secondary me-1"></i> <?= htmlspecialchars($g['unidad'] ?? 'Sin unidad') ?></div>
                                <span class="winner-score"><?= number_format($g['puntaje_total'], 1) ?> pts</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($podio[1]): $g = $podio[1]; ?>
                        <div class="col-md-4 podium-order-1 mb-md-3">
                            <div class="winner-card gold py-5 shadow-lg">
                                <div class="medal-icon">🥇</div>
                                <div class="winner-name fs-4"><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></div>
                                <div class="winner-unit"><i class="bi bi-shield-fill text-warning me-1"></i> <?= htmlspecialchars($g['unidad'] ?? 'Sin unidad') ?></div>
                                <span class="winner-score"><?= number_format($g['puntaje_total'], 1) ?> pts</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($podio[3]): $g = $podio[3]; ?>
                        <div class="col-md-4 podium-order-3">
                            <div class="winner-card bronze">
                                <div class="medal-icon">🥉</div>
                                <div class="winner-name"><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></div>
                                <div class="winner-unit"><i class="bi bi-shield-fill me-1" style="color:#ED8936"></i> <?= htmlspecialchars($g['unidad'] ?? 'Sin unidad') ?></div>
                                <span class="winner-score"><?= number_format($g['puntaje_total'], 1) ?> pts</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm max-w-md mx-auto">
                    <i class="bi bi-trophy text-body-tertiary" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 fw-bold text-secondary">Aún no hay ganadores registrados</h5>
                    <p class="text-muted small px-4">¡Pronto conoceremos a los conquistadores más destacados de este año!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="top10" class="py-5 bg-white">
        <div class="container py-4">
            <h2 class="section-title">📊 Top 10 — Especialidades <?= date('Y') ?></h2>
            
            <?php if ($top10 && $top10->num_rows > 0): ?>
                <div class="row">
                    <div class="col-lg-9 mx-auto">
                        <div class="top-table-card table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;" class="text-center">Puesto</th>
                                        <th>Conquistador</th>
                                        <th>Unidad</th>
                                        <th class="text-end">Especialidades</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $pos = 1; while ($t = $top10->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="rank-badge <?= $pos <= 3 ? 'rank-'.$pos : '' ?>">
                                                    <?= $pos ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-placeholder">
                                                        <?= mb_substr($t['nombre'], 0, 1) . mb_substr($t['apellido'], 0, 1) ?>
                                                    </div>
                                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-secondary rounded-pill px-3 py-2 fw-medium border">
                                                    <i class="bi bi-tag-fill me-1 text-black-50"></i><?= htmlspecialchars($t['unidad'] ?? 'Sin unidad') ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold text-primary-custom" style="color: var(--secondary-cyan)">
                                                <i class="bi bi-patch-check-fill me-1"></i> <?= $t['total_especialidades'] ?>
                                            </td>
                                        </tr>
                                    <?php $pos++; endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-bar-chart text-body-tertiary" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-muted fw-bold">Sin actividad registrada en <?= date('Y') ?></h5>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="nosotros" class="py-5 nosotros-section">
        <div class="container py-4 text-center">
            <h2 class="section-title text-white">ℹ️ Nuestro Enfoque</h2>
            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="icon-box">
                        <div class="icon-circle" style="background: rgba(255, 211, 0, 0.15); color: var(--accent-yellow);"><i class="bi bi-heart-fill"></i></div>
                        <h4 class="fw-bold mb-3">Servicio</h4>
                        <p class="opacity-75 small lh-lg">Comprometidos firmemente con Dios y nuestra comunidad mediante iniciativas de soporte y asistencia desinteresada.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="icon-box">
                        <div class="icon-circle" style="background: rgba(228, 38, 19, 0.15); color: var(--accent-red);"><i class="bi bi-people-fill"></i></div>
                        <h4 class="fw-bold mb-3">Compañerismo</h4>
                        <p class="opacity-75 small lh-lg">Fomentando lazos fraternales saludables, el trabajo colectivo y la empatía en cada una de nuestras actividades.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="icon-box">
                        <div class="icon-circle" style="background: rgba(27, 161, 173, 0.15); color: var(--secondary-cyan);"><i class="bi bi-book-fill"></i></div>
                        <h4 class="fw-bold mb-3">Aprendizaje</h4>
                        <p class="opacity-75 small lh-lg">Desarrollando talentos prácticos, obtención de nuevas especialidades científicas, técnicas y de liderazgo integral.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer text-center">
        <div class="container">
            <p class="mb-1">© <?= date('Y') ?> Club de Conquistadores <strong style="color: var(--accent-yellow);">Betelgeuse</strong></p>
            <span class="opacity-50 text-xs">Desarrollado para el crecimiento y liderazgo juvenil</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>