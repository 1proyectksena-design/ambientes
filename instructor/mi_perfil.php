<?php
session_start();
if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* Buscar por identificación */
$identificacion_buscada = $_POST['identificacion'] ?? null;
$instructor_info = null;
$stats = null;

if($identificacion_buscada){
    $identificacion_buscada = mysqli_real_escape_string($conexion, $identificacion_buscada);
    
    /* Buscar instructor */
    $sqlInst = "SELECT * FROM instructores WHERE identificacion = '$identificacion_buscada'";
    $resInst = mysqli_query($conexion, $sqlInst);
    $instructor_info = mysqli_fetch_assoc($resInst);
    
    /* Estadísticas del instructor */
    if($instructor_info){
        $id_instructor = $instructor_info['id'];
        
        /* Total autorizaciones */
        $total_auth = mysqli_fetch_row(mysqli_query($conexion, 
            "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE id_instructor='$id_instructor'"))[0];
        
        /* Autorizaciones activas */
        $hoy = date('Y-m-d');
        $activas = mysqli_fetch_row(mysqli_query($conexion, 
            "SELECT COUNT(*) FROM autorizaciones_ambientes 
             WHERE id_instructor='$id_instructor' 
             AND fecha_inicio <= '$hoy' 
             AND fecha_fin >= '$hoy'
             AND estado='Aprobado'"))[0];
        
        /* Ambientes únicos usados */
        $ambientes_unicos = mysqli_fetch_row(mysqli_query($conexion, 
            "SELECT COUNT(DISTINCT id_ambiente) FROM autorizaciones_ambientes 
             WHERE id_instructor='$id_instructor'"))[0];
        
        /* Novedades reportadas */
        $novedades_count = mysqli_fetch_row(mysqli_query($conexion, 
            "SELECT COUNT(*) FROM autorizaciones_ambientes 
             WHERE id_instructor='$id_instructor' 
             AND novedades IS NOT NULL 
             AND novedades != ''"))[0];
        
        $stats = [
            'total' => $total_auth,
            'activas' => $activas,
            'ambientes' => $ambientes_unicos,
            'novedades' => $novedades_count
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Mi Perfil</h1>
            <span>Información personal y estadísticas</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-chalkboard-user user-icon"></i> Instructor
    </div>
</div>

<div class="consultar-container">

    <!-- BUSCADOR POR IDENTIFICACIÓN -->
    <div class="search-section">
        <h3><i class="fa-solid fa-id-card"></i> Ingresa tu Identificación</h3>
        <form method="POST" class="search-form">
            <input 
                type="text" 
                name="identificacion" 
                placeholder="Ej: 1234567890" 
                value="<?= htmlspecialchars($identificacion_buscada ?? '') ?>" 
                required
            >
            <button type="submit">
                <i class="fa-solid fa-search"></i> Buscar
            </button>
        </form>
    </div>

    <!-- INFORMACIÓN DEL INSTRUCTOR -->
    <?php if($identificacion_buscada && $instructor_info): ?>
        
        <!-- PERFIL -->
        <div class="perfil-card">
            <div class="perfil-header">
                <div class="avatar">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div class="perfil-info">
                    <h2><?= htmlspecialchars($instructor_info['nombre']) ?></h2>
                    <p class="cedula"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($instructor_info['identificacion']) ?></p>
                </div>
            </div>
            
            <div class="perfil-detalles">
                <div class="detalle-item">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <div>
                        <strong>Fecha Inicio</strong>
                        <span><?= date('d/m/Y', strtotime($instructor_info['fecha_inicio'])) ?></span>
                    </div>
                </div>
                
                <div class="detalle-item">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <div>
                        <strong>Fecha Fin</strong>
                        <span><?= $instructor_info['fecha_fin'] ? date('d/m/Y', strtotime($instructor_info['fecha_fin'])) : '<span class="indefinido">Indefinido</span>' ?></span>
                    </div>
                </div>
                
                <?php if($instructor_info['novedades']): ?>
                <div class="detalle-item novedades-instructor">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Información Adicional</strong>
                        <span><?= htmlspecialchars($instructor_info['novedades']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="stats-container">
            <h3><i class="fa-solid fa-chart-line"></i> Mis Estadísticas</h3>
            
            <div class="stats-grid-profile">
                <div class="stat-box">
                    <div class="stat-icon total">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div class="stat-data">
                        <div class="stat-number"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total Autorizaciones</div>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon activas">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="stat-data">
                        <div class="stat-number"><?= $stats['activas'] ?></div>
                        <div class="stat-label">Activas Ahora</div>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon ambientes">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div class="stat-data">
                        <div class="stat-number"><?= $stats['ambientes'] ?></div>
                        <div class="stat-label">Ambientes Usados</div>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon novedades">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="stat-data">
                        <div class="stat-number"><?= $stats['novedades'] ?></div>
                        <div class="stat-label">Novedades Reportadas</div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif($identificacion_buscada && !$instructor_info): ?>
        <div class="ambiente-result">
            <div class="no-results">
                <i class="fa-solid fa-user-slash"></i>
                <p>No se encontró instructor con identificación "<?= htmlspecialchars($identificacion_buscada) ?>"</p>
            </div>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
/* PERFIL CARD */
.perfil-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.perfil-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 25px;
}

.avatar {
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    border: 4px solid rgba(255,255,255,0.3);
}

.perfil-info h2 {
    color: white;
    margin: 0 0 8px 0;
    font-size: 1.8rem;
}

.cedula {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin: 0;
}

.perfil-detalles {
    padding: 25px 30px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.detalle-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.detalle-item i {
    font-size: 1.5rem;
    color: #667eea;
    margin-top: 2px;
}

.detalle-item div strong {
    display: block;
    color: #333;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.detalle-item div span {
    color: #666;
    font-size: 1.05rem;
}

.indefinido {
    color: #43a047;
    font-weight: 600;
}

.novedades-instructor {
    background: #fff3e0;
    border-left: 4px solid #fb8c00;
}

.novedades-instructor i {
    color: #fb8c00;
}

/* ESTADÍSTICAS */
.stats-container {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.stats-container h3 {
    color: #333;
    margin: 0 0 20px 0;
    font-size: 1.3rem;
}

.stats-grid-profile {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-icon.total {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.activas {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
}

.stat-icon.ambientes {
    background: linear-gradient(135deg, #00acc1 0%, #26c6da 100%);
}

.stat-icon.novedades {
    background: linear-gradient(135deg, #fb8c00 0%, #f57c00 100%);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #333;
}

.stat-label {
    font-size: 0.85rem;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}

@media (max-width: 768px) {
    .perfil-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid-profile {
        grid-template-columns: 1fr;
    }
}
</style>

</body>
</html>