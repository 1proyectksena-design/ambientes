<?php
session_start();

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

$usuario = $_SESSION['usuario'] ?? 'Instructor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Instructor</title>
    <link rel="stylesheet" href="../css/instructor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        <i class="fa-solid fa-chalkboard-user user-icon"></i>
        <?= htmlspecialchars($usuario) ?>
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<div class="dashboard-container">
    <div class="actions-container">
        <h2 class="actions-title">Acciones disponibles</h2>
        <div class="menu-grid">
            <a href="mis_ambientes.php" class="menu-card primary">
                <div class="menu-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="menu-card-title">Mis Ambientes Hoy</div>
                <div class="menu-card-description">Ver ambientes asignados y reportar novedades</div>
            </a>
            <a href="historial_ambiente.php" class="menu-card secondary">
                <div class="menu-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="menu-card-title">Historial de Ambientes</div>
                <div class="menu-card-description">Consultar historial de uso por ambiente</div>
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