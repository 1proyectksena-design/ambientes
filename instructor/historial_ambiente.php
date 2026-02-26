<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$nombre_ambiente = $_POST['nombre_ambiente'] ?? null;
$ambiente_info = null;
$historial = null;

/* Fecha y hora actual para calcular estados (Colombia/Bogotá) */
$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');

if($nombre_ambiente){
    $nombre_ambiente = mysqli_real_escape_string($conexion, $nombre_ambiente);
    
    /* Buscar ambiente */
    $sqlAmb = "SELECT * FROM ambientes WHERE nombre_ambiente LIKE '%$nombre_ambiente%' LIMIT 1";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambiente_info = mysqli_fetch_assoc($resAmb);
    
    /* Buscar historial */
    if($ambiente_info){
        $sqlHist = "SELECT 
                        au.*,
                        i.nombre AS nombre_instructor,
                        a.nombre_ambiente
                    FROM autorizaciones_ambientes au
                    JOIN instructores i ON au.id_instructor = i.id
                    JOIN ambientes a ON au.id_ambiente = a.id
                    WHERE au.id_ambiente = '".$ambiente_info['id']."'
                    ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                    LIMIT 50";
        
        $historial = mysqli_query($conexion, $sqlHist);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ambiente</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Historial de Ambiente</h1>
            <span>Consulta de autorizaciones por ambiente</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-chalkboard-user user-icon"></i> Instructor
    </div>
</div>

<div class="consultar-container">

    <!-- BUSCADOR POR AMBIENTE -->
    <div class="search-section">
        <h3><i class="fa-solid fa-building"></i> Buscar Ambiente</h3>
        <form method="POST" class="search-form">
            <input 
                type="text" 
                name="nombre_ambiente" 
                placeholder="Ej: 308, Laboratorio, Sala 101..." 
                value="<?= htmlspecialchars($nombre_ambiente ?? '') ?>" 
                required
            >
            <button type="submit">
                <i class="fa-solid fa-search"></i> Buscar
            </button>
        </form>
    </div>

    <!-- INFORMACIÓN DEL AMBIENTE -->
    <?php if($nombre_ambiente && $ambiente_info): ?>
        <div class="ambiente-result">
            <h3 style="margin: 0 0 20px 0; color: #333;">
                <i class="fa-solid fa-door-open" style="color:#355d91;"></i> 
                Información del Ambiente
            </h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nombre</label>
                    <span><?= htmlspecialchars($ambiente_info['nombre_ambiente']) ?></span>
                </div>
                <div class="info-item">
                    <label>Estado</label>
                    <span class="estado-badge estado-<?= strtolower($ambiente_info['estado']) ?>">
                        <?= htmlspecialchars($ambiente_info['estado']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Horario Fijo</label>
                    <span><?= htmlspecialchars($ambiente_info['horario_fijo'] ?: 'No definido') ?></span>
                </div>
                <div class="info-item">
                    <label>Horario Disponible</label>
                    <span><?= htmlspecialchars($ambiente_info['horario_disponible'] ?: 'No definido') ?></span>
                </div>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left"></i> 
                    Historial de "<?= htmlspecialchars($ambiente_info['nombre_ambiente']) ?>"
                </h3>
            </div>
            
            <?php if($historial && mysqli_num_rows($historial) > 0): ?>
            <div class="table-scroll-wrapper"> 
                <table>
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Período</th>
                            <th>Horario</th>
                            <th>Estado Actual</th>
                            <th>Autorizado Por</th>
                            <th>Observaciones</th>
                            <th>Novedades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($historial)): 
                            /* CALCULAR ESTADO ACTUAL */
                            $estadoActual = 'desocupado';
                            $textoEstado = 'Desocupado';
                            $iconoEstado = '<i class="fa-solid fa-circle"></i>';
                            
                            if($row['estado'] == 'Aprobado') {
                                if($fecha_actual >= $row['fecha_inicio'] && $fecha_actual <= $row['fecha_fin']) {
                                    if($hora_actual >= $row['hora_inicio'] && $hora_actual <= $row['hora_final']) {
                                        $estadoActual = 'ocupado-ahora';
                                        $textoEstado = 'Ocupado Ahora';
                                        $iconoEstado = '<i class="fa-solid fa-circle-dot"></i>';
                                    } else {
                                        $estadoActual = 'programado';
                                        $textoEstado = 'Programado (' . date('h:i A', strtotime($row['hora_inicio'])) . ' - ' . date('h:i A', strtotime($row['hora_final'])) . ')';
                                        $iconoEstado = '<i class="fa-regular fa-clock"></i>';
                                    }
                                }
                            } elseif($row['estado'] == 'Pendiente') {
                                $estadoActual = 'pendiente';
                                $textoEstado = 'Pendiente';
                                $iconoEstado = '<i class="fa-solid fa-hourglass-half"></i>';
                            } elseif($row['estado'] == 'Rechazado') {
                                $estadoActual = 'rechazado';
                                $textoEstado = 'Rechazado';
                                $iconoEstado = '<i class="fa-solid fa-ban"></i>';
                            }
                        ?>
                        <tr>
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                                <?= htmlspecialchars($row['nombre_instructor']) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?><br>
                                <small style="color:#999;">al <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></small>
                            </td>
                            <td>
                                <?= date('h:i A', strtotime($row['hora_inicio'])) ?> -<br>
                                <?= date('h:i A', strtotime($row['hora_final'])) ?>
                            </td>
                            <td>
                                <span class="estado-badge estado-<?= $estadoActual ?>">
                                    <?= $iconoEstado ?> <?= $textoEstado ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                            <td><?= htmlspecialchars($row['observaciones'] ?: '—') ?></td>
                            <td style="position: relative;">
                                <?php if($row['novedades']): ?>
                                    <button onclick="verNovedades(this)" class="btn-ver-novedades">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                    <div class="novedades-modal" style="display:none;">
                                        <div class="modal-header">
                                            <strong>Novedades reportadas por:</strong>
                                            <span class="instructor-name"><?= htmlspecialchars($row['nombre_instructor']) ?></span>
                                        </div>
                                        <div class="modal-content">
                                            <pre><?= htmlspecialchars($row['novedades']) ?></pre>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#999;">Sin novedades</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>    
            <?php else: ?>
            <div class="no-results">
                <i class="fa-solid fa-inbox"></i>
                <p>No hay historial para este ambiente</p>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif($nombre_ambiente && !$ambiente_info): ?>
        <div class="ambiente-result">
            <div class="no-results">
                <i class="fa-solid fa-building-slash"></i>
                <p>No se encontró el ambiente "<?= htmlspecialchars($nombre_ambiente) ?>"</p>
                <small>Intenta con otro nombre o revisa la escritura</small>
            </div>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
/* ========== ESTADOS OCUPADO/DESOCUPADO ========== */
.estado-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.estado-badge i {
    font-size: 10px;
}

/* OCUPADO AHORA - Verde brillante */
.estado-badge.estado-ocupado-ahora {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(67, 160, 71, 0.3);
    animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
    0%, 100% { box-shadow: 0 2px 8px rgba(67, 160, 71, 0.3); }
    50% { box-shadow: 0 4px 16px rgba(67, 160, 71, 0.5); }
}

/* PROGRAMADO - Naranja */
.estado-badge.estado-programado {
    background: #fff3e0;
    color: #e65100;
    border: 2px solid #fb8c00;
}

/* DESOCUPADO - Gris */
.estado-badge.estado-desocupado {
    background: #f5f5f5;
    color: #757575;
    border: 2px solid #e0e0e0;
}

/* PENDIENTE - Amarillo */
.estado-badge.estado-pendiente {
    background: #fff3e0;
    color: #f57c00;
    border: 2px solid #ffa726;
}

/* RECHAZADO - Rojo */
.estado-badge.estado-rechazado {
    background: #ffebee;
    color: #c62828;
    border: 2px solid #e53935;
}

.btn-ver-novedades {
    background: #667eea;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-ver-novedades:hover {
    background: #5568d3;
}

.novedades-modal {
    position: absolute;
    top: 40px;
    right: 0;
    background: white;
    border: 2px solid #fb8c00;
    border-radius: 12px;
    padding: 0;
    min-width: 350px;
    max-width: 450px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 100;
}

.modal-header {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    padding: 12px 15px;
    border-bottom: 2px solid #fb8c00;
    border-radius: 10px 10px 0 0;
}

.modal-header strong {
    display: block;
    color: #e65100;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.instructor-name {
    color: #333;
    font-weight: 600;
    font-size: 1.05rem;
}

.modal-content {
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.modal-content pre {
    margin: 0;
    white-space: pre-wrap;
    font-family: inherit;
    font-size: 0.9rem;
    color: #333;
    line-height: 1.6;
}
</style>

<script>
function verNovedades(btn) {
    const modal = btn.nextElementSibling;
    const allModals = document.querySelectorAll('.novedades-modal');
    
    // Cerrar todos los demás
    allModals.forEach(m => {
        if(m !== modal) m.style.display = 'none';
    });
    
    // Toggle
    if(modal.style.display === 'none'){
        modal.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar';
    } else {
        modal.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
    }
}

// Cerrar al hacer click fuera
document.addEventListener('click', function(e) {
    if(!e.target.closest('td')) {
        document.querySelectorAll('.novedades-modal').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.btn-ver-novedades').forEach(b => {
            b.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
        });
    }
});
</script>

</body>
</html>