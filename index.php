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
            --primary-blue: #0F2A60;
            --primary-light: #184194;
            --secondary-cyan: #148994;
            --accent-yellow: #FFD300;
            --accent-red: #D92211;
            --bg-light: #F7FAFC;
            --text-dark: #1A202C;
            --card-shadow: 0 12px 30px rgba(15, 42, 96, 0.04), 0 4px 12px rgba(0, 0, 0, 0.02);
            --transition-smooth: all 0.35s cubic-bezier(0.25, 1, 0.5, 1);
        }

        * { font-family: 'Montserrat', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); -webkit-font-smoothing: antialiased; }

        /* Navbar Smooth Look */
        .navbar-custom {
            background: rgba(15, 42, 90, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            transition: var(--transition-smooth);
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            font-size: 1.3rem;
            color: #ffffff !important;
            letter-spacing: 0.5px;
        }
        .navbar-custom .navbar-brand span {
            color: var(--accent-yellow);
        }
        .navbar-custom .nav-link {
            font-weight: 600;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.8) !important;
            padding: 6px 14px !important;
            border-radius: 20px;
            transition: var(--transition-smooth);
        }
        .navbar-custom .nav-link:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Modernized Hero Section */
        .hero {
            background: radial-gradient(circle at 90% 10%, rgba(20, 137, 148, 0.2) 0%, transparent 45%),
                        linear-gradient(135deg, var(--primary-blue) 0%, #051026 100%);
            position: relative;
            padding: 180px 0 130px;
        }
        .hero h1 {
            font-weight: 800;
            font-size: 4rem;
            color: #ffffff;
            letter-spacing: -1px;
            line-height: 1.1;
        }
        .hero .btn-cta {
            background-color: var(--accent-red);
            color: white;
            border: none;
            font-weight: 700;
            padding: 16px 42px;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
            transition: var(--transition-smooth);
            box-shadow: 0 10px 25px rgba(217, 34, 17, 0.35);
        }
        .hero .btn-cta:hover {
            background-color: #b81b0e;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(217, 34, 17, 0.5);
        }

        /* Section Header Styles */
        .section-title {
            font-weight: 800;
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 50px;
            color: var(--primary-blue);
            letter-spacing: -0.5px;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 5px;
            background: var(--secondary-cyan);
            margin: 18px auto 0;
            border-radius: 10px;
        }

        /* Hall of Fame & Podium Optimization */
        .year-header { margin-bottom: 45px; }
        .year-badge {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 8px 20px rgba(15, 42, 96, 0.15);
        }
        
        .winner-card {
            background: white;
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            transition: var(--transition-smooth);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.03);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        .winner-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 38px rgba(15, 42, 96, 0.08);
        }
        
        /* Premium Podium Styling */
        .winner-card.gold { 
            background: linear-gradient(180deg, #FFFDF0 0%, #FFFFFF 100%);
            border: 2px solid rgba(255, 211, 0, 0.3);
        }
        .winner-card.silver { 
            background: linear-gradient(180deg, #F8FAFC 0%, #FFFFFF 100%);
            border: 2px solid rgba(203, 213, 225, 0.4);
        }
        .winner-card.bronze { 
            background: linear-gradient(180deg, #FFFAF5 0%, #FFFFFF 100%);
            border: 2px solid rgba(237, 137, 54, 0.2);
        }
        
        .medal-icon {
            font-size: 4rem;
            line-height: 1;
            margin-bottom: 20px;
            filter: drop-shadow(0 6px 8px rgba(0,0,0,0.08));
        }
        .winner-name {
            font-weight: 700;
            font-size: 1.35rem;
            color: var(--primary-blue);
            margin-bottom: 6px;
        }
        .winner-unit {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 22px;
        }
        .winner-score {
            align-self: center;
            background: #EDF2F7;
            color: var(--primary-blue);
            padding: 8px 22px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition-smooth);
        }
        .winner-card.gold .winner-score {
            background: var(--accent-yellow);
            color: #000000;
        }

        /* Clean Top 10 Table Layout */
        .top-table-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.02);
            overflow: hidden;
            padding: 10px;
        }
        .table-custom th {
            background: #FFFFFF;
            color: #718096;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 20px 24px;
            border-bottom: 2px solid #F1F5F9;
        }
        .table-custom td {
            padding: 20px 24px;
            vertical-align: middle;
            color: var(--text-dark);
            border-bottom: 1px solid #F1F5F9;
        }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tbody tr { border-radius: 12px; transition: var(--transition-smooth); }
        .table-custom tbody tr:hover { background-color: #F8FAFC; transform: scale(1.005); }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            background: #EDF2F7;
            color: #4A5568;
        }
        .rank-1 { background: #FFF9E6; color: #D69E2E; font-size: 1.1rem; }
        .rank-2 { background: #E2E8F0; color: #4A5568; }
        .rank-3 { background: #FFFAF0; color: #DD6B20; }

        .avatar-placeholder {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(20, 137, 148, 0.1);
            color: var(--secondary-cyan);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* Feature Cards - Info Grid */
        .nosotros-section {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #06132D 100%);
            color: white;
        }
        .nosotros-section .icon-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 24px;
            padding: 45px 35px;
            transition: var(--transition-smooth);
            height: 100%;
        }
        .nosotros-section .icon-box:hover {
            transform: translateY(-6px);
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
        }
        .nosotros-section .icon-circle {
            width: 75px;
            height: 75px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* Footer */
        .footer {
            background: #040D1F;
            color: #718096;
            padding: 40px 0;
            font-size: 0.95rem;
            border-top: 1px solid rgba(255,255,255,0.04);
        }

        @media (min-width: 768px) {
            .winner-card.gold {
                transform: scale(1.05);
                z-index: 2;
            }
            .winner-card.gold:hover {
                transform: scale(1.07) translateY(-4px);
            }
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.8rem; }
            .hero { padding: 15px 0 80px; }
            .section-title { font-size: 2rem; }
            .podium-row { display: flex; flex-direction: column !important; gap: 1.5rem; }
            .podium-order-1 { order: 1 !important; }
            .podium-order-2 { order: 2 !important; }
            .podium-order-3 { order: 3 !important; }
            .winner-card.gold { transform: none; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="assets/img/logo.png" alt="Logo" style="height: 38px; background: transparent;">
                <span>CLUB <span>BETELGEUSE</span></span>
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
            <img src="assets/img/logo.png" alt="Logo Club Betelgeuse" style="height: 150px; margin-bottom: 25px; filter: drop-shadow(0 15px 25px rgba(0,0,0,0.4)); background: transparent;">
            <h1 class="mb-2">Club de Conquistadores</h1>
            <h2 class="display-3 mb-4" style="color: var(--accent-yellow); font-weight: 800; letter-spacing: 1px;">BETELGEUSE</h2>
            <p class="lead text-white-50 max-w-2xl mx-auto mb-5" style="font-size: 1.25rem; font-weight: 500;">Formando líderes para el servicio de Dios y la comunidad</p>
            <a href="#ganadores" class="btn btn-cta rounded-pill">
                <i class="bi bi-trophy-fill me-2"></i> Explorar Ganadores
            </a>
        </div>
    </section>

    <section id="ganadores" class="py-5">
        <div class="container py-5">
            <h2 class="section-title">🏆 Salón de la Fama</h2>
            
            <?php if (!empty($por_anio)): ?>
                <?php foreach ($por_anio as $anio => $ganadores_anio): ?>
                    
                    <div class="text-center year-header">
                        <span class="year-badge"><i class="bi bi-calendar3 me-2"></i>Edición <?= $anio ?></span>
                    </div>

                    <?php 
                    $podio = [1 => null, 2 => null, 3 => null];
                    foreach ($ganadores_anio as $g) {
                        if (in_array($g['posicion'], [1, 2, 3])) {
                            $podio[$g['posicion']] = $g;
                        }
                    }
                    ?>

                    <div class="row g-4 justify-content-center align-items-center podium-row mb-5">
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
                        <div class="col-md-4 podium-order-1">
                            <div class="winner-card gold py-5">
                                <div class="medal-icon">🥇</div>
                                <div class="winner-name fs-3"><?= htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) ?></div>
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
        <div class="container py-5">
            <h2 class="section-title">📊 Top 10 — Especialidades <?= date('Y') ?></h2>
            
            <?php if ($top10 && $top10->num_rows > 0): ?>
                <div class="row">
                    <div class="col-lg-9 mx-auto">
                        <div class="top-table-card table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 110px;" class="text-center">Puesto</th>
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
                                                    <span class="fw-bold text-dark" style="font-size:0.95rem;"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-secondary rounded-pill px-3 py-2 fw-semibold border">
                                                    <i class="bi bi-tag-fill me-1 text-muted"></i><?= htmlspecialchars($t['unidad'] ?? 'Sin unidad') ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold" style="color: var(--secondary-cyan); font-size: 1.05rem;">
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
        <div class="container py-5 text-center">
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
                        <div class="icon-circle" style="background: rgba(217, 34, 17, 0.15); color: var(--accent-red);"><i class="bi bi-people-fill"></i></div>
                        <h4 class="fw-bold mb-3">Compañerismo</h4>
                        <p class="opacity-75 small lh-lg">Fomentando lazos fraternales saludables, el trabajo colectivo y la empatía en cada una de nuestras actividades.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="icon-box">
                        <div class="icon-circle" style="background: rgba(20, 137, 148, 0.15); color: var(--secondary-cyan);"><i class="bi bi-book-fill"></i></div>
                        <h4 class="fw-bold mb-3">Aprendizaje</h4>
                        <p class="opacity-75 small lh-lg">Desarrollando talentos prácticos, obtención de nuevas especialidades científicas, técnicas y de liderazgo integral.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer text-center">
        <div class="container">
            <p class="mb-2">© <?= date('Y') ?> Club de Conquistadores <strong style="color: var(--accent-yellow);">Betelgeuse</strong></p>
            <span class="opacity-50 small">Desarrollado para el crecimiento y liderazgo juvenil de manera íntegra</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>