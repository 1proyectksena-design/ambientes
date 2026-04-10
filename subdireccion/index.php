<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$mes = date('m');
$anio = date('Y');

$resHabilitados = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Habilitado'");
$ambientes_habilitados = mysqli_fetch_row($resHabilitados)[0];
$resDeshabilitados = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Deshabilitado'");
$ambientes_deshabilitados = mysqli_fetch_row($resDeshabilitados)[0];
$resMantenimiento = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Mantenimiento'");
$ambientes_mantenimiento = mysqli_fetch_row($resMantenimiento)[0];
$total_ambientes = $ambientes_habilitados + $ambientes_deshabilitados + $ambientes_mantenimiento;

$resDisponibles = mysqli_query($conexion, "
    SELECT COUNT(DISTINCT a.id) FROM ambientes a
    WHERE a.estado = 'Habilitado'
    AND a.id NOT IN (
        SELECT id_ambiente FROM autorizaciones_ambientes
        WHERE fecha_inicio <= '$hoy' AND fecha_fin >= '$hoy'
        AND hora_inicio <= '$hora_actual' AND hora_final >= '$hora_actual'
        AND estado = 'Aprobado'
    )
");
$disponibles_ahora = mysqli_fetch_row($resDisponibles)[0];

$resMes = mysqli_query($conexion, "
    SELECT COUNT(*) FROM autorizaciones_ambientes
    WHERE MONTH(fecha_inicio) = '$mes' AND YEAR(fecha_inicio) = '$anio'
");
$autorizaciones_mes = mysqli_fetch_row($resMes)[0];

// ── NUEVO: solicitudes pendientes ──────────────────────────────
$resPendientes = mysqli_query($conexion, "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE estado = 'Pendiente'");
$solicitudes_pendientes = mysqli_fetch_row($resPendientes)[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administración</title>
    <link rel="stylesheet" href="../css/subdire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ── Campana de notificaciones ─────────────────────── */
        .notif-bell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; height: 40px;
            background: transparent;
            border: 1px solid var(--border, #2e3250);
            border-radius: 10px;
            color: var(--text-muted, #7c85b3);
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .notif-bell:hover { border-color: #f59e0b; color: #f59e0b; }
        .notif-bell .badge-bell {
            position: absolute;
            top: -6px; right: -6px;
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            animation: bellPulse 1.5s ease infinite;
        }
        @keyframes bellPulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.2); }
        }

        /* ── Card de pendientes ────────────────────────────── */
        .stat-card.warning {
            border-color: rgba(245,158,11,0.35) !important;
            background: rgba(245,158,11,0.04) !important;
        }
        .stat-card.warning .stat-value { color: #f59e0b; }
        .stat-card.warning:hover { border-color: #f59e0b !important; }

        .btn-ver {
            display: inline-flex; align-items: center; gap: .4rem;
            margin-top: .75rem;
            padding: .4rem .9rem;
            background: #f59e0b;
            color: #000; font-weight: 700; font-size: .78rem;
            border-radius: 20px; text-decoration: none;
            transition: background .2s, transform .15s;
        }
        .btn-ver:hover { background: #d97706; transform: translateY(-1px); }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Panel de Administración</h1>
            <span>Gestión y control de ambientes</span>
        </div>
    </div>
    <div class="header-user" style="display:flex;align-items:center;gap:.75rem;">
        <!-- Campana de notificaciones -->
        <a href="solicitudes.php" class="notif-bell" title="Solicitudes pendientes">
            <i class="fa-solid fa-bell"></i>
            <?php if ($solicitudes_pendientes > 0): ?>
                <span class="badge-bell"><?= $solicitudes_pendientes ?></span>
            <?php endif; ?>
        </a>
        Salir
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="dashboard-container">
    <div class="stats-grid">
        <!-- Card: Total ambientes -->
        <a href="total_ambientes.php" class="stat-card stat-link">
            <div class="stat-label">TOTAL AMBIENTES</div>
            <div class="stat-value"><?= $total_ambientes ?></div>
            <div class="stat-details">
                <span class="badge-habilitado"><?= $ambientes_habilitados ?> Habilitados</span>
                <span class="badge-deshabilitado"><?= $ambientes_deshabilitados ?> Deshabilitados</span>
                <span class="badge-mantenimiento"><?= $ambientes_mantenimiento ?> Mantenimiento</span>
            </div>
        </a>

        <!-- Card: Disponibles ahora -->
        <a href="disponibles.php" class="stat-card stat-link success">
            <div class="stat-label">DISPONIBLES AHORA</div>
            <div class="stat-value"><?= $disponibles_ahora ?></div>
            <div class="stat-details"><small>Ambientes libres en este momento</small></div>
        </a>

        <!-- Card: Autorizaciones del mes -->
        <a href="autorizacion_mes.php" class="stat-card stat-link info">
            <div class="stat-label">AUTORIZACIONES DEL MES</div>
            <div class="stat-value"><?= $autorizaciones_mes ?></div>
            <div class="stat-details"><small><?= date('F Y') ?></small></div>
        </a>

        <!-- ── NUEVA Card: Solicitudes pendientes ── -->
        <div class="stat-card warning" style="display:flex;flex-direction:column;justify-content:space-between;">
            <div>
                <div class="stat-label">
                    <i class="fa-solid fa-bell" style="margin-right:.35rem;"></i>SOLICITUDES PENDIENTES
                </div>
                <div class="stat-value"><?= $solicitudes_pendientes ?></div>
                <div class="stat-details">
                    <small>
                        <?= $solicitudes_pendientes > 0
                            ? $solicitudes_pendientes . ' solicitud' . ($solicitudes_pendientes !== 1 ? 'es esperan revisión' : ' espera revisión')
                            : 'Sin solicitudes por revisar ✓' ?>
                    </small>
                </div>
            </div>
            <a href="solicitudes.php" class="btn-ver">
                <i class="fa-solid fa-eye"></i> Ver solicitudes
            </a>
        </div>
    </div>

    <div class="actions-container">
        <h2 class="actions-title">Acciones disponibles</h2>
        <div class="menu-grid">
            <a href="consultar.php" class="menu-card">
                <div class="menu-card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <div class="menu-card-title">Consultar Ambiente</div>
                <div class="menu-card-description">Buscar ambiente, ver historial y gestionar permisos</div>
            </a>
            <a href="historial.php" class="menu-card">
                <div class="menu-card-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="menu-card-title">Historial Autorizaciones</div>
                <div class="menu-card-description">Ver todas las autorizaciones del sistema</div>
            </a>
            <a href="registro.php" class="menu-card registro">
                <div class="menu-card-icon"><i class="fa-solid fa-circle-plus"></i></div>
                <div class="menu-card-title">Crear Registros</div>
                <div class="menu-card-description">Ver registros del sistema</div>
            </a>
            <!-- ── NUEVO acceso rápido ── -->
            <a href="solicitudes.php" class="menu-card" style="position:relative;">
                <div class="menu-card-icon" style="color:#f59e0b;">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($solicitudes_pendientes > 0): ?>
                        <span style="
                            position:absolute;top:1rem;right:1rem;
                            background:#ef4444;color:#fff;font-size:.65rem;
                            font-weight:700;width:20px;height:20px;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;">
                            <?= $solicitudes_pendientes ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="menu-card-title">Solicitudes</div>
                <div class="menu-card-description">Aprobar o rechazar solicitudes de instructores</div>
            </a>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="footer-top-line"></div>
    <div class="footer-container">
        <div class="footer-brand">
            <div class="footer-logo"><span>&#94;</span></div>
            <div class="footer-brand-text">
                <span class="footer-label">INSTITUCIONAL</span>
                <h3 class="footer-title">Sistema de Gestión<br>de Ambientes</h3>
            </div>
        </div>
        <div class="footer-description">
            <p>Plataforma institucional para la administración y control de ambientes de aprendizaje.</p>
        </div>
        <div class="footer-nav">
            <span class="footer-section-title">NAVEGACIÓN</span>
            <ul>
                <li><a href="#">Inicio</a></li>
                <li><a href="consultar.php">Consultar Ambiente</a></li>
                <li><a href="historial.php">Historial Autorizaciones</a></li>
                <li><a href="solicitudes.php">Solicitudes</a></li>
            </ul>
        </div>
        <div class="footer-location">
            <span class="footer-section-title">UBICACIÓN</span>
            <ul>
                <li><span class="footer-icon">&#9679;</span>Centro de Industria y Servicios del Meta</li>
                <li><span class="footer-icon">&#9711;</span>Villavicencio, Meta — Colombia</li>
                <li><span class="footer-icon">&#9993;</span>sena.edu.co</li>
            </ul>
        </div>
    </div>
    <div class="footer-divider"></div>
    <div class="footer-bottom">
        <p>© <?= date('Y') ?> <strong>SENA</strong> — Gestión de Ambientes. Todos los derechos reservados.</p>
        <div class="footer-status">
            <span class="footer-status-dot"></span>
            Sistema operativo
        </div>
    </div>
</footer>

<script>
// Refresca el contador de la campana cada 30 segundos sin recargar toda la página
setInterval(() => {
    fetch('api_pendientes.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.querySelector('.badge-bell');
            if (data.count > 0) {
                if (!badge) {
                    const bell = document.querySelector('.notif-bell');
                    const span = document.createElement('span');
                    span.className = 'badge-bell';
                    span.textContent = data.count;
                    bell.appendChild(span);
                } else {
                    badge.textContent = data.count;
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(() => {}); // silencioso si falla
}, 30000);
</script>
</body>
</html>