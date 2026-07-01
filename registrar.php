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

    if (empty($nombre) || empty($apellido) || empty($dni) || empty($celular) || empty($fecha_nacimiento)) {
        $errores[] = "Todos los campos obligatorios deben completarse.";
    }

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
        if (!$genero) $errores[] = "Debe seleccionar el género.";
    } elseif ($tipo_miembro === 'lider') {
        if ($edad_real < 16) $errores[] = "El líder debe tener al menos 16 años.";
        if (empty($unidad_id)) $errores[] = "Debe seleccionar una unidad para el líder.";
        if (empty($clase_lider_id)) $errores[] = "Debe seleccionar la clase que lidera.";
    } else {
        $errores[] = "Tipo de miembro inválido.";
    }

    $check_dni = $conn->query("SELECT id FROM usuarios WHERE dni = '$dni'");
    if ($check_dni->num_rows > 0) $errores[] = "Ya existe un miembro con ese DNI.";

    if (empty($errores)) {
        $conn->begin_transaction();
        try {
            $rol = ($tipo_miembro === 'conquistador') ? 'conquistador' : 'lider';
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, dni, celular, celular_emergencia, bautizado, tipo_sangre, alergias, fecha_nacimiento, rol) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssissss", $nombre, $apellido, $dni, $celular, $celular_emergencia, $bautizado, $tipo_sangre, $alergias, $fecha_nacimiento, $rol);
            $stmt->execute();
            $usuario_id = $conn->insert_id;

            // Determinar clase_actual_id para conquistadores
            $clase_actual_id = null;
            if ($tipo_miembro === 'conquistador') {
                $clase_result = $conn->query("SELECT id FROM clases_regulares WHERE edad_requerida = $edad_oficial")->fetch_assoc();
                $clase_actual_id = $clase_result['id'] ?? null;
            }

            $rango = ($tipo_miembro === 'lider') ? 'guia' : null;
            $stmt = $conn->prepare("INSERT INTO miembros (usuario_id, tipo, rango, clase_actual_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $usuario_id, $tipo_miembro, $rango, $clase_actual_id);
            $stmt->execute();
            $miembro_id = $conn->insert_id;

            // Subir foto
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

            // Asignar unidad para conquistadores según edad oficial y género
            if ($tipo_miembro === 'conquistador') {
                $stmt_unidad = $conn->prepare("SELECT id FROM unidades WHERE genero = ? AND edad_min <= ? AND edad_max >= ? LIMIT 1");
                $stmt_unidad->bind_param("sii", $genero, $edad_oficial, $edad_oficial);
                $stmt_unidad->execute();
                $unidad = $stmt_unidad->get_result()->fetch_assoc();
                if ($unidad) $unidad_id = $unidad['id'];
                else throw new Exception("No se encontró una unidad para edad $edad_oficial y género $genero.");
            }

            $check_unidad = $conn->query("SELECT id FROM miembro_unidad WHERE miembro_id = $miembro_id");
            if ($check_unidad->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE miembro_unidad SET unidad_id = ?, fecha_asignacion = CURDATE() WHERE miembro_id = ?");
                $stmt->bind_param("ii", $unidad_id, $miembro_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO miembro_unidad (miembro_id, unidad_id, fecha_asignacion) VALUES (?, ?, CURDATE())");
                $stmt->bind_param("ii", $miembro_id, $unidad_id);
            }
            $stmt->execute();

            // Registrar logro de clase regular automáticamente
            if ($tipo_miembro === 'conquistador' && $clase_actual_id) {
                $stmt_logro = $conn->prepare("INSERT IGNORE INTO logros_clase_regular (miembro_id, clase_regular_id, anio) VALUES (?, ?, YEAR(CURDATE()))");
                $stmt_logro->bind_param("ii", $miembro_id, $clase_actual_id);
                $stmt_logro->execute();
            }

            // Líder: guardar clase que lidera
            if ($tipo_miembro === 'lider' && !empty($clase_lider_id)) {
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
    <title>Registrar Integrante · Club Betelgeuse</title>
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

        .navbar-register {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-register .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-register .navbar-brand:hover { color: var(--accent-club) !important; }

        .register-card {
            background: white;
            border-radius: 28px;
            padding: 40px 36px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .register-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .register-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .section-title {
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            background: #fafbfc;
            color: #0f172a;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-club);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            background: white;
            outline: none;
        }

        .photo-upload-area {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .photo-preview {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #94a3b8;
            border: 2px dashed #cbd5e1;
            transition: var(--transition);
            overflow: hidden;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }
        .photo-preview.has-photo {
            border-style: solid;
            border-color: var(--accent-club);
        }

        .btn-register {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 1rem;
            padding: 12px 32px;
            border-radius: 14px;
            transition: var(--transition);
            box-shadow: 0 8px 20px -8px rgba(30, 64, 175, 0.3);
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -6px rgba(30, 64, 175, 0.4);
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }

        .alert-success {
            border-radius: 16px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            color: #065f46;
            font-weight: 600;
        }
        .alert-danger {
            border-radius: 16px;
            border-left: 4px solid #ef4444;
            background: #fef2f2;
            color: #991b1b;
            font-weight: 500;
        }

        .form-check-input:checked {
            background-color: var(--accent-club);
            border-color: var(--accent-club);
        }
        .form-check-label {
            font-weight: 600;
            color: #475569;
        }

        @media (max-width: 768px) {
            .register-card { padding: 28px 20px; border-radius: 22px; }
            .register-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-register">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="register-card">

                    <div class="register-header">
                        <h2><i class="bi bi-person-plus-fill me-2" style="color: var(--accent-club);"></i>Registrar Nuevo Integrante</h2>
                        <p>Completa los datos para añadir un conquistador o líder al club.</p>
                    </div>

                    <?php if ($exito): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div>¡Integrante registrado exitosamente!</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>Corrige los siguientes errores:</strong>
                            </div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errores as $e) echo "<li>$e</li>"; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">

                        <!-- DATOS PERSONALES -->
                        <div class="section-title"><i class="bi bi-person-lines-fill me-2"></i>Datos Personales</div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" required value="<?= $_POST['nombre'] ?? '' ?>" placeholder="Ej: Mateo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Apellido *</label>
                                <input type="text" name="apellido" class="form-control" required value="<?= $_POST['apellido'] ?? '' ?>" placeholder="Ej: García">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">DNI *</label>
                                <input type="text" name="dni" class="form-control" required value="<?= $_POST['dni'] ?? '' ?>" placeholder="12345678">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Celular *</label>
                                <input type="text" name="celular" class="form-control" required value="<?= $_POST['celular'] ?? '' ?>" placeholder="600101010">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Celular Emergencia *</label>
                                <input type="text" name="celular_emergencia" class="form-control" required value="<?= $_POST['celular_emergencia'] ?? '' ?>" placeholder="600101011">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Fecha de Nacimiento *</label>
                                <input type="date" name="fecha_nacimiento" class="form-control" required value="<?= $_POST['fecha_nacimiento'] ?? '' ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo de Sangre</label>
                                <select name="tipo_sangre" class="form-select">
                                    <option value="">Seleccionar</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                                        <option value="<?= $ts ?>" <?= ($_POST['tipo_sangre'] ?? '') === $ts ? 'selected' : '' ?>><?= $ts ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Alergias</label>
                                <input type="text" name="alergias" class="form-control" value="<?= $_POST['alergias'] ?? '' ?>" placeholder="Ninguna">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Bautizado</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="bautizado" class="form-check-input" value="1" <?= isset($_POST['bautizado']) ? 'checked' : '' ?> id="chkBautizado">
                                    <label class="form-check-label" for="chkBautizado">Sí</label>
                                </div>
                            </div>
                        </div>

                        <!-- FOTO DE PERFIL -->
                        <div class="section-title mt-3"><i class="bi bi-camera-fill me-2"></i>Foto de Perfil</div>
                        <div class="photo-upload-area mb-3">
                            <div class="photo-preview" id="photoPreview">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div>
                                <label class="form-label mb-1">Seleccionar imagen</label>
                                <input type="file" name="foto" class="form-control" accept="image/*" id="inputFoto" onchange="previewPhoto()" style="max-width: 260px;">
                                <small class="text-muted d-block mt-1">JPG, PNG o WebP. Máx 2 MB.</small>
                            </div>
                        </div>

                        <!-- TIPO DE MIEMBRO -->
                        <div class="section-title mt-4"><i class="bi bi-person-badge-fill me-2"></i>Tipo de Miembro</div>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de Miembro *</label>
                                <select name="tipo_miembro" id="tipo_miembro" class="form-select" required>
                                    <option value="">Seleccionar</option>
                                    <option value="conquistador" <?= ($_POST['tipo_miembro'] ?? '') === 'conquistador' ? 'selected' : '' ?>>🧒 Conquistador (10-15 años)</option>
                                    <option value="lider" <?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'selected' : '' ?>>👤 Líder (16+ años)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="div_genero" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'conquistador' ? 'block' : 'none' ?>;">
                                <label class="form-label">Género *</label>
                                <select name="genero" class="form-select">
                                    <option value="">Seleccionar</option>
                                    <option value="varon" <?= ($_POST['genero'] ?? '') === 'varon' ? 'selected' : '' ?>>Varón</option>
                                    <option value="mujer" <?= ($_POST['genero'] ?? '') === 'mujer' ? 'selected' : '' ?>>Mujer</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="div_unidad_lider" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'block' : 'none' ?>;">
                                <label class="form-label">Unidad *</label>
                                <select name="unidad_id" id="unidad_lider" class="form-select" onchange="cargarClasesLider()">
                                    <option value="">Seleccionar</option>
                                    <?php $unidades = $conn->query("SELECT id, nombre, genero, edad_min, edad_max FROM unidades ORDER BY genero, edad_min"); while ($u = $unidades->fetch_assoc()): $info = $u['nombre'] . ' (' . ucfirst($u['genero']) . ' ' . $u['edad_min'] . '-' . $u['edad_max'] . ')'; ?>
                                        <option value="<?= $u['id'] ?>" data-edad-min="<?= $u['edad_min'] ?>" data-edad-max="<?= $u['edad_max'] ?>" <?= ($_POST['unidad_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= $info ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3" id="div_clase_lider" style="display:<?= ($_POST['tipo_miembro'] ?? '') === 'lider' ? 'block' : 'none' ?>;">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Clase que lidera *</label>
                                <select name="clase_lider_id" id="clase_lider" class="form-select">
                                    <option value="">Seleccionar clase</option>
                                    <?php $clases = $conn->query("SELECT id, nombre, edad_requerida FROM clases_regulares ORDER BY edad_requerida"); while ($c = $clases->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" data-edad="<?= $c['edad_requerida'] ?>" <?= ($_POST['clase_lider_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= $c['nombre'] ?> (<?= $c['edad_requerida'] ?> años)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2 rounded-3 px-4 fw-semibold">Cancelar</a>
                            <button type="submit" class="btn btn-register">
                                <i class="bi bi-check-circle me-2"></i> Registrar Integrante
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
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

        function previewPhoto() {
            var file = document.getElementById('inputFoto').files[0];
            var preview = document.getElementById('photoPreview');
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = 'url(' + e.target.result + ')';
                    preview.classList.add('has-photo');
                    preview.innerHTML = '';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.backgroundImage = '';
                preview.classList.remove('has-photo');
                preview.innerHTML = '<i class="bi bi-person-badge"></i>';
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>