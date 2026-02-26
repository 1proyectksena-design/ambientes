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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ==================== HEADER ==================== */
        .header {
            background: linear-gradient(135deg, #2c5282 0%, #2d3e63 100%);
            padding: 25px 30px;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-left img {
            width: 65px;
            height: 65px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .header-info h1 {
            color: white;
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .header-info p {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            margin-top: 2px;
        }

        .header-badge {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-logout-header {
            background: rgba(220, 38, 38, 0.9);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-logout-header:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        /* ==================== CONTAINER ==================== */
        .dashboard-container {
            max-width: 1100px;
            width: 100%;
            margin: 30px auto;
            padding: 0 20px;
            flex: 1;
        }

        /* ==================== ACTIONS ==================== */
        .actions-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .actions-title {
            color: #2c5282;
            font-size: clamp(1.2rem, 3.5vw, 1.5rem);
            font-weight: 700;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .menu-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 35px 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .menu-card:hover::before {
            left: 100%;
        }

        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            border-color: #4e8799;
        }

        .menu-card-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            box-shadow: 0 6px 20px rgba(67, 160, 71, 0.35);
            transition: transform 0.3s ease;
        }

        .menu-card:hover .menu-card-icon {
            transform: scale(1.1) translateY(-5px);
        }

        .menu-card.search-card .menu-card-icon {
            background: linear-gradient(135deg, #172f63 0%, #355d91 100%);
            box-shadow: 0 6px 20px rgba(30, 136, 229, 0.35);
        }

        .menu-card-title {
            font-size: clamp(1.15rem, 3vw, 1.3rem);
            font-weight: 700;
            margin-bottom: 12px;
            color: #1f2937;
        }

        .menu-card-description {
            font-size: clamp(0.9rem, 2.5vw, 0.95rem);
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* ==================== FORM BÚSQUEDA ==================== */
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .search-form input {
            flex: 1;
            min-width: 200px;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .search-form input:focus {
            outline: none;
            border-color: #1e88e5;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #172f63 0%, #355d91 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 136, 229, 0.4);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        /* ==================== MODAL QR ==================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-title {
            margin-bottom: 20px;
            text-align: center;
            color: #1f2937;
            font-size: 1.3rem;
        }

        #reader {
            border: 3px solid #43a047;
            border-radius: 12px;
            overflow: hidden;
        }

        .scanner-status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }

        .scanner-status.ready {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .scanner-status.scanning {
            background: #fff3e0;
            color: #f57c00;
        }

        /* ==================== FOOTER ==================== */
        .footer {
            background: linear-gradient(135deg, #2c5282 0%, #2d3e63 100%);
            color: white;
            padding: 28px 30px;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: center;
            gap: 16px;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .footer-logo {
            width: 38px;
            height: 38px;
            object-fit: contain;
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
        }

        .footer-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }

        .footer-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            margin: 3px 0 0 0;
        }

        .footer-center {
            text-align: center;
            font-size: 13px;
            color: rgba(255,255,255,0.85);
        }

        .footer-center p {
            margin: 3px 0;
        }

        .footer-year {
            font-size: 11px;
            color: rgba(255,255,255,0.55);
            margin-top: 4px !important;
        }

        .footer-right {
            text-align: right;
            font-size: 12px;
            color: rgba(255,255,255,0.75);
        }

        .footer-right p {
            margin: 2px 0;
        }

        .footer-right strong {
            color: white;
            font-weight: 700;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .header {
                padding: 20px;
                border-radius: 0 0 16px 16px;
            }

            .header-left {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-left img {
                width: 55px;
                height: 55px;
            }

            .dashboard-container {
                padding: 0 15px;
                margin: 20px auto;
            }

            .actions-container {
                padding: 25px 20px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input,
            .btn-search {
                width: 100%;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
            }

            .footer-left {
                grid-column: 1;
                grid-row: 1;
            }

            .footer-right {
                grid-column: 2;
                grid-row: 1;
            }

            .footer-center {
                grid-column: 1 / -1;
                grid-row: 2;
                padding-top: 12px;
                border-top: 1px solid rgba(255,255,255,0.15);
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 15px;
                flex-direction: column;
                text-align: center;
            }

            .header-badge {
                width: 100%;
                justify-content: center;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 14px;
            }

            .footer-left {
                justify-content: center;
            }

            .footer-center {
                border-top: none;
                padding-top: 0;
            }

            .footer-right {
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- HEADER MEJORADO -->
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