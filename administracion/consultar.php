<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* Fecha y hora actual (Colombia/Bogotá) */
$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');

/* =========================
   BUSCAR AMBIENTE
   ========================= */
$ambienteBuscado = $_GET['ambiente'] ?? null;
$ambienteInfo = null;
$historialAmbiente = null;

if ($ambienteBuscado) {
    $ambienteBuscado = mysqli_real_escape_string($conexion, $ambienteBuscado);
    
    /* Buscar info del ambiente */
    $sqlAmb = "SELECT * FROM ambientes WHERE nombre_ambiente LIKE '%$ambienteBuscado%'";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambienteInfo = mysqli_fetch_assoc($resAmb);
    
    /* Si se encontró, traer su historial */
    if($ambienteInfo){
        $id_ambiente = $ambienteInfo['id'];
        $sqlHist = "SELECT 
                        au.*,
                        i.nombre AS nombre_instructor,
                        a.nombre_ambiente
                    FROM autorizaciones_ambientes au
                    JOIN instructores i ON au.id_instructor = i.id
                    JOIN ambientes a ON au.id_ambiente = a.id
                    WHERE au.id_ambiente = '$id_ambiente'
                    ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                    LIMIT 50";
        $historialAmbiente = mysqli_query($conexion, $sqlHist);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Ambiente - Administración</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Consultar Ambiente</h1>
            <span>Buscar y gestionar permisos</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="consultar-container">

    <!-- ========================= BUSCAR AMBIENTE ========================= -->
    <div class="search-section">
        <h3><i class="fa-solid fa-magnifying-glass"></i> Buscar Ambiente</h3>
        <form method="GET" class="search-form">
            <input 
                type="text" 
                name="ambiente" 
                placeholder="Ej: 308, Laboratorio de Química, Sala 101..." 
                value="<?= htmlspecialchars($ambienteBuscado ?? '') ?>" 
                required
            >
            <button type="submit">
                <i class="fa-solid fa-search"></i> Buscar
            </button>
        </form>
    </div>

    <!-- ========================= RESULTADO DE BÚSQUEDA ========================= -->
    <?php if ($ambienteBuscado && $ambienteInfo): ?>
        <div class="ambiente-result">
            <h3 style="margin: 0 0 20px 0; color: #333;">
                <i class="fa-solid fa-door-open" style="color:#355d91;"></i> 
                Información del Ambiente
            </h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nombre</label>
                    <span><?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?></span>
                </div>
                <div class="info-item">
                    <label>Estado</label>
                    <span class="estado-badge estado-<?= strtolower($ambienteInfo['estado']) ?>">
                        <?= htmlspecialchars($ambienteInfo['estado']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Horario Fijo</label>
                    <span><?= htmlspecialchars($ambienteInfo['horario_fijo'] ?: 'No definido') ?></span>
                </div>
                <div class="info-item">
                    <label>Horario Disponible</label>
                    <span><?= htmlspecialchars($ambienteInfo['horario_disponible'] ?: 'No definido') ?></span>
                </div>
            </div>
            
            <?php if($ambienteInfo['descripcion_general']): ?>
            <div class="descripcion-ambiente">
                <strong>Descripción:</strong>
                <p><?= htmlspecialchars($ambienteInfo['descripcion_general']) ?></p>
            </div>
            <?php endif; ?>

            <!-- BOTONES DE ACCIÓN -->
            <div class="action-buttons">
                <!-- BOTÓN DE AUTORIZAR: Solo si está Habilitado -->
                <?php if($ambienteInfo['estado'] == 'Habilitado'): ?>
                    <a href="permisos.php?id_ambiente=<?= $ambienteInfo['id'] ?>" class="btn-permiso">
                        <i class="fa-solid fa-circle-check"></i> Autorizar Ambiente
                    </a>
                <?php else: ?>
                    <div class="alert-disabled">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <p>Este ambiente está <strong><?= htmlspecialchars($ambienteInfo['estado']) ?></strong></p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ========================= HISTORIAL DEL AMBIENTE ========================= -->
        <?php if($historialAmbiente && mysqli_num_rows($historialAmbiente) > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left"></i> 
                    Historial de "<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>"
                </h3>
            </div>
            <div class="table-scroll-wrapper"> 

                <table>
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Horario</th>
                            <th>Estado Actual</th>
                            <th>Autorizado Por</th>
                            <th>Novedades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($historialAmbiente)): 
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
                            <td><?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></td>
                            <td>
                                <?= date('h:i A', strtotime($row['hora_inicio'])) ?> - 
                                <?= date('h:i A', strtotime($row['hora_final'])) ?>
                            </td>
                            <td>
                                <span class="estado-badge estado-<?= $estadoActual ?>">
                                    <?= $iconoEstado ?> <?= $textoEstado ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
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
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>    
        </div>
        <?php else: ?>
        <div class="table-container">
            <div class="no-results">
                <i class="fa-solid fa-inbox"></i>
                <p>Este ambiente no tiene historial de autorizaciones</p>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($ambienteBuscado && !$ambienteInfo): ?>
        <div class="ambiente-result">
            <div class="no-results">
                <i class="fa-solid fa-circle-xmark"></i>
                <p>No se encontró el ambiente "<?= htmlspecialchars($ambienteBuscado) ?>"</p>
                <small>Intenta con otro nombre o revisa la escritura</small>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================= BOTÓN VOLVER ========================= -->
    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>


<script>
function verNovedades(btn) {
    const modal = btn.nextElementSibling;
    const allModals = document.querySelectorAll('.novedades-modal');
    
    // Cerrar todos
    allModals.forEach(m => {
        if(m !== modal) m.style.display = 'none';
    });
    
    // Toggle
    if(modal.style.display === 'none') {
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