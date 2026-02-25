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
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Panel de Seguridad</h1>
            <span>Control de Acceso a Ambientes</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-shield-halved user-icon"></i>
        Guarda de Seguridad
        <a href="../logout.php" class="btn-logout-header" title="Cerrar Sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<!-- CONTENEDOR PRINCIPAL -->
<div class="dashboard-container">

    <!-- ACCIONES PRINCIPALES -->
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

<!-- FOOTER -->
<div class="footer">
    <div class="footer-content">
        <div class="footer-left">
            <img src="../css/img/senab.png" alt="Logo SENA" class="footer-logo">
            <div class="footer-brand">
                <p class="footer-title">SENA</p>
                <p class="footer-sub">Centro de Industria y Servicios</p>
            </div>
        </div>
        
        <div class="footer-center">
            <p>Sistema de Gestión de Ambientes</p>
            <p class="footer-year">© <?= date('Y') ?> - Todos los derechos reservados</p>
        </div>
        
        <div class="footer-right">
            <p><strong>Panel:</strong> Guarda de Seguridad</p>
            <p>Control de Acceso</p>
        </div>
    </div>
</div>

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
            document.getElementById('scannerStatus').textContent = '✅ QR Detectado! Redirigiendo...';
            
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
        document.getElementById('scannerStatus').textContent = '📷 Cámara lista - Apunta al código QR';
    })
    .catch((err) => {
        document.getElementById('scannerStatus').className = 'scanner-status';
        document.getElementById('scannerStatus').style.background = '#ffebee';
        document.getElementById('scannerStatus').style.color = '#c62828';
        document.getElementById('scannerStatus').textContent = '❌ Error: ' + err;
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