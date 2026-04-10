<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

$id_instructor = intval($_SESSION['id_usuario']);
$usuario = $_SESSION['usuario'] ?? 'Instructor';

// Contar solicitudes propias por estado
$stmt = $conexion->prepare("
    SELECT estado, COUNT(*) as total
    FROM autorizaciones_ambientes
    WHERE id_instructor = ?
    GROUP BY estado
");
$stmt->bind_param("i", $id_instructor);
$stmt->execute();
$res = $stmt->get_result();
$conteos = ['Pendiente' => 0, 'Aprobado' => 0, 'Rechazado' => 0];
while ($row = $res->fetch_assoc()) {
    $conteos[$row['estado']] = $row['total'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Instructor</title>
    <link rel="stylesheet" href="../css/instructor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ── Mini stats de solicitudes propias ───────────────── */
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-mini-card {
            background: var(--surface, #1a1d27);
            border: 1px solid var(--border, #2e3250);
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            text-decoration: none;
            color: inherit;
            transition: border-color .2s, transform .15s;
            text-align: center;
        }
        .stat-mini-card:hover { transform: translateY(-2px); }
        .stat-mini-card .num { font-size: 1.9rem; font-weight: 900; line-height: 1; }
        .stat-mini-card .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted, #7c85b3); margin-top: .35rem; }
        .stat-mini-card.pendiente { border-color: rgba(245,158,11,.3); }
        .stat-mini-card.pendiente:hover { border-color: #f59e0b; }
        .stat-mini-card.pendiente .num { color: #f59e0b; }
        .stat-mini-card.aprobado  { border-color: rgba(34,197,94,.3); }
        .stat-mini-card.aprobado:hover  { border-color: #22c55e; }
        .stat-mini-card.aprobado .num  { color: #22c55e; }
        .stat-mini-card.rechazado { border-color: rgba(239,68,68,.3); }
        .stat-mini-card.rechazado:hover { border-color: #ef4444; }
        .stat-mini-card.rechazado .num { color: #ef4444; }

        /* Destacar card "Solicitar Ambiente" */
        .menu-card.destacado {
            background: linear-gradient(135deg, rgba(79,142,247,.12), rgba(124,92,191,.08)) !important;
            border-color: rgba(79,142,247,.4) !important;
        }
        .menu-card.destacado:hover {
            border-color: #4f8ef7 !important;
            box-shadow: 0 0 0 2px rgba(79,142,247,.15) !important;
        }

        @media (max-width: 600px) {
            .stats-mini-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Panel de Instructor</h1>
            <span>Gestión de ambientes asignados</span>
        </div>
    </div>
    <div class="header-user">
        Salir
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="dashboard-container">

 

    <div class="actions-container">
        <h2 class="actions-title">Acciones disponibles</h2>
        <div class="menu-grid">

            <!-- ── NUEVO: Solicitar ambiente ── -->
            <a href="solicitar_ambiente.php" class="menu-card destacado">
                <div class="menu-card-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <div class="menu-card-title">Solicitar Ambiente</div>
                <div class="menu-card-description">Enviar una solicitud de uso de ambiente al administrador</div>
            </a>

            <!-- ── NUEVO: Mis solicitudes ── -->
            <a href="mis_solicitudes.php" class="menu-card" style="position:relative;">
                <div class="menu-card-icon"><i class="fa-solid fa-list-check"></i></div>
                <?php if ($conteos['Pendiente'] > 0): ?>
                    <span style="
                        position:absolute;top:1rem;right:1rem;
                        background:#f59e0b;color:#000;font-size:.65rem;
                        font-weight:700;width:20px;height:20px;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;">
                        <?= $conteos['Pendiente'] ?>
                    </span>
                <?php endif; ?>
                <div class="menu-card-title">Mis Solicitudes</div>
                <div class="menu-card-description">Ver el estado de todas tus solicitudes enviadas</div>
            </a>

            <!-- Mis ambientes hoy -->
            <a href="mis_ambientes.php" class="menu-card primary">
                <div class="menu-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="menu-card-title">Mis Ambientes Hoy</div>
                <div class="menu-card-description">Ver ambientes asignados y reportar novedades</div>
            </a>

            <!-- Historial -->
            <a href="historial_ambiente.php" class="menu-card secondary">
                <div class="menu-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="menu-card-title">Historial de Ambientes</div>
                <div class="menu-card-description">Consultar historial de uso por ambiente</div>
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
            <p>Plataforma institucional para la administración y control de ambientes de aprendizaje, orientada a la excelencia en la formación técnica y tecnológica.</p>
        </div>
        <div class="footer-nav">
            <span class="footer-section-title">NAVEGACIÓN</span>
            <ul>
                <li><a href="#">Inicio</a></li>
                <li><a href="solicitar_ambiente.php">Solicitar Ambiente</a></li>
                <li><a href="mis_solicitudes.php">Mis Solicitudes</a></li>
                <li><a href="mis_ambientes.php">Mis Ambientes Hoy</a></li>
                <li><a href="historial_ambiente.php">Historial de Ambientes</a></li>
            </ul>
        </div>
        <div class="footer-location">
            <span class="footer-section-title">UBICACIÓN</span>
            <ul>
                <li>
                    <span class="footer-icon">&#9679;</span>
                    Centro de Industria y Servicios del Meta<br>Villavicencio, Meta — Colombia
                </li>
                <li><span class="footer-icon">&#9711;</span>Regional Llanos Orientales</li>
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

</body>
</html>