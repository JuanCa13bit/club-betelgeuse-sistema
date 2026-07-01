<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$mensaje = '';
$error = '';
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Guardar nuevo evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'] ?? null;
    $tipo = $_POST['tipo'];
    $creador = $_SESSION['usuario_id'];
    
    $conn->query("INSERT INTO eventos_calendario (titulo, descripcion, fecha, hora, tipo, creado_por) VALUES ('$titulo', '$descripcion', '$fecha', " . ($hora ? "'$hora'" : "NULL") . ", '$tipo', $creador)");
    $mensaje = "Evento agregado correctamente.";
}

// Eliminar evento
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conn->query("DELETE FROM eventos_calendario WHERE id = $id");
    $mensaje = "Evento eliminado.";
}

// Obtener eventos del mes
$primer_dia = "$anio-$mes-01";
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));
$eventos = $conn->query("SELECT * FROM eventos_calendario WHERE fecha BETWEEN '$primer_dia' AND '$ultimo_dia' ORDER BY fecha, hora");
$eventos_por_dia = [];
while ($e = $eventos->fetch_assoc()) {
    $dia = intval(date('d', strtotime($e['fecha'])));
    $eventos_por_dia[$dia][] = $e;
}

// Cumpleaños del mes
$cumpleanios = $conn->query("SELECT nombre, apellido, fecha_nacimiento, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE MONTH(fecha_nacimiento) = $mes ORDER BY DAY(fecha_nacimiento)");
$cumples_por_dia = [];
while ($c = $cumpleanios->fetch_assoc()) {
    $dia = intval(date('d', strtotime($c['fecha_nacimiento'])));
    $cumples_por_dia[$dia][] = $c;
}

// Calendario
$total_dias = date('t', strtotime($primer_dia));
$dia_semana_inicio = date('N', strtotime($primer_dia));
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mes_anterior = $mes - 1; $anio_anterior = $anio;
if ($mes_anterior < 1) { $mes_anterior = 12; $anio_anterior--; }
$mes_siguiente = $mes + 1; $anio_siguiente = $anio;
if ($mes_siguiente > 12) { $mes_siguiente = 1; $anio_siguiente++; }

$tipos_evento = ['campamento','concurso','servicio','reunion','especial','otro'];
$iconos_tipo = [
    'campamento' => '🏕️',
    'concurso' => '🏆',
    'servicio' => '🤝',
    'reunion' => '📅',
    'especial' => '🎉',
    'otro' => '📌'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendario · Club Betelgeuse</title>
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

        /* Navbar */
        .navbar-calendario {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-calendario .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-calendario .navbar-brand:hover { color: var(--accent-club) !important; }

        /* Calendario */
        .calendar {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
        }
        .calendar th {
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .calendar td {
            width: 14.28%;
            height: 95px;
            vertical-align: top;
            padding: 6px;
            border: 1px solid #f1f5f9;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 8px;
        }
        .calendar td:hover { background: #f0f4ff; }
        .calendar td.today {
            background: #fffbeb;
            border: 2px solid var(--accent-club);
        }
        .calendar .dia-num {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .calendar .evento {
            font-size: 0.65rem;
            padding: 1px 5px;
            border-radius: 8px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            font-weight: 500;
        }
        .evento-campamento { background: #d4edda; color: #155724; }
        .evento-concurso { background: #fff3cd; color: #856404; }
        .evento-servicio { background: #d1ecf1; color: #0c5460; }
        .evento-reunion { background: #e2d9f3; color: #5a3d7e; }
        .evento-especial { background: #fce4ec; color: #880e4f; }
        .evento-otro { background: #e9ecef; color: #495057; }
        .cumple-badge {
            font-size: 0.6rem;
            background: #fecaca;
            color: #991b1b;
            padding: 1px 5px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Panel lateral */
        .side-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-bottom: 20px;
        }
        .side-card h6 {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-select, .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 0.85rem;
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
        .btn-guardar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 10px;
            transition: var(--transition);
        }
        .btn-guardar:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-mes {
            border-radius: 10px;
            font-weight: 600;
            padding: 6px 12px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        .alert-success {
            border-radius: 14px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            color: #065f46;
            font-weight: 600;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-calendario">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
            <a href="notificaciones_config.php" class="btn btn-sm btn-outline-light rounded-pill">
                <i class="bi bi-envelope me-1"></i> Destinatarios
            </a>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <?php if ($mensaje): ?>
            <div class="alert alert-success d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i> <div><?= $mensaje ?></div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Calendario -->
            <div class="col-lg-8">
                <div class="calendar">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?mes=<?= $mes_anterior ?>&anio=<?= $anio_anterior ?>" class="btn btn-outline-secondary btn-mes">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <h4 class="mb-0 fw-bold" style="color: #0f172a;"><?= $meses[$mes-1] ?> <?= $anio ?></h4>
                        <a href="?mes=<?= $mes_siguiente ?>&anio=<?= $anio_siguiente ?>" class="btn btn-outline-secondary btn-mes">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>

                    <table class="table table-borderless calendar">
                        <thead>
                            <tr>
                                <th>Lun</th><th>Mar</th><th>Mié</th><th>Jue</th><th>Vie</th><th>Sáb</th><th>Dom</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                            <?php
                            // Días vacíos al inicio
                            for ($i = 1; $i < $dia_semana_inicio; $i++) {
                                echo '<td></td>';
                            }
                            
                            // Días del mes
                            for ($dia = 1; $dia <= $total_dias; $dia++) {
                                $hoy = ($dia == date('d') && $mes == date('m') && $anio == date('Y')) ? 'today' : '';
                                echo "<td class='$hoy' onclick=\"mostrarDia($dia)\">";
                                echo "<div class='dia-num'>$dia</div>";
                                
                                // Cumpleaños
                                if (isset($cumples_por_dia[$dia])) {
                                    foreach ($cumples_por_dia[$dia] as $c) {
                                        echo "<span class='cumple-badge' title='🎂 {$c['nombre']} ({$c['edad']} años)'>🎂 {$c['nombre']}</span><br>";
                                    }
                                }
                                
                                // Eventos
                                if (isset($eventos_por_dia[$dia])) {
                                    foreach ($eventos_por_dia[$dia] as $e) {
                                        $tipo_class = 'evento-' . $e['tipo'];
                                        $icono = $iconos_tipo[$e['tipo']];
                                        echo "<span class='evento $tipo_class' title='{$icono} {$e['titulo']}'>$icono {$e['titulo']}</span><br>";
                                    }
                                }
                                
                                echo '</td>';
                                
                                // Nueva fila cada domingo
                                if (($dia + $dia_semana_inicio - 1) % 7 == 0) {
                                    echo '</tr><tr>';
                                }
                            }
                            
                            // Días vacíos al final
                            $dias_restantes = (7 - (($total_dias + $dia_semana_inicio - 1) % 7)) % 7;
                            for ($i = 0; $i < $dias_restantes; $i++) {
                                echo '<td></td>';
                            }
                            ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel lateral -->
            <div class="col-lg-4">
                <!-- Agregar evento -->
                <div class="side-card">
                    <h6><i class="bi bi-plus-circle" style="color: var(--accent-club);"></i> Agregar Evento</h6>
                    <form method="post">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Título *</label>
                            <input type="text" name="titulo" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Fecha *</label>
                                <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Hora</label>
                                <input type="time" name="hora" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Tipo</label>
                            <select name="tipo" class="form-select">
                                <?php foreach ($tipos_evento as $t): ?>
                                    <option value="<?= $t ?>"><?= $iconos_tipo[$t] ?> <?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="guardar" class="btn btn-guardar w-100">
                            <i class="bi bi-check-circle me-1"></i> Guardar
                        </button>
                    </form>
                </div>

                <!-- Eventos del día seleccionado -->
                <div class="side-card" id="eventosDia" style="display:none;">
                    <h6><i class="bi bi-calendar-day"></i> <span id="tituloDia"></span></h6>
                    <div id="contenidoDia"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var eventosPorDia = <?= json_encode($eventos_por_dia) ?>;
        var cumplesPorDia = <?= json_encode($cumples_por_dia) ?>;
        var mes = <?= $mes ?>;
        var anio = <?= $anio ?>;
        var meses = <?= json_encode($meses) ?>;
        var iconosTipo = <?= json_encode($iconos_tipo) ?>;
        
        function mostrarDia(dia) {
            document.getElementById('tituloDia').textContent = dia + ' de ' + meses[mes-1];
            var html = '';
            
            if (cumplesPorDia[dia]) {
                cumplesPorDia[dia].forEach(function(c) {
                    html += '<div class="p-2 border-bottom"><span class="badge bg-danger me-2">🎂</span><strong>' + c.nombre + ' ' + c.apellido + '</strong> cumple ' + (c.edad+1) + ' años</div>';
                });
            }
            
            if (eventosPorDia[dia]) {
                eventosPorDia[dia].forEach(function(e) {
                    html += '<div class="p-2 border-bottom"><span class="badge bg-secondary me-2">' + iconosTipo[e.tipo] + '</span><strong>' + e.titulo + '</strong>';
                    if (e.hora) html += ' <small class="text-muted">(' + e.hora.substring(0,5) + ')</small>';
                    if (e.descripcion) html += '<br><small>' + e.descripcion + '</small>';
                    html += ' <a href="?mes=' + mes + '&anio=' + anio + '&eliminar=' + e.id + '" class="text-danger small" onclick="return confirm(\'¿Eliminar evento?\')"><i class="bi bi-trash"></i></a>';
                    html += '</div>';
                });
            }
            
            if (!html) html = '<p class="text-muted p-3 mb-0">No hay eventos este día.</p>';
            
            document.getElementById('contenidoDia').innerHTML = html;
            document.getElementById('eventosDia').style.display = 'block';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>