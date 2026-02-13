<?php
session_start();
include("../includes/conexion.php");

/* =========================
   PROTEGER VISTA SUBDIRECCIÓN
   ========================= */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   ESTADÍSTICAS DASHBOARD
   ========================= */
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$mes = date('m');
$anio = date('Y');

/* Total ambientes ACTIVOS (excluyendo los en mantenimiento) */
$resTotal = mysqli_query($conexion, "
    SELECT COUNT(*) 
    FROM ambientes 
    WHERE estado IN ('disponible', 'ocupado')
");
$total_ambientes = mysqli_fetch_row($resTotal)[0];

/* Ambientes ocupados AHORA */
$resOcupados = mysqli_query($conexion, "
    SELECT COUNT(DISTINCT id_ambiente)
    FROM autorizaciones_ambientes
    WHERE fecha='$hoy'
    AND hora_inicio <= '$hora_actual'
    AND hora_fin >= '$hora_actual'
");
$ocupados_ahora = mysqli_fetch_row($resOcupados)[0];

/* Disponibles ahora */
$disponibles_ahora = $total_ambientes - $ocupados_ahora;

/* Autorizaciones este mes */
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
    <title>Panel Subdirección</title>
    <link rel="stylesheet" href="../css/subdire.css">
    <!-- Font Awesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">

        <div class="header-title">
            <h1>Panel de Subdirección</h1>
            <span>Gestión y control de ambientes</span>
        </div>
    </div>

    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<!-- ========================= DASHBOARD STATS ========================= -->
<div class="dashboard">

    <div class="card">
        <h3>Total Ambientes</h3>
        <p><?= $total_ambientes ?></p>
    </div>

    <div class="card success">
        <h3>Disponibles Ahora</h3>
        <p><?= $disponibles_ahora ?></p>
    </div>

   

    <div class="card info">
        <h3>Autorizaciones del Mes</h3>
        <p><?= $autorizaciones_mes ?></p>
    </div>

</div>

<!-- ========================= MENÚ HORIZONTAL ========================= -->
<div class="menu-horizontal">

    <a href="consultar.php" class="menu-btn">
        <div class="menu-btn-icon">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <div class="text">
            <h3>Consultar</h3>
            <p>Historial y disponibilidad</p>
        </div>
    </a>

    <a href="permisos.php" class="menu-btn">
        <div class="menu-btn-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="text">
            <h3>Autorizar</h3>
            <p>Autorizar uso de ambientes</p>
        </div>
    </a>

  

    <a href="../logout.php" class="menu-btn danger">
        <div class="menu-btn-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <div class="text">
            <h3>Cerrar sesión</h3>
            <p>Salir de forma segura</p>
        </div>
    </a>

</div>

</body>
</html>