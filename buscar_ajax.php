<?php
require_once 'includes/config.php';

$tipo = $_GET['tipo'] ?? 'dni';
$q = trim($_GET['q'] ?? '');
$unidad = $_GET['unidad'] ?? '';

if (empty($q) && empty($unidad)) {
    exit;
}

$q = $conn->real_escape_string($q);
$unidad = $conn->real_escape_string($unidad);

switch ($tipo) {
    case 'dni':
        $where = "u.dni LIKE '$q%'";
        break;
    case 'nombre':
        $where = "(u.nombre LIKE '%$q%' OR u.apellido LIKE '%$q%')";
        break;
    case 'unidad':
        $where = "un.nombre = '$unidad'";
        break;
    case 'clase':
        $where = "m.id IN (SELECT lcr2.miembro_id FROM logros_clase_regular lcr2 JOIN clases_regulares cr2 ON lcr2.clase_regular_id = cr2.id WHERE cr2.nombre = '$q')";
        break;
    case 'clase_cursando':
    $clase_info = $conn->query("SELECT id FROM clases_regulares WHERE nombre = '$q'")->fetch_assoc();
    if ($clase_info) {
        $clase_id = intval($clase_info['id']);
        $where = "(m.tipo = 'conquistador' AND m.activo = 1 AND m.clase_actual_id = $clase_id)";
        $where .= " OR m.id IN (SELECT lc.miembro_id FROM lider_clase lc WHERE lc.clase_regular_id = $clase_id)";
    } else {
        $where = "1=0";
    }
    break;
    default:
        $where = "u.dni LIKE '$q%'";
}

$sql = "
    SELECT DISTINCT u.*, m.id AS miembro_id, m.tipo, m.rango, m.activo,
           un.nombre AS unidad,
           GROUP_CONCAT(DISTINCT cr.nombre ORDER BY cr.edad_requerida SEPARATOR ', ') AS clases
    FROM usuarios u
    LEFT JOIN miembros m ON u.id = m.usuario_id
    LEFT JOIN miembro_unidad mu ON m.id = mu.miembro_id
    LEFT JOIN unidades un ON mu.unidad_id = un.id
    LEFT JOIN logros_clase_regular lcr ON m.id = lcr.miembro_id
    LEFT JOIN clases_regulares cr ON lcr.clase_regular_id = cr.id
    WHERE $where
    GROUP BY u.id, m.id, un.nombre
    ORDER BY u.apellido, u.nombre
    LIMIT 50
";

$resultados = $conn->query($sql);

if ($resultados && $resultados->num_rows > 0): ?>
    <div class="table-responsive mb-4">
        <table class="table table-hover">
            <thead class="table-primary">
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Tipo</th>
                    <th>Unidad</th>
                    <?php if ($tipo !== 'clase_cursando'): ?><th>Clases</th><?php endif; ?>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $resultados->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r['nombre'] . ' ' . $r['apellido'] ?></td>
                        <td><?= $r['dni'] ?></td>
                        <td>
                            <?= $r['tipo'] === 'lider' ? '<span class="badge bg-warning text-dark">👤 Líder</span>' : '<span class="badge bg-info text-dark">🧒 Conquistador</span>' ?>
                        </td>
                        <td><?= $r['unidad'] ?? '-' ?></td>
                        <?php if ($tipo !== 'clase_cursando'): ?>
                        <td><?= $r['clases'] ?? '-' ?></td>
                        <?php endif; ?>
                        <td>
                            <?= $r['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" onclick="verFicha(<?= $r['miembro_id'] ?>)">
                                <i class="bi bi-eye"></i> Ver ficha
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No se encontraron resultados.</div>
<?php endif; ?>