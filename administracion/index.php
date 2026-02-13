<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../includes/conexion.php");

/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit;
}

$rol = $_SESSION['rol'];

/* =========================
   ESTADÍSTICAS DINÁMICAS
========================= */

$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$mes = date('m');
$anio = date('Y');

/* TOTAL AMBIENTES */
$resTotal = mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes");
$total_ambientes = mysqli_fetch_row($resTotal)[0];

/* OCUPADOS AHORA */
$resOcupados = mysqli_query($conexion, "
    SELECT COUNT(DISTINCT id_ambiente)
    FROM autorizaciones_ambientes
    WHERE fecha='$hoy'
    AND hora_inicio <= '$hora_actual'
    AND hora_fin >= '$hora_actual'
");
$ocupados_ahora = mysqli_fetch_row($resOcupados)[0];

/* DISPONIBLES AHORA */
$disponibles_ahora = $total_ambientes - $ocupados_ahora;

/* AUTORIZACIONES DEL MES */
$resMes = mysqli_query($conexion, "
    SELECT COUNT(*)
    FROM autorizaciones_ambientes
    WHERE MONTH(fecha)='$mes'
    AND YEAR(fecha)='$anio'
");
$autorizaciones_mes = mysqli_fetch_row($resMes)[0];

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel <?= ucfirst($rol) ?></title>
<link rel="stylesheet" href="../css/admin.css">
</head>

<body>

<div class="admin-container">

    <!-- HEADER -->
    <div class="admin-header">
        <div class="header-left">
            <h1>Panel de <?= ucfirst($rol) ?></h1>
            <p>Gestión y control de ambientes</p>
        </div>
        <div class="user-badge">
            👤 <?= ucfirst($rol) ?>
        </div>
    </div>

    <!-- TARJETAS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-label">TOTAL AMBIENTES</div>
            <div class="stat-value"><?= $total_ambientes ?></div>
        </div>

        <div class="stat-card success">
            <div class="stat-label">DISPONIBLES AHORA</div>
            <div class="stat-value"><?= $disponibles_ahora ?></div>
        </div>

        <div class="stat-card info">
            <div class="stat-label">AUTORIZACIONES DEL MES</div>
            <div class="stat-value"><?= $autorizaciones_mes ?></div>
        </div>

    </div>

    <!-- ACCIONES -->
<div class="actions-container">
    <h2 class="actions-title">Acciones disponibles</h2>

    <div class="menu-grid">
        <a href="consultar.php" class="menu-card">
            <div class="menu-card-icon">📄</div>
            <div class="menu-card-title">Consultar historial</div>
            <div class="menu-card-description">
                Revisa el historial completo de permisos y uso de ambientes
            </div>
        </a>

        <a href="permisos.php" class="menu-card">
            <div class="menu-card-icon">✅</div>
            <div class="menu-card-title">Autorizar ambiente</div>
            <div class="menu-card-description">
                Gestiona y autoriza solicitudes de acceso a ambientes
            </div>
        </a>

        <a href="../logout.php" class="menu-card logout">
            <div class="menu-card-icon">🔐</div>
            <div class="menu-card-title">Cerrar sesión</div>
            <div class="menu-card-description">
                Sal de forma segura del sistema
            </div>
        </a>
    </div>
</div>

</body>
</html>
