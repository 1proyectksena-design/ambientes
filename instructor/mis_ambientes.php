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
$ambientes_hoy = null;

/* REPORTAR NOVEDAD */
if(isset($_POST['reportar_novedad'])){
    $id_autorizacion = mysqli_real_escape_string($conexion, $_POST['id_autorizacion']);
    $novedad = mysqli_real_escape_string($conexion, $_POST['novedad_texto']);
    
    $sqlUpdate = "UPDATE autorizaciones_ambientes 
                  SET novedades = CONCAT(COALESCE(novedades, ''), '\n[".date('Y-m-d H:i')."] ', '$novedad')
                  WHERE id = '$id_autorizacion'";
    
    if(mysqli_query($conexion, $sqlUpdate)){
        echo "<script>alert('✅ Novedad reportada correctamente');</script>";
    } else {
        echo "<script>alert('❌ Error al reportar novedad');</script>";
    }
}

if($identificacion_buscada){
    $identificacion_buscada = mysqli_real_escape_string($conexion, $identificacion_buscada);
    
    /* Buscar instructor */
    $sqlInst = "SELECT * FROM instructores WHERE identificacion = '$identificacion_buscada'";
    $resInst = mysqli_query($conexion, $sqlInst);
    $instructor_info = mysqli_fetch_assoc($resInst);
    
    /* Buscar ambientes de HOY */
    if($instructor_info){
        $hoy = date('Y-m-d');
        $hora_actual = date('H:i:s');
        
        $sqlAmb = "SELECT 
                        au.*,
                        a.nombre_ambiente,
                        a.estado AS estado_ambiente
                    FROM autorizaciones_ambientes au
                    JOIN ambientes a ON au.id_ambiente = a.id
                    WHERE au.id_instructor = '".$instructor_info['id']."'
                    AND au.fecha_inicio <= '$hoy'
                    AND au.fecha_fin >= '$hoy'
                    AND au.estado = 'Aprobado'
                    ORDER BY au.hora_inicio ASC";
        
        $ambientes_hoy = mysqli_query($conexion, $sqlAmb);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Ambientes Hoy</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Mis Ambientes Hoy</h1>
            <span>Consulta y reporta novedades</span>
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
        <div class="ambiente-result">
            <h3 style="margin: 0 0 20px 0; color: #333;">
                <i class="fa-solid fa-user-tie" style="color:#355d91;"></i> 
                Información Personal
            </h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nombre</label>
                    <span><?= htmlspecialchars($instructor_info['nombre']) ?></span>
                </div>
                <div class="info-item">
                    <label>Identificación</label>
                    <span><?= htmlspecialchars($instructor_info['identificacion']) ?></span>
                </div>
                <div class="info-item">
                    <label>Fecha Inicio</label>
                    <span><?= date('d/m/Y', strtotime($instructor_info['fecha_inicio'])) ?></span>
                </div>
                <div class="info-item">
                    <label>Fecha Fin</label>
                    <span><?= $instructor_info['fecha_fin'] ? date('d/m/Y', strtotime($instructor_info['fecha_fin'])) : 'Indefinido' ?></span>
                </div>
            </div>
        </div>

        <!-- AMBIENTES ASIGNADOS HOY -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-calendar-check"></i> 
                    Ambientes Asignados - <?= date('d/m/Y') ?>
                </h3>
            </div>
            
            <?php if($ambientes_hoy && mysqli_num_rows($ambientes_hoy) > 0): ?>
            
                <?php while($amb = mysqli_fetch_assoc($ambientes_hoy)): 
                    $activo = (strtotime(date('H:i:s')) >= strtotime($amb['hora_inicio']) && 
                               strtotime(date('H:i:s')) <= strtotime($amb['hora_final']));
                ?>
                
                <div class="ambiente-card <?= $activo ? 'activo' : '' ?>">
                    <div class="amb-header">
                        <h4>
                            <i class="fa-solid fa-door-open"></i>
                            <?= htmlspecialchars($amb['nombre_ambiente']) ?>
                        </h4>
                        <?php if($activo): ?>
                            <span class="badge-activo">🟢 En Uso Ahora</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="amb-info">
                        <div class="amb-detail">
                            <i class="fa-regular fa-clock"></i>
                            <strong>Horario:</strong>
                            <?= date('h:i A', strtotime($amb['hora_inicio'])) ?> - 
                            <?= date('h:i A', strtotime($amb['hora_final'])) ?>
                        </div>
                        
                        <?php if($amb['observaciones']): ?>
                        <div class="amb-detail">
                            <i class="fa-solid fa-comment"></i>
                            <strong>Observaciones:</strong>
                            <?= htmlspecialchars($amb['observaciones']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($amb['novedades']): ?>
                        <div class="amb-detail novedades-previas">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <strong>Novedades Reportadas:</strong>
                            <pre><?= htmlspecialchars($amb['novedades']) ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- FORMULARIO DE NOVEDAD -->
                    <div class="novedad-form">
                        <form method="POST" onsubmit="return confirm('¿Confirmar reporte de novedad?');">
                            <input type="hidden" name="id_autorizacion" value="<?= $amb['id'] ?>">
                            <input type="hidden" name="identificacion" value="<?= $identificacion_buscada ?>">
                            <textarea 
                                name="novedad_texto" 
                                placeholder="Reporta cualquier novedad al dejar el ambiente (daños, faltantes, etc.)"
                                rows="3"
                                required
                            ></textarea>
                            <button type="submit" name="reportar_novedad" class="btn-reportar">
                                <i class="fa-solid fa-paper-plane"></i> Reportar Novedad
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php endwhile; ?>
                
            <?php else: ?>
                <div class="no-results">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>No tienes ambientes asignados para hoy</p>
                </div>
            <?php endif; ?>
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
.ambiente-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.ambiente-card.activo {
    border-color: #43a047;
    background: linear-gradient(135deg, #f1f8f4 0%, #ffffff 100%);
    box-shadow: 0 4px 15px rgba(67, 160, 71, 0.2);
}

.amb-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
}

.amb-header h4 {
    margin: 0;
    color: #333;
    font-size: 1.3rem;
}

.badge-activo {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
}

.amb-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.amb-detail {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: #555;
    font-size: 0.95rem;
}

.amb-detail i {
    color: #355d91;
    margin-top: 2px;
}

.novedades-previas {
    background: #fff3e0;
    padding: 12px;
    border-radius: 8px;
    border-left: 4px solid #fb8c00;
}

.novedades-previas pre {
    margin: 8px 0 0 0;
    font-family: inherit;
    white-space: pre-wrap;
    color: #666;
}

.novedad-form {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-top: 15px;
}

.novedad-form textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
    margin-bottom: 10px;
}

.novedad-form textarea:focus {
    outline: none;
    border-color: #667eea;
}

.btn-reportar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-reportar:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
</style>

</body>
</html>