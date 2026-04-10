<?php
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

/* ══════════════════════════════════════════════════════════
   AJAX BUSCAR INSTRUCTOR
   ══════════════════════════════════════════════════════════ */
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json');

    $identificacion = $_GET['documento'] ?? '';

    if (!$identificacion) {
        echo json_encode(["error" => "Identificación vacía"]);
        exit;
    }

    $sql = "SELECT id, nombre, identificacion FROM instructores WHERE identificacion = ?";
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        echo json_encode(["error" => "Error en la consulta"]);
        exit;
    }

    $stmt->bind_param("s", $identificacion);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(["error" => "Instructor no encontrado"]);
        exit;
    }

    $row = $res->fetch_assoc();

    echo json_encode([
        "id" => $row['id'],
        "nombre" => $row['nombre'],
        "identificacion" => $row['identificacion']
    ]);

    exit;
}

/* ══════════════════════════════════════════════════════════
   PROCESAR ENVÍO DE SOLICITUD
   ══════════════════════════════════════════════════════════ */
$msg_success = '';
$msg_error = '';

if (isset($_POST['enviar_solicitud'])) {
    $id_instructor = (int)($_POST['id_instructor'] ?? 0);
    $id_ambiente = (int)($_POST['id_ambiente'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $hora_inicio = trim($_POST['hora_inicio'] ?? '');
    $hora_fin = trim($_POST['hora_fin'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $novedades = trim($_POST['novedades'] ?? '');

    if (!$id_instructor || !$id_ambiente || !$fecha_inicio || !$fecha_fin || !$hora_inicio || !$hora_fin) {
        $msg_error = 'Todos los campos son requeridos';
    } elseif ($fecha_inicio > $fecha_fin) {
        $msg_error = 'La fecha fin debe ser igual o mayor que la fecha inicio';
    } elseif ($hora_inicio >= $hora_fin) {
        $msg_error = 'La hora fin debe ser mayor que la hora inicio';
    } else {
        $stmtInsert = $conexion->prepare("
            INSERT INTO autorizaciones_ambientes 
            (id_ambiente, id_instructor, rol_autorizado, fecha_inicio, fecha_fin, hora_inicio, hora_final, estado, observaciones, novedades)
            VALUES (?, ?, 'instructor', ?, ?, ?, ?, 'Pendiente', ?, ?)
        ");
        $stmtInsert->bind_param('iissssss', $id_ambiente, $id_instructor, $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $observaciones, $novedades);

        if ($stmtInsert->execute()) {
            $msg_success = 'Solicitud enviada correctamente. Estado: Pendiente';
        } else {
            $msg_error = 'Error al enviar la solicitud';
        }
        $stmtInsert->close();
    }
}

/* ══════════════════════════════════════════════════════════
   CARGAR AMBIENTES
   ══════════════════════════════════════════════════════════ */
$ambientes = [];
$stAmb = $conexion->prepare("SELECT id, nombre_ambiente, horario_disponible FROM ambientes WHERE estado = 'Habilitado' ORDER BY nombre_ambiente");
$stAmb->execute();
$ambientes = $stAmb->get_result()->fetch_all(MYSQLI_ASSOC);
$stAmb->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitar Ambiente</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../css/permisos.css">
<style>
body { background: #eef0f5; font-family: 'Segoe UI', system-ui, sans-serif; }

.header {
    background: #1b2a4a;
    height: 64px;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.header-left { display: flex; align-items: center; gap: 14px; }
.logo-sena { height: 38px; }
.header-title h1 { font-size: 16px; font-weight: 700; color: #fff; }
.header-title span { font-size: 12px; color: rgba(255,255,255,0.5); }
.header-right a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1.5px solid rgba(255,255,255,0.2);
    transition: all 0.18s;
}
.header-right a:hover { background: rgba(255,255,255,0.1); color: #fff; }

.container { max-width: 1000px; margin: 30px auto; padding: 0 18px 80px; }

.card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #dde1ea;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    padding: 24px 28px;
    margin-bottom: 20px;
}
.card-title {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 9px;
    color: #1b2a4a;
    margin-bottom: 18px;
    padding-bottom: 13px;
    border-bottom: 2px solid #eefbe5;
}
.card-title i { color: #39a900; }

.buscar-row { display: flex; gap: 10px; margin-bottom: 14px; }
.buscar-input {
    flex: 1;
    padding: 12px 16px;
    border: 1.5px solid #dde1ea;
    border-radius: 9px;
    font-size: 15px;
    background: #f5f7fa;
}
.buscar-input:focus { outline: none; border-color: #243660; }
.btn-buscar {
    padding: 12px 22px;
    background: #243660;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.alert { border-radius: 9px; padding: 13px 16px; font-size: 13.5px; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 16px; }
.alert i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.alert-error { background: #fdf0ef; border: 1.5px solid #f5c6c3; color: #8b2117; }
.alert-success { background: #eefbe5; border: 1.5px solid #b8e990; color: #2d8600; }

.inst-chip { display: flex; align-items: center; gap: 14px; background: #eefbe5; border: 1.5px solid #b8e990; border-radius: 14px; padding: 14px 20px; margin-bottom: 18px; }
.inst-av { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, #2d8600, #39a900); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
.inst-info strong { font-size: 15px; color: #1b2a4a; display: block; }
.inst-info span { font-size: 13px; color: #5c6880; }

/* CALENDARIO */
.calendar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.amb-card {
    background: #fff;
    border: 2px solid #dde1ea;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
}
.amb-card:hover { border-color: #243660; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.amb-card.seleccionado { border-color: #39a900; background: #eefbe5; }
.amb-nombre { font-size: 16px; font-weight: 700; color: #1b2a4a; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
.amb-nombre i { color: #39a900; }
.amb-horario { font-size: 13px; color: #5c6880; margin-bottom: 10px; }
.amb-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.amb-status.libre { background: #eefbe5; color: #2d8600; }
.amb-status.ocupado { background: #fdf0ef; color: #8b2117; }
.amb-status.parcial { background: #fff8e1; color: #7a5f00; }

.calendario-box { background: #f5f7fa; border-radius: 12px; padding: 16px; margin-top: 14px; }
.calendario-title { font-size: 14px; font-weight: 600; color: #1b2a4a; margin-bottom: 10px; }
.dias-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.dia-chip {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.dia-chip.libre { background: #eefbe5; color: #2d8600; border: 1px solid #b8e990; }
.dia-chip.ocupado { background: #fdf0ef; color: #8b2117; border: 1px solid #f5c6c3; }
.dia-chip:hover { transform: scale(1.1); }

.btn-solicitar {
    padding: 12px 24px;
    background: #39a900;
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    justify-content: center;
    margin-top: 14px;
}
.btn-solicitar:hover { background: #2d8600; }

.form-card { background: #fff; border-radius: 14px; border: 1px solid #dde1ea; padding: 24px 28px; }
.form-header { margin-bottom: 20px; border-bottom: 2px solid #eefbe5; padding-bottom: 14px; }
.form-header h2 { font-size: 18px; color: #1b2a4a; display: flex; align-items: center; gap: 10px; }
.form-header p { font-size: 13px; color: #5c6880; margin-top: 4px; }

.time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #1b2a4a; margin-bottom: 6px; }
.form-group select, .form-group input, .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #dde1ea;
    border-radius: 9px;
    font-size: 14px;
    background: #f5f7fa;
}
.form-group select:focus, .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #243660; }

.hidden { display: none; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="SENA" class="logo-sena">
        <div class="header-title">
            <h1>Solicitar Ambiente</h1>
            <span>Reserva un ambiente para tus clases</span>
        </div>
    </div>
    <div class="header-right">
        <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="container">

<?php if($msg_success): ?>
<div class="alert alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($msg_success) ?></span>
</div>
<?php endif; ?>
<?php if($msg_error): ?>
<div class="alert alert-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span><?= htmlspecialchars($msg_error) ?></span>
</div>
<?php endif; ?>

<!-- BUSCADOR -->
<div class="card" id="buscador-card">
    <div class="card-title"><i class="fa-solid fa-id-card"></i> Buscar Instructor</div>
    <div class="buscar-row">
        <input type="text" id="identificacion" class="buscar-input" placeholder="Número de identificación del instructor" autocomplete="off">
        <button onclick="buscarInstructor()" class="btn-buscar">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
    </div>
    <div id="mensaje"></div>
</div>

<!-- RESULTADO BUSQUEDA -->
<div id="instructor-result" class="hidden">
    <div class="inst-chip">
        <div class="inst-av"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="inst-info">
            <strong id="inst-nombre"></strong>
            <span id="inst-identificacion"></span>
        </div>
        <i class="fa-solid fa-circle-check" style="color:#39a900;font-size:22px;margin-left:auto;"></i>
    </div>
    <input type="hidden" id="inst-id">
</div>

<!-- SELECCIÓN DE AMBIENTE -->
<div id="ambiente-section" class="hidden">
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-building"></i> Selecciona un Ambiente</div>
        
        <div class="buscar-row">
            <input type="date" id="fecha-seleccion" class="buscar-input" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" onchange="cargarDisponibilidad()">
            <input type="time" id="hora-inicio" class="buscar-input" value="07:00" onchange="cargarDisponibilidad()">
            <input type="time" id="hora-fin" class="buscar-input" value="18:00" onchange="cargarDisponibilidad()">
        </div>

        <div id="calendar-container">
            <div class="calendar-grid" id="ambientes-grid">
                <!-- Los ambientes se cargan aquí -->
            </div>
        </div>

        <button onclick="mostrarFormulario()" class="btn-solicitar" id="btn-continuar" disabled>
            <i class="fa-solid fa-arrow-right"></i> Continuar con Solicitud
        </button>
    </div>
</div>

<!-- FORMULARIO DE SOLICITUD -->
<div id="formulario-section" class="hidden">
    <div class="form-card">
        <div class="form-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Solicitar Ambiente</h2>
            <p>Complete los datos para enviar la solicitud</p>
        </div>

        <form method="POST" id="form-solicitud">
            <input type="hidden" name="enviar_solicitud" value="1">
            <input type="hidden" name="id_instructor" id="form-instructor-id">
            <input type="hidden" name="id_ambiente" id="form-ambiente-id">

            <div class="form-group">
                <label><i class="fa-solid fa-user"></i> Instructor</label>
                <input type="text" id="form-instructor-nombre" readonly>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-building"></i> Ambiente</label>
                <input type="text" id="form-ambiente-nombre" readonly>
            </div>

            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-calendar-days"></i> Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="form-fecha-inicio" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-calendar-days"></i> Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="form-fecha-fin" required>
                </div>
            </div>

            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Inicio</label>
                    <input type="time" name="hora_inicio" id="form-hora-inicio" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Fin</label>
                    <input type="time" name="hora_fin" id="form-hora-fin" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-comment"></i> Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Observaciones generales..."></textarea>
            </div>

            <button type="submit" class="btn-solicitar">
                <i class="fa-solid fa-paper-plane"></i> Enviar Solicitud
            </button>
        </form>
    </div>
</div>

</div>

<script>
let instructorData = null;
let ambienteSeleccionado = null;

async function buscarInstructor() {
    const doc = document.getElementById('identificacion').value.trim();
    const mensaje = document.getElementById('mensaje');

    mensaje.innerHTML = '';

    if (!doc) {
        mensaje.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Ingrese una identificación</div>';
        return;
    }

    try {
        const res = await fetch('solicitar_ambiente.php?buscar=1&documento=' + doc);
        const data = await res.json();

        if (data.error) {
            mensaje.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> ' + data.error + '</div>';
            document.getElementById('instructor-result').classList.add('hidden');
            document.getElementById('ambiente-section').classList.add('hidden');
            return;
        }

        instructorData = data;
        document.getElementById('inst-id').value = data.id;
        document.getElementById('inst-nombre').textContent = data.nombre;
        document.getElementById('inst-identificacion').textContent = 'C.C. ' + data.identificacion;
        document.getElementById('instructor-result').classList.remove('hidden');
        document.getElementById('ambiente-section').classList.remove('hidden');
        mensaje.innerHTML = '';

        cargarDisponibilidad();

    } catch (e) {
        mensaje.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Error al buscar el instructor</div>';
    }
}

async function cargarDisponibilidad() {
    const fecha = document.getElementById('fecha-seleccion').value;
    const hora_ini = document.getElementById('hora-inicio').value;
    const hora_fin = document.getElementById('hora-fin').value;
    const grid = document.getElementById('ambientes-grid');

    if (!fecha || !hora_ini || !hora_fin) return;

    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#5c6880;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando disponibilidad...</div>';

    try {
        const params = new URLSearchParams({ fecha, hora_ini, hora_fin });
        const res = await fetch('get_disponibilidad_ajax.php?' + params);
        const data = await res.json();

        if (data.error) {
            grid.innerHTML = '<div class="alert alert-error" style="grid-column:1/-1;"><i class="fa-solid fa-triangle-exclamation"></i> ' + data.error + '</div>';
            return;
        }

        grid.innerHTML = '';
        
        data.forEach(amb => {
            const statusClass = amb.libre ? 'libre' : (amb.fechas_libres.length > 0 ? 'parcial' : 'ocupado');
            const statusText = amb.libre ? '✓ Disponible' : (amb.fechas_libres.length > 0 ? '⚠ Parcial' : '✗ Ocupado');
            
            const card = document.createElement('div');
            card.className = 'amb-card';
            card.dataset.id = amb.id;
            card.dataset.nombre = amb.nombre_ambiente;
            card.onclick = () => seleccionarAmbiente(amb.id, amb.nombre_ambiente, card);
            
            card.innerHTML = `
                <div class="amb-nombre"><i class="fa-solid fa-building"></i> ${amb.nombre_ambiente}</div>
                <div class="amb-horario"><i class="fa-regular fa-clock"></i> ${amb.horario_disponible || 'Sin horario definido'}</div>
                <span class="amb-status ${statusClass}">${statusText}</span>
            `;
            
            grid.appendChild(card);
        });

    } catch (e) {
        grid.innerHTML = '<div class="alert alert-error" style="grid-column:1/-1;">Error al cargar disponibilidad</div>';
    }
}

function seleccionarAmbiente(id, nombre, element) {
    document.querySelectorAll('.amb-card').forEach(c => c.classList.remove('seleccionado'));
    element.classList.add('seleccionado');
    
    ambienteSeleccionado = { id, nombre };
    document.getElementById('btn-continuar').disabled = false;
}

function mostrarFormulario() {
    if (!instructorData || !ambienteSeleccionado) {
        alert('Seleccione un ambiente');
        return;
    }

    document.getElementById('form-instructor-id').value = instructorData.id;
    document.getElementById('form-instructor-nombre').value = instructorData.nombre;
    document.getElementById('form-ambiente-id').value = ambienteSeleccionado.id;
    document.getElementById('form-ambiente-nombre').value = ambienteSeleccionado.nombre;

    document.getElementById('form-fecha-inicio').value = document.getElementById('fecha-seleccion').value;
    document.getElementById('form-fecha-fin').value = document.getElementById('fecha-seleccion').value;
    document.getElementById('form-hora-inicio').value = document.getElementById('hora-inicio').value;
    document.getElementById('form-hora-fin').value = document.getElementById('hora-fin').value;

    document.getElementById('ambiente-section').classList.add('hidden');
    document.getElementById('formulario-section').classList.remove('hidden');
    document.getElementById('formulario-section').scrollIntoView({ behavior: 'smooth' });
}
</script>

</body>
</html>
