<?php
require_once 'includes/config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) exit;

$stmt = $conn->prepare("
    SELECT u.*, m.id AS miembro_id, m.tipo, m.rango, m.activo, m.clase_actual_id,
           un.nombre AS unidad,
           cr_actual.nombre AS clase_actual
    FROM usuarios u
    LEFT JOIN miembros m ON u.id = m.usuario_id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN clases_regulares cr_actual ON m.clase_actual_id = cr_actual.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$miembro = $stmt->get_result()->fetch_assoc();
if (!$miembro) exit;

$mid = $miembro['miembro_id'];

$logros_clases = $conn->query("SELECT cr.nombre AS clase, lcr.anio FROM logros_clase_regular lcr JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id WHERE lcr.miembro_id = $mid ORDER BY lcr.anio DESC");
$logros_especialidades = $conn->query("SELECT e.nombre_especialidad, le.anio, cat.nombre_categoria FROM logros_especialidad le JOIN especialidades e ON le.especialidad_id = e.id JOIN categorias_especialidades cat ON e.categoria_id = cat.id WHERE le.miembro_id = $mid ORDER BY le.anio DESC");

$maestrias_completadas = [];
$maestrias = $conn->query("SELECT * FROM maestrias ORDER BY id");
while ($maestria = $maestrias->fetch_assoc()) {
    $requisitos = $conn->query("SELECT mr.* FROM maestria_requisitos mr WHERE mr.maestria_id = {$maestria['id']}");
    if ($requisitos->num_rows === 0) continue;
    $cumplido = true;
    while ($req = $requisitos->fetch_assoc()) {
        $necesarias = intval($req['cantidad_minima']);
        if (!empty($req['categoria_id']) && empty($req['especialidad_id'])) {
            $cat_id = intval($req['categoria_id']);
            $count = $conn->query("SELECT COUNT(*) as total FROM logros_especialidad le JOIN especialidades e ON le.especialidad_id = e.id WHERE le.miembro_id = $mid AND e.categoria_id = $cat_id")->fetch_assoc()['total'];
            if ($count < $necesarias) $cumplido = false;
        } elseif (!empty($req['especialidad_id'])) {
            $esp_id = intval($req['especialidad_id']);
            $tiene = $conn->query("SELECT COUNT(*) as total FROM logros_especialidad WHERE miembro_id = $mid AND especialidad_id = $esp_id")->fetch_assoc()['total'];
            if ($tiene < $necesarias) $cumplido = false;
        }
    }
    if ($cumplido) $maestrias_completadas[] = $maestria['nombre_maestria'];
}

$clase_lidera = null;
if ($miembro['tipo'] === 'lider') {
    $clase_lidera = $conn->query("SELECT cr.nombre FROM lider_clase lc JOIN clases_regulares cr ON lc.clase_regular_id = cr.id WHERE lc.miembro_id = $mid ORDER BY lc.fecha_inicio DESC LIMIT 1")->fetch_assoc();
}
?>

<!-- Estilos para el contenido del modal -->
<style>
    .ficha-avatar {
        width: 100px;
        height: 100px;
        border-radius: 20px;
        object-fit: cover;
        border: 3px solid var(--accent-club);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .ficha-avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 20px;
        background: linear-gradient(135deg, #1e40af, #1e3a8a);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 2rem;
        margin: 0 auto;
    }
    .ficha-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .ficha-section h5 {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .badge-maestria {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-block;
        margin-bottom: 4px;
    }
</style>

<!-- Contenido del modal -->
<div class="text-center mb-4">
    <?php if ($miembro['foto']): ?>
        <img src="<?= $miembro['foto'] ?>" alt="Foto" class="ficha-avatar">
    <?php else: ?>
        <div class="ficha-avatar-placeholder">
            <?= strtoupper(substr($miembro['nombre'], 0, 1)) ?>
        </div>
    <?php endif; ?>
    <h4 class="mt-3 fw-bold"><?= htmlspecialchars($miembro['nombre'] . ' ' . $miembro['apellido']) ?></h4>
    <?php if ($miembro['clase_actual']): ?>
        <div class="mt-1"><?= badge_clase($miembro['clase_actual']) ?></div>
    <?php endif; ?>
</div>

<!-- Datos personales -->
<div class="ficha-section">
    <h5><i class="bi bi-person-lines-fill"></i> Datos Personales</h5>
    <div class="row">
        <div class="col-md-6">
            <p class="mb-2"><strong>DNI:</strong> <?= $miembro['dni'] ?></p>
            <p class="mb-2"><strong>Edad:</strong> <?= (new DateTime())->diff(new DateTime($miembro['fecha_nacimiento']))->y ?> años</p>
            <p class="mb-2"><strong>Fecha Nacimiento:</strong> <?= date('d/m/Y', strtotime($miembro['fecha_nacimiento'])) ?></p>
            <p class="mb-2"><strong>Celular:</strong> <?= $miembro['celular'] ?></p>
            <p class="mb-2"><strong>Celular Emergencia:</strong> <?= $miembro['celular_emergencia'] ?></p>
        </div>
        <div class="col-md-6">
            <p class="mb-2"><strong>Tipo de Sangre:</strong> <?= $miembro['tipo_sangre'] ?? '-' ?></p>
            <p class="mb-2"><strong>Alergias:</strong> <?= $miembro['alergias'] ?: 'Ninguna' ?></p>
            <p class="mb-2"><strong>Bautizado:</strong> <?= $miembro['bautizado'] ? 'Sí' : 'No' ?></p>
            <p class="mb-2"><strong>Unidad:</strong> <?= $miembro['unidad'] ?? 'No asignada' ?></p>
            <p class="mb-2"><strong>Rol:</strong> <?= $miembro['rol'] ?> · <strong>Estado:</strong> <?= $miembro['activo'] ? 'Activo' : 'Inactivo' ?></p>
            <?php if ($miembro['tipo'] === 'lider'): ?>
                <p class="mb-2"><strong>Clase que lidera:</strong> <?= $clase_lidera['nombre'] ?? 'No asignada' ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Clases completadas -->
<div class="ficha-section">
    <h5><i class="bi bi-book"></i> Clases Completadas</h5>
    <?php if ($logros_clases->num_rows > 0): ?>
        <div class="d-flex flex-wrap gap-1">
            <?php while ($c = $logros_clases->fetch_assoc()): ?>
                <?= badge_clase($c['clase']) ?>
                <small class="text-muted ms-1">(<?= $c['anio'] ?>)</small>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">Sin clases completadas.</p>
    <?php endif; ?>
</div>

<!-- Especialidades -->
<div class="ficha-section">
    <h5><i class="bi bi-star"></i> Especialidades</h5>
    <?php if ($logros_especialidades->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm table-borderless mb-0">
                <thead><tr class="text-secondary"><th>Especialidad</th><th>Categoría</th><th>Año</th></tr></thead>
                <tbody>
                    <?php while ($e = $logros_especialidades->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-semibold"><?= $e['nombre_especialidad'] ?></td>
                            <td><?= $e['nombre_categoria'] ?></td>
                            <td><?= $e['anio'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">Sin especialidades registradas.</p>
    <?php endif; ?>
</div>

<!-- Maestrías -->
<div class="ficha-section">
    <h5><i class="bi bi-award-fill text-warning"></i> Maestrías</h5>
    <?php if (!empty($maestrias_completadas)): ?>
        <div class="d-flex flex-wrap gap-1">
            <?php foreach ($maestrias_completadas as $nombre): ?>
                <span class="badge-maestria"><i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($nombre) ?></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted mb-0">No tiene maestrías completadas.</p>
    <?php endif; ?>
</div>

<!-- Subir foto -->
<div class="text-center mt-3">
    <form action="subir_foto.php" method="post" enctype="multipart/form-data" class="row g-2 justify-content-center">
        <input type="hidden" name="miembro_id" value="<?= $miembro['miembro_id'] ?>">
        <div class="col-auto">
            <input type="file" name="foto" accept="image/*" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-upload"></i> Subir foto</button>
        </div>
    </form>
</div>