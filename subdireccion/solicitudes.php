<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

$query = "
    SELECT aa.id, aa.fecha_inicio, aa.fecha_fin, aa.hora_inicio, aa.hora_final,
           aa.estado, aa.fecha_registro, aa.observaciones,
           i.nombre AS nombre_instructor, a.nombre_ambiente,
           aa.id_instructor, aa.id_ambiente
    FROM autorizaciones_ambientes aa
    INNER JOIN instructores i ON aa.id_instructor = i.id
    INNER JOIN ambientes    a ON aa.id_ambiente   = a.id
    WHERE aa.estado = 'Pendiente'
    ORDER BY aa.fecha_registro DESC, aa.fecha_inicio ASC
";
$res = mysqli_query($conexion, $query);

$grupos = []; $key_map = [];
while ($row = mysqli_fetch_assoc($res)) {
    $clave = $row['id_instructor'].'_'.$row['id_ambiente'].'_'
           . $row['hora_inicio'].'_'.$row['hora_final']
           . '_'.date('Ymd', strtotime($row['fecha_registro']));
    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'tipo'=>'unico','nombre_instructor'=>$row['nombre_instructor'],
            'nombre_ambiente'=>$row['nombre_ambiente'],
            'hora_inicio'=>$row['hora_inicio'],'hora_final'=>$row['hora_final'],
            'observaciones'=>$row['observaciones'],'fecha_registro'=>$row['fecha_registro'],
            'ids'=>[],'fechas'=>[],
        ];
    }
    $grupos[$clave]['ids'][]    = $row['id'];
    $grupos[$clave]['fechas'][] = $row['fecha_inicio'];
    $key_map[$row['id']]        = $clave;
}
foreach ($grupos as &$g) {
    if (count($g['ids']) > 1) $g['tipo'] = 'recurrente';
    sort($g['fechas']);
}
unset($g);
$total_grupos = count($grupos);

function getDiasActivos(array $fechas): array {
    $found = [];
    foreach ($fechas as $f) {
        $n = (int)(new DateTime($f))->format('N');
        $found[$n] = true;
    }
    ksort($found);
    return $found;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes Pendientes — SENA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--bg:#f0f2f8;--surface:#fff;--surface2:#f7f8fc;--border:#e2e6f0;--border2:#c8d0e8;--navy:#1b2a4a;--navy2:#243560;--teal:#0d7f6e;--teal-lt:#e6f5f3;--teal-mid:#9dd4cc;--amber:#c97d10;--amber-lt:#fef6e7;--amber-mid:#f5c96a;--purple:#6b3fa0;--purple-lt:#f3eeff;--purple-mid:#d4b8ff;--red:#b33030;--red-lt:#fdf0f0;--red-mid:#f5b8b8;--text:#1b2a4a;--muted:#6b7c9e;--r:14px;--sh:0 2px 20px rgba(27,42,74,0.08);--sh-lg:0 8px 40px rgba(27,42,74,0.14)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;background-image:radial-gradient(ellipse 800px 400px at 0% 0%,rgba(13,127,110,.06) 0%,transparent 70%),radial-gradient(ellipse 600px 600px at 100% 100%,rgba(27,42,74,.06) 0%,transparent 70%)}

.hdr{background:var(--navy);height:62px;display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:100;box-shadow:0 1px 0 rgba(255,255,255,.07),0 4px 20px rgba(0,0,0,.22)}
.hdr-left{display:flex;align-items:center;gap:14px}
.hdr-logo{height:36px}
.hdr-title h1{font-size:15px;font-weight:700;color:#fff;letter-spacing:-.01em}
.hdr-title p{font-size:11.5px;color:rgba(255,255,255,.4)}
.hdr-right{display:flex;align-items:center;gap:10px}
.btn-hdr{display:inline-flex;align-items:center;gap:7px;padding:7px 15px;border-radius:9px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;text-decoration:none;border:none;transition:all .15s}
.btn-hdr-ghost{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);color:rgba(255,255,255,.7)}
.btn-hdr-ghost:hover{background:rgba(255,255,255,.13);color:#fff}
.btn-hdr-icon{width:36px;height:36px;padding:0;justify-content:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.55);border-radius:9px}
.btn-hdr-icon:hover{background:rgba(179,48,48,.22);border-color:rgba(179,48,48,.4);color:#f87171}

.wrap{max-width:980px;margin:0 auto;padding:2.2rem 1.5rem 5rem}

.page-hero{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-hero-title{display:flex;align-items:center;gap:14px}
.hero-icon{width:52px;height:52px;border-radius:14px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 16px rgba(27,42,74,.22)}
.hero-text h2{font-size:1.55rem;font-weight:700;color:var(--navy);letter-spacing:-.02em}
.hero-text p{font-size:14px;color:var(--muted);margin-top:2px}
.pill{display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:100px;font-size:13px;font-weight:700}
.pill-amber{background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber-mid)}
.pill-teal{background:var(--teal-lt);color:var(--teal);border:1.5px solid var(--teal-mid)}

.refresh-row{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted);margin-bottom:1.6rem}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--teal);animation:livepulse 2s infinite}
@keyframes livepulse{0%,100%{opacity:1}50%{opacity:.25}}

