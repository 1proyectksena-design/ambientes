<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

$rol = $_SESSION['rol'];
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Subdirección</title>
    <link rel="stylesheet" href="../css/subdire.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Panel de Subdirección</h1>
            <span>Gestión y control de ambientes</span>
        </div>
    </div>
    <div class="header-user">
        Cerrar Sesión
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="dashboard-container">
    <div class="stats-grid">
        <a href="total_ambientes.php" class="stat-card stat-link">
            <div class="stat-label">TOTAL AMBIENTES</div>
            <div class="stat-value"><?= $total_ambientes ?></div>
            <div class="stat-details">
                <span class="badge-habilitado"><?= $ambientes_habilitados ?> Habilitados</span>
                <span class="badge-deshabilitado"><?= $ambientes_deshabilitados ?> Deshabilitados</span>
                <span class="badge-mantenimiento"><?= $ambientes_mantenimiento ?> Mantenimiento</span>
            </div>
        </a>
        <a href="disponibles.php" class="stat-card stat-link success">
            <div class="stat-label">DISPONIBLES AHORA</div>
            <div class="stat-value"><?= $disponibles_ahora ?></div>
            <div class="stat-details"><small>Ambientes libres en este momento</small></div>
        </a>
        <a href="autorizacion_mes.php" class="stat-card stat-link info">
            <div class="stat-label">AUTORIZACIONES DEL MES</div>
            <div class="stat-value"><?= $autorizaciones_mes ?></div>
            <div class="stat-details"><small><?= date('F Y') ?></small></div>
        </a>
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
        </div>
    </div>
</div>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-left">
            <img src="../css/img/senab.png" alt="Logo SENA" class="footer-logo">
            <div>
                <p class="footer-title">SENA</p>
                <p class="footer-sub">Servicio Nacional de Aprendizaje</p>
            </div>
        </div>
        <div class="footer-center">
            <p>Sistema de Gestión de Ambientes</p>
            <p class="footer-year">© <?= date('Y') ?> — Todos los derechos reservados</p>
        </div>
        <div class="footer-right">
            <p>Desarrollado para</p>
            <p><strong>Centro de Gestión de Mercados,<br>Logística y TIC's</strong></p>
        </div>
        
    </div>
</footer>

</body>
</html>