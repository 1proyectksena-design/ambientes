<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin SENA - Demo Premium</title>
    <style> 
        /* ========================================
        VERSIÓN PREMIUM - CON ESTADÍSTICAS
        DISEÑO ACTUALIZADO Y RESPONSIVE
        ======================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --success: #43a047;
            --warning: #fb8c00;
            --danger: #e53935;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-700: #374151;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            margin: 0;
        }

        /* ========================================
        HEADER PRINCIPAL
        ======================================== */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-md);
            animation: slideDown 0.6s ease-out;
            flex-wrap: wrap;
            gap: 15px;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            min-width: 0;
        }

        .header-left img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }

        .header-left img:hover {
            transform: scale(1.1) rotate(5deg);
        }

        .header-title {
            min-width: 0;
        }

        .header-left h1 {
            color: white;
            font-size: clamp(20px, 4vw, 28px);
            margin: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header-left p {
            color: rgba(255,255,255,0.9);
            font-size: clamp(12px, 2.5vw, 14px);
            margin: 5px 0 0 0;
        }

        .user-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: clamp(12px, 2.5vw, 14px);
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ========================================
        CONTENEDOR PRINCIPAL
        ======================================== */
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* ========================================
        TARJETAS DE ESTADÍSTICAS
        ======================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.15));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.3rem, 3vw, 1.6rem);
            margin-bottom: 15px;
        }

        .stat-label {
            color: #666;
            font-size: clamp(12px, 2.5vw, 14px);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            color: var(--gray-700);
            font-size: clamp(28px, 6vw, 36px);
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Variaciones de colores para estadísticas */
        .stat-card.success .stat-value {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.warning .stat-value {
            background: linear-gradient(135deg, #fb8c00 0%, #ffa726 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.info .stat-value {
            background: linear-gradient(135deg, #00acc1 0%, #26c6da 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ========================================
        CONTENEDOR DE ACCIONES
        ======================================== */
        .actions-container {
            background: white;
            padding: 35px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            animation: fadeInUp 0.8s ease-out 0.3s both;
        }

        .actions-title {
            color: var(--gray-700);
            font-size: clamp(1.2rem, 3.5vw, 1.5rem);
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-100);
        }

        /* ========================================
        MENÚ DE TARJETAS
        ======================================== */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr));
            gap: 20px;
        }

        .menu-card {
            background: white;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 25px;
            text-decoration: none;
            color: var(--gray-700);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: block;
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .menu-card:nth-child(1) { animation-delay: 0.1s; }
        .menu-card:nth-child(2) { animation-delay: 0.2s; }
        .menu-card:nth-child(3) { animation-delay: 0.3s; }
        .menu-card:nth-child(4) { animation-delay: 0.4s; }
        .menu-card:nth-child(5) { animation-delay: 0.5s; }
        .menu-card:nth-child(6) { animation-delay: 0.6s; }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s ease;
        }

        .menu-card:hover::before {
            left: 100%;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .menu-card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.5rem, 4vw, 1.8rem);
            color: white;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }

        .menu-card:hover .menu-card-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .menu-card-title {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-700);
            transition: color 0.3s ease;
        }

        .menu-card:hover .menu-card-title {
            color: var(--primary);
        }

        .menu-card-description {
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            color: #6b7280;
            line-height: 1.5;
            transition: color 0.3s ease;
        }

        .menu-card:hover .menu-card-description {
            color: #555;
        }

        /* ========================================
        ESTILO ESPECIAL PARA CERRAR SESIÓN
        ======================================== */
        .menu-card.logout {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-color: #ef9a9a;
        }

        .menu-card.logout .menu-card-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .menu-card.logout:hover {
            background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%);
            border-color: var(--danger);
        }

        .menu-card.logout:hover .menu-card-title {
            color: var(--danger);
        }

        /* ========================================
        RESPONSIVE
        ======================================== */

        /* Tablets */
        @media (max-width: 1024px) {
            .admin-container {
                padding: 25px 20px;
            }
            
            .actions-container {
                padding: 30px 25px;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                padding: 15px 20px;
                flex-direction: column;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }
            
            .header-left img {
                width: 45px;
                height: 45px;
            }
            
            .user-badge {
                width: 100%;
                justify-content: center;
            }
            
            .admin-container {
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 25px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .actions-container {
                padding: 25px 20px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        /* Móviles */
        @media (max-width: 480px) {
            .admin-header {
                padding: 12px 15px;
            }
            
            .header-left img {
                width: 40px;
                height: 40px;
            }
            
            .admin-container {
                padding: 15px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 18px 15px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
            }
            
            .actions-container {
                padding: 20px 15px;
                border-radius: 15px;
            }
            
            .menu-grid {
                gap: 12px;
            }
            
            .menu-card {
                padding: 20px;
            }
            
            .menu-card-icon {
                width: 55px;
                height: 55px;
            }
        }

        /* Móviles muy pequeños */
        @media (max-width: 360px) {
            .admin-header {
                padding: 10px 12px;
            }
            
            .admin-container {
                padding: 12px 10px;
            }
            
            .stat-card {
                padding: 15px 12px;
            }
            
            .actions-container {
                padding: 18px 12px;
            }
            
            .menu-card {
                padding: 18px 15px;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <!-- Header con información del usuario -->
    <div class="admin-header">
        <div class="header-left">
            <h1>Panel de Administración</h1>
            <p>Gestiona ambientes y permisos del sistema</p>
        </div>
        <div class="user-badge">
            👤 Admin Usuario
        </div>
    </div>
    
    <!-- Tarjetas de estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-label">Ambientes totales</div>
            <div class="stat-value">12</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Permisos otorgados</div>
            <div class="stat-value">45</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-label">Pendientes</div>
            <div class="stat-value">3</div>
        </div>
    </div>
    
    <!-- Menú de acciones -->
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
                <div class="menu-card-icon">🚪</div>
                <div class="menu-card-title">Cerrar sesión</div>
                <div class="menu-card-description">
                    Sal de forma segura del sistema
                </div>
            </a>
        </div>
    </div>
</div>

<script>
    // Animaciones suaves
    document.addEventListener('DOMContentLoaded', function() {
        // Contador animado para las estadísticas
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach(stat => {
            const target = parseInt(stat.textContent);
            let current = 0;
            const increment = target / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    stat.textContent = target;
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current);
                }
            }, 30);
        });
        
        // Prevenir navegación en demo
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                if (link.getAttribute('href') === '#') {
                    e.preventDefault();
                    alert('Esta es una demo. En la versión real navegarás a: ' + link.querySelector('.menu-card-title').textContent);
                }
            });
        });
    });
</script>

</body>
</html>