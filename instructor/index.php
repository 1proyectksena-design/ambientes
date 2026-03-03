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
    <div class="footer-top-line"></div>
    <div class="footer-container">

        <div class="footer-brand">
            <div class="footer-logo">
                <span>&#94;</span>
            </div>
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
                <li><a href="#">Mis Ambientes Hoy</a></li>
                <li><a href="#">Historial de Ambientes</a></li>
                <li><a href="#">Panel de Instructor</a></li>
            </ul>
        </div>

        <div class="footer-location">
            <span class="footer-section-title">UBICACIÓN</span>
            <ul>
                <li>
                    <span class="footer-icon">&#9679;</span>
                    Centro de Industria y Comercio<br>Villavicencio, Meta — Colombia
                </li>
                <li>
                    <span class="footer-icon">&#9711;</span>
                    Regional Llanos Orientales
                </li>
                <li>
                    <span class="footer-icon">&#9993;</span>
                    sena.edu.co
                </li>
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