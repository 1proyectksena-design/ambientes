<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'guarda') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Seguridad - Guarda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/guarda.css">
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA">
        <div class="header-info">
            <h1>Panel de Seguridad</h1>
            <p>Control de Acceso a Ambientes</p>
        </div>
    </div>
    <div class="header-badge">
        <i class="fa-solid fa-shield-halved"></i>
        Guarda De Seguridad
        <a href="../logout.php" class="btn-logout-header" title="Cerrar Sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<!-- CONTENEDOR PRINCIPAL -->
<div class="dashboard-container">

    <div class="actions-container">
        <h2 class="actions-title">
            <i class="fa-solid fa-list-check"></i>
            Opciones de Verificación
        </h2>

        <div class="menu-grid">

            <!-- ESCANEAR QR -->
            <div class="menu-card qr-card" onclick="abrirScanner()">
                <div class="menu-card-icon">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h3 class="menu-card-title">Escanear QR</h3>
                <p class="menu-card-description">
                    Usa la cámara para escanear el código QR del ambiente
                </p>
            </div>

            <!-- BÚSQUEDA MANUAL -->
            <div class="menu-card search-card">
                <div class="menu-card-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3 class="menu-card-title">Buscar Ambiente</h3>
                <p class="menu-card-description">
                    Busca manualmente por número o nombre del ambiente
                </p>

                <?php if(isset($_SESSION['error_busqueda'])): ?>
                <div class="error-message">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error_busqueda']) ?>
                </div>
                <?php
                    unset($_SESSION['error_busqueda']);
                endif; ?>

                <form method="POST" action="buscar_ambiente.php" class="search-form">
                    <input
                        type="text"
                        name="nombre_ambiente"
                        placeholder="Ej: 308, Lab Química..."
                        required
                    >
                    <button type="submit" class="btn-search">
                        <i class="fa-solid fa-search"></i>
                        Buscar
                    </button>
                </form>
            </div>

        </div>
    </div>

</div>

<!-- FOOTER OSCURO DORADO -->
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
                <li><a href="#">Escanear QR</a></li>
                <li><a href="#">Buscar Ambiente</a></li>
                <li><a href="#">Panel de Seguridad</a></li>
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

<!-- MODAL SCANNER QR -->
<div class="modal-overlay" id="scannerModal">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarScanner()">&times;</button>
        <h3 class="modal-title">
            <i class="fa-solid fa-qrcode"></i> Escanear Código QR
        </h3>
        <div id="reader"></div>
        <div class="scanner-status ready" id="scannerStatus">
            📷 Apunta la cámara al código QR
        </div>
    </div>
</div>

<!-- HTML5 QR Code Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let html5QrCode = null;

function abrirScanner() {
    document.getElementById('scannerModal').classList.add('active');
    document.getElementById('scannerStatus').textContent = '📷 Iniciando cámara...';

    html5QrCode = new Html5Qrcode("reader");

    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        (decodedText) => {
            document.getElementById('scannerStatus').className = 'scanner-status scanning';
            document.getElementById('scannerStatus').textContent = ' QR Detectado! Redirigiendo...';

            html5QrCode.stop().then(() => {
                window.location.href = decodedText;
            });
        },
        (errorMessage) => {
            // Error normal de escaneo
        }
    )
    .then(() => {
        document.getElementById('scannerStatus').className = 'scanner-status ready';
        document.getElementById('scannerStatus').textContent = ' Cámara lista - Apunta al código QR';
    })
    .catch((err) => {
        document.getElementById('scannerStatus').className = 'scanner-status';
        document.getElementById('scannerStatus').style.background = '#ffebee';
        document.getElementById('scannerStatus').style.color = '#c62828';
        document.getElementById('scannerStatus').textContent = ' Error: ' + err;
    });
}

function cerrarScanner() {
    if(html5QrCode) {
        html5QrCode.stop().then(() => {
            document.getElementById('scannerModal').classList.remove('active');
            html5QrCode = null;
        }).catch((err) => {
            console.error("Error al detener scanner:", err);
        });
    } else {
        document.getElementById('scannerModal').classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        cerrarScanner();
    }
});
</script>

</body>
</html>