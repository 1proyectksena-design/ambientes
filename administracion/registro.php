<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* ── CREAR AMBIENTE ── */
if (isset($_POST['crear_ambiente'])) {
    $nombre        = mysqli_real_escape_string($conexion, $_POST['nombre_ambiente']);
    $estado        = mysqli_real_escape_string($conexion, $_POST['estado']);
    $descripcion   = mysqli_real_escape_string($conexion, $_POST['descripcion']);
    $instructor_id = !empty($_POST['instructor_id'])
                     ? mysqli_real_escape_string($conexion, $_POST['instructor_id'])
                     : null;

    /* hora_inicio / hora_fin (24 h) */
    $hora_inicio_val = !empty($_POST['hora_inicio']) && preg_match('/^\d{2}:\d{2}$/', $_POST['hora_inicio'])
                       ? "'" . mysqli_real_escape_string($conexion, $_POST['hora_inicio']) . ":00'"
                       : 'NULL';
    $hora_fin_val    = !empty($_POST['hora_fin']) && preg_match('/^\d{2}:\d{2}$/', $_POST['hora_fin'])
                       ? "'" . mysqli_real_escape_string($conexion, $_POST['hora_fin']) . ":00'"
                       : 'NULL';

    /* Validar hora_fin > hora_inicio si ambas están presentes */
    if (!empty($_POST['hora_inicio']) && !empty($_POST['hora_fin'])) {
        if ($_POST['hora_fin'] <= $_POST['hora_inicio']) {
            echo "<script>alert('❌ La hora de fin debe ser mayor que la hora de inicio.'); window.history.back();</script>";
            exit;
        }
    }

    $check = mysqli_query($conexion, "SELECT id FROM ambientes WHERE nombre_ambiente = '$nombre'");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Ya existe un ambiente con el nombre \"$nombre\"'); window.history.back();</script>";
        exit;
    }

    $sql = "INSERT INTO ambientes
                (nombre_ambiente, estado, descripcion_general, hora_inicio, hora_fin, instructor_id)
            VALUES
                ('$nombre', '$estado', '$descripcion', $hora_inicio_val, $hora_fin_val,
                 " . ($instructor_id ? "'$instructor_id'" : 'NULL') . ")";

    if (mysqli_query($conexion, $sql)) {
        $id_ambiente = mysqli_insert_id($conexion);
        $qr_msg = 'Ambiente creado correctamente';
        try {
            include_once('../includes/generar_qr.php');
            generarQR($id_ambiente, $nombre);
        } catch (Throwable $e) {
            $qr_msg = 'Ambiente creado. QR no generado: ' . $e->getMessage();
        }
        echo "<script>alert('$qr_msg'); window.location.href='registro.php';</script>";
    } else {
        echo "<script>alert('Error al crear ambiente: " . mysqli_error($conexion) . "');</script>";
    }
}

/* ── CREAR INSTRUCTOR ── */
if (isset($_POST['crear_instructor'])) {
    $nombre         = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $identificacion = mysqli_real_escape_string($conexion, $_POST['identificacion']);
    $fecha_inicio   = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
    $fecha_fin      = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
    $novedades      = mysqli_real_escape_string($conexion, $_POST['novedades']);

    $checkDoc = mysqli_query($conexion, "SELECT id FROM instructores WHERE identificacion = '$identificacion'");
    if (mysqli_num_rows($checkDoc) > 0) {
        echo "<script>alert('Ya existe un instructor con la identificación \"$identificacion\"'); window.history.back();</script>";
        exit;
    }

    $sql = "INSERT INTO instructores (nombre, identificacion, fecha_inicio, fecha_fin, novedades)
            VALUES ('$nombre', '$identificacion', '$fecha_inicio',
                    " . ($fecha_fin ? "'$fecha_fin'" : 'NULL') . ", '$novedades')";

    if (mysqli_query($conexion, $sql)) {
        echo "<script>alert('Instructor creado correctamente'); window.location.href='registro.php';</script>";
    } else {
        echo "<script>alert('Error al crear instructor: " . mysqli_error($conexion) . "');</script>";
    }
}

