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
$solicitudes_raw = [];
$grupos          = [];
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
        ORDER BY aa.fecha_registro DESC, aa.fecha_inicio ASC
    ");
    $stSol->bind_param('i', $instructor['id']);
    $stSol->execute();
    $solicitudes_raw = $stSol->get_result()->fetch_all(MYSQLI_ASSOC);
    $stSol->close();

    foreach ($solicitudes_raw as $row) {
        $clave = $row['nombre_ambiente'] . '_'
               . $row['hora_inicio']     . '_'
               . $row['hora_final']      . '_'
               . $row['estado']          . '_'
               . date('Ymd', strtotime($row['fecha_registro']));

        if (!isset($grupos[$clave])) {
            $grupos[$clave] = [
                'tipo'           => 'unico',
                'nombre_ambiente'=> $row['nombre_ambiente'],
                'hora_inicio'    => $row['hora_inicio'],
                'hora_final'     => $row['hora_final'],
                'estado'         => $row['estado'],
                'observaciones'  => $row['observaciones'],
                'novedades'      => $row['novedades'],
                'fecha_registro' => $row['fecha_registro'],
                'fechas'         => [],
                'ids'            => [],
            ];
        }
        $grupos[$clave]['fechas'][] = $row['fecha_inicio'];
        $grupos[$clave]['ids'][]    = $row['id'];
        if (empty($grupos[$clave]['novedades']) && !empty($row['novedades']))
            $grupos[$clave]['novedades'] = $row['novedades'];
    }

    foreach ($grupos as &$g) {
        sort($g['fechas']);
        if (count($g['ids']) > 1) $g['tipo'] = 'recurrente';
        if ($g['estado'] === 'Pendiente')  $pendientes++;
        if ($g['estado'] === 'Aprobado')   $aprobadas++;
        if ($g['estado'] === 'Rechazado')  $rechazadas++;
    }
    unset($g);
}

