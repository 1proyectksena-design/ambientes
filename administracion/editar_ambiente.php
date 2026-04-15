<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$id_ambiente = $_GET['id'] ?? null;
if (!$id_ambiente) { header("Location: consultar.php"); exit; }

/* ── Cargar ambiente ── */
$sql = "SELECT a.*,
               TIME_FORMAT(a.hora_inicio, '%H:%i') AS fmt_hora_inicio,
               TIME_FORMAT(a.hora_fin,    '%H:%i') AS fmt_hora_fin,
               i.nombre AS nombre_instructor_actual
        FROM ambientes a
        LEFT JOIN instructores i ON a.instructor_id = i.id
        WHERE a.id = '" . mysqli_real_escape_string($conexion, $id_ambiente) . "'";
$res      = mysqli_query($conexion, $sql);
$ambiente = mysqli_fetch_assoc($res);

if (!$ambiente) {
    echo "<script>alert('Ambiente no encontrado'); window.location.href='consultar.php';</script>";
    exit;
}

/* ── Auto-expiración de mantenimiento ── */
if (
    $ambiente['estado'] === 'Mantenimiento' &&
    !empty($ambiente['mantenimiento_fin']) &&
    strtotime($ambiente['mantenimiento_fin']) < strtotime(date('Y-m-d'))
) {
    $sqlAuto = "UPDATE ambientes
                SET estado = 'Habilitado',
                    mantenimiento_inicio = NULL,
                    mantenimiento_fin    = NULL
                WHERE id = '" . mysqli_real_escape_string($conexion, $id_ambiente) . "'";
    mysqli_query($conexion, $sqlAuto);
    $ambiente['estado']              = 'Habilitado';
    $ambiente['mantenimiento_inicio'] = null;
    $ambiente['mantenimiento_fin']    = null;
}