/* ── BUSCAR QR POR AJAX ── */
if (isset($_GET['buscar_qr'])) {
    $termino    = mysqli_real_escape_string($conexion, $_GET['buscar_qr']);
    $resultados = [];
    $res = mysqli_query($conexion, "SELECT id, nombre_ambiente FROM ambientes WHERE nombre_ambiente LIKE '%$termino%' ORDER BY nombre_ambiente ASC LIMIT 10");
    while ($row = mysqli_fetch_assoc($res)) {
        $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $row['nombre_ambiente']);
        $nombre_limpio = trim(preg_replace('/_+/', '_', $nombre_limpio), '_');
        $resultados[]  = [
            'id'     => $row['id'],
            'nombre' => $row['nombre_ambiente'],
            'qr'     => "../qrs/{$nombre_limpio}_{$row['id']}.png",
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($resultados);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Registros</title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Crear Registros</h1>
            <span>Ambientes e Instructores</span>
        </div>
    </div>
    <div class="header-user"><i class="fa-solid fa-user user-icon"></i> Administración</div>
</div>

<div class="permisos-container">

    <div class="toggle-forms">
        <button class="toggle-btn active" onclick="showForm('ambiente')">
            <i class="fa-solid fa-building"></i> Crear Ambiente
        </button>
        <button class="toggle-btn" onclick="showForm('instructor')">
            <i class="fa-solid fa-chalkboard-user"></i> Crear Instructor
        </button>
        <button class="toggle-btn" onclick="showForm('buscar')">
            <i class="fa-solid fa-qrcode"></i> Buscar QR
        </button>
    </div>

    <!-- FORMULARIO AMBIENTE -->
    <div class="form-card" id="form-ambiente">
        <div class="form-header">
            <h2><i class="fa-solid fa-building"></i> Nuevo Ambiente</h2>
            <p>Complete la información del ambiente</p>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Nombre del Ambiente *</label>
                <input type="text" name="nombre_ambiente" placeholder="Ej: Laboratorio 308" required>
            </div>
            <div class="form-group">
                <label>Estado *</label>
                <select name="estado" required>
                    <option value="Habilitado">Habilitado</option>
                    <option value="Deshabilitado">Deshabilitado</option>
                    <option value="Mantenimiento">Mantenimiento</option>
                </select>
            </div>
            <div class="form-group">
                <label>Instructor Asignado</label>
                <select name="instructor_id">
                    <option value="">— Sin asignar —</option>
                    <?php
                    $res = mysqli_query($conexion, "SELECT id, nombre FROM instructores ORDER BY nombre ASC");
                    while ($row = mysqli_fetch_assoc($res)) {
                        echo "<option value='{$row['id']}'>" . htmlspecialchars($row['nombre']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Descripción General</label>
                <textarea name="descripcion" placeholder="Equipamiento, capacidad, observaciones..."></textarea>
            </div>
            <!-- hora_inicio / hora_fin en 24 h -->
            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora de inicio (24 h)</label>
                    <input type="time" name="hora_inicio" placeholder="07:00">
                    <small class="form-hint">Hora en que el ambiente abre.</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora de fin (24 h)</label>
                    <input type="time" name="hora_fin" placeholder="22:00">
                    <small class="form-hint">Hora en que el ambiente cierra.</small>
                </div>
            </div>
            <button type="submit" name="crear_ambiente" class="btn-submit">
                <i class="fa-solid fa-plus-circle"></i> Crear Ambiente
            </button>
        </form>
    </div>

    <!-- FORMULARIO INSTRUCTOR -->
    <div class="form-card" id="form-instructor" style="display:none;">
        <div class="form-header">
            <h2><i class="fa-solid fa-chalkboard-user"></i> Nuevo Instructor</h2>
            <p>Complete la información del instructor</p>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Nombre Completo *</label>
                <input type="text" name="nombre" placeholder="Ej: Juan Carlos Pérez" required>
            </div>
            <div class="form-group">
                <label>Identificación *</label>
                <input type="text" name="identificacion" placeholder="Número de documento" required>
            </div>
            <div class="time-grid">
                <div class="form-group">
                    <label>Fecha Inicio *</label>
                    <input type="date" name="fecha_inicio" required>
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="fecha_fin">
                </div>
            </div>
            <div class="form-group">
                <label>Novedades</label>
                <textarea name="novedades" placeholder="Observaciones, horarios especiales..."></textarea>
            </div>
            <button type="submit" name="crear_instructor" class="btn-submit">
                <i class="fa-solid fa-plus-circle"></i> Crear Instructor
            </button>
        </form>
    </div>

    <!-- BUSCAR QR -->
    <div class="form-card" id="form-buscar" style="display:none;">
        <div class="form-header">
            <h2><i class="fa-solid fa-qrcode"></i> Buscar QR de Ambiente</h2>
            <p>Escribe el nombre o número del ambiente</p>
        </div>
        <div class="form-group">
            <label>Buscar Ambiente</label>
            <div class="qr-search-group">
                <i class="fa-solid fa-magnifying-glass qr-search-icon"></i>
                <input type="text" id="inputBuscarQR"
                    placeholder="Ej: 108, Laboratorio..."
                    autocomplete="off"
                    oninput="buscarQR(this.value)">
                <button type="button" class="qr-btn-clear" id="btnLimpiar" onclick="limpiarBusqueda()" style="display:none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div id="qr-loading" style="display:none;" class="qr-estado">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Buscando...
        </div>
        <div id="qr-empty" style="display:none;" class="qr-estado">
            <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
            <p>No se encontró ningún ambiente con ese nombre.</p>
        </div>
        <div id="qr-resultados" class="qr-resultados"></div>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>
</div>

<style>
.toggle-forms { display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:25px; }
.toggle-btn { background:white;border:2px solid #e5e7eb;padding:15px 20px;border-radius:12px;font-size:16px;font-weight:600;color:#666;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:10px; }
.toggle-btn:hover { border-color:#667eea;color:#667eea; }
.toggle-btn.active { background:linear-gradient(135deg,#24315e 0%,#6177a0 100%);border-color:#667eea;color:white; }
.qr-search-group { position:relative;display:flex;align-items:center; }
.qr-search-group input { padding-left:40px !important;padding-right:40px !important; }
.qr-search-icon { position:absolute;left:14px;color:#9ca3af;font-size:15px;pointer-events:none;z-index:1; }
.qr-btn-clear { position:absolute;right:12px;background:none;border:none;color:#9ca3af;cursor:pointer;font-size:16px;padding:0;display:flex;align-items:center;z-index:1; }
.qr-btn-clear:hover { color:#ef4444; }
.qr-estado { text-align:center;padding:30px 0;color:#9ca3af;font-size:15px;display:flex;flex-direction:column;align-items:center;gap:10px; }
.qr-estado i { font-size:2rem;color:#667eea; }
.qr-resultados { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;margin-top:10px; }
.qr-card { background:#f8f9fc;border:2px solid #e5e7eb;border-radius:14px;padding:20px 15px;display:flex;flex-direction:column;align-items:center;gap:12px;transition:all .25s;animation:fadeInCard .3s ease; }
.qr-card:hover { border-color:#667eea;box-shadow:0 6px 20px rgba(102,126,234,.15);transform:translateY(-3px); }
@keyframes fadeInCard { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.qr-card-nombre { font-weight:700;font-size:15px;color:#24315e;text-align:center;word-break:break-word; }
.qr-card img { width:140px;height:140px;border-radius:8px;border:1px solid #e5e7eb;object-fit:contain;background:white; }
.qr-card-noimg { width:140px;height:140px;border-radius:8px;border:2px dashed #d1d5db;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;gap:8px;text-align:center; }
.qr-card-noimg i { font-size:2rem; }
.btn-descargar-qr { background:linear-gradient(135deg,#24315e 0%,#6177a0 100%);color:white;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;transition:opacity .2s;text-decoration:none; }
.btn-descargar-qr:hover { opacity:.85; }
.form-hint { display:block;margin-top:6px;font-size:.82rem;color:#6b7280; }
@media (max-width:600px) { .toggle-forms { grid-template-columns:1fr; } }
</style>

<script>
function showForm(tipo) {
    ['ambiente','instructor','buscar'].forEach(t => {
        document.getElementById('form-' + t).style.display = 'none';
    });
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    const map = { ambiente:0, instructor:1, buscar:2 };
    document.querySelectorAll('.toggle-btn')[map[tipo]].classList.add('active');
    document.getElementById('form-' + tipo).style.display = 'block';
    if (tipo === 'buscar') document.getElementById('inputBuscarQR').focus();
}

let debounceTimer = null;

function buscarQR(termino) {
    const btnLimpiar = document.getElementById('btnLimpiar');
    const loading    = document.getElementById('qr-loading');
    const empty      = document.getElementById('qr-empty');
    const resultados = document.getElementById('qr-resultados');

    btnLimpiar.style.display = termino.length > 0 ? 'flex' : 'none';
    clearTimeout(debounceTimer);

    if (termino.trim().length === 0) {
        loading.style.display = empty.style.display = 'none';
        resultados.innerHTML = '';
        return;
    }

    loading.style.display = 'flex';
    empty.style.display   = 'none';
    resultados.innerHTML  = '';

    debounceTimer = setTimeout(() => {
        fetch('registro.php?buscar_qr=' + encodeURIComponent(termino.trim()))
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.length === 0) { empty.style.display = 'flex'; return; }
                resultados.innerHTML = data.map(item => `
                    <div class="qr-card">
                        <div class="qr-card-nombre">
                            <i class="fa-solid fa-building" style="color:#6177a0;margin-right:5px;"></i>${item.nombre}
                        </div>
                        <img src="${item.qr}" alt="QR ${item.nombre}" onerror="this.outerHTML='<div class=qr-card-noimg><i class=fa-solid fa-qrcode></i><span>QR no<br>disponible</span></div>'">
                        <a class="btn-descargar-qr" href="${item.qr}" download="QR_${item.nombre}.png">
                            <i class="fa-solid fa-download"></i> Descargar
                        </a>
                    </div>
                `).join('');
            })
            .catch(() => {
                loading.style.display = 'none';
                empty.style.display   = 'flex';
            });
    }, 350);
}

function limpiarBusqueda() {
    const input = document.getElementById('inputBuscarQR');
    input.value = '';
    buscarQR('');
    input.focus();
}
</script>
</body>
</html>