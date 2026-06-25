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
    <title>Buscar Miembro - Club Betelgeuse</title>
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
        <h2><i class="bi bi-search"></i> Buscar Miembro</h2>

        <form id="formBuscar" class="row g-2 mb-4" onsubmit="return false;">
            <input type="hidden" name="tipo" id="tipoHidden" value="dni">
            <div class="col-md-3">
                <select id="tipoBusqueda" class="form-select" onchange="cambiarTipo()">
                    <option value="dni">Por DNI</option>
                    <option value="nombre">Por Nombre/Apellido</option>
                    <option value="unidad">Por Unidad</option>
                    <option value="clase">Por Clase (completada)</option>
                    <option value="clase_cursando">Por Clase (cursando)</option>
                </select>
            </div>
            <div class="col-md-4" id="divBusquedaTexto">
                <input type="text" id="inputBusqueda" class="form-control" placeholder="Escribe para buscar..." onkeyup="buscarConRetraso()">
            </div>
            <div class="col-md-4" id="divBusquedaUnidad" style="display:none;">
                <select id="selectUnidad" class="form-select" onchange="buscarInmediato()">
                    <option value="">Seleccionar unidad...</option>
                    <?php while ($u = $unidades->fetch_assoc()): ?>
                        <option value="<?= $u['nombre'] ?>"><?= $u['nombre'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4" id="divBusquedaClase" style="display:none;">
                <select id="selectClase" class="form-select" onchange="buscarInmediato()">
                    <option value="">Seleccionar clase...</option>
                    <?php foreach (['Amigo','Compañero','Explorador','Pionero','Excursionista','Guía'] as $cl): ?>
                        <option value="<?= $cl ?>"><?= $cl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="limpiarBusqueda()" title="Limpiar búsqueda">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </form>

        <div id="resultadosBusqueda"></div>
    </div>

    <!-- Modal para ficha del miembro -->
    <div class="modal fade" id="modalFicha" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> <span id="modalNombre">Ficha del Miembro</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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