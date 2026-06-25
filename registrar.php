<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $dni = trim($_POST['dni']);
    $celular = trim($_POST['celular']);
    $celular_emergencia = trim($_POST['celular_emergencia']);
    $bautizado = isset($_POST['bautizado']) ? 1 : 0;
    $tipo_sangre = $_POST['tipo_sangre'] ?? '';
    $alergias = trim($_POST['alergias'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $tipo_miembro = $_POST['tipo_miembro'];
    $genero = $_POST['genero'] ?? null;
    $unidad_id = $_POST['unidad_id'] ?? null;
    $clase_lider_id = $_POST['clase_lider_id'] ?? null;

    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($dni) || empty($celular) || empty($fecha_nacimiento)) {
        $errores[] = "Todos los campos obligatorios deben completarse.";
    }

    // Calcular edad real hoy
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad_real = $hoy->diff($fecha_nac)->y;

    // Calcular edad oficial al 30 de junio del año actual
    $anio_actual = date('Y');
    $fecha_corte = new DateTime("$anio_actual-06-30");
    $edad_oficial = $fecha_corte->diff($fecha_nac)->y;

    if ($tipo_miembro === 'conquistador') {
        if ($edad_oficial < 10 || $edad_oficial > 15) {
            $errores[] = "La edad oficial del conquistador (al 30 de junio) debe estar entre 10 y 15 años. Edad calculada: $edad_oficial años.";
        }
        if (!$genero) {
            $errores[] = "Debe seleccionar el género.";
        }
    } elseif ($tipo_miembro === 'lider') {
        if ($edad_real < 16) {
            $errores[] = "El líder debe tener al menos 16 años.";
        }
        if (empty($unidad_id)) {
            $errores[] = "Debe seleccionar una unidad para el líder.";
        }
        if (empty($clase_lider_id)) {
            $errores[] = "Debe seleccionar la clase que lidera.";
        }
    } else {
        $errores[] = "Tipo de miembro inválido.";
    }

    // Verificar si el DNI ya existe
    $check_dni = $conn->query("SELECT id FROM usuarios WHERE dni = '$dni'");
    if ($check_dni->num_rows > 0) {
        $errores[] = "Ya existe un miembro con ese DNI.";
    }

    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            // 1. Insertar en usuarios
            $rol = ($tipo_miembro === 'conquistador') ? 'conquistador' : 'lider';
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, dni, celular, celular_emergencia, bautizado, tipo_sangre, alergias, fecha_nacimiento, rol) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssissss", $nombre, $apellido, $dni, $celular, $celular_emergencia, $bautizado, $tipo_sangre, $alergias, $fecha_nacimiento, $rol);
            $stmt->execute();
            $usuario_id = $conn->insert_id;

            // 2. Insertar en miembros
            $rango = ($tipo_miembro === 'lider') ? 'guia' : null;
            $clase_actual_id = null;

            if ($tipo_miembro === 'conquistador') {
                // Calcular clase según edad oficial al 30 de junio
                $clase_result = $conn->query("SELECT id FROM clases_regulares WHERE edad_requerida = $edad_oficial")->fetch_assoc();
                if ($clase_result) {
                    $clase_actual_id = $clase_result['id'];
                }
            }

            $stmt = $conn->prepare("INSERT INTO miembros (usuario_id, tipo, rango, clase_actual_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $usuario_id, $tipo_miembro, $rango, $clase_actual_id);
            $stmt->execute();
            $miembro_id = $conn->insert_id;

            // 3. Subir foto si se envió
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $archivo = $_FILES['foto'];
                $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($extension, $permitidas) && $archivo['size'] < 2097152) {
                    $nombre_foto = 'miembro_' . $miembro_id . '_' . time() . '.' . $extension;
                    $destino = 'assets/fotos/' . $nombre_foto;
                    move_uploaded_file($archivo['tmp_name'], $destino);
                    $conn->query("UPDATE usuarios SET foto = '$destino' WHERE id = $usuario_id");
                }
            }

            // 4. Asignar unidad
            if ($tipo_miembro === 'conquistador') {
                // Buscar unidad según género y edad oficial
                $stmt_unidad = $conn->prepare("SELECT id FROM unidades WHERE genero = ? AND edad_min <= ? AND edad_max >= ? LIMIT 1");
                $stmt_unidad->bind_param("sii", $genero, $edad_oficial, $edad_oficial);
                $stmt_unidad->execute();
                $unidad = $stmt_unidad->get_result()->fetch_assoc();
                if ($unidad) {
                    $unidad_id = $unidad['id'];
                } else {
                    throw new Exception("No se encontró una unidad para género $genero y edad $edad_oficial.");
                }
            }

            // Insertar o actualizar miembro_unidad
            $check_unidad = $conn->query("SELECT id FROM miembro_unidad WHERE miembro_id = $miembro_id");
            if ($check_unidad->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE miembro_unidad SET unidad_id = ?, fecha_asignacion = CURDATE() WHERE miembro_id = ?");
                $stmt->bind_param("ii", $unidad_id, $miembro_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO miembro_unidad (miembro_id, unidad_id, fecha_asignacion) VALUES (?, ?, CURDATE())");
                $stmt->bind_param("ii", $miembro_id, $unidad_id);
            }
            $stmt->execute();

            // 5. Si es conquistador, registrar la clase regular automáticamente
            if ($tipo_miembro === 'conquistador' && $clase_actual_id) {
                $stmt_logro = $conn->prepare("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES (?, ?, YEAR(CURDATE()))");
                $stmt_logro->bind_param("ii", $miembro_id, $clase_actual_id);
                $stmt_logro->execute();
            }

            // 6. Si es líder, guardar la clase que lidera
            if ($tipo_miembro === 'lider' && !empty($clase_lider_id)) {
                $clase_lider_id = intval($clase_lider_id);
                $conn->query("INSERT INTO lider_clase (miembro_id, unidad_id, clase_regular_id, fecha_inicio) VALUES ($miembro_id, $unidad_id, $clase_lider_id, CURDATE())");
            }

            $conn->commit();
            $exito = true;
            $_POST = [];
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar Integrante - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver al Panel</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-person-plus-fill"></i> Registrar Nuevo Integrante</h2>

        <?php if ($exito): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> ¡Integrante registrado exitosamente!</div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $e) echo "<li>$e</li>"; ?></ul></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required value="<?= $_POST['nombre'] ?? '' ?>"></div>
                <div class="col-md-6"><label class="form-label">Apellido *</label><input type="text" name="apellido" class="form-control" required value="<?= $_POST['apellido'] ?? '' ?>"></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">DNI *</label><input type="text" name="dni" class="form-control" required value="<?= $_POST['dni'] ?? '' ?>"></div>
                <div class="col-md-4"><label class="form-label">Celular *</label><input type="text" name="celular" class="form-control" required value="<?= $_POST['celular'] ?? '' ?>"></div>
                <div class="col-md-4"><label class="form-label">Celular Emergencia *</label><input type="text" name="celular_emergencia" class="form-control" required value="<?= $_POST['celular_emergencia'] ?? '' ?>"></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4"><label class="form-label">Fecha de Nacimiento *</label><input type="date" name="fecha_nacimiento" class="form-control" required value="<?= $_POST['fecha_nacimiento'] ?? '' ?>"></div>
                <div class="col-md-3"><label class="form-label">Tipo de Sangre</label><select name="tipo_sangre" class="form-select"><option value="">Seleccionar</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?><option value="<?= $ts ?>" <?= ($_POST['tipo_sangre'] ?? '') === $ts ? 'selected' : '' ?>><?= $ts ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Alergias</label><input type="text" name="alergias" class="form-control" value="<?= $_POST['alergias'] ?? '' ?>"></div>
                <div class="col-md-2"><label class="form-label">Bautizado</label><div class="form-check mt-2"><input type="checkbox" name="bautizado" class="form-check-input" value="1" <?= isset($_POST['bautizado']) ? 'checked' : '' ?>><label class="form-check-label">Sí</label></div></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3"><label class="form-label">Foto de perfil</label><input type="file" name="foto" class="form-control" accept="image/*"></div>
                <div class="col-md-3"><label class="form-label">Tipo de Miembro *</label><select name="tipo_miembro" id="tipo_miembro" class="form-select" required><option value="">Seleccionar</option><option value="conquistador" <?= ($_POST['tipo_miembro'] ?? '') === 'conquistador' ? 'selected' : '' ?>>Conquistador (10-15 años)</option><option value="lider" <?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'selected' : '' ?>>Líder (16+ años)</option></select></div>
                <div class="col-md-3" id="div_genero" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'conquistador' ? 'block' : 'none' ?>;"><label class="form-label">Género *</label><select name="genero" class="form-select"><option value="">Seleccionar</option><option value="varon" <?= ($_POST['genero'] ?? '') === 'varon' ? 'selected' : '' ?>>Varón</option><option value="mujer" <?= ($_POST['genero'] ?? '') === 'mujer' ? 'selected' : '' ?>>Mujer</option></select></div>
                <div class="col-md-3" id="div_unidad_lider" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'block' : 'none' ?>;"><label class="form-label">Unidad *</label><select name="unidad_id" id="unidad_lider" class="form-select" onchange="cargarClasesLider()"><option value="">Seleccionar</option><?php $unidades = $conn->query("SELECT id, nombre, genero, edad_min, edad_max FROM unidades ORDER BY genero, edad_min"); while ($u = $unidades->fetch_assoc()): $info = $u['nombre'] . ' (' . ucfirst($u['genero']) . ' ' . $u['edad_min'] . '-' . $u['edad_max'] . ')'; ?><option value="<?= $u['id'] ?>" data-edad-min="<?= $u['edad_min'] ?>" data-edad-max="<?= $u['edad_max'] ?>" <?= ($_POST['unidad_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= $info ?></option><?php endwhile; ?></select></div>
            </div>
            <div class="row mb-3" id="div_clase_lider" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'block' : 'none' ?>;"><div class="col-md-3"><label class="form-label">Clase que lidera *</label><select name="clase_lider_id" id="clase_lider" class="form-select"><option value="">Seleccionar clase</option><?php $clases = $conn->query("SELECT id, nombre, edad_requerida FROM clases_regulares ORDER BY edad_requerida"); while ($c = $clases->fetch_assoc()): ?><option value="<?= $c['id'] ?>" data-edad="<?= $c['edad_requerida'] ?>" <?= ($_POST['clase_lider_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= $c['nombre'] ?> (<?= $c['edad_requerida'] ?> años)</option><?php endwhile; ?></select></div></div>
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> Registrar</button>
        </form>
    </div>

    <script>
        document.getElementById('tipo_miembro').addEventListener('change', function() {
            var tipo = this.value;
            document.getElementById('div_genero').style.display = (tipo === 'conquistador') ? 'block' : 'none';
            document.getElementById('div_unidad_lider').style.display = (tipo === 'lider') ? 'block' : 'none';
            document.getElementById('div_clase_lider').style.display = (tipo === 'lider') ? 'block' : 'none';
        });
        function cargarClasesLider() {
            var unidadSelect = document.getElementById('unidad_lider');
            var option = unidadSelect.options[unidadSelect.selectedIndex];
            var edadMin = parseInt(option.getAttribute('data-edad-min')) || 0;
            var edadMax = parseInt(option.getAttribute('data-edad-max')) || 99;
            var claseSelect = document.getElementById('clase_lider');
            var opciones = claseSelect.options;
            for (var i = 0; i < opciones.length; i++) {
                var edadClase = parseInt(opciones[i].getAttribute('data-edad'));
                if (!edadClase) continue;
                opciones[i].style.display = (edadClase >= edadMin && edadClase <= edadMax) ? 'block' : 'none';
            }
            claseSelect.value = '';
        }
        cargarClasesLider();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>