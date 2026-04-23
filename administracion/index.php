<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit;
}

$meses_espanol = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$rol         = $_SESSION['rol'];
$hoy         = date('Y-m-d');
$hora_actual = date('H:i:s');
$mes         = date('m');
$anio        = date('Y');

/* ── Conteos de ambientes ── */
$resHabilitados   = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Habilitado'");
$ambientes_habilitados   = mysqli_fetch_row($resHabilitados)[0];

$resDeshabilitados = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Deshabilitado'");
$ambientes_deshabilitados = mysqli_fetch_row($resDeshabilitados)[0];

$resMantenimiento = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Mantenimiento'");
$ambientes_mantenimiento = mysqli_fetch_row($resMantenimiento)[0];

$total_ambientes = $ambientes_habilitados + $ambientes_deshabilitados + $ambientes_mantenimiento;

/* ── Disponibles ahora ── */
/* ── Disponibles ahora (versión actualizada) ── */
$resDisponibles = mysqli_query($conexion, "
    SELECT COUNT(DISTINCT a.id)
    FROM ambientes a
    WHERE a.estado = 'Habilitado'
      /* sin reserva activa */
      AND a.id NOT IN (
          SELECT au.id_ambiente
          FROM autorizaciones_ambientes au
          WHERE au.fecha_inicio <= '$hoy'
            AND au.fecha_fin    >= '$hoy'
            AND au.hora_inicio  <= '$hora_actual'
            AND au.hora_final   >= '$hora_actual'
            AND au.estado = 'Aprobado'
      )
      /* sin bloque 'Ocupado' activo */
      AND a.id NOT IN (
          SELECT da.id_ambiente
          FROM disponibilidad_ambiente da
          WHERE da.fecha       = '$hoy'
            AND da.hora_inicio <= '$hora_actual'
            AND da.hora_fin    >= '$hora_actual'
            AND da.estado = 'Ocupado'
      )
");
$disponibles_ahora = mysqli_fetch_row($resDisponibles)[0];

/* ── Autorizaciones del mes ── */
$resMes = mysqli_query($conexion, "
    SELECT COUNT(*) FROM autorizaciones_ambientes
    WHERE MONTH(fecha_inicio) = '$mes' AND YEAR(fecha_inicio) = '$anio'
");
$autorizaciones_mes = mysqli_fetch_row($resMes)[0];

/* ── Solicitudes pendientes ── */
$resPendientes = mysqli_query($conexion, "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE estado = 'Pendiente'");
$solicitudes_pendientes = mysqli_fetch_row($resPendientes)[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administración</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- ═══════════════════════ HEADER ═══════════════════════ -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Panel de Administración</h1>
            <span>Gestión y control de ambientes</span>
        </div>
    </div>
    <div class="header-user">
        <!-- Campana -->
        <a href="solicitudes.php?estado=Pendiente" class="notif-bell" title="Solicitudes pendientes">
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

<!-- ═══════════════════════ DASHBOARD ═══════════════════════ -->
<div class="dashboard-container">

    <!-- STAT CARDS (4 columnas) -->
    <div class="stats-grid">

        <!-- 1. Total ambientes -->
        <a href="total_ambientes.php" class="stat-card stat-link">
            <div class="stat-label">TOTAL AMBIENTES</div>
            <div class="stat-value"><?= $total_ambientes ?></div>
            <div class="stat-details">
                <span class="badge-habilitado"><?= $ambientes_habilitados ?> Habilitados</span>
                <span class="badge-deshabilitado"><?= $ambientes_deshabilitados ?> Deshabilitados</span>
                <span class="badge-mantenimiento"><?= $ambientes_mantenimiento ?> Mantenimiento</span>
            </div>
        </a>

        <!-- 2. Disponibles ahora -->
        <a href="disponibles.php" class="stat-card stat-link success">
            <div class="stat-label">DISPONIBLES AHORA</div>
            <div class="stat-value"><?= $disponibles_ahora ?></div>
            <div class="stat-details"><small>Ambientes libres en este momento</small></div>
        </a>

        <!-- 3. Autorizaciones del mes -->
        <a href="autorizacion_mes.php" class="stat-card stat-link info">
            <div class="stat-label">AUTORIZACIONES DEL MES</div>
            <div class="stat-value"><?= $autorizaciones_mes ?></div>
            <div class="stat-details">
                <small><?= $meses_espanol[$mes] . ' ' . $anio ?></small>
            </div>
        </a>

        <!-- 4. Solicitudes pendientes -->
        <div class="stat-card warning">
            <div>
                <div class="stat-label">
                    <i class="fa-solid fa-bell"></i> SOLICITUDES PENDIENTES
                </div>
                <div class="stat-value"><?= $solicitudes_pendientes ?></div>
                <div class="stat-details">
                    <small>
                        <?= $solicitudes_pendientes > 0
                            ? $solicitudes_pendientes . ' solicitud' . ($solicitudes_pendientes !== 1 ? 'es por revisar' : ' por revisar')
                            : 'Sin solicitudes pendientes ✓' ?>
                    </small>
                </div>
            </div>
            <div>
                <a href="solicitudes.php?estado=Pendiente" class="btn-ver-sol">
                    <i class="fa-solid fa-eye"></i> Ver solicitudes
                </a>
            </div>
        </div>
        <div class="card-fichas">
            <div class="card-fichas__icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                    <line x1="9" y1="12" x2="15" y2="12"/>
                    <line x1="9" y1="16" x2="13" y2="16"/>
                </svg>
            </div>
            <div class="card-fichas__body">
                <h3 class="card-fichas__title">Gestión de Fichas</h3>
                <p class="card-fichas__desc">Consultar programación de ambientes o registrar nuevas fichas en el sistema.</p>
                <div class="card-fichas__actions">
                    <a href="programacion_fichas.php" class="btn-ficha btn-ficha--search">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                        </svg>
                        Buscar Programación
                    </a>
                    <a href="gestionar_fichas.php" class="btn-ficha btn-ficha--add">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ingresar Fichas
                    </a>
                </div>
            </div>
        </div>


    </div><!-- /stats-grid -->

    <!-- ACCIONES -->
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

            <a href="calendario.php" class="menu-card calendario">
                <div class="menu-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="menu-card-title">Calendario de Ambientes</div>
                <div class="menu-card-description">Vista interactiva de reservas y permisos por día, semana o mes</div>
            </a>
              <a href="registro.php" class="menu-card crear">
                <div class="menu-card-icon"><i class="fa-solid fa-circle-plus"></i></div>
                <div class="menu-card-title">Crear Registros</div>
                <div class="menu-card-description">Registrar nuevos ambientes e instructores</div>
            </a>
            
        </div><!-- /menu-grid -->
    </div><!-- /actions-container -->

</div><!-- /dashboard-container -->

<!-- ═══════════════════════ FOOTER ═══════════════════════ -->
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
                <li><a href="registro.php">Crear Registros</a></li>
                <li><a href="calendario.php">Calendario de Ambientes</a></li>
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

<!-- Refresca contador de campana cada 30 s sin recargar la página -->
<script>
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
        .catch(() => {});
}, 30000);
</script>

</body>
</html>