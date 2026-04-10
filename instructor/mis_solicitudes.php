<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$hoy = date('Y-m-d');

/* ══ Buscar instructor por cédula ══ */
$instructor     = null;
$cedula_buscada = '';
$error_cedula   = '';

$ced_param = $_POST['cedula'] ?? $_GET['cedula'] ?? '';

if (isset($_POST['buscar_cedula']) || isset($_GET['cedula'])) {
    $cedula_buscada = trim($ced_param);
    if ($cedula_buscada !== '') {
        $st = $conexion->prepare("
            SELECT id, nombre, identificacion
            FROM instructores
            WHERE identificacion = ?
            LIMIT 1
        ");
        $st->bind_param('s', $cedula_buscada);
        $st->execute();
        $instructor = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$instructor) $error_cedula = "No se encontró instructor con identificación \"$cedula_buscada\"";
    }
}

/* ══ Solicitudes del instructor ══ */
$solicitudes = [];
$pendientes = $aprobadas = $rechazadas = 0;

if ($instructor) {
    $stSol = $conexion->prepare("
        SELECT
            aa.id,
            aa.fecha_inicio,
            aa.fecha_fin,
            aa.hora_inicio,
            aa.hora_final,
            aa.estado,
            aa.observaciones,
            aa.novedades,
            aa.fecha_registro,
            a.nombre_ambiente
        FROM autorizaciones_ambientes aa
        JOIN ambientes a ON aa.id_ambiente = a.id
        WHERE aa.id_instructor = ?
        ORDER BY aa.fecha_registro DESC
    ");
    $stSol->bind_param('i', $instructor['id']);
    $stSol->execute();
    $solicitudes = $stSol->get_result()->fetch_all(MYSQLI_ASSOC);
    $stSol->close();

    foreach ($solicitudes as $s) {
        if ($s['estado'] === 'Pendiente')  $pendientes++;
        if ($s['estado'] === 'Aprobado')   $aprobadas++;
        if ($s['estado'] === 'Rechazado')  $rechazadas++;
    }
}

/* ══ Cargar ambientes habilitados para el select ══ */
$ambientes = [];
$stAmb = $conexion->prepare("SELECT id, nombre_ambiente FROM ambientes WHERE estado = 'Habilitado' ORDER BY nombre_ambiente");
$stAmb->execute();
$ambientes = $stAmb->get_result()->fetch_all(MYSQLI_ASSOC);
$stAmb->close();

/* ══ Procesar envío de solicitud ══ */
$msg_success = '';
$msg_error = '';

if (isset($_POST['enviar_solicitud']) && $instructor) {
    $id_ambiente = (int)($_POST['id_ambiente'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $hora_inicio = trim($_POST['hora_inicio'] ?? '');
    $hora_fin = trim($_POST['hora_fin'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if (!$id_ambiente || !$fecha_inicio || !$fecha_fin || !$hora_inicio || !$hora_fin) {
        $msg_error = 'Todos los campos son requeridos';
    } elseif ($fecha_inicio > $fecha_fin) {
        $msg_error = 'La fecha fin debe ser igual o mayor que la fecha inicio';
    } elseif ($hora_inicio >= $hora_fin) {
        $msg_error = 'La hora fin debe ser mayor que la hora inicio';
    } else {
        $stmtInsert = $conexion->prepare("
            INSERT INTO autorizaciones_ambientes 
            (id_ambiente, id_instructor, rol_autorizado, fecha_inicio, fecha_fin, hora_inicio, hora_final, estado, observaciones)
            VALUES (?, ?, 'instructor', ?, ?, ?, ?, 'Pendiente', ?)
        ");
        $stmtInsert->bind_param('iisssss', $id_ambiente, $instructor['id'], $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $observaciones);
        
        if ($stmtInsert->execute()) {
            $msg_success = 'Solicitud enviada correctamente. Estado: Pendiente';
            // Recargar solicitudes
            $stSol = $conexion->prepare("
                SELECT aa.id, aa.fecha_inicio, aa.fecha_fin, aa.hora_inicio, aa.hora_final, aa.estado, aa.observaciones, aa.novedades, aa.fecha_registro, a.nombre_ambiente
                FROM autorizaciones_ambientes aa
                JOIN ambientes a ON aa.id_ambiente = a.id
                WHERE aa.id_instructor = ?
                ORDER BY aa.fecha_registro DESC
            ");
            $stSol->bind_param('i', $instructor['id']);
            $stSol->execute();
            $solicitudes = $stSol->get_result()->fetch_all(MYSQLI_ASSOC);
            $stSol->close();
            $pendientes = $aprobadas = $rechazadas = 0;
            foreach ($solicitudes as $s) {
                if ($s['estado'] === 'Pendiente') $pendientes++;
                if ($s['estado'] === 'Aprobado') $aprobadas++;
                if ($s['estado'] === 'Rechazado') $rechazadas++;
            }
        } else {
            $msg_error = 'Error al enviar la solicitud';
        }
        $stmtInsert->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Solicitudes — Instructor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --bg:#eef0f5; --surface:#fff; --surface2:#f5f7fa; --borde:#dde1ea;
    --navy:#1b2a4a; --navy2:#243660;
    --verde:#39a900; --verde-d:#2d8600; --verde-p:#eefbe5; --verde-m:#b8e990;
    --naranja:#f59e0b; --rojo:#e74c3c; --rojo-p:#fdf0ef; --rojo-m:#f5c6c3;
    --texto:#1a2235; --sub:#5c6880;
    --radio:14px; --radio-sm:9px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--texto);min-height:100vh}

/* TOPBAR */
.topbar{background:var(--navy);height:64px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;box-shadow:0 2px 14px rgba(0,0,0,.2)}
.topbar-left{display:flex;align-items:center;gap:14px}
.logo-sena{height:38px}
.topbar-title h1{font-size:16px;font-weight:700;color:#fff}
.topbar-title span{font-size:12px;color:rgba(255,255,255,.5)}
.topbar-right{display:flex;align-items:center;gap:8px}
.btn-top{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;color:rgba(255,255,255,.8);border:1.5px solid rgba(255,255,255,.2);transition:all .18s}
.btn-top:hover{background:rgba(255,255,255,.1);color:#fff}
.btn-top.danger:hover{border-color:var(--rojo);color:var(--rojo)}
.btn-top.primary{background:var(--verde);border-color:var(--verde);color:#fff}
.btn-top.primary:hover{background:var(--verde-d)}

/* LAYOUT */
.page{max-width:980px;margin:30px auto;padding:0 18px 80px}

/* CARD */
.card{background:var(--surface);border-radius:var(--radio);border:1px solid var(--borde);box-shadow:0 4px 20px rgba(0,0,0,.07);padding:24px 28px;margin-bottom:20px;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.card-title{font-size:15px;font-weight:700;display:flex;align-items:center;gap:9px;color:var(--navy);margin-bottom:18px;padding-bottom:13px;border-bottom:2px solid var(--verde-p)}
.card-title i{color:var(--verde)}

/* CÉDULA */
.ced-row{display:flex;gap:10px}
.ced-input{flex:1;padding:12px 16px;border:1.5px solid var(--borde);border-radius:var(--radio-sm);font-size:15px;color:var(--texto);background:var(--surface2);transition:border-color .18s}
.ced-input:focus{outline:none;border-color:var(--navy2)}
.btn-buscar{padding:12px 22px;background:var(--navy2);color:#fff;border:none;border-radius:var(--radio-sm);font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .18s}
.btn-buscar:hover{background:var(--navy)}

/* ALERTAS */
.alert{border-radius:var(--radio-sm);padding:13px 16px;font-size:13.5px;display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;line-height:1.5;animation:fadeUp .3s ease}
.alert i{font-size:16px;flex-shrink:0;margin-top:1px}
.alert-error{background:var(--rojo-p);border:1.5px solid var(--rojo-m);color:#8b2117}

/* INSTRUCTOR CHIP */
.inst-chip{display:flex;align-items:center;gap:14px;background:var(--verde-p);border:1.5px solid var(--verde-m);border-radius:var(--radio);padding:14px 20px;margin-bottom:18px}
.inst-av{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--verde-d),var(--verde));display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0}
.inst-info strong{font-size:15px;color:var(--navy);display:block}
.inst-info span{font-size:13px;color:var(--sub)}

/* STATS */
.stats-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.stat-box{background:var(--surface);border:1px solid var(--borde);border-radius:var(--radio-sm);padding:16px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.stat-num{font-size:2rem;font-weight:900;line-height:1}
.stat-lbl{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--sub);margin-top:4px}
.stat-box.pend .stat-num{color:var(--naranja)}
.stat-box.apro .stat-num{color:var(--verde)}
.stat-box.rech .stat-num{color:var(--rojo)}

/* TABS */
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.tab{padding:7px 16px;border-radius:20px;border:1.5px solid var(--borde);background:var(--surface);color:var(--sub);font-size:13px;font-weight:600;cursor:pointer;transition:all .18s}
.tab:hover,.tab.active{background:var(--navy);color:#fff;border-color:var(--navy)}

/* TABLA */
.table-wrap{overflow-x:auto;border-radius:var(--radio-sm);border:1px solid var(--borde)}
table{width:100%;border-collapse:collapse}
thead{background:var(--navy)}
thead th{padding:12px 14px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.8);text-align:left;white-space:nowrap;font-weight:600}
tbody tr{border-top:1px solid var(--borde);transition:background .15s}
tbody tr:hover{background:var(--surface2)}
tbody td{padding:12px 14px;font-size:14px;vertical-align:middle}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.badge-pend{background:#fff8e1;border:1px solid #ffe082;color:#7a5f00}
.badge-apro{background:var(--verde-p);border:1px solid var(--verde-m);color:var(--verde-d)}
.badge-rech{background:var(--rojo-p);border:1px solid var(--rojo-m);color:#8b2117}

/* NOVEDADES tooltip */
.nota-cell{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--sub);font-size:13px}

/* EMPTY */
.empty-st{text-align:center;padding:52px 20px;color:var(--sub)}
.empty-st i{font-size:44px;opacity:.22;display:block;margin-bottom:14px}
.empty-st p{font-size:14px}
.empty-st a{color:var(--verde);text-decoration:none;font-weight:600}

@media(max-width:620px){.ced-row{flex-direction:column}.stats-bar{grid-template-columns:1fr 1fr}.topbar{padding:0 12px}}

/* FORMULARIO SOLICITUD */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px}
.form-group label{display:block;font-size:13px;font-weight:600;color:var(--navy);margin-bottom:6px}
.form-group select,.form-group input,.form-group textarea{width:100%;padding:10px 12px;border:1.5px solid var(--borde);border-radius:var(--radio-sm);font-size:14px;background:var(--surface2);color:var(--texto)}
.form-group select:focus,.form-group input:focus,.form-group textarea:focus{outline:none;border-color:var(--navy2)}
.form-group textarea{resize:vertical}

/* AVAILABILITY */
.avail-card{background:var(--surface);border:1px solid var(--borde);border-radius:var(--radio-sm);padding:14px 16px;margin-bottom:10px}
.avail-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.avail-header strong{color:var(--navy);font-size:14px}
.avail-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700}
.avail-badge.libre{background:var(--verde-p);color:var(--verde-d)}
.avail-badge.ocupado{background:var(--rojo-p);color:#8b2117}
.avail-dates{font-size:13px;color:var(--sub)}
.avail-dates span{display:inline-block;padding:2px 6px;margin:2px;border-radius:4px;font-size:12px}
.avail-dates .libre{background:var(--verde-p);color:var(--verde-d)}
.avail-dates .ocupado{background:var(--rojo-p);color:#8b2117}

.btn-submit{padding:12px 24px;background:var(--verde);color:#fff;border:none;border-radius:var(--radio-sm);font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .18s;width:100%}
.btn-submit:hover{background:var(--verde-d)}

.alert-success{background:var(--verde-p);border:1.5px solid var(--verde-m);color:var(--verde-d)}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <img src="../css/img/senab.png" alt="SENA" class="logo-sena">
        <div class="topbar-title">
            <h1>Mis Solicitudes</h1>
            <span>Historial de solicitudes de ambientes</span>
        </div>
    </div>
    <div class="topbar-right">
        <a href="solicitar_ambiente.php<?= $instructor ? '?cedula='.urlencode($instructor['identificacion']) : '' ?>"
           class="btn-top primary"><i class="fa-solid fa-plus"></i> Nueva</a>
        <a href="index.php" class="btn-top"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <a href="../logout.php" class="btn-top danger"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="page">

    <!-- ── BUSCAR CÉDULA ── -->
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-id-card"></i> Ingresa tu Identificación</div>
        <form method="POST">
            <div class="ced-row">
                <input type="text" name="cedula" class="ced-input"
                       placeholder="Número de cédula o identificación"
                       value="<?= htmlspecialchars($cedula_buscada) ?>"
                       autocomplete="off" required>
                <button type="submit" name="buscar_cedula" class="btn-buscar">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                </button>
            </div>
        </form>
        <?php if($error_cedula): ?>
        <div class="alert alert-error" style="margin-top:14px;">
            <i class="fa-solid fa-user-slash"></i>
            <span><?= htmlspecialchars($error_cedula) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if($instructor): ?>

    <!-- CHIP INSTRUCTOR -->
    <div class="inst-chip">
        <div class="inst-av"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="inst-info">
            <strong><?= htmlspecialchars($instructor['nombre']) ?></strong>
            <span>C.C. <?= htmlspecialchars($instructor['identificacion']) ?> &nbsp;·&nbsp; <?= count($solicitudes) ?> solicitudes registradas</span>
        </div>
        <i class="fa-solid fa-circle-check" style="color:var(--verde);font-size:22px;margin-left:auto;"></i>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-box pend">
            <div class="stat-num"><?= $pendientes ?></div>
            <div class="stat-lbl">⏳ Pendientes</div>
        </div>
        <div class="stat-box apro">
            <div class="stat-num"><?= $aprobadas ?></div>
            <div class="stat-lbl">✅ Aprobadas</div>
        </div>
        <div class="stat-box rech">
            <div class="stat-num"><?= $rechazadas ?></div>
            <div class="stat-lbl">❌ Rechazadas</div>
        </div>
    </div>

    <!-- NUEVA SOLICITUD -->
    <?php if($msg_success): ?>
    <div class="alert" style="background:var(--verde-p);border:1.5px solid var(--verde-m);color:var(--verde-d);">
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

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-plus-circle"></i> Solicitar Nuevo Ambiente</div>
        
        <form id="form-solicitud" method="POST">
            <input type="hidden" name="cedula" value="<?= htmlspecialchars($instructor['identificacion']) ?>">
            <input type="hidden" name="id_instructor" value="<?= $instructor['id'] ?>">
            <input type="hidden" name="id_ambiente" id="id_ambiente">
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fa-solid fa-building"></i> Ambiente</label>
                    <select id="select-ambiente" required onchange="loadAvailability()">
                        <option value="">-- Seleccione un ambiente --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar"></i> Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" min="<?= date('Y-m-d') ?>" required onchange="loadAvailability()">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar-check"></i> Fecha Fin</label>
                    <input type="date" id="fecha_fin" min="<?= date('Y-m-d') ?>" required onchange="loadAvailability()">
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Inicio</label>
                    <input type="time" id="hora_inicio" required onchange="loadAvailability()">
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Fin</label>
                    <input type="time" id="hora_fin" required onchange="loadAvailability()">
                </div>
            </div>

            <div id="availability-result" style="margin: 16px 0;"></div>

            <div class="form-group">
                <label><i class="fa-solid fa-comment"></i> Observaciones</label>
                <textarea id="observaciones" rows="2" placeholder="Observaciones..."></textarea>
            </div>

            <button type="submit" class="btn-buscar" style="width:100%;">
                <i class="fa-solid fa-paper-plane"></i> Enviar Solicitud
            </button>
        </form>
    </div>

    <!-- TABS FILTRO -->
    <?php if(count($solicitudes) > 0): ?>
    <div class="tabs">
        <button class="tab active" onclick="filtrar('todas',this)">Todas (<?= count($solicitudes) ?>)</button>
        <button class="tab" onclick="filtrar('Pendiente',this)">Pendientes (<?= $pendientes ?>)</button>
        <button class="tab" onclick="filtrar('Aprobado',this)">Aprobadas (<?= $aprobadas ?>)</button>
        <button class="tab" onclick="filtrar('Rechazado',this)">Rechazadas (<?= $rechazadas ?>)</button>
    </div>
    <?php endif; ?>

    <!-- TABLA -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><i class="fa-solid fa-building"></i> Ambiente</th>
                    <th><i class="fa-regular fa-calendar"></i> Fecha inicio</th>
                    <th><i class="fa-regular fa-calendar-check"></i> Fecha fin</th>
                    <th><i class="fa-regular fa-clock"></i> Horario</th>
                    <th><i class="fa-solid fa-circle-dot"></i> Estado</th>
                    <th><i class="fa-solid fa-comment"></i> Novedades</th>
                    <th><i class="fa-solid fa-calendar-plus"></i> Enviada</th>
                </tr>
            </thead>
            <tbody id="tbody-sol">
                <?php if(empty($solicitudes)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-st">
                            <i class="fa-solid fa-inbox"></i>
                            <p>Aún no tienes solicitudes registradas.<br>
                               <a href="solicitar_ambiente.php?cedula=<?= urlencode($instructor['identificacion']) ?>">Crea tu primera solicitud →</a>
                            </p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($solicitudes as $idx => $s): ?>
                <tr data-estado="<?= $s['estado'] ?>">
                    <td style="color:var(--sub);font-size:13px;"><?= $idx+1 ?></td>
                    <td><strong><?= htmlspecialchars($s['nombre_ambiente']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($s['fecha_inicio'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($s['fecha_fin'])) ?></td>
                    <td style="white-space:nowrap;">
                        <?= date('h:i A', strtotime($s['hora_inicio'])) ?>
                        <span style="color:var(--sub);">–</span>
                        <?= date('h:i A', strtotime($s['hora_final'])) ?>
                    </td>
                    <td>
                        <?php if($s['estado']==='Pendiente'): ?>
                            <span class="badge badge-pend"><i class="fa-solid fa-hourglass-half"></i> Pendiente</span>
                        <?php elseif($s['estado']==='Aprobado'): ?>
                            <span class="badge badge-apro"><i class="fa-solid fa-check"></i> Aprobado</span>
                        <?php else: ?>
                            <span class="badge badge-rech"><i class="fa-solid fa-xmark"></i> Rechazado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($s['novedades'])): ?>
                            <span class="nota-cell" title="<?= htmlspecialchars($s['novedades']) ?>">
                                <?= htmlspecialchars($s['novedades']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#bbb;font-size:13px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--sub);font-size:13px;white-space:nowrap;">
                        <?= date('d/m/Y H:i', strtotime($s['fecha_registro'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- Sin instructor aún -->
    <?php if(empty($cedula_buscada)): ?>
    <div class="card">
        <div class="empty-st">
            <i class="fa-solid fa-id-badge"></i>
            <p>Ingresa tu número de identificación para ver tu historial de solicitudes.</p>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
function filtrar(estado, btn) {
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#tbody-sol tr[data-estado]').forEach(tr => {
        tr.style.display = (estado === 'todas' || tr.dataset.estado === estado) ? '' : 'none';
    });
}

// Cargar ambientes al iniciar
document.addEventListener('DOMContentLoaded', function() {
    fetch('get_disponibilidad_ajax.php?load_ambientes=1')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('select-ambiente');
            data.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre_ambiente;
                sel.appendChild(opt);
            });
        });
});

async function loadAvailability() {
    const id_ambiente = document.getElementById('select-ambiente').value;
    const fecha_inicio = document.getElementById('fecha_inicio').value;
    const fecha_fin = document.getElementById('fecha_fin').value;
    const hora_inicio = document.getElementById('hora_inicio').value;
    const hora_fin = document.getElementById('hora_fin').value;
    const container = document.getElementById('availability-result');
    
    if (!id_ambiente || !fecha_inicio || !fecha_fin || !hora_inicio || !hora_fin) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--sub);"><i class="fa-solid fa-spinner fa-spin"></i> Verificando disponibilidad...</div>';
    
    const params = new URLSearchParams({
        fecha: fecha_inicio,
        hora_ini: hora_inicio,
        hora_fin: hora_fin
    });
    
    try {
        const res = await fetch('get_disponibilidad_ajax.php?' + params);
        const data = await res.json();
        
        if (data.error) {
            container.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> ' + data.error + '</div>';
            return;
        }
        
        const amb = data.find(x => x.id == id_ambiente);
        if (!amb) {
            container.innerHTML = '<div class="alert alert-error">Ambiente no encontrado</div>';
            return;
        }
        
        document.getElementById('id_ambiente').value = id_ambiente;
        
        const badgeClass = amb.libre ? 'libre' : 'ocupado';
        const badgeText = amb.libre ? '✓ Disponible' : '⚠ Con conflictos';
        
        let html = '<div class="avail-card">';
        html += '<div class="avail-header">';
        html += '<strong>' + amb.nombre_ambiente + '</strong>';
        html += '<span class="avail-badge ' + badgeClass + '">' + badgeText + '</span>';
        html += '</div>';
        
        if (amb.fechas_libres.length > 0) {
            html += '<div class="avail-dates">Fechas disponibles: ';
            amb.fechas_libres.slice(0, 5).forEach(f => {
                html += '<span class="libre">' + f + '</span>';
            });
            if (amb.fechas_libres.length > 5) html += ' ...';
            html += '</div>';
        }
        
        if (amb.conflictos.length > 0) {
            html += '<div class="avail-dates" style="margin-top:8px;">Conflictos: ';
            amb.conflictos.slice(0, 3).forEach(c => {
                html += '<span class="ocupado">' + c.fecha + ' ' + c.hora_ini + '-' + c.hora_fin + ' (' + c.instructor + ')</span>';
            });
            if (amb.conflictos.length > 3) html += ' ...';
            html += '</div>';
        }
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (e) {
        container.innerHTML = '<div class="alert alert-error">Error al verificar disponibilidad</div>';
    }
}

// Enviar solicitud
document.getElementById('form-solicitud').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const id_ambiente = document.getElementById('id_ambiente').value;
    const fecha_inicio = document.getElementById('fecha_inicio').value;
    const fecha_fin = document.getElementById('fecha_fin').value;
    const hora_inicio = document.getElementById('hora_inicio').value;
    const hora_fin = document.getElementById('hora_fin').value;
    const observaciones = document.getElementById('observaciones').value;
    
    if (!id_ambiente) {
        alert('Seleccione un ambiente y verifique disponibilidad');
        return;
    }
    
    const formData = new FormData();
    formData.append('enviar_solicitud', '1');
    formData.append('id_ambiente', id_ambiente);
    formData.append('fecha_inicio', fecha_inicio);
    formData.append('fecha_fin', fecha_fin);
    formData.append('hora_inicio', hora_inicio);
    formData.append('hora_fin', hora_fin);
    formData.append('observaciones', observaciones);
    formData.append('cedula', '<?= $instructor["identificacion"] ?>');
    
    try {
        const res = await fetch('', { method: 'POST', body: formData });
        const html = await res.text();
        
        if (html.includes('msg_success')) {
            location.reload();
        } else {
            alert('Error al enviar la solicitud');
        }
    } catch (e) {
        alert('Error al enviar la solicitud');
    }
});
</script>
</body>
</html>