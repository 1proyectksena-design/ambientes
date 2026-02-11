<?php
session_start();

/* =========================
   PROTEGER VISTA SUBDIRECCIÓN
   ========================= */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Subdirección</title>
    <link rel="stylesheet" href="../css/subdire.css">
</head>
<body>

<!-- =========================
     HEADER
     ========================= -->
<div class="header">
    <div class="header-left">
        <!-- LOGO INSTITUCIÓN -->
        <img src="../css/img/logo.png" alt="Logo Institución">

        <div class="header-title">
            <h1>Panel de Subdirección</h1>
            <span>Gestión y control de ambientes</span>
        </div>
    </div>

    <div class="header-user">
        Subdirección
    </div>
</div>

<!-- =========================
     MENÚ HORIZONTAL
     ========================= -->
<div class="menu-horizontal">

    <a href="consultar.php" class="menu-btn">
        <div class="text">
            <h3>Consultar</h3>
            <p>Historial y disponibilidad de ambientes</p>
        </div>
    </a>

    <a href="permisos.php" class="menu-btn">
        <div class="text">
            <h3>Autorizar</h3>
            <p>Autorizar uso de ambientes</p>
        </div>
    </a>

    <a href="registro.php" class="menu-btn">
        <div class="text">
            <h3>Registros</h3>
            <p>Ver registros del sistema</p>
        </div>
    </a>

    <a href="../logout.php" class="menu-btn danger">
        <div class="icon">🚪</div>
        <div class="text">
            <h3>Cerrar sesión</h3>
            <p>Salir de forma segura</p>
        </div>
    </a>

</div>

</body>
</html>