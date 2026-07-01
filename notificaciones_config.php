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
    <title>Destinatarios · Club Betelgeuse</title>
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

        .navbar-notif {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-notif .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-notif .navbar-brand:hover { color: var(--accent-club) !important; }

        .notif-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .notif-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .notif-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        .form-select, .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.9rem;
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

        .table-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
        }
        .table-card table { margin-bottom: 0; }
        .table-card thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 16px;
        }
        .table-card tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-weight: 500;
        }
        .table-card tbody tr:hover { background: #f8fafc; }

        .badge-estado {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .badge-activo {
            background: #dcfce7;
            color: #166534;
        }
        .badge-inactivo {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-accion {
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 4px 14px;
            transition: var(--transition);
        }
        .btn-accion:hover { transform: translateY(-1px); }

        .btn-agregar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 12px;
            transition: var(--transition);
        }
        .btn-agregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
        }

        .alert-success {
            border-radius: 16px;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            color: #065f46;
            font-weight: 600;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .notif-card { padding: 24px 18px; border-radius: 22px; }
            .notif-header h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-notif">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
            <a href="calendario.php" class="btn btn-sm btn-outline-light rounded-pill">
                <i class="bi bi-calendar3 me-1"></i> Calendario
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="notif-card">

            <div class="notif-header">
                <h2><i class="bi bi-envelope me-2" style="color: var(--accent-club);"></i>Destinatarios de Notificaciones</h2>
                <p>Administra quiénes recibirán los correos de cumpleaños y eventos del club.</p>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?= $mensaje ?></div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario para agregar -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-primary text-white rounded-top-4">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Agregar Destinatario</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nombre</label>
                                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Carlos Luján">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control" required placeholder="correo@ejemplo.com">
                                </div>
                                <button type="submit" name="agregar" class="btn btn-agregar w-100">
                                    <i class="bi bi-check-circle me-1"></i> Agregar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lista de destinatarios -->
                <div class="col-md-7">
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($d = $destinatarios->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($d['nombre']) ?></strong></td>
                                            <td class="text-secondary"><?= htmlspecialchars($d['email']) ?></td>
                                            <td>
                                                <?php if ($d['activo']): ?>
                                                    <span class="badge-estado badge-activo">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge-estado badge-inactivo">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?toggle=<?= $d['id'] ?>" class="btn btn-accion btn-outline-warning" title="Activar/Desactivar">
                                                        <i class="bi bi-power"></i>
                                                    </a>
                                                    <a href="?eliminar=<?= $d['id'] ?>" class="btn btn-accion btn-outline-danger" onclick="return confirm('¿Eliminar destinatario?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if ($destinatarios->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">No hay destinatarios registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>