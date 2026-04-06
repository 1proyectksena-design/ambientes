<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$fecha_actual = date('Y-m-d');
$hora_actual  = date('H:i:s');

$ambienteBuscado   = $_GET['ambiente'] ?? null;
$ambienteInfo      = null;
$usoActual         = null;
$proximosUsos      = null;
$historialReciente = null;

if ($ambienteBuscado) {
    $ambienteBuscado = mysqli_real_escape_string($conexion, $ambienteBuscado);

    $sqlAmb = "SELECT a.*, 
                      i.nombre          AS nombre_instructor_fijo,
                      i.identificacion  AS doc_instructor_fijo,
                      i.novedades       AS novedades_instructor_fijo
               FROM ambientes a
               LEFT JOIN instructores i ON a.instructor_id = i.id
               WHERE a.nombre_ambiente LIKE '%$ambienteBuscado%'";
    $resAmb       = mysqli_query($conexion, $sqlAmb);
    $ambienteInfo = mysqli_fetch_assoc($resAmb);

    if($ambienteInfo){
        $id_ambiente = $ambienteInfo['id'];
        
        /* USO ACTUAL */
        $sqlUsoActual = "SELECT au.*, i.nombre AS nombre_instructor
                         FROM autorizaciones_ambientes au
                         JOIN instructores i ON au.id_instructor = i.id
                         WHERE au.id_ambiente = '$id_ambiente'
                           AND au.fecha_inicio = '$fecha_actual'
                           AND '$hora_actual' BETWEEN au.hora_inicio AND au.hora_final
                           AND au.estado = 'Aprobado'
                         LIMIT 1";
        $resUsoActual = mysqli_query($conexion, $sqlUsoActual);
        $usoActual = mysqli_fetch_assoc($resUsoActual);

        /* PRÓXIMOS USOS (agrupados) */
        $sqlProximosUsos = "SELECT 
                                MIN(au.fecha_inicio)  AS fecha_inicio,
                                MAX(au.fecha_inicio)  AS fecha_fin,
                                au.hora_inicio,
                                au.hora_final,
                                au.id_instructor,
                                i.nombre              AS nombre_instructor,
                                au.observaciones,
                                GROUP_CONCAT(
                                    DISTINCT DAYOFWEEK(au.fecha_inicio)
                                    ORDER BY DAYOFWEEK(au.fecha_inicio)
                                )                     AS dias_semana
                            FROM autorizaciones_ambientes au
                            JOIN instructores i ON au.id_instructor = i.id
                            WHERE au.id_ambiente = '$id_ambiente'
                              AND (
                                (au.fecha_inicio > '$fecha_actual')
                                OR
                                (au.fecha_inicio = '$fecha_actual' AND au.hora_inicio > '$hora_actual')
                              )
                              AND au.estado = 'Aprobado'
                            GROUP BY au.id_instructor, au.hora_inicio, au.hora_final, au.observaciones
                            ORDER BY MIN(au.fecha_inicio) ASC
                            LIMIT 10";
        $proximosUsos = mysqli_query($conexion, $sqlProximosUsos);

        /* HISTORIAL RECIENTE */
        $sqlHistorialReciente = "SELECT au.*, 
                                        a.nombre_ambiente,
                                        i.nombre AS nombre_instructor
                                 FROM autorizaciones_ambientes au
                                 JOIN ambientes a ON au.id_ambiente = a.id
                                 JOIN instructores i ON au.id_instructor = i.id
                                 WHERE au.id_ambiente = '$id_ambiente'
                                   AND (
                                     (au.fecha_inicio < '$fecha_actual')
                                     OR
                                     (au.fecha_inicio = '$fecha_actual' AND au.hora_final < '$hora_actual')
                                   )
                                 ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                                 LIMIT 10";
        $historialReciente = mysqli_query($conexion, $sqlHistorialReciente);
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

    <div class="search-section">
        <h3><i class="fa-solid fa-magnifying-glass"></i> Buscar Ambiente</h3>
        <form method="GET" class="search-form">
            <input type="text" name="ambiente" placeholder="Ej: 308, Laboratorio de Química, Sala 101..."
                   value="<?= htmlspecialchars($ambienteBuscado ?? '') ?>" required>
            <button type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
        </form>
    </div>

    <?php if ($ambienteBuscado && $ambienteInfo): ?>
        <div class="ambiente-result">
            <div class="result-title-row">
                <h3><i class="fa-solid fa-door-open" style="color:#355d91;"></i> Información del Ambiente</h3>
            </div>

            <div class="info-grid">
                <div class="info-item"><label>Nombre</label><span><?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?></span></div>
                <div class="info-item"><label>Estado</label><span class="estado-badge estado-<?= strtolower($ambienteInfo['estado']) ?>"><?= htmlspecialchars($ambienteInfo['estado']) ?></span></div>
                <div class="info-item"><label>Horario Fijo</label><span><?= htmlspecialchars($ambienteInfo['horario_fijo'] ?: 'No definido') ?></span></div>
                <div class="info-item"><label>Horario Disponible</label><span><?= htmlspecialchars($ambienteInfo['horario_disponible'] ?: 'No definido') ?></span></div>
            </div>

            <!-- ESTADO USO -->
            <div class="estado-uso-card">
                <?php if($usoActual): ?>
                    <div class="estado-header en-uso">
                        <div class="estado-icon"><i class="fa-solid fa-circle-dot"></i></div>
                        <div><h3>EN USO AHORA</h3><p>Este ambiente está siendo utilizado en este momento</p></div>
                    </div>
                    <div class="estado-body">
                        <div class="uso-actual-info">
                            <div class="uso-item">
                                <i class="fa-solid fa-user"></i>
                                <div><label>Instructor</label><strong><?= htmlspecialchars($usoActual['nombre_instructor']) ?></strong></div>
                            </div>
                            <div class="uso-item">
                                <i class="fa-regular fa-clock"></i>
                                <div><label>Horario</label><strong><?= date('h:i A', strtotime($usoActual['hora_inicio'])) ?> - <?= date('h:i A', strtotime($usoActual['hora_final'])) ?></strong></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="estado-header libre">
                        <div class="estado-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div><h3>LIBRE</h3><p>El ambiente está disponible en este momento</p></div>
                    </div>
                    <?php $proximoInmediato = $proximosUsos ? mysqli_fetch_assoc($proximosUsos) : null; if($proximoInmediato): mysqli_data_seek($proximosUsos, 0); ?>
                    <div class="estado-body">
                        <div class="proximo-uso-rapido">
                            <i class="fa-regular fa-calendar-check"></i>
                            <span>Próximo uso: <strong><?= date('d/m/Y', strtotime($proximoInmediato['fecha_inicio'])) ?> a las <?= date('h:i A', strtotime($proximoInmediato['hora_inicio'])) ?></strong></span>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- INSTRUCTOR FIJO -->
            <?php if($ambienteInfo['nombre_instructor_fijo']): ?>
            <div class="instructor-fijo-card">
                <div class="instructor-fijo-header"><i class="fa-solid fa-chalkboard-user"></i><strong>Instructor de Horario Fijo</strong></div>
                <div class="instructor-fijo-body">
                    <div class="instructor-fijo-info">
                        <div class="instr-avatar"><?= strtoupper(substr($ambienteInfo['nombre_instructor_fijo'], 0, 1)) ?></div>
                        <div>
                            <p class="instr-nombre"><?= htmlspecialchars($ambienteInfo['nombre_instructor_fijo']) ?></p>
                            <p class="instr-doc"><i class="fa-solid fa-id-card"></i> <?= htmlspecialchars($ambienteInfo['doc_instructor_fijo']) ?></p>
                            <?php if($ambienteInfo['novedades_instructor_fijo']): ?>
                            <p class="instr-novedad"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($ambienteInfo['novedades_instructor_fijo']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="instructor-fijo-card sin-instructor"><i class="fa-solid fa-user-slash"></i><span>Este ambiente no tiene instructor de horario fijo asignado</span></div>
            <?php endif; ?>

            <?php if($ambienteInfo['descripcion_general']): ?>
            <div class="descripcion-ambiente"><strong>Descripción:</strong><p><?= htmlspecialchars($ambienteInfo['descripcion_general']) ?></p></div>
            <?php endif; ?>

            <div class="action-buttons">
                <?php if($proximosUsos || $historialReciente): ?>
                <a href="exportar_ambiente.php?id=<?= $ambienteInfo['id'] ?>" class="btn-exportar-excel">
                    <i class="fa-solid fa-file-excel"></i> Exportar a Excel
                </a>
                <?php endif; ?>
                <?php if($ambienteInfo['estado'] == 'Habilitado'): ?>
                    <a href="permisos.php?id_ambiente=<?= $ambienteInfo['id'] ?>" class="btn-permiso"><i class="fa-solid fa-circle-check"></i> Autorizar Ambiente</a>
                <?php else: ?>
                    <div class="alert-disabled"><i class="fa-solid fa-triangle-exclamation"></i><p>Este ambiente está <strong><?= htmlspecialchars($ambienteInfo['estado']) ?></strong></p></div>
                <?php endif; ?>
                <a href="editar_ambiente.php?id=<?= $ambienteInfo['id'] ?>" class="btn-action-edit"><i class="fa-solid fa-pen-to-square"></i> Editar</a>
            </div>
        </div>

        <!-- PRÓXIMOS USOS -->
        <?php if($proximosUsos && mysqli_num_rows($proximosUsos) > 0): ?>
        <div class="table-container">
            <div class="table-header"><h3><i class="fa-solid fa-calendar-days"></i> Próximos Usos de "<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>"</h3></div>
            <div class="table-scroll-wrapper">
                <table>
                    <thead><tr><th>Fecha Inicio</th><th>Fecha Fin</th><th>Horario</th><th>Días</th><th>Instructor</th><th>Observaciones</th></tr></thead>
                    <tbody>
                        <?php
                        $abrevDias = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
                        while($prox = mysqli_fetch_assoc($proximosUsos)):
                            $diasNums = ($prox['dias_semana'] !== null && $prox['dias_semana'] !== '') ? explode(',', $prox['dias_semana']) : [];
                            $diasHtml = '';
                            if(count($diasNums) > 0){
                                foreach($diasNums as $dn){
                                    $dn = (int)$dn;
                                    $abrev = $abrevDias[$dn] ?? '?';
                                    $diasHtml .= '<span class="dia-badge">' . $abrev . '</span>';
                                }
                            } else {
                                $diasHtml = '<span style="color:#999;">—</span>';
                            }
                        ?>
                        <tr>
                            <td><span class="cell-fecha"><i class="fa-regular fa-calendar"></i> <?= date('d/m/Y', strtotime($prox['fecha_inicio'])) ?></span></td>
                            <td><span class="cell-fecha"><i class="fa-regular fa-calendar-check"></i> <?= date('d/m/Y', strtotime($prox['fecha_fin'])) ?></span></td>
                            <td><span class="cell-horario"><i class="fa-regular fa-clock"></i> <?= date('h:i A', strtotime($prox['hora_inicio'])) ?> &mdash; <?= date('h:i A', strtotime($prox['hora_final'])) ?></span></td>
                            <td><?= $diasHtml ?></td>
                            <td><i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i><?= htmlspecialchars($prox['nombre_instructor']) ?></td>
                            <td><?= htmlspecialchars($prox['observaciones'] ?: '—') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- HISTORIAL RECIENTE -->
        <?php if($historialReciente && mysqli_num_rows($historialReciente) > 0): ?>
        <div class="table-container">
            <div class="table-header"><h3><i class="fa-solid fa-clock-rotate-left"></i> Historial Reciente de "<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>"</h3></div>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ambiente</th>
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
                        <?php 
                        $modalCounter = 0; // Contador único para cada modal
                        while($hist = mysqli_fetch_assoc($historialReciente)): 
                            // CALCULAR ESTADO ACTUAL
                            $estadoActual = 'desocupado';
                            $textoEstado  = 'Desocupado';
                            $iconoEstado  = '<i class="fa-solid fa-circle"></i>';

                            if($hist['estado'] == 'Aprobado') {
                                if($fecha_actual >= $hist['fecha_inicio'] && $fecha_actual <= $hist['fecha_fin']) {
                                    if($hora_actual >= $hist['hora_inicio'] && $hora_actual <= $hist['hora_final']) {
                                        $estadoActual = 'ocupado-ahora';
                                        $textoEstado  = 'Ocupado Ahora';
                                        $iconoEstado  = '<i class="fa-solid fa-circle-dot"></i>';
                                    } else {
                                        $estadoActual = 'programado';
                                        $textoEstado  = 'Programado';
                                        $iconoEstado  = '<i class="fa-regular fa-clock"></i>';
                                    }
                                }
                            } elseif($hist['estado'] == 'Pendiente') {
                                $estadoActual = 'pendiente';
                                $textoEstado  = 'Pendiente';
                                $iconoEstado  = '<i class="fa-solid fa-hourglass-half"></i>';
                            } elseif($hist['estado'] == 'Rechazado') {
                                $estadoActual = 'rechazado';
                                $textoEstado  = 'Rechazado';
                                $iconoEstado  = '<i class="fa-solid fa-ban"></i>';
                            }

                            // PARSEAR NOVEDAD — con o sin prefijo de fecha
                            $novedad_raw    = trim($hist['novedades'] ?? '');
                            $novedad_texto  = '';
                            $fecha_novedad  = '';

                            if ($novedad_raw !== '') {
                                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*([\s\S]*)$/u', $novedad_raw, $matches)) {
                                    // Formato con prefijo: [YYYY-MM-DD HH:MM] texto
                                    $fecha_novedad = date('d/m/Y h:i A', strtotime($matches[1]));
                                    $novedad_texto = trim($matches[2]);
                                } else {
                                    // Sin prefijo: mostrar tal cual
                                    $novedad_texto = $novedad_raw;
                                    if (!empty($hist['fecha_registro'])) {
                                        $fecha_novedad = date('d/m/Y h:i A', strtotime($hist['fecha_registro']));
                                    }
                                }
                            }

                            // ID único para este modal
                            $modalId = 'modal-nov-' . $modalCounter++;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($hist['nombre_ambiente']) ?></strong></td>
                            <td><i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i><?= htmlspecialchars($hist['nombre_instructor']) ?></td>
                            <td><?= date('d/m/Y', strtotime($hist['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($hist['fecha_fin'])) ?></td>
                            <td><?= date('h:i A', strtotime($hist['hora_inicio'])) ?> - <?= date('h:i A', strtotime($hist['hora_final'])) ?></td>
                            <td><span class="estado-badge estado-<?= $estadoActual ?>"><?= $iconoEstado ?> <?= $textoEstado ?></span></td>
                            <td><span style="text-transform:capitalize; color:#666;"><?= htmlspecialchars($hist['rol_autorizado']) ?></span></td>
                            <td>
                                <?php if($novedad_texto !== ''): ?>
                                    <!-- El botón referencia al modal por ID, eliminando la dependencia de nextElementSibling -->
                                    <button
                                        type="button"
                                        class="btn-ver-novedades"
                                        data-modal="<?= $modalId ?>"
                                        onclick="mostrarModal(this)">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
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

        <!-- =====================================================
             MODALES FUERA DE LA TABLA — evita problemas de DOM,
             z-index y overflow dentro de <td>
        ====================================================== -->
        <?php
        // Reiniciamos el result para recorrer de nuevo y generar los modales
        mysqli_data_seek($historialReciente, 0);
        $modalCounter = 0;
        while($hist = mysqli_fetch_assoc($historialReciente)):
            $novedad_raw   = trim($hist['novedades'] ?? '');
            $novedad_texto = '';
            $fecha_novedad = '';

            if ($novedad_raw !== '') {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*([\s\S]*)$/u', $novedad_raw, $matches)) {
                    $fecha_novedad = date('d/m/Y h:i A', strtotime($matches[1]));
                    $novedad_texto = trim($matches[2]);
                } else {
                    $novedad_texto = $novedad_raw;
                    if (!empty($hist['fecha_registro'])) {
                        $fecha_novedad = date('d/m/Y h:i A', strtotime($hist['fecha_registro']));
                    }
                }
            }

            if ($novedad_texto === '') { $modalCounter++; continue; }
            $modalId = 'modal-nov-' . $modalCounter++;
        ?>
        <div class="novedades-modal" id="<?= $modalId ?>" role="dialog" aria-modal="true" aria-label="Novedad">
            <div class="modal-header">
                <div class="modal-instructor-row">
                    <div class="modal-avatar"><?= strtoupper(substr($hist['nombre_instructor'], 0, 1)) ?></div>
                    <div>
                        <div class="modal-label">Novedad reportada por</div>
                        <div class="modal-instructor-name"><?= htmlspecialchars($hist['nombre_instructor']) ?></div>
                    </div>
                </div>
                <!-- Botón X para cerrar -->
                <button type="button" class="modal-btn-cerrar" onclick="cerrarModal('<?= $modalId ?>')" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <?php if($fecha_novedad): ?>
            <div style="padding: 0 18px;">
                <div class="modal-fecha-badge"><i class="fa-regular fa-calendar-check"></i> <?= $fecha_novedad ?></div>
            </div>
            <?php endif; ?>
            <div class="modal-content">
                <pre><?= htmlspecialchars($novedad_texto) ?></pre>
            </div>
        </div>
        <?php endwhile; ?>

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

    <a href="index.php" class="btn-volver"><i class="fa-solid fa-arrow-left"></i> Volver al Panel</a>

</div>

<!-- Overlay global -->
<div class="novedades-overlay" id="modalOverlay"></div>

<script>
/**
 * Abre el modal cuyo id está en data-modal del botón.
 * Ya no depende de nextElementSibling ni de la posición en el DOM.
 */
function mostrarModal(btn) {
    const modalId = btn.dataset.modal;
    if (!modalId) return;

    const modal   = document.getElementById(modalId);
    const overlay = document.getElementById('modalOverlay');
    if (!modal || !overlay) return;

    const abierto = modal.classList.contains('visible');

    if (abierto) {
        cerrarModal(modalId);
    } else {
        cerrarTodosModales();           // cierra cualquier otro antes de abrir
        overlay.style.display = 'block';
        modal.style.display   = 'block';
        // Pequeño delay para que la transición CSS funcione
        requestAnimationFrame(() => {
            requestAnimationFrame(() => modal.classList.add('visible'));
        });
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Cerrar';
    }
}

/**
 * Cierra un modal específico por su id.
 */
function cerrarModal(modalId) {
    const modal   = document.getElementById(modalId);
    const overlay = document.getElementById('modalOverlay');
    if (!modal) return;

    modal.classList.remove('visible');

    // Espera la transición antes de ocultarlo con display:none
    modal.addEventListener('transitionend', function handler() {
        modal.style.display = 'none';
        modal.removeEventListener('transitionend', handler);
    });

    overlay.style.display = 'none';

    // Restaura el texto del botón que abrió este modal
    const btn = document.querySelector(`[data-modal="${modalId}"]`);
    if (btn) btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
}

/**
 * Cierra todos los modales abiertos.
 */
function cerrarTodosModales() {
    document.querySelectorAll('.novedades-modal.visible').forEach(m => {
        cerrarModal(m.id);
    });
    document.getElementById('modalOverlay').style.display = 'none';
}

// Cerrar al hacer clic en el overlay
document.getElementById('modalOverlay').addEventListener('click', cerrarTodosModales);

// Cerrar con tecla Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarTodosModales();
});
</script>

</body>
</html>