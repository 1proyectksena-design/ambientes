<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin SENA - Demo Premium</title>
    <link rel="stylesheet" href="../css/admin_styles.css">
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