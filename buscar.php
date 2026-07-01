<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
verificar_sesion(['admin','director','secretario']);

$unidades = $conn->query("SELECT nombre FROM unidades ORDER BY genero, edad_min");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscar Miembro · Club Betelgeuse</title>
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
        .navbar-search {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .navbar-search .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .navbar-search .navbar-brand:hover { color: var(--accent-club) !important; }

        /* Tarjeta principal */
        .search-card {
            background: white;
            border-radius: 28px;
            padding: 36px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
            margin-top: 30px;
            margin-bottom: 40px;
        }

        /* Encabezado */
        .search-header h2 {
            font-weight: 800;
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .search-header p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 28px;
        }

        /* Selector y campos */
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

        /* Botón limpiar */
        .btn-clear {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            border-radius: 12px;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-clear:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Tabla de resultados */
        .resultados-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(241, 245, 249, 0.9);
        }
        .resultados-table table {
            margin-bottom: 0;
        }
        .resultados-table thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .resultados-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .badge-tipo {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        /* Modal */
        .modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 20px 60px -10px rgba(15, 23, 42, 0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-radius: 24px 24px 0 0;
            padding: 20px 28px;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .modal-body {
            padding: 28px;
        }

        /* Botón ver ficha */
        .btn-ficha {
            border-radius: 20px;
            font-weight: 600;
            padding: 4px 16px;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        .btn-ficha:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-search">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver al Panel
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="search-card">

            <div class="search-header">
                <h2><i class="bi bi-search me-2" style="color: var(--accent-club);"></i>Buscar Miembro</h2>
                <p>Encuentra conquistadores o líderes por DNI, nombre, unidad o clase.</p>
            </div>

            <form id="formBuscar" class="row g-2 mb-4" onsubmit="return false;">
                <input type="hidden" name="tipo" id="tipoHidden" value="dni">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-secondary">CRITERIO</label>
                    <select id="tipoBusqueda" class="form-select" onchange="cambiarTipo()">
                        <option value="dni">Por DNI</option>
                        <option value="nombre">Por Nombre/Apellido</option>
                        <option value="unidad">Por Unidad</option>
                        <option value="clase">Por Clase (completada)</option>
                        <option value="clase_cursando">Por Clase (cursando)</option>
                    </select>
                </div>
                <div class="col-md-5" id="divBusquedaTexto">
                    <label class="form-label fw-semibold small text-secondary">BÚSQUEDA</label>
                    <input type="text" id="inputBusqueda" class="form-control" placeholder="Escribe para buscar..." onkeyup="buscarConRetraso()">
                </div>
                <div class="col-md-5" id="divBusquedaUnidad" style="display:none;">
                    <label class="form-label fw-semibold small text-secondary">UNIDAD</label>
                    <select id="selectUnidad" class="form-select" onchange="buscarInmediato()">
                        <option value="">Seleccionar unidad...</option>
                        <?php while ($u = $unidades->fetch_assoc()): ?>
                            <option value="<?= $u['nombre'] ?>"><?= $u['nombre'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-5" id="divBusquedaClase" style="display:none;">
                    <label class="form-label fw-semibold small text-secondary">CLASE</label>
                    <select id="selectClase" class="form-select" onchange="buscarInmediato()">
                        <option value="">Seleccionar clase...</option>
                        <?php foreach (['Amigo','Compañero','Explorador','Pionero','Excursionista','Guía'] as $cl): ?>
                            <option value="<?= $cl ?>"><?= $cl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-clear w-100" onclick="limpiarBusqueda()" title="Limpiar búsqueda" type="button">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </form>

            <div id="resultadosBusqueda"></div>
        </div>
    </div>

    <!-- Modal para ficha del miembro -->
    <div class="modal fade" id="modalFicha" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i><span id="modalNombre">Ficha del Miembro</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Se llena con AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let timeoutId;
        let modalInstance = null;

        function cambiarTipo() {
            const tipo = document.getElementById('tipoBusqueda').value;
            document.getElementById('divBusquedaTexto').style.display = (tipo === 'dni' || tipo === 'nombre') ? 'block' : 'none';
            document.getElementById('divBusquedaUnidad').style.display = (tipo === 'unidad') ? 'block' : 'none';
            document.getElementById('divBusquedaClase').style.display = (tipo === 'clase' || tipo === 'clase_cursando') ? 'block' : 'none';
            document.getElementById('tipoHidden').value = tipo;
            document.getElementById('inputBusqueda').value = '';
            document.getElementById('resultadosBusqueda').innerHTML = '';
        }

        function buscarConRetraso() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(buscarInmediato, 1500);
        }

        function buscarInmediato() {
            const tipo = document.getElementById('tipoBusqueda').value;
            let q = document.getElementById('inputBusqueda').value;
            let params = `tipo=${tipo}`;

            if (tipo === 'unidad') {
                q = document.getElementById('selectUnidad').value;
                params += `&unidad=${encodeURIComponent(q)}`;
            } else if (tipo === 'clase' || tipo === 'clase_cursando') {
                q = document.getElementById('selectClase').value;
                params += `&q=${encodeURIComponent(q)}`;
            } else {
                params += `&q=${encodeURIComponent(q)}`;
            }

            if (!q) {
                document.getElementById('resultadosBusqueda').innerHTML = '';
                return;
            }

            const url = `buscar_ajax.php?${params}`;
            fetch(url)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('resultadosBusqueda').innerHTML = html;
                });
        }

        function limpiarBusqueda() {
            document.getElementById('inputBusqueda').value = '';
            document.getElementById('selectUnidad').value = '';
            document.getElementById('selectClase').value = '';
            document.getElementById('resultadosBusqueda').innerHTML = '';
        }

        function verFicha(id) {
            fetch(`get_ficha_miembro.php?id=${id}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                    document.getElementById('modalNombre').textContent = 'Ficha del Miembro';
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(document.getElementById('modalFicha'));
                    }
                    modalInstance.show();
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>