.alert{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:var(--r);font-size:14px;margin-bottom:1.4rem;font-weight:500}
.alert-ok{background:var(--teal-lt);border:1.5px solid var(--teal-mid);color:var(--teal)}
.alert-err{background:var(--red-lt);border:1.5px solid var(--red-mid);color:var(--red)}

.empty{text-align:center;padding:5rem 2rem;background:var(--surface);border:1.5px solid var(--border);border-radius:20px;box-shadow:var(--sh)}
.empty-icon{font-size:3.2rem;color:var(--teal);margin-bottom:1.2rem}
.empty h3{font-size:1.2rem;font-weight:700;color:var(--navy);margin-bottom:6px}
.empty p{font-size:14px;color:var(--muted)}

.cards{display:flex;flex-direction:column;gap:16px}
.scard{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;box-shadow:var(--sh);overflow:hidden;animation:slideUp .3s ease both;transition:border-color .18s,box-shadow .18s}
.scard:hover{border-color:var(--border2);box-shadow:var(--sh-lg)}
@keyframes slideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

.scard-inner{display:flex}
.scard-stripe{width:5px;flex-shrink:0;background:linear-gradient(180deg,var(--amber),#e8a030)}
.scard-stripe.rec{background:linear-gradient(180deg,var(--purple),#9b5ccc)}
.scard-body{flex:1;padding:1.5rem 1.6rem}

.scard-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:1.1rem}
.inst-row{display:flex;align-items:center;gap:12px}
.avatar{width:46px;height:46px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;background:linear-gradient(135deg,var(--navy2),#3d5a9e)}
.inst-name{font-size:15.5px;font-weight:700;color:var(--navy);letter-spacing:-.01em}
.inst-sub{font-size:12.5px;color:var(--muted);margin-top:2px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:100px;font-size:12.5px;font-weight:700;white-space:nowrap}
.badge-pending{background:var(--amber-lt);border:1.5px solid var(--amber-mid);color:var(--amber)}
.badge-rec{background:var(--purple-lt);border:1.5px solid var(--purple-mid);color:var(--purple)}

.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:10px;margin-bottom:1rem}
.info-cell{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 13px}
.info-cell-lbl{font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;margin-bottom:4px}
.info-cell-val{font-size:13.5px;font-weight:700;color:var(--text)}
.info-cell-val .ic{margin-right:5px;color:var(--teal);font-size:11px;font-family:'DM Sans',sans-serif}

.dias-section{margin-bottom:1rem}
.dias-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;margin-bottom:7px;display:flex;align-items:center;gap:5px}
.dias-row{display:flex;flex-wrap:wrap;gap:6px}
.dia-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:8px;font-size:12.5px;font-weight:700;border:1.5px solid var(--border);background:var(--surface2);color:var(--muted);transition:all .12s}
.dia-chip.rec-act{background:var(--purple-lt);border-color:var(--purple-mid);color:var(--purple)}
.dia-chip.uni-act{background:var(--teal-lt);border-color:var(--teal-mid);color:var(--teal)}
.dia-dot{width:6px;height:6px;border-radius:50%;display:none;flex-shrink:0}
.dia-chip.rec-act .dia-dot{display:inline-block;background:var(--purple)}
.dia-chip.uni-act .dia-dot{display:inline-block;background:var(--teal)}

.rec-toggle{display:inline-flex;align-items:center;gap:7px;background:var(--purple-lt);border:1.5px solid var(--purple-mid);color:var(--purple);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;margin-bottom:1rem;transition:all .15s}
.rec-toggle:hover{background:#ece0ff}
.rec-toggle i{transition:transform .2s}
.rec-toggle.open i{transform:rotate(90deg)}
.fechas-list{display:none;flex-wrap:wrap;gap:7px;margin-bottom:1rem}
.fechas-list.open{display:flex;animation:fadeIn .2s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.fecha-chip{display:inline-flex;align-items:center;gap:6px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:5px 11px;font-size:12.5px;font-weight:600;color:var(--navy);font-family:'DM Mono',monospace}

.obs-block{background:#fffdf5;border-left:3px solid var(--amber);border-radius:0 9px 9px 0;padding:10px 14px;margin-bottom:1.1rem;font-size:13.5px;color:var(--muted)}
.obs-block strong{color:var(--amber);font-size:12px;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px}

.scard-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-top:1.1rem;border-top:1px solid var(--border)}
.foot-meta{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.actions{display:flex;gap:10px}
.btn-action{display:inline-flex;align-items:center;gap:8px;padding:9px 20px;border-radius:10px;font-size:13.5px;font-weight:700;font-family:inherit;border:none;cursor:pointer;text-decoration:none;transition:all .15s}
.btn-approve{background:var(--teal);color:#fff}
.btn-approve:hover{background:var(--teal-lt);color:var(--teal);box-shadow:0 0 0 2px var(--teal)}
.btn-reject{background:transparent;border:1.5px solid var(--red-mid);color:var(--red)}
.btn-reject:hover{background:var(--red);color:#fff;border-color:var(--red)}

.modal-bg{display:none;position:fixed;inset:0;background:rgba(15,20,40,.55);z-index:400;backdrop-filter:blur(3px);align-items:center;justify-content:center}
.modal-bg.on{display:flex}
.modal{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;padding:2rem 2rem 1.6rem;width:100%;max-width:440px;box-shadow:var(--sh-lg);animation:slideUp .22s ease}
.modal-hdr{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.modal-hdr h3{font-size:1.05rem;font-weight:700;color:var(--navy)}
.modal-icon{width:36px;height:36px;border-radius:9px;background:var(--red-lt);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:15px}
.modal p{font-size:13.5px;color:var(--muted);margin-bottom:1.2rem}
.modal textarea{width:100%;background:var(--surface2);border:1.5px solid var(--border);border-radius:10px;color:var(--text);padding:11px 14px;font-family:'DM Sans',sans-serif;font-size:14px;resize:vertical;min-height:100px;margin-bottom:1.3rem;transition:border-color .15s}
.modal textarea:focus{outline:none;border-color:var(--red-mid)}
.modal-foot{display:flex;gap:10px;justify-content:flex-end}
.btn-cancel{padding:9px 18px;background:var(--surface2);border:1.5px solid var(--border);color:var(--muted);border-radius:10px;cursor:pointer;font-size:13.5px;font-family:inherit;font-weight:600;transition:all .15s}
.btn-cancel:hover{border-color:var(--navy);color:var(--navy)}
.btn-do-reject{padding:9px 22px;background:var(--red);border:none;color:#fff;border-radius:10px;cursor:pointer;font-size:13.5px;font-weight:700;font-family:inherit;transition:background .15s;display:inline-flex;align-items:center;gap:7px}
.btn-do-reject:hover{background:#9b2525}
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-left">
        <img src="../css/img/senab.png" alt="SENA" class="hdr-logo">
        <div class="hdr-title">
            <h1>Solicitudes de Ambientes</h1>
            <p>Panel de subdirección</p>
        </div>
    </div>
    <div class="hdr-right">
        <a href="index.php" class="btn-hdr btn-hdr-ghost"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <a href="../logout.php" class="btn-hdr btn-hdr-icon"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="wrap">

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg']==='aprobado'): ?>
    <div class="alert alert-ok"><i class="fa-solid fa-circle-check"></i> Solicitud <strong>aprobada</strong> exitosamente.</div>
    <?php elseif ($_GET['msg']==='rechazado'): ?>
    <div class="alert alert-err"><i class="fa-solid fa-circle-xmark"></i> Solicitud <strong>rechazada</strong> correctamente.</div>
    <?php elseif ($_GET['msg']==='error'): ?>
    <div class="alert alert-err"><i class="fa-solid fa-triangle-exclamation"></i> Error al procesar la solicitud. Intente nuevamente.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-hero">
    <div class="page-hero-title">
        <div class="hero-icon"><i class="fa-solid fa-inbox"></i></div>
        <div class="hero-text">
            <h2>Solicitudes Pendientes</h2>
            <p>Revisa y gestiona las solicitudes enviadas por instructores</p>
        </div>
    </div>
    <?php if ($total_grupos > 0): ?>
    <span class="pill pill-amber"><i class="fa-solid fa-hourglass-half"></i> <?= $total_grupos ?> pendiente<?= $total_grupos!==1?'s':'' ?></span>
    <?php else: ?>
    <span class="pill pill-teal"><i class="fa-solid fa-check"></i> Al día</span>
    <?php endif; ?>
</div>

<div class="refresh-row">
    <div class="live-dot"></div>
    Actualizando en <strong id="cnt" style="color:var(--teal);margin:0 3px;">30</strong>s
</div>

<?php if ($total_grupos === 0): ?>
<div class="empty">
    <div class="empty-icon"><i class="fa-solid fa-circle-check"></i></div>
    <h3>¡Todo al día!</h3>
    <p>No hay solicitudes pendientes por revisar.</p>
</div>

<?php else: ?>
<?php
$TODOS_DIAS = [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'];
$i = 0;
?>
<div class="cards">
<?php foreach ($grupos as $clave => $g):
    $i++;
    $esRec       = ($g['tipo']==='recurrente');
    $nombres     = explode(' ', trim($g['nombre_instructor']));
    $iniciales   = strtoupper(($nombres[0][0]??'').(end($nombres)[0]??''));
    $idsStr      = implode(',', $g['ids']);
    $primerFecha = date('d/m/Y', strtotime($g['fechas'][0]));
    $ultimaFecha = date('d/m/Y', strtotime(end($g['fechas'])));
    $hIni        = substr($g['hora_inicio'],0,5);
    $hFin        = substr($g['hora_final'], 0,5);
    $fechaReg    = date('d/m/Y H:i', strtotime($g['fecha_registro']));
    $dowActivos  = getDiasActivos($g['fechas']);
    $diasNombres = array_map(fn($n)=>$TODOS_DIAS[$n], array_keys($dowActivos));
?>
<div class="scard" style="animation-delay:<?= $i*60 ?>ms">
    <div class="scard-inner">
        <div class="scard-stripe <?= $esRec?'rec':'' ?>"></div>
        <div class="scard-body">

            <div class="scard-head">
                <div class="inst-row">
                    <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
                    <div>
                        <div class="inst-name"><?= htmlspecialchars($g['nombre_instructor']) ?></div>
                        <div class="inst-sub">Instructor SENA</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($esRec): ?>
                    <span class="badge badge-rec"><i class="fa-solid fa-arrows-rotate"></i> Recurrente · <?= count($g['ids']) ?> sesiones</span>
                    <?php endif; ?>
                    <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> Pendiente</span>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-cell">
                    <div class="info-cell-lbl">Ambiente</div>
                    <div class="info-cell-val"><?= htmlspecialchars($g['nombre_ambiente']) ?></div>
                </div>
                <?php if (!$esRec): ?>
                <div class="info-cell">
                    <div class="info-cell-lbl">Fecha</div>
                    <div class="info-cell-val"><?= $primerFecha ?></div>
                </div>
                <?php else: ?>
                <div class="info-cell">
                    <div class="info-cell-lbl">Desde</div>
                    <div class="info-cell-val"><?= $primerFecha ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-lbl">Hasta</div>
                    <div class="info-cell-val"><?= $ultimaFecha ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-lbl">Sesiones</div>
                    <div class="info-cell-val"><?= count($g['ids']) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-cell">
                    <div class="info-cell-lbl">Hora inicio</div>
                    <div class="info-cell-val"><?= $hIni ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-lbl">Hora fin</div>
                    <div class="info-cell-val"><?= $hFin ?></div>
                </div>
            </div>

            <div class="dias-section">
                <div class="dias-label">
                    <i class="fa-solid fa-calendar-week" style="color:var(--<?= $esRec?'purple':'teal' ?>);font-size:11px;"></i>
                    <?= $esRec ? 'Días de recurrencia' : 'Día de la semana' ?>
                </div>
                <div class="dias-row">
                    <?php foreach ($TODOS_DIAS as $n => $lbl):
                        $activo = isset($dowActivos[$n]);
                        $clase  = $activo ? ($esRec ? 'rec-act' : 'uni-act') : '';
                    ?>
                    <span class="dia-chip <?= $clase ?>">
                        <span class="dia-dot"></span>
                        <?= $lbl ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($esRec): ?>
            <button class="rec-toggle" onclick="toggleFechas(this,'fechas-<?= $i ?>')">
                <i class="fa-solid fa-chevron-right"></i> Ver <?= count($g['fechas']) ?> fechas específicas
            </button>
            <div class="fechas-list" id="fechas-<?= $i ?>">
                <?php foreach ($g['fechas'] as $f): ?>
                <span class="fecha-chip"><i class="fa-regular fa-calendar" style="color:var(--purple);"></i> <?= date('d/m/Y', strtotime($f)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($g['observaciones'])): ?>
            <div class="obs-block">
                <strong><i class="fa-solid fa-comment-dots"></i> Observaciones</strong>
                <?= htmlspecialchars($g['observaciones']) ?>
            </div>
            <?php endif; ?>

            <div class="scard-foot">
                <span class="foot-meta">
                    <i class="fa-regular fa-paper-plane"></i> Enviado el <?= $fechaReg ?>
                    <?php if ($esRec): ?>
                    &nbsp;·&nbsp;<i class="fa-solid fa-calendar-week"></i> <?= implode(', ', $diasNombres) ?>
                    <?php endif; ?>
                </span>
                <div class="actions">
                    <!-- APROBAR: usa "ids" (plural) con todos los IDs del grupo -->
                    <form method="POST" action="procesar_solicitud.php" style="display:inline;">
                        <input type="hidden" name="ids"    value="<?= htmlspecialchars($idsStr) ?>">
                        <input type="hidden" name="accion" value="aprobar">
                        <button type="submit" class="btn-action btn-approve"
                                onclick="return confirm('¿Aprobar <?= $esRec?"las ".count($g['ids'])." sesiones":"esta solicitud" ?>?')">
                            <i class="fa-solid fa-check"></i> Aprobar<?= $esRec?' todas':'' ?>
                        </button>
                    </form>
                    <!-- RECHAZAR: abre modal y pasa todos los IDs -->
                    <button class="btn-action btn-reject" onclick="openModal('<?= htmlspecialchars($idsStr) ?>')">
                        <i class="fa-solid fa-xmark"></i> Rechazar<?= $esRec?' todas':'' ?>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div>

<!-- MODAL RECHAZO -->
<div class="modal-bg" id="modalBg">
    <div class="modal">
        <div class="modal-hdr">
            <div class="modal-icon"><i class="fa-solid fa-xmark"></i></div>
            <h3>Rechazar solicitud</h3>
        </div>
        <p>Puedes indicar un motivo (opcional). El instructor podrá verlo en su panel.</p>
        <form method="POST" action="procesar_solicitud.php">
            <!-- "ids" plural para que procesar_solicitud.php los maneje todos -->
            <input type="hidden" name="ids"    id="modal_ids">
            <input type="hidden" name="accion" value="rechazar">
            <textarea name="motivo_rechazo" placeholder="Motivo del rechazo..."></textarea>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-do-reject"><i class="fa-solid fa-xmark"></i> Confirmar rechazo</button>
            </div>
        </form>
    </div>
</div>

<script>
let t = 30;
const cnt = document.getElementById('cnt');
setInterval(() => { t--; if(cnt) cnt.textContent = t; if(t <= 0) location.reload(); }, 1000);

function openModal(ids) {
    document.getElementById('modal_ids').value = ids;
    document.getElementById('modalBg').classList.add('on');
}
function closeModal() {
    document.getElementById('modalBg').classList.remove('on');
}
document.getElementById('modalBg').addEventListener('click', e => {
    if (e.target === document.getElementById('modalBg')) closeModal();
});

function toggleFechas(btn, id) {
    const list = document.getElementById(id);
    list.classList.toggle('open');
    btn.classList.toggle('open');
    const n = list.children.length;
    const last = btn.lastChild;
    if (last.nodeType === 3)
        last.textContent = list.classList.contains('open')
            ? ' Ocultar fechas'
            : ` Ver ${n} fechas específicas`;
}
</script>






</body>
</html>