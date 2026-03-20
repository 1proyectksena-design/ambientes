<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'administracion') {
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

    if ($ambienteInfo) {
        $id_ambiente = $ambienteInfo['id'];

        $sqlUsoActual = "SELECT au.*, i.nombre AS nombre_instructor
                         FROM autorizaciones_ambientes au
                         JOIN instructores i ON au.id_instructor = i.id
                         WHERE au.id_ambiente = '$id_ambiente'
                           AND au.fecha_inicio = '$fecha_actual'
                           AND '$hora_actual' BETWEEN au.hora_inicio AND au.hora_final
                           AND au.estado = 'Aprobado'
                         LIMIT 1";
        $resUsoActual = mysqli_query($conexion, $sqlUsoActual);
        $usoActual    = mysqli_fetch_assoc($resUsoActual);

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
                                ) AS dias_semana
                            FROM autorizaciones_ambientes au
                            JOIN instructores i ON au.id_instructor = i.id
                            WHERE au.id_ambiente = '$id_ambiente'
                              AND (
                                (au.fecha_inicio > '$fecha_actual')
                                OR (au.fecha_inicio = '$fecha_actual' AND au.hora_inicio > '$hora_actual')
                              )
                              AND au.estado = 'Aprobado'
                            GROUP BY au.id_instructor, au.hora_inicio, au.hora_final, au.observaciones
                            ORDER BY MIN(au.fecha_inicio) ASC
                            LIMIT 10";
        $proximosUsos = mysqli_query($conexion, $sqlProximosUsos);

        $sqlHistorialReciente = "SELECT au.*, i.nombre AS nombre_instructor
                                 FROM autorizaciones_ambientes au
                                 JOIN instructores i ON au.id_instructor = i.id
                                 WHERE au.id_ambiente = '$id_ambiente'
                                   AND (
                                     (au.fecha_inicio < '$fecha_actual')
                                     OR (au.fecha_inicio = '$fecha_actual' AND au.hora_final < '$hora_actual')
                                   )
                                   AND au.estado = 'Aprobado'
                                 ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                                 LIMIT 5";
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
            <input type="text" name="ambiente"
                placeholder="Ej: 308, Laboratorio de Química, Sala 101..."
                value="<?= htmlspecialchars($ambienteBuscado ?? '') ?>"
                required>
            <button type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
        </form>
    </div>

    <?php if ($ambienteBuscado && $ambienteInfo): ?>
        <div class="ambiente-result">

            <div class="result-title-row">
                <h3><i class="fa-solid fa-door-open" style="color:#355d91;"></i> Información del Ambiente</h3>
            </div>

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

            <div class="estado-instructor-row">

                <div class="estado-uso-card">
                    <?php if ($usoActual): ?>
                        <div class="estado-header en-uso">
                            <div class="estado-icon"><i class="fa-solid fa-circle-dot"></i></div>
                            <div>
                                <h3>EN USO AHORA</h3>
                                <p>Siendo utilizado en este momento</p>
                            </div>
                        </div>
                        <div class="estado-body">
                            <div class="uso-actual-info">
                                <div class="uso-item">
                                    <i class="fa-solid fa-user"></i>
                                    <div>
                                        <label>Instructor</label>
                                        <strong><?= htmlspecialchars($usoActual['nombre_instructor']) ?></strong>
                                    </div>
                                </div>
                                <div class="uso-item">
                                    <i class="fa-regular fa-clock"></i>
                                    <div>
                                        <label>Horario</label>
                                        <strong>
                                            <?= date('h:i A', strtotime($usoActual['hora_inicio'])) ?>
                                            —
                                            <?= date('h:i A', strtotime($usoActual['hora_final'])) ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="estado-header libre">
                            <div class="estado-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div>
                                <h3>LIBRE</h3>
                                <p>Disponible en este momento</p>
                            </div>
                        </div>
                        <?php
                        $proximoInmediato = $proximosUsos ? mysqli_fetch_assoc($proximosUsos) : null;
                        if ($proximoInmediato): mysqli_data_seek($proximosUsos, 0); ?>
                        <div class="estado-body">
                            <div class="proximo-uso-rapido">
                                <i class="fa-regular fa-calendar-check"></i>
                                <span>Próximo uso:
                                    <strong>
                                        <?= date('d/m/Y', strtotime($proximoInmediato['fecha_inicio'])) ?>
                                        a las
                                        <?= date('h:i A', strtotime($proximoInmediato['hora_inicio'])) ?>
                                    </strong>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($ambienteInfo['nombre_instructor_fijo']): ?>
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
                                <?php if ($ambienteInfo['novedades_instructor_fijo']): ?>
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
                    <span>Sin instructor de horario fijo asignado</span>
                </div>
                <?php endif; ?>

            </div>

            <?php if ($ambienteInfo['descripcion_general']): ?>
            <div class="descripcion-ambiente">
                <strong>Descripción:</strong>
                <p><?= htmlspecialchars($ambienteInfo['descripcion_general']) ?></p>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <?php if ($ambienteInfo['estado'] == 'Habilitado'): ?>
                    <a href="permisos.php?id_ambiente=<?= $ambienteInfo['id'] ?>" class="btn-permiso">
                        <i class="fa-solid fa-circle-check"></i> Autorizar Ambiente
                    </a>
                <?php else: ?>
                    <div class="alert-disabled">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <p>Este ambiente está <strong><?= htmlspecialchars($ambienteInfo['estado']) ?></strong></p>
                    </div>
                <?php endif; ?>
                <a href="editar_ambiente.php?id=<?= $ambienteInfo['id'] ?>" class="btn-action-edit">
                    <i class="fa-solid fa-pen-to-square"></i> Editar
                </a>
            </div>
        </div>

        <!-- PRÓXIMOS USOS -->
        <?php if ($proximosUsos && mysqli_num_rows($proximosUsos) > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-calendar-days"></i>
                    Próximos Usos de "<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>"
                </h3>
            </div>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Horario</th>
                            <th>Días</th>
                            <th>Instructor</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $abrevDias = [1=>'Dom',2=>'Lun',3=>'Mar',4=>'Mié',5=>'Jue',6=>'Vie',7=>'Sáb'];
                        while ($prox = mysqli_fetch_assoc($proximosUsos)):
                            $diasNums = ($prox['dias_semana'] !== null && $prox['dias_semana'] !== '')
                                        ? explode(',', $prox['dias_semana']) : [];
                            $diasHtml = '';
                            if (count($diasNums) > 0) {
                                $diasHtml = '<div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">';
                                foreach ($diasNums as $dn) {
                                    $dn = (int)$dn;
                                    $diasHtml .= '<span class="dia-badge">' . ($abrevDias[$dn] ?? '?') . '</span>';
                                }
                                $diasHtml .= '</div>';
                            } else {
                                $diasHtml = '<span style="color:#999;">—</span>';
                            }
                        ?>
                        <tr>
                            <td><span class="cell-fecha"><i class="fa-regular fa-calendar"></i><?= date('d/m/Y', strtotime($prox['fecha_inicio'])) ?></span></td>
                            <td><span class="cell-fecha"><i class="fa-regular fa-calendar-check"></i><?= date('d/m/Y', strtotime($prox['fecha_fin'])) ?></span></td>
                            <td>
                                <span class="cell-horario">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('h:i A', strtotime($prox['hora_inicio'])) ?> &mdash; <?= date('h:i A', strtotime($prox['hora_final'])) ?>
                                </span>
                            </td>
                            <td><?= $diasHtml ?></td>
                            <td><i class="fa-solid fa-user" style="color:#355d91;margin-right:5px;"></i><?= htmlspecialchars($prox['nombre_instructor']) ?></td>
                            <td><?= htmlspecialchars($prox['observaciones'] ?: '—') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- HISTORIAL RECIENTE -->
        <?php if ($historialReciente && mysqli_num_rows($historialReciente) > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Historial Reciente de "<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>"
                </h3>
            </div>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Instructor</th>
                            <th>Novedades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($hist = mysqli_fetch_assoc($historialReciente)):
                            $novedad_texto = $hist['novedades'];
                            $fecha_novedad = '';
                            if ($novedad_texto && preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*(.*)$/s', $novedad_texto, $matches)) {
                                $fecha_novedad = date('d/m/Y h:i A', strtotime($matches[1]));
                                $novedad_texto = $matches[2];
                            }
                            $instructor_js = htmlspecialchars($hist['nombre_instructor'], ENT_QUOTES);
                            $novedad_js    = htmlspecialchars($novedad_texto,             ENT_QUOTES);
                            $fecha_js      = htmlspecialchars($fecha_novedad,             ENT_QUOTES);
                            $inicial       = strtoupper(substr($hist['nombre_instructor'], 0, 1));
                        ?>
                        <tr>
                            <td><span class="cell-fecha"><i class="fa-regular fa-calendar"></i><?= date('d/m/Y', strtotime($hist['fecha_inicio'])) ?></span></td>
                            <td>
                                <span class="cell-horario">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('h:i A', strtotime($hist['hora_inicio'])) ?> &mdash; <?= date('h:i A', strtotime($hist['hora_final'])) ?>
                                </span>
                            </td>
                            <td><i class="fa-solid fa-user" style="color:#355d91;margin-right:5px;"></i><?= htmlspecialchars($hist['nombre_instructor']) ?></td>
                            <td>
                                <?php if ($hist['novedades']): ?>
                                    <!--  Solo el botón con data-attributes -->
                                    <button
                                        class="btn-ver-novedades"
                                        onclick="abrirModal(this)"
                                        data-instructor="<?= $instructor_js ?>"
                                        data-inicial="<?= $inicial ?>"
                                        data-novedad="<?= $novedad_js ?>"
                                        data-fecha="<?= $fecha_js ?>">
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

<!--  OVERLAY Y MODAL GLOBAL: directamente en body -->
<div class="novedades-overlay" id="modalOverlay" onclick="cerrarModal()"></div>

<div class="novedades-modal" id="modalNovedades">
    <div class="modal-header">
        <div class="modal-instructor-row">
            <div class="modal-avatar" id="modalAvatar">A</div>
            <div style="min-width:0;">
                <div class="modal-label">Novedad reportada por</div>
                <div class="modal-instructor-name" id="modalNombre"></div>
            </div>
        </div>
        <button class="modal-btn-cerrar" onclick="cerrarModal()" title="Cerrar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <!-- Badge de fecha (se muestra solo si tiene fecha) -->
    <div id="modalFechaBadge" class="modal-fecha-badge" style="display:none; margin: 0 18px 12px;">
        <i class="fa-regular fa-calendar-check"></i>
        <span id="modalFechaTexto"></span>
    </div>
    <div class="modal-content">
        <pre id="modalTexto"></pre>
    </div>
</div>

<script>
function abrirModal(btn) {
    document.getElementById('modalAvatar').textContent = btn.dataset.inicial;
    document.getElementById('modalNombre').textContent = btn.dataset.instructor;
    document.getElementById('modalTexto').textContent  = btn.dataset.novedad;

    const fechaBadge = document.getElementById('modalFechaBadge');
    const fechaTexto = document.getElementById('modalFechaTexto');
    if (btn.dataset.fecha) {
        fechaTexto.textContent  = btn.dataset.fecha;
        fechaBadge.style.display = 'inline-flex';
    } else {
        fechaBadge.style.display = 'none';
    }

    document.getElementById('modalOverlay').style.display = 'block';
    const modal = document.getElementById('modalNovedades');
    modal.style.display = 'block';
    requestAnimationFrame(() => modal.classList.add('visible'));
}

function cerrarModal() {
    const modal = document.getElementById('modalNovedades');
    modal.classList.remove('visible');
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('modalOverlay').style.display = 'none';
    }, 200);
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
</script>
</body>
</html>