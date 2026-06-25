<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director']);

$mensaje = '';

// Agregar destinatario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $conn->query("INSERT INTO notificacion_destinatarios (email, nombre) VALUES ('$email', '$nombre')");
    $mensaje = "Destinatario agregado.";
}

// Activar/desactivar
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE notificacion_destinatarios SET activo = NOT activo WHERE id = $id");
    header("Location: notificaciones_config.php");
    exit;
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conn->query("DELETE FROM notificacion_destinatarios WHERE id = $id");
    $mensaje = "Destinatario eliminado.";
}

$destinatarios = $conn->query("SELECT * FROM notificacion_destinatarios ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Destinatarios - Club Betelgeuse</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-arrow-left-circle"></i> Volver</a>
            <a href="calendario.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-calendar3"></i> Calendario
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="bi bi-envelope"></i> Destinatarios de Notificaciones</h2>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-5">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Agregar Destinatario</h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <button type="submit" name="agregar" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle"></i> Agregar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-people"></i> Lista de Destinatarios</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr><th>Nombre</th><th>Email</th><th>Estado</th><th>Acción</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($d = $destinatarios->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['nombre']) ?></td>
                                        <td><?= htmlspecialchars($d['email']) ?></td>
                                        <td>
                                            <?php if ($d['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?toggle=<?= $d['id'] ?>" class="btn btn-sm btn-outline-warning" title="Activar/Desactivar">
                                                <i class="bi bi-power"></i>
                                            </a>
                                            <a href="?eliminar=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($destinatarios->num_rows == 0): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No hay destinatarios.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>