function getDiasLabel(array $fechas): array {
    $dias_num = [];
    $map = [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'];
    foreach ($fechas as $f) {
        $n = (int)(new DateTime($f))->format('N');
        $dias_num[$n] = true;
    }
    ksort($dias_num);
    return array_map(fn($n) => $map[$n], array_keys($dias_num));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Solicitudes — Instructor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* ══ VARIABLES ══ */
:root {
    --bg:        #eef1f7;
    --surface:   #ffffff;
    --surface2:  #f5f7fb;
    --border:    #dde3ef;
    --border2:   #c5cfe4;
    --navy:      #1b2a4a;
    --navy2:     #243560;
    --green:     #0d8a5e;
    --green-lt:  #e8f7f1;
    --green-mid: #9dd6be;
    --amber:     #c47a0e;
    --amber-lt:  #fef5e7;
    --amber-mid: #f5c660;
    --red:       #b03030;
    --red-lt:    #fdf0f0;
    --red-mid:   #f5b5b5;
    --text:      #1b2a4a;
    --muted:     #6b7c9e;
    --r:         14px;
    --sh:        0 2px 18px rgba(27,42,74,0.08);
    --sh-lg:     0 8px 36px rgba(27,42,74,0.13);
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior: smooth; }
body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh;
       background-image: radial-gradient(ellipse 700px 350px at 100% 0%, rgba(13,138,94,0.05) 0%, transparent 70%); }

/* ══ TOPBAR — más alto ══ */
.topbar {
    background:var(--navy); height:92px; padding:0 2rem;
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:200;
    box-shadow:0 3px 20px rgba(0,0,0,0.26);
}
.topbar-left { display:flex; align-items:center; gap:16px; }
.logo-sena   { height:40px; }
.topbar-divider {
    width: 1.5px; height: 38px;
    background: rgba(255,255,255,0.15);
}
.topbar-title h1  { font-size:20px; font-weight:700; color:#fff; letter-spacing:-.01em; }
.topbar-title span{ font-size:12px; color:rgba(255,255,255,0.42); margin-top:2px; display:block; }
.topbar-right { display:flex; align-items:center; gap:8px; }

.btn-top {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 16px; border-radius:9px; font-size:13px; font-weight:600;
    font-family:inherit; text-decoration:none; border:none; cursor:pointer;
    transition:all .15s; white-space: nowrap;
}
.btn-ghost { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); color:rgba(255,255,255,.75); }
.btn-ghost:hover { background:rgba(255,255,255,.13); color:#fff; }
.btn-green { background:var(--green); color:#fff; border:1px solid var(--green); }
.btn-green:hover { background:#0a7050; }
.btn-icon { width:38px; height:38px; padding:0; justify-content:center;
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:rgba(255,255,255,.55); border-radius:9px; }
.btn-icon:hover { background:rgba(176,48,48,.22); border-color:rgba(176,48,48,.4); color:#f87171; }

/* ══ PAGE ══ */
.page { max-width:980px; margin:0 auto; padding:2.2rem 1.5rem 5rem; }

/* ══ CARD ══ */
.card {
    background:var(--surface); border-radius:var(--r);
    border:1.5px solid var(--border); box-shadow:var(--sh);
    padding:22px 26px; margin-bottom:18px;
    animation:fadeUp .3s ease;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
.card-title {
    font-size:14.5px; font-weight:700; color:var(--navy);
    display:flex; align-items:center; gap:9px;
    padding-bottom:13px; border-bottom:2px solid var(--green-lt); margin-bottom:18px;
}
.card-title i { color:var(--green); }

/* ══ BUSCAR CÉDULA ══ */
.ced-row { display:flex; gap:10px; }
.ced-input {
    flex:1; padding:11px 15px; border:1.5px solid var(--border);
    border-radius:10px; font-size:15px; font-family:inherit;
    background:var(--surface2); color:var(--text); transition:border-color .15s;
    min-width: 0;
}
.ced-input:focus { outline:none; border-color:var(--navy2); background:#fff; }
.btn-buscar {
    padding:11px 22px; background:var(--navy2); color:#fff; border:none;
    border-radius:10px; font-size:14px; font-weight:700; font-family:inherit;
    cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background .15s;
    white-space: nowrap; flex-shrink: 0;
}
.btn-buscar:hover { background:var(--navy); }

/* ══ ALERTAS ══ */
.alert {
    border-radius:10px; padding:12px 16px; font-size:13.5px;
    display:flex; align-items:center; gap:10px; margin-bottom:14px; font-weight:500;
}
.alert-ok  { background:var(--green-lt); border:1.5px solid var(--green-mid); color:var(--green); }
.alert-err { background:var(--red-lt);   border:1.5px solid var(--red-mid);   color:var(--red);   }

/* ══ INSTRUCTOR CHIP ══ */
.inst-chip {
    display:flex; align-items:center; gap:14px;
    background:var(--green-lt); border:1.5px solid var(--green-mid);
    border-radius:var(--r); padding:14px 20px; margin-bottom:18px;
    flex-wrap: wrap;
}
.inst-av {
    width:46px; height:46px; border-radius:50%;
    background:linear-gradient(135deg,#0a7050,var(--green));
    color:#fff; font-size:17px; display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.inst-info { min-width: 0; flex: 1; }
.inst-info strong { font-size:15px; color:var(--navy); display:block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inst-info span   { font-size:13px; color:var(--muted); }

/* ══ STATS ══ */
.stats-bar {
    display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px;
}
.stat-box {
    background:var(--surface); border:1.5px solid var(--border);
    border-radius:12px; padding:16px 18px; text-align:center; box-shadow:var(--sh);
}
.stat-num { font-size:2rem; font-weight:900; line-height:1; font-family:'DM Mono',monospace; }
.stat-lbl { font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-top:4px; }
.stat-box.pend .stat-num { color:var(--amber); }
.stat-box.apro .stat-num { color:var(--green); }
.stat-box.rech .stat-num { color:var(--red);   }

/* ══ TABS ══ */
.tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.tab {
    padding:7px 16px; border-radius:20px; border:1.5px solid var(--border);
    background:var(--surface); color:var(--muted); font-size:13px; font-weight:600;
    cursor:pointer; font-family:inherit; transition:all .15s;
}
.tab:hover,.tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }

/* ══ LISTA SOLICITUDES ══ */
.solicitudes-list { display:flex; flex-direction:column; gap:14px; }

.sol-card {
    background:var(--surface); border:1.5px solid var(--border);
    border-radius:16px; box-shadow:var(--sh); overflow:hidden;
    animation:fadeUp .3s ease both; transition:border-color .18s, box-shadow .18s;
}
.sol-card:hover { border-color:var(--border2); box-shadow:var(--sh-lg); }

.sol-inner { display:flex; }
.sol-stripe { width:5px; flex-shrink:0; background:linear-gradient(180deg,var(--amber),#e8a030); }
.sol-stripe.apro { background:linear-gradient(180deg,var(--green),#0a9c72); }
.sol-stripe.rech { background:linear-gradient(180deg,var(--red),#c04040); }
.sol-stripe.rec  { background:linear-gradient(180deg,#6b3fa0,#9b5ccc); }

.sol-body { flex:1; padding:1.3rem 1.5rem; min-width:0; }

.sol-head {
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:10px; flex-wrap:wrap; margin-bottom:1rem;
}
.amb-name { font-size:15.5px; font-weight:700; color:var(--navy); display:flex; align-items:center; gap:8px; }
.amb-name i { color:var(--green); font-size:13px; flex-shrink:0; }

.badges { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
.badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:100px; font-size:12px; font-weight:700; white-space:nowrap;
}
.badge-pend { background:var(--amber-lt); border:1.5px solid var(--amber-mid); color:var(--amber); }
.badge-apro { background:var(--green-lt); border:1.5px solid var(--green-mid); color:var(--green); }
.badge-rech { background:var(--red-lt);   border:1.5px solid var(--red-mid);   color:var(--red);   }
.badge-rec  { background:#f3eeff;         border:1.5px solid #d4b8ff;          color:#6b3fa0;      }

/* ══ INFO GRID ══ */
.info-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
    gap:8px; margin-bottom:.9rem;
}
.info-cell { background:var(--surface2); border:1px solid var(--border); border-radius:9px; padding:9px 12px; }
.info-lbl  { font-size:10px; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); font-weight:600; margin-bottom:3px; }
.info-val  { font-size:13px; font-weight:700; color:var(--text); font-family:'DM Mono',monospace; }
.info-val .ic { color:var(--green); font-size:10px; margin-right:4px; font-family:'DM Sans',sans-serif; }

/* ══ DÍAS CHIPS ══ */
.dias-row { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:.9rem; }
.dia-chip {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 11px; border-radius:8px; font-size:12px; font-weight:700;
    background:var(--surface2); border:1.5px solid var(--border); color:var(--navy);
}
.dia-chip.active { background:#f3eeff; border-color:#d4b8ff; color:#6b3fa0; }

/* ══ FECHAS TOGGLE ══ */
.rec-toggle {
    display:inline-flex; align-items:center; gap:7px;
    background:var(--green-lt); border:1.5px solid var(--green-mid);
    color:var(--green); border-radius:9px; padding:6px 13px;
    font-size:12.5px; font-weight:600; font-family:inherit;
    cursor:pointer; margin-bottom:.85rem; transition:all .15s;
}
.rec-toggle:hover { background:#c4e8d8; }
.rec-toggle i { transition:transform .2s; }
.rec-toggle.open i { transform:rotate(90deg); }

.fechas-list { display:none; flex-wrap:wrap; gap:6px; margin-bottom:.85rem; }
.fechas-list.open { display:flex; animation:fadeIn .2s; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.fecha-chip {
    display:inline-flex; align-items:center; gap:5px;
    background:var(--surface2); border:1px solid var(--border);
    border-radius:7px; padding:4px 10px;
    font-size:12px; font-weight:600; color:var(--navy);
    font-family:'DM Mono',monospace;
}

/* ══ NOVEDADES ══ */
.nov-block {
    background:#fffdf5; border-left:3px solid var(--amber);
    border-radius:0 8px 8px 0; padding:9px 13px;
    font-size:13px; color:var(--muted); margin-bottom:.85rem;
}
.nov-block strong { color:var(--amber); font-size:11px; text-transform:uppercase; letter-spacing:.05em; display:block; margin-bottom:2px; }

/* ══ FOOTER SOLICITUD ══ */
.sol-foot {
    display:flex; align-items:center; justify-content:space-between;
    padding-top:.9rem; border-top:1px solid var(--border);
    font-size:12px; color:var(--muted); gap:8px; flex-wrap:wrap;
}
.sol-foot i { margin-right:4px; }

/* ══ EMPTY STATE ══ */
.empty-st { text-align:center; padding:4rem 2rem; color:var(--muted); }
.empty-st i { font-size:3rem; opacity:.2; display:block; margin-bottom:1rem; }
.empty-st p { font-size:14px; }
.empty-st a { color:var(--green); text-decoration:none; font-weight:700; }

/* ══ RESPONSIVE ══════════════════════════════════════════ */
@media (max-width: 768px) {
    .topbar {
        height: 68px;
        padding: 0 16px;
    }
    .logo-sena { height: 34px; }
    .topbar-title h1 { font-size: 14px; }
    .topbar-title span { font-size: 11px; }
    .topbar-divider { display: none; }

    /* Ocultar texto en botones de nav en tablet */
    .btn-top.btn-ghost .btn-lbl { display: none; }

    .page { padding: 1.4rem 1rem 4rem; }
    .card { padding: 18px 16px; }

    .ced-row { flex-direction: column; }
    .btn-buscar { width: 100%; justify-content: center; }

    .stats-bar { gap: 10px; }
    .stat-num { font-size: 1.6rem; }
    .stat-lbl { font-size: 10.5px; }
    .stat-box { padding: 12px 14px; }

    .tabs { gap: 6px; }
    .tab { padding: 6px 12px; font-size: 12px; }

    .sol-body { padding: 1rem 1.1rem; }
    .amb-name { font-size: 14px; }
    .info-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
    .info-cell { padding: 8px 10px; }
    .info-val  { font-size: 12px; }
}

@media (max-width: 480px) {
    .topbar { height: 62px; padding: 0 12px; }
    .logo-sena { height: 30px; }
    .topbar-title h1 { font-size: 13px; }
    .topbar-title span { display: none; }

    /* Solo iconos en mobile */
    .btn-top:not(.btn-icon) .fa-solid,
    .btn-top:not(.btn-icon) .fa-regular { margin-right: 0; }
    .btn-top:not(.btn-icon) { padding: 7px 10px; }
    .btn-top-lbl { display: none; }

    .stats-bar { grid-template-columns: 1fr 1fr; }
    /* Ocultar rechazadas stat si no caben */

    .inst-chip { padding: 12px 14px; gap: 10px; }
    .inst-av { width: 38px; height: 38px; font-size: 15px; }
    .inst-info strong { font-size: 14px; }

    .sol-card { border-radius: 12px; }
    .sol-body { padding: .9rem 1rem; }
    .amb-name { font-size: 13.5px; }
    .badge { font-size: 11px; padding: 3px 9px; }

    .info-grid { grid-template-columns: 1fr 1fr; }
    .info-lbl  { font-size: 9.5px; }
    .info-val  { font-size: 11.5px; }

    .dias-row { gap: 4px; }
    .dia-chip { padding: 3px 8px; font-size: 11px; }

    .sol-foot { font-size: 11px; }

    .rec-toggle { font-size: 12px; padding: 5px 11px; }
    .fecha-chip { font-size: 11px; padding: 3px 8px; }

    .tabs { gap: 5px; }
    .tab  { padding: 6px 10px; font-size: 11.5px; }
}

@media (max-width: 360px) {
    .stats-bar { grid-template-columns: 1fr; }
    .stat-box  { padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; }
    .stat-num  { font-size: 1.4rem; }
    .stat-lbl  { font-size: 10px; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <img src="../css/img/senab.png" alt="SENA" class="logo-sena">
        <div class="topbar-divider"></div>
        <div class="topbar-title">
            <h1>Mis Solicitudes</h1>
            <span>Historial de solicitudes de ambientes</span>
        </div>
    </div>
    <div class="topbar-right">
        <a href="solicitar_ambiente.php<?= $instructor ? '?cedula='.urlencode($instructor['identificacion']) : '' ?>"
           class="btn-top btn-green">
            <i class="fa-solid fa-plus"></i>
            <span class="btn-top-lbl">Nueva</span>
        </a>
        <a href="index.php" class="btn-top btn-ghost">
            <i class="fa-solid fa-arrow-left"></i>
            <span class="btn-top-lbl">Volver</span>
        </a>
        <a href="../logout.php" class="btn-top btn-icon" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="page">

    <!-- BUSCAR CÉDULA -->
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
        <?php if ($error_cedula): ?>
        <div class="alert alert-err" style="margin-top:14px;">
            <i class="fa-solid fa-user-slash"></i>
            <?= htmlspecialchars($error_cedula) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($instructor): ?>

    <!-- CHIP INSTRUCTOR -->
    <div class="inst-chip">
        <div class="inst-av"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div class="inst-info">
            <strong><?= htmlspecialchars($instructor['nombre']) ?></strong>
            <span>C.C. <?= htmlspecialchars($instructor['identificacion']) ?>
                  &nbsp;·&nbsp; <?= count($grupos) ?> solicitud<?= count($grupos) !== 1 ? 'es' : '' ?> registrada<?= count($grupos) !== 1 ? 's' : '' ?></span>
        </div>
        <i class="fa-solid fa-circle-check" style="color:var(--green);font-size:22px;margin-left:auto;flex-shrink:0;"></i>
    </div>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-box pend">
            <div class="stat-num"><?= $pendientes ?></div>
            <div class="stat-lbl">Pendientes</div>
        </div>
        <div class="stat-box apro">
            <div class="stat-num"><?= $aprobadas ?></div>
            <div class="stat-lbl">Aprobadas</div>
        </div>
        <div class="stat-box rech">
            <div class="stat-num"><?= $rechazadas ?></div>
            <div class="stat-lbl">Rechazadas</div>
        </div>
    </div>

    <!-- TABS -->
    <?php if (count($grupos) > 0): ?>
    <div class="tabs">
        <button class="tab active" onclick="filtrar('todas',this)">Todas (<?= count($grupos) ?>)</button>
        <button class="tab" onclick="filtrar('Pendiente',this)">Pendientes (<?= $pendientes ?>)</button>
        <button class="tab" onclick="filtrar('Aprobado',this)">Aprobadas (<?= $aprobadas ?>)</button>
        <button class="tab" onclick="filtrar('Rechazado',this)">Rechazadas (<?= $rechazadas ?>)</button>
    </div>
    <?php endif; ?>

    <!-- LISTA -->
    <div class="solicitudes-list" id="sol-list">

        <?php if (empty($grupos)): ?>
        <div class="empty-st">
            <i class="fa-solid fa-inbox"></i>
            <p>Aún no tienes solicitudes registradas.<br>
               <a href="solicitar_ambiente.php?cedula=<?= urlencode($instructor['identificacion']) ?>">Crea tu primera solicitud →</a>
            </p>
        </div>
        <?php else: ?>

        <?php
        $gi = 0;
        foreach ($grupos as $clave => $g):
            $gi++;
            $esRec      = ($g['tipo'] === 'recurrente');
            $estado     = $g['estado'];
            $primerFecha= date('d/m/Y', strtotime($g['fechas'][0]));
            $ultimaFecha= date('d/m/Y', strtotime(end($g['fechas'])));
            $hIni       = substr($g['hora_inicio'], 0, 5);
            $hFin       = substr($g['hora_final'],  0, 5);
            $fechaReg   = date('d/m/Y H:i', strtotime($g['fecha_registro']));
            $diasLabels = getDiasLabel($g['fechas']);

            $stripeClass = match($estado) {
                'Aprobado'  => 'apro',
                'Rechazado' => 'rech',
                default     => ($esRec ? 'rec' : ''),
            };

            $badgeEstado = match($estado) {
                'Aprobado'  => '<span class="badge badge-apro"><i class="fa-solid fa-check"></i> Aprobado</span>',
                'Rechazado' => '<span class="badge badge-rech"><i class="fa-solid fa-xmark"></i> Rechazado</span>',
                default     => '<span class="badge badge-pend"><i class="fa-solid fa-hourglass-half"></i> Pendiente</span>',
            };

            $TODOS_DIAS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
        ?>
        <div class="sol-card" data-estado="<?= $estado ?>" style="animation-delay:<?= $gi * 55 ?>ms">
            <div class="sol-inner">
                <div class="sol-stripe <?= $stripeClass ?>"></div>
                <div class="sol-body">

                    <div class="sol-head">
                        <div class="amb-name">
                            <i class="fa-solid fa-door-open"></i>
                            <?= htmlspecialchars($g['nombre_ambiente']) ?>
                        </div>
                        <div class="badges">
                            <?php if ($esRec): ?>
                            <span class="badge badge-rec">
                                <i class="fa-solid fa-arrows-rotate"></i>
                                Recurrente · <?= count($g['ids']) ?> ses.
                            </span>
                            <?php endif; ?>
                            <?= $badgeEstado ?>
                        </div>
                    </div>

                    <div class="info-grid">
                        <?php if (!$esRec): ?>
                        <div class="info-cell">
                            <div class="info-lbl">Fecha</div>
                            <div class="info-val"><i class="ic fa-regular fa-calendar"></i><?= $primerFecha ?></div>
                        </div>
                        <?php else: ?>
                        <div class="info-cell">
                            <div class="info-lbl">Desde</div>
                            <div class="info-val"><i class="ic fa-regular fa-calendar"></i><?= $primerFecha ?></div>
                        </div>
                        <div class="info-cell">
                            <div class="info-lbl">Hasta</div>
                            <div class="info-val"><i class="ic fa-regular fa-calendar-check"></i><?= $ultimaFecha ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-cell">
                            <div class="info-lbl">Hora inicio</div>
                            <div class="info-val"><i class="ic fa-regular fa-clock"></i><?= $hIni ?></div>
                        </div>
                        <div class="info-cell">
                            <div class="info-lbl">Hora fin</div>
                            <div class="info-val"><i class="ic fa-regular fa-clock"></i><?= $hFin ?></div>
                        </div>
                        <?php if ($esRec): ?>
                        <div class="info-cell">
                            <div class="info-lbl">Sesiones</div>
                            <div class="info-val"><i class="ic fa-solid fa-layer-group"></i><?= count($g['ids']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($esRec): ?>
                    <div class="dias-row">
                        <?php foreach ($TODOS_DIAS as $d): ?>
                        <span class="dia-chip <?= in_array($d, $diasLabels) ? 'active' : '' ?>">
                            <?php if (in_array($d, $diasLabels)): ?>
                            <i class="fa-solid fa-circle-dot" style="font-size:8px;"></i>
                            <?php endif; ?>
                            <?= $d ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($esRec): ?>
                    <button class="rec-toggle" onclick="toggleFechas(this,'fechas-inst-<?= $gi ?>')">
                        <i class="fa-solid fa-chevron-right"></i>
                        Ver <?= count($g['fechas']) ?> fechas específicas
                    </button>
                    <div class="fechas-list" id="fechas-inst-<?= $gi ?>">
                        <?php foreach ($g['fechas'] as $f): ?>
                        <span class="fecha-chip">
                            <i class="fa-regular fa-calendar" style="color:var(--green);"></i>
                            <?= date('d/m/Y', strtotime($f)) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($g['novedades'])): ?>
                    <div class="nov-block">
                        <strong><i class="fa-solid fa-comment-dots"></i> Novedad</strong>
                        <?= htmlspecialchars($g['novedades']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($g['observaciones'])): ?>
                    <div class="nov-block" style="border-color:var(--green);background:#f6fdf9;">
                        <strong style="color:var(--green);"><i class="fa-solid fa-align-left"></i> Observaciones</strong>
                        <?= htmlspecialchars($g['observaciones']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="sol-foot">
                        <span><i class="fa-regular fa-paper-plane"></i> Enviado el <?= $fechaReg ?></span>
                        <?php if (!$esRec): ?>
                        <span><i class="fa-regular fa-calendar"></i> <?= $primerFecha ?></span>
                        <?php else: ?>
                        <span><i class="fa-solid fa-arrows-rotate"></i> <?= implode(', ', $diasLabels) ?></span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <?php else: ?>
    <?php if (empty($cedula_buscada)): ?>
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
    document.querySelectorAll('#sol-list .sol-card').forEach(card => {
        card.style.display = (estado === 'todas' || card.dataset.estado === estado) ? '' : 'none';
    });
}

function toggleFechas(btn, id) {
    const list = document.getElementById(id);
    list.classList.toggle('open');
    btn.classList.toggle('open');
    const n = list.children.length;
    btn.childNodes[btn.childNodes.length - 1].textContent =
        list.classList.contains('open') ? ' Ocultar fechas' : ` Ver ${n} fechas específicas`;
}
</script>
</body>
</html>