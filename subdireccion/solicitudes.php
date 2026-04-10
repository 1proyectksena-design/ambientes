<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

// Obtener todas las solicitudes pendientes con JOIN
$query = "
    SELECT 
        aa.id,
        aa.fecha_inicio,
        aa.fecha_fin,
        aa.hora_inicio,
        aa.hora_final,
        aa.estado,
        aa.fecha_registro,
        aa.observaciones,
        i.nombre AS nombre_instructor,
        a.nombre_ambiente AS nombre_ambiente
    FROM autorizaciones_ambientes aa
    INNER JOIN instructores i ON aa.id_instructor = i.id
    INNER JOIN ambientes a ON aa.id_ambiente = a.id
    WHERE aa.estado = 'Pendiente'
    ORDER BY aa.fecha_registro DESC
";
$resultado = mysqli_query($conexion, $query);

$pendientes = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $pendientes[] = $fila;
}
$total_pendientes = count($pendientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes Pendientes — Administración</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: #2e3250;
            --accent: #4f8ef7;
            --accent2: #7c5cbf;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text: #e8eaf6;
            --text-muted: #7c85b3;
            --font: 'Segoe UI', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* HEADER */
        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 64px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-sena { height: 40px; }
        .header-title h1 { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .header-title span { font-size: 0.75rem; color: var(--text-muted); }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-back:hover { border-color: var(--accent); color: var(--accent); }
        .btn-logout-header {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout-header:hover { border-color: var(--danger); color: var(--danger); }

        /* MAIN */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title-block h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text);
        }
        .page-title-block p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .badge-count {
            background: var(--warning);
            color: #000;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
        }

        /* ALERT SUCCESS/ERROR desde URL */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: var(--success); }
        .alert-danger { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: var(--danger); }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .empty-state i { font-size: 3rem; color: var(--success); margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; }

        /* CARDS */
        .solicitudes-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .solicitud-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            animation: fadeInUp 0.3s ease both;
        }
        .solicitud-card:hover {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent)22;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .card-instructor {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .avatar {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            flex-shrink: 0;
        }
        .instructor-name { font-weight: 700; font-size: 1rem; color: var(--text); }
        .instructor-sub { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.1rem; }

        .badge-pendiente {
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.35);
            color: var(--warning);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            white-space: nowrap;
        }

        .card-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 1rem;
            margin: 1.25rem 0;
            padding: 1.25rem;
            background: var(--surface2);
            border-radius: 10px;
        }
        .info-item label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        .info-item span {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text);
        }
        .info-item .icon-inline {
            margin-right: 0.35rem;
            color: var(--accent);
            font-size: 0.8rem;
        }

        .observaciones-block {
            background: rgba(79,142,247,0.07);
            border-left: 3px solid var(--accent);
            border-radius: 0 8px 8px 0;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .observaciones-block strong { color: var(--accent); }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .fecha-registro {
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .card-actions {
            display: flex;
            gap: 0.75rem;
        }
        .btn-aprobar, .btn-rechazar {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-aprobar {
            background: var(--success);
            color: #fff;
        }
        .btn-aprobar:hover { background: #16a34a; transform: translateY(-1px); }
        .btn-rechazar {
            background: transparent;
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        .btn-rechazar:hover { background: var(--danger); color: #fff; transform: translateY(-1px); }

        /* MODAL RECHAZO */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            width: 100%;
            max-width: 440px;
            animation: fadeInUp 0.25s ease;
        }
        .modal h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text); }
        .modal p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; }
        .modal textarea {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 0.75rem;
            font-family: var(--font);
            font-size: 0.9rem;
            resize: vertical;
            min-height: 90px;
            margin-bottom: 1.25rem;
            transition: border-color 0.2s;
        }
        .modal textarea:focus { outline: none; border-color: var(--danger); }
        .modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
        .btn-cancel {
            padding: 0.5rem 1rem;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-cancel:hover { border-color: var(--accent); color: var(--accent); }
        .btn-confirm-rechazar {
            padding: 0.5rem 1.25rem;
            background: var(--danger);
            border: none;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-confirm-rechazar:hover { background: #dc2626; }

        /* Refresh indicator */
        .refresh-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        .refresh-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Solicitudes de Ambientes</h1>
            <span>Panel de administración</span>
        </div>
    </div>
    <div class="header-actions">
        <a href="index.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="main">

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'aprobado'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Solicitud <strong>aprobada</strong> correctamente.
            </div>
        <?php elseif ($_GET['msg'] === 'rechazado'): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark"></i>
                Solicitud <strong>rechazada</strong> correctamente.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="page-header">
        <div class="page-title-block">
            <h2><i class="fa-solid fa-bell" style="color:var(--warning);margin-right:.5rem;"></i> Solicitudes Pendientes</h2>
            <p>Revisa y gestiona las solicitudes de ambientes enviadas por instructores</p>
        </div>
        <?php if ($total_pendientes > 0): ?>
            <span class="badge-count">⏳ <?= $total_pendientes ?> pendiente<?= $total_pendientes !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>

    <div class="refresh-bar">
        <div class="refresh-dot"></div>
        Actualizando automáticamente cada 10 segundos
        <span id="countdown" style="margin-left:.5rem;font-weight:600;color:var(--accent);">10s</span>
    </div>

    <?php if ($total_pendientes === 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-check-circle"></i>
            <h3>¡Todo al día!</h3>
            <p>No hay solicitudes pendientes en este momento.</p>
        </div>
    <?php else: ?>
        <div class="solicitudes-list">
            <?php foreach ($pendientes as $s):
                $iniciales = strtoupper(substr($s['nombre_instructor'], 0, 1) . substr(strrchr($s['nombre_instructor'], ' '), 1, 1));
                $fecha_display = date('d/m/Y', strtotime($s['fecha_inicio']));
                $fecha_fin_display = date('d/m/Y', strtotime($s['fecha_fin']));
                $hora_i = substr($s['hora_inicio'], 0, 5);
                $hora_f = substr($s['hora_final'], 0, 5);
                $fecha_reg = date('d/m/Y H:i', strtotime($s['fecha_registro']));
            ?>
                <div class="solicitud-card">
                    <div class="card-top">
                        <div class="card-instructor">
                            <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
                            <div>
                                <div class="instructor-name"><?= htmlspecialchars($s['nombre_instructor']) ?></div>
                                <div class="instructor-sub">Instructor SENA</div>
                            </div>
                        </div>
                        <span class="badge-pendiente"><i class="fa-solid fa-clock" style="margin-right:.35rem;"></i>Pendiente</span>
                    </div>

                    <div class="card-info">
                        <div class="info-item">
                            <label>Ambiente</label>
                            <span><i class="fa-solid fa-door-open icon-inline"></i><?= htmlspecialchars($s['nombre_ambiente']) ?></span>
                        </div>
                        <?php if (!empty($s['ubicacion_ambiente'])): ?>
                        <div class="info-item">
                            <label>Ubicación</label>
                            <span><i class="fa-solid fa-location-dot icon-inline"></i><?= htmlspecialchars($s['ubicacion_ambiente']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>Fecha inicio</label>
                            <span><i class="fa-solid fa-calendar icon-inline"></i><?= $fecha_display ?></span>
                        </div>
                        <div class="info-item">
                            <label>Fecha fin</label>
                            <span><i class="fa-solid fa-calendar-check icon-inline"></i><?= $fecha_fin_display ?></span>
                        </div>
                        <div class="info-item">
                            <label>Hora inicio</label>
                            <span><i class="fa-solid fa-clock icon-inline"></i><?= $hora_i ?></span>
                        </div>
                        <div class="info-item">
                            <label>Hora final</label>
                            <span><i class="fa-solid fa-clock icon-inline"></i><?= $hora_f ?></span>
                        </div>
                    </div>

                    <?php if (!empty($s['observaciones'])): ?>
                        <div class="observaciones-block">
                            <strong><i class="fa-solid fa-comment-dots" style="margin-right:.4rem;"></i>Observaciones:</strong>
                            <?= htmlspecialchars($s['observaciones']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-footer">
                        <span class="fecha-registro">
                            <i class="fa-solid fa-paper-plane" style="margin-right:.3rem;"></i>
                            Enviado el <?= $fecha_reg ?>
                        </span>
                        <div class="card-actions">
                            <!-- Aprobar directo -->
                            <form method="POST" action="procesar_solicitud.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button type="submit" class="btn-aprobar" onclick="return confirm('¿Aprobar esta solicitud?')">
                                    <i class="fa-solid fa-check"></i> Aprobar
                                </button>
                            </form>
                            <!-- Rechazar con modal -->
                            <button class="btn-rechazar" onclick="abrirModalRechazo(<?= $s['id'] ?>)">
                                <i class="fa-solid fa-xmark"></i> Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de rechazo -->
<div class="modal-overlay" id="modalRechazo">
    <div class="modal">
        <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);margin-right:.5rem;"></i> Rechazar Solicitud</h3>
        <p>Puedes incluir un motivo de rechazo (opcional). El instructor podrá verlo.</p>
        <form method="POST" action="procesar_solicitud.php">
            <input type="hidden" name="id" id="modal_id">
            <input type="hidden" name="accion" value="rechazar">
            <textarea name="motivo_rechazo" placeholder="Motivo del rechazo (opcional)..."></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-confirm-rechazar">
                    <i class="fa-solid fa-xmark"></i> Confirmar rechazo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Contador regresivo y recarga automática
let segundos = 10;
const countdown = document.getElementById('countdown');

setInterval(() => {
    segundos--;
    if (countdown) countdown.textContent = segundos + 's';
    if (segundos <= 0) {
        window.location.reload();
    }
}, 1000);

// Modal rechazo
function abrirModalRechazo(id) {
    document.getElementById('modal_id').value = id;
    document.getElementById('modalRechazo').classList.add('active');
}
function cerrarModal() {
    document.getElementById('modalRechazo').classList.remove('active');
}
document.getElementById('modalRechazo').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>
</body>
</html>