/* ── Actualizar ── */
if (isset($_POST['actualizar'])) {
    $nuevo_estado  = mysqli_real_escape_string($conexion, $_POST['estado']);
    $descripcion   = mysqli_real_escape_string($conexion, $_POST['descripcion_general']);

    /* hora_inicio / hora_fin — validar formato HH:MM */
    $hora_inicio = !empty($_POST['hora_inicio']) && preg_match('/^\d{2}:\d{2}$/', $_POST['hora_inicio'])
                   ? "'" . mysqli_real_escape_string($conexion, $_POST['hora_inicio']) . ":00'"
                   : 'NULL';
    $hora_fin    = !empty($_POST['hora_fin']) && preg_match('/^\d{2}:\d{2}$/', $_POST['hora_fin'])
                   ? "'" . mysqli_real_escape_string($conexion, $_POST['hora_fin']) . ":00'"
                   : 'NULL';

    $instructor_id = !empty($_POST['instructor_id'])
                     ? "'" . mysqli_real_escape_string($conexion, $_POST['instructor_id']) . "'"
                     : 'NULL';

    $mant_inicio = 'NULL';
    $mant_fin    = 'NULL';

    if ($nuevo_estado === 'Mantenimiento') {
        if (!empty($_POST['mantenimiento_inicio'])) {
            $mant_inicio = "'" . mysqli_real_escape_string($conexion, $_POST['mantenimiento_inicio']) . "'";
        }
        if (!empty($_POST['mantenimiento_fin'])) {
            $mant_fin = "'" . mysqli_real_escape_string($conexion, $_POST['mantenimiento_fin']) . "'";
        }
        if (!empty($_POST['mantenimiento_inicio']) && !empty($_POST['mantenimiento_fin'])) {
            if (strtotime($_POST['mantenimiento_inicio']) > strtotime($_POST['mantenimiento_fin'])) {
                echo "<script>alert('❌ La fecha de inicio no puede ser mayor que la fecha de fin.');</script>";
                goto fin_update;
            }
        }
    }

    $sqlUpdate = "UPDATE ambientes
                  SET estado               = '$nuevo_estado',
                      descripcion_general  = '$descripcion',
                      hora_inicio          = $hora_inicio,
                      hora_fin             = $hora_fin,
                      instructor_id        = $instructor_id,
                      mantenimiento_inicio = $mant_inicio,
                      mantenimiento_fin    = $mant_fin
                  WHERE id = '$id_ambiente'";

    if (mysqli_query($conexion, $sqlUpdate)) {
        echo "<script>
                alert('✅ Ambiente actualizado correctamente');
                window.location.href='consultar.php?ambiente=" . urlencode($ambiente['nombre_ambiente']) . "';
              </script>";
        exit;
    } else {
        echo "<script>alert('❌ Error al actualizar: " . mysqli_error($conexion) . "');</script>";
    }
    fin_update:;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ambiente - <?= htmlspecialchars($ambiente['nombre_ambiente']) ?></title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Editar Ambiente</h1>
            <span>Modificar estado y configuración</span>
        </div>
    </div>
    <div class="header-user"><i class="fa-solid fa-user user-icon"></i> Administración</div>
</div>

<div class="permisos-container">
    <div class="form-card">
        <div class="form-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Editar: <?= htmlspecialchars($ambiente['nombre_ambiente']) ?></h2>
            <p>Modifica el estado y la información del ambiente</p>
        </div>

        <div class="estado-actual">
            <strong>Estado Actual:</strong>
            <span class="estado-badge estado-<?= strtolower($ambiente['estado']) ?>">
                <?= htmlspecialchars($ambiente['estado']) ?>
            </span>

            <?php if ($ambiente['estado'] === 'Mantenimiento' && !empty($ambiente['mantenimiento_fin'])): ?>
                <span class="mant-fechas-badge">
                    <i class="fa-solid fa-calendar-days"></i>
                    <?= date('d/m/Y', strtotime($ambiente['mantenimiento_inicio'])) ?>
                    &nbsp;→&nbsp;
                    <?= date('d/m/Y', strtotime($ambiente['mantenimiento_fin'])) ?>
                </span>
            <?php endif; ?>

            <?php if ($ambiente['nombre_instructor_actual']): ?>
                <span class="instructor-actual-badge">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <?= htmlspecialchars($ambiente['nombre_instructor_actual']) ?>
                </span>
            <?php else: ?>
                <span class="instructor-actual-badge sin-instructor">
                    <i class="fa-solid fa-user-slash"></i> Sin instructor asignado
                </span>
            <?php endif; ?>
        </div>

        <form method="POST">

            <div class="form-group">
                <label><i class="fa-solid fa-toggle-on"></i> Cambiar Estado *</label>
                <select name="estado" id="estado_select" required>
                    <option value="Habilitado"    <?= $ambiente['estado'] == 'Habilitado'    ? 'selected' : '' ?>>Habilitado</option>
                    <option value="Deshabilitado" <?= $ambiente['estado'] == 'Deshabilitado' ? 'selected' : '' ?>>Deshabilitado</option>
                    <option value="Mantenimiento" <?= $ambiente['estado'] == 'Mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                </select>
            </div>

            <!-- Bloque mantenimiento -->
            <div id="bloque-mantenimiento" style="display:none;">
                <div class="mant-banner">
                    <i class="fa-solid fa-wrench"></i>
                    <strong>Período de mantenimiento</strong>
                    <span>El ambiente se habilitará automáticamente al llegar la fecha de fin.</span>
                </div>
                <div class="time-grid">
                    <div class="form-group">
                        <label><i class="fa-regular fa-calendar-minus"></i> Fecha de inicio</label>
                        <input type="date" name="mantenimiento_inicio" id="mant_inicio"
                               value="<?= htmlspecialchars($ambiente['mantenimiento_inicio'] ?? '') ?>"
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-regular fa-calendar-check"></i> Fecha de fin</label>
                        <input type="date" name="mantenimiento_fin" id="mant_fin"
                               value="<?= htmlspecialchars($ambiente['mantenimiento_fin'] ?? '') ?>"
                               min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div id="mant-resumen" class="mant-resumen" style="display:none;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="mant-resumen-texto"></span>
                </div>
            </div>

            <!-- Instructor fijo -->
            <div class="form-group">
                <label><i class="fa-solid fa-chalkboard-user"></i> Instructor de Horario Fijo</label>
                <select name="instructor_id">
                    <option value="">— Sin asignar —</option>
                    <?php
                    $resInst = mysqli_query($conexion, "SELECT id, nombre, identificacion FROM instructores ORDER BY nombre ASC");
                    while ($inst = mysqli_fetch_assoc($resInst)):
                        $sel = ($ambiente['instructor_id'] == $inst['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $inst['id'] ?>" <?= $sel ?>>
                            <?= htmlspecialchars($inst['nombre']) ?> — <?= htmlspecialchars($inst['identificacion']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="form-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Instructor que ocupa el ambiente en su horario habitual.
                </small>
            </div>

            <!-- Descripción -->
            <div class="form-group">
                <label><i class="fa-solid fa-file-lines"></i> Descripción General</label>
                <textarea name="descripcion_general" rows="4"><?= htmlspecialchars($ambiente['descripcion_general']) ?></textarea>
            </div>

            <!-- hora_inicio / hora_fin en formato 24 h (reemplazan horario_fijo / horario_disponible) -->
            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora de inicio (24 h)</label>
                    <input type="time" name="hora_inicio"
                           value="<?= htmlspecialchars($ambiente['fmt_hora_inicio'] ?? '') ?>"
                           placeholder="Ej: 07:00">
                    <small class="form-hint">Hora en que el ambiente comienza a estar disponible.</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora de fin (24 h)</label>
                    <input type="time" name="hora_fin"
                           value="<?= htmlspecialchars($ambiente['fmt_hora_fin'] ?? '') ?>"
                           placeholder="Ej: 22:00">
                    <small class="form-hint">Hora en que el ambiente cierra.</small>
                </div>
            </div>

            <!-- Advertencias -->
            <div class="estado-warnings">
                <div class="warning-item" id="warn-habilitado" style="display:none;">
                    <i class="fa-solid fa-circle-check"></i>
                    <p>El ambiente estará disponible para nuevas autorizaciones.</p>
                </div>
                <div class="warning-item warning-danger" id="warn-deshabilitado" style="display:none;">
                    <i class="fa-solid fa-ban"></i>
                    <p><strong>Advertencia:</strong> No se podrán crear nuevas autorizaciones mientras esté deshabilitado.</p>
                </div>
                <div class="warning-item warning-warning" id="warn-mantenimiento" style="display:none;">
                    <i class="fa-solid fa-wrench"></i>
                    <p><strong>Nota:</strong> El ambiente estará temporalmente fuera de servicio.</p>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" name="actualizar" class="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
                <a href="consultar.php?ambiente=<?= urlencode($ambiente['nombre_ambiente']) ?>" class="btn-cancel">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.estado-actual { background:#f8f9fa;padding:15px 20px;border-radius:10px;margin-bottom:25px;display:flex;align-items:center;gap:12px;flex-wrap:wrap; }
.instructor-actual-badge { display:inline-flex;align-items:center;gap:7px;background:#e8f0fe;color:#1a56db;border:1px solid #b3c6f7;border-radius:20px;padding:5px 14px;font-size:.88rem;font-weight:600; }
.instructor-actual-badge.sin-instructor { background:#f3f4f6;color:#9ca3af;border-color:#e5e7eb; }
.mant-fechas-badge { display:inline-flex;align-items:center;gap:7px;background:#fff3e0;color:#b45309;border:1px solid #fbbf24;border-radius:20px;padding:5px 14px;font-size:.88rem;font-weight:600; }
.mant-banner { background:#fffbeb;border:1px solid #fbbf24;border-left:4px solid #f59e0b;border-radius:8px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.mant-banner i { color:#d97706; }
.mant-resumen { background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin:8px 0 16px;display:flex;align-items:center;gap:8px;font-size:.9rem;color:#166534; }
.form-hint { display:block;margin-top:6px;font-size:.82rem;color:#6b7280; }
.form-hint i { color:#667eea;margin-right:4px; }
.estado-warnings { margin:20px 0; }
.warning-item { background:#e3f2fd;border-left:4px solid #2196f3;padding:12px 15px;border-radius:8px;display:flex;align-items:center;gap:10px;margin-bottom:10px; }
.warning-item i { font-size:1.3rem;color:#2196f3; }
.warning-item.warning-danger { background:#ffebee;border-left-color:#e53935; }
.warning-item.warning-danger i { color:#e53935; }
.warning-item.warning-warning { background:#fff3e0;border-left-color:#fb8c00; }
.warning-item.warning-warning i { color:#fb8c00; }
.warning-item p { margin:0;font-size:.95rem;color:#555; }
.form-buttons { display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:25px; }
.btn-cancel { background:#6c757d;color:white;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:600;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:all .3s; }
.btn-cancel:hover { background:#5a6268;transform:translateY(-2px); }
@media (max-width:768px) { .form-buttons { grid-template-columns:1fr; } }
</style>

<script>
const estadoSelect = document.getElementById('estado_select');
const bloqueMantenimiento = document.getElementById('bloque-mantenimiento');
const mantInicio   = document.getElementById('mant_inicio');
const mantFin      = document.getElementById('mant_fin');
const resumenDiv   = document.getElementById('mant-resumen');
const resumenTexto = document.getElementById('mant-resumen-texto');

const warnings = {
    'Habilitado':    document.getElementById('warn-habilitado'),
    'Deshabilitado': document.getElementById('warn-deshabilitado'),
    'Mantenimiento': document.getElementById('warn-mantenimiento'),
};

function updateWarning() {
    Object.values(warnings).forEach(w => w.style.display = 'none');
    const sel = estadoSelect.value;
    if (warnings[sel]) warnings[sel].style.display = 'flex';
    bloqueMantenimiento.style.display = (sel === 'Mantenimiento') ? 'block' : 'none';
    calcularResumen();
}

function calcularResumen() {
    if (estadoSelect.value !== 'Mantenimiento') { resumenDiv.style.display = 'none'; return; }
    const ini = mantInicio.value, fin = mantFin.value;
    if (!ini || !fin) { resumenDiv.style.display = 'none'; return; }
    const diff = Math.round((new Date(fin) - new Date(ini)) / 86400000);
    if (diff < 0) {
        Object.assign(resumenDiv.style, { background:'#fef2f2', borderColor:'#fca5a5', color:'#991b1b' });
        resumenTexto.textContent = '⚠ La fecha de fin debe ser igual o posterior al inicio.';
    } else {
        Object.assign(resumenDiv.style, { background:'#f0fdf4', borderColor:'#86efac', color:'#166534' });
        resumenTexto.textContent = `Período: ${diff} día${diff !== 1 ? 's' : ''}. Se habilitará automáticamente el ${formatFecha(fin)}.`;
    }
    resumenDiv.style.display = 'flex';
}

mantInicio.addEventListener('change', function () {
    mantFin.min = this.value;
    if (mantFin.value && mantFin.value < this.value) mantFin.value = this.value;
    calcularResumen();
});
mantFin.addEventListener('change', calcularResumen);

function formatFecha(ymd) {
    const [y, m, d] = ymd.split('-');
    return `${d}/${m}/${y}`;
}

estadoSelect.addEventListener('change', updateWarning);
updateWarning();
</script>
</body>
</html>