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

$logros_clases = $conn->query("
    SELECT cr.nombre AS clase, lcr.anio
    FROM logros_clase_regular lcr
    JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id
    WHERE lcr.miembro_id = $mid
    ORDER BY lcr.anio DESC
");

$logros_especialidades = $conn->query("
    SELECT e.nombre_especialidad, le.anio, cat.nombre_categoria
    FROM logros_especialidad le
    JOIN especialidades e ON le.especialidad_id = e.id
    JOIN categorias_especialidades cat ON e.categoria_id = cat.id
    WHERE le.miembro_id = $mid
    ORDER BY le.anio DESC
");

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
    $clase_lidera = $conn->query("
        SELECT cr.nombre 
        FROM lider_clase lc
        JOIN clases_regulares cr ON lc.clase_regular_id = cr.id
        WHERE lc.miembro_id = $mid
        ORDER BY lc.fecha_inicio DESC LIMIT 1
    ")->fetch_assoc();
}
?>

<?php if ($miembro['foto']): ?>
<div class="text-center mb-3">
    <img src="<?= $miembro['foto'] ?>" alt="Foto" style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #FFD300;">
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <p><strong>DNI:</strong> <?= $miembro['dni'] ?></p>
        <p><strong>Edad:</strong> <?= (new DateTime())->diff(new DateTime($miembro['fecha_nacimiento']))->y ?> años</p>
        <p><strong>Fecha Nacimiento:</strong> <?= date('d/m/Y', strtotime($miembro['fecha_nacimiento'])) ?></p>
        <p><strong>Celular:</strong> <?= $miembro['celular'] ?></p>
        <p><strong>Celular Emergencia:</strong> <?= $miembro['celular_emergencia'] ?></p>
    </div>
    <div class="col-md-6">
        <p><strong>Tipo de Sangre:</strong> <?= $miembro['tipo_sangre'] ?? '-' ?></p>
        <p><strong>Alergias:</strong> <?= $miembro['alergias'] ?: 'Ninguna' ?></p>
        <p><strong>Bautizado:</strong> <?= $miembro['bautizado'] ? 'Sí' : 'No' ?></p>
        <p><strong>Unidad:</strong> <?= $miembro['unidad'] ?? 'No asignada' ?></p>
        <p><strong>Clase actual:</strong> <span class="badge bg-primary"><?= $miembro['clase_actual'] ?? 'No asignada' ?></span></p>
        <p><strong>Rol:</strong> <?= $miembro['rol'] ?> | <strong>Estado:</strong> <?= $miembro['activo'] ? 'Activo' : 'Inactivo' ?></p>
        <?php if ($miembro['tipo'] === 'lider'): ?>
            <p><strong>Clase que lidera:</strong> <?= $clase_lidera['nombre'] ?? 'No asignada' ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="text-center mt-3">
    <form action="subir_foto.php" method="post" enctype="multipart/form-data" class="row g-2 justify-content-center">
        <input type="hidden" name="miembro_id" value="<?= $miembro['miembro_id'] ?>">
        <div class="col-auto">
            <input type="file" name="foto" accept="image/*" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Subir foto</button>
        </div>
    </form>
</div>

<h4 class="mt-3"><i class="bi bi-book"></i> Clases Completadas</h4>
<?php if ($logros_clases->num_rows > 0): ?>
    <ul class="list-group mb-3">
        <?php while ($c = $logros_clases->fetch_assoc()): ?>
            <li class="list-group-item"><?= $c['clase'] ?> (<?= $c['anio'] ?>)</li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p class="text-muted">Sin clases completadas.</p>
<?php endif; ?>

<h4><i class="bi bi-star"></i> Especialidades</h4>
<?php if ($logros_especialidades->num_rows > 0): ?>
    <div class="table-responsive mb-3">
        <table class="table table-striped table-sm">
            <thead><tr><th>Especialidad</th><th>Categoría</th><th>Año</th></tr></thead>
            <tbody>
                <?php while ($e = $logros_especialidades->fetch_assoc()): ?>
                    <tr>
                        <td><?= $e['nombre_especialidad'] ?></td>
                        <td><?= $e['nombre_categoria'] ?></td>
                        <td><?= $e['anio'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">Sin especialidades registradas.</p>
<?php endif; ?>

<h4 class="mt-3"><i class="bi bi-award-fill text-warning"></i> Maestrías</h4>
<?php if (!empty($maestrias_completadas)): ?>
    <?php foreach ($maestrias_completadas as $nombre): ?>
        <span class="badge bg-warning text-dark fs-6 me-1"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($nombre) ?></span>
    <?php endforeach; ?>
<?php else: ?>
    <p class="text-muted">No tiene maestrías completadas.</p>
<?php endif; ?>