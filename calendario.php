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

// Editar evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['evento_id']);
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'] ?? null;
    $tipo = $_POST['tipo'];
    
    $conn->query("UPDATE eventos_calendario SET titulo='$titulo', descripcion='$descripcion', fecha='$fecha', hora=" . ($hora ? "'$hora'" : "NULL") . ", tipo='$tipo' WHERE id=$id");
    $mensaje = "Evento actualizado correctamente.";
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

$eventos = $conn->query("
    SELECT * FROM eventos_calendario 
    WHERE fecha BETWEEN '$primer_dia' AND '$ultimo_dia'
    ORDER BY fecha, hora
");

// Organizar eventos por día
$eventos_por_dia = [];
while ($e = $eventos->fetch_assoc()) {
    $dia = intval(date('d', strtotime($e['fecha'])));
    $eventos_por_dia[$dia][] = $e;
}

// Cumpleaños del mes
$cumpleanios = $conn->query("
    SELECT nombre, apellido, fecha_nacimiento,
           TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad
    FROM usuarios
    WHERE MONTH(fecha_nacimiento) = $mes
    ORDER BY DAY(fecha_nacimiento)
");

$cumples_por_dia = [];
while ($c = $cumpleanios->fetch_assoc()) {
    $dia = intval(date('d', strtotime($c['fecha_nacimiento'])));
    $cumples_por_dia[$dia][] = $c;
}

// Calendario
$total_dias = date('t', strtotime($primer_dia));
$dia_semana_inicio = date('N', strtotime($primer_dia));

$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mes_anterior = $mes - 1;
$anio_anterior = $anio;
if ($mes_anterior < 1) { $mes_anterior = 12; $anio_anterior--; }
$mes_siguiente = $mes + 1;
$anio_siguiente = $anio;
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
    <title>Calendario - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .calendar { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .calendar th { text-align: center; padding: 12px; background: #0d1b3e; color: white; }
        .calendar td { 
            width: 14.28%; height: 100px; vertical-align: top; padding: 8px;
            border: 1px solid #eee; cursor: pointer; transition: 0.2s;
        }
        .calendar td:hover { background: #f0f4ff; }
        .calendar td.today { background: #fff3cd; }
        .calendar td.other-month { background: #f8f9fa; color: #ccc; }
        .calendar .dia-num { font-weight: 700; font-size: 1.1rem; margin-bottom: 3px; }
        .calendar .evento { 
            font-size: 0.7rem; padding: 2px 5px; border-radius: 4px; 
            margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            cursor: pointer;
        }
        .evento-campamento { background: #d4edda; color: #155724; }
        .evento-concurso { background: #fff3cd; color: #856404; }
        .evento-servicio { background: #d1ecf1; color: #0c5460; }
        .evento-reunion { background: #e2d9f3; color: #5a3d7e; }
        .evento-especial { background: #fce4ec; color: #880e4f; }
        .evento-otro { background: #e9ecef; color: #495057; }
        .cumple-badge { 
            font-size: 0.65rem; background: #ff6b6b; color: white; 
            padding: 1px 5px; border-radius: 10px; 
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver</a>
            <span class="navbar-text text-white">
                <a href="notificaciones_config.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-envelope"></i> Destinatarios
                </a>
            </span>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Calendario -->
            <div class="col-lg-8">
                <div class="calendar">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?mes=<?= $mes_anterior ?>&anio=<?= $anio_anterior ?>" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <h4 class="mb-0 fw-bold"><?= $meses[$mes-1] ?> <?= $anio ?></h4>
                        <a href="?mes=<?= $mes_siguiente ?>&anio=<?= $anio_siguiente ?>" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>

                    <table class="table table-bordered calendar">
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
                                echo '<td class="other-month"></td>';
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
                                echo '<td class="other-month"></td>';
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
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar Evento</h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-2">
                                <label class="form-label small">Título *</label>
                                <input type="text" name="titulo" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Descripción</label>
                                <textarea name="descripcion" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Fecha *</label>
                                    <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Hora</label>
                                    <input type="time" name="hora" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <?php foreach ($tipos_evento as $t): ?>
                                        <option value="<?= $t ?>"><?= $iconos_tipo[$t] ?> <?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="guardar" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-check-circle"></i> Guardar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Eventos del día seleccionado -->
                <div class="card" id="eventosDia" style="display:none;">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-calendar-day"></i> <span id="tituloDia"></span></h6>
                    </div>
                    <div class="card-body p-0" id="contenidoDia">
                    </div>
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
            var fecha = anio + '-' + String(mes).padStart(2,'0') + '-' + String(dia).padStart(2,'0');
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