<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* Fecha y hora actual */
$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');

/* BUSCAR AMBIENTE */
$ambienteBuscado = $_GET['ambiente'] ?? null;
$ambienteInfo = null;
$historialAmbiente = null;

if ($ambienteBuscado) {
    $ambienteBuscado = mysqli_real_escape_string($conexion, $ambienteBuscado);
    
    /* Buscar info del ambiente + instructor asignado */
    $sqlAmb = "SELECT a.*, 
                      i.nombre          AS nombre_instructor_fijo,
                      i.identificacion  AS doc_instructor_fijo,
                      i.novedades       AS novedades_instructor_fijo
               FROM ambientes a
               LEFT JOIN instructores i ON a.instructor_id = i.id
               WHERE a.nombre_ambiente LIKE '%$ambienteBuscado%'";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambienteInfo = mysqli_fetch_assoc($resAmb);
    
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
    <title>Consultar Ambiente - Subdirección</title>
    
    
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"> 
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Consultar Ambiente</h1>
            <span>Buscar y gestionar permisos</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<div class="consultar-container">

    <!-- BUSCAR -->
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

    <!-- RESULTADO -->
    <?php if ($ambienteBuscado && $ambienteInfo): ?>
        <div class="ambiente-result">
            <div class="result-title-row">
                <h3><i class="fa-solid fa-door-open" style="color:#355d91;"></i> Información del Ambiente</h3>
            </div>
            
            <!-- GRID DE DATOS BÁSICOS -->
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

            <!-- ===== INSTRUCTOR DE HORARIO FIJO ===== -->
            <?php if($ambienteInfo['nombre_instructor_fijo']): ?>
            <div class="instructor-fijo-card">
                <div class="instructor-fijo-header">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <strong>Instructor de Horario Fijo</strong>
                </div>
                <div class="instructor-fijo-body">
                    <div class="instructor-fijo-info">
                        <div class="instr-avatar">
                            <?= strtoupper(substr($ambienteInfo['nombre_instructor_fijo'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="instr-nombre"><?= htmlspecialchars($ambienteInfo['nombre_instructor_fijo']) ?></p>
                            <p class="instr-doc">
                                <i class="fa-solid fa-id-card"></i>
                                <?= htmlspecialchars($ambienteInfo['doc_instructor_fijo']) ?>
                            </p>
                            <?php if($ambienteInfo['novedades_instructor_fijo']): ?>
                            <p class="instr-novedad">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <?= htmlspecialchars($ambienteInfo['novedades_instructor_fijo']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="instructor-fijo-card sin-instructor">
                <i class="fa-solid fa-user-slash"></i>
                <span>Este ambiente no tiene instructor de horario fijo asignado</span>
            </div>
            <?php endif; ?>
            
            <!-- DESCRIPCIÓN -->
            <?php if($ambienteInfo['descripcion_general']): ?>
            <div class="descripcion-ambiente">
                <strong>Descripción:</strong>
                <p><?= htmlspecialchars($ambienteInfo['descripcion_general']) ?></p>
            </div>
            <?php endif; ?>

            <!-- BOTONES DE ACCIÓN -->
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

        <!-- HISTORIAL -->
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
                                        $textoEstado = 'Programado ('.date('h:i A', strtotime($row['hora_inicio'])).' - '.date('h:i A', strtotime($row['hora_final'])).')';
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

                            /* Marcar si esta fila es del instructor fijo */
                            $esFijo = ($ambienteInfo['instructor_id'] && $row['id_instructor'] == $ambienteInfo['instructor_id']);
                            
                            /* EXTRAER FECHA/HORA DE NOVEDAD SI EXISTE */
                            $novedad_texto = $row['novedades'];
                            $fecha_novedad = '';
                            
                            if($novedad_texto && preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*(.*)$/s', $novedad_texto, $matches)){
                                $fecha_novedad = date('d/m/Y h:i A', strtotime($matches[1]));
                                $novedad_texto = $matches[2];
                            } elseif($novedad_texto) {
                                $fecha_novedad = date('d/m/Y h:i A', strtotime($row['fecha_registro']));
                            }
                        ?>
                        <tr <?= $esFijo ? 'class="row-instructor-fijo"' : '' ?>>
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                                <?= htmlspecialchars($row['nombre_instructor']) ?>
                                <?php if($esFijo): ?>
                                    <span class="badge-fijo" title="Instructor de horario fijo">
                                        <i class="fa-solid fa-star"></i> Fijo
                                    </span>
                                <?php endif; ?>
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
                            <td>
                                <?php if($row['novedades']): ?>
                                    <button onclick="mostrarModal(this)" class="btn-ver-novedades">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                    <div class="novedades-modal" style="display:none;">
                                        <div class="modal-header">
                                            <strong>Novedades reportadas por:</strong>
                                            <span class="instructor-name"><?= htmlspecialchars($row['nombre_instructor']) ?></span>
                                            <div style="font-size: 0.85rem; color: #f57c00; margin-top: 4px;">
                                                <i class="fa-regular fa-clock"></i> <?= $fecha_novedad ?>
                                            </div>
                                        </div>
                                        <div class="modal-content">
                                            <pre><?= htmlspecialchars($novedad_texto) ?></pre>
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

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<!-- OVERLAY OSCURO -->
<div class="novedades-overlay" id="modalOverlay"></div>

<script>
function mostrarModal(btn) {
    const modal = btn.nextElementSibling;
    const overlay = document.getElementById('modalOverlay');
    
    // Verificar si está abierto (el modal está visible)
    const estaAbierto = modal.style.display === 'block';
    
    if(estaAbierto) {
        // CERRAR
        overlay.style.display = 'none';
        modal.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
    } else {
        // Primero cerrar todos los demás
        cerrarTodosModales();
        
        // ABRIR este
        overlay.style.display = 'block';
        modal.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Cerrar';
    }
}

function cerrarTodosModales() {
    const overlay = document.getElementById('modalOverlay');
    if(overlay) {
        overlay.style.display = 'none';
    }
    
    // Cerrar todos los modales
    document.querySelectorAll('.novedades-modal').forEach(function(modal) {
        modal.style.display = 'none';
    });
    
    // Resetear todos los botones
    document.querySelectorAll('.btn-ver-novedades').forEach(function(btn) {
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
    });
}

// Cerrar al hacer click en el overlay
document.addEventListener('click', function(e) {
    if(e.target && e.target.id === 'modalOverlay') {
        cerrarTodosModales();
    }
});

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        cerrarTodosModales();
    }
});
</script>

</body>
</html>