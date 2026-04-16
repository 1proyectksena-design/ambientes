<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$nombre_ambiente = $_POST['nombre_ambiente'] ?? null;
$ambiente_info   = null;
$historial       = null;
$fecha_actual    = date('Y-m-d');
$hora_actual     = date('H:i:s');

/*
 * PHP date('w') → 0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb
 * MySQL DAYOFWEEK → 1=Dom, 2=Lun, 3=Mar, 4=Mié, 5=Jue, 6=Vie, 7=Sáb
 * Conversión: $dia_mysql = date('w') + 1
 */
$dia_actual_mysql = (int)date('w') + 1;   // día de hoy en formato DAYOFWEEK de MySQL

if ($nombre_ambiente) {
    $nombre_ambiente = mysqli_real_escape_string($conexion, $nombre_ambiente);

    $sqlAmb      = "SELECT * FROM ambientes WHERE nombre_ambiente LIKE '%$nombre_ambiente%' LIMIT 1";
    $resAmb      = mysqli_query($conexion, $sqlAmb);
    $ambiente_info = mysqli_fetch_assoc($resAmb);

    if ($ambiente_info) {
        // ── FIX 1: MAX(au.fecha_fin) para el extremo final del rango ──────────
        $sqlHist = "SELECT 
                        MIN(au.fecha_inicio)  AS fecha_inicio,
                        MAX(au.fecha_fin)     AS fecha_fin,
                        au.hora_inicio,
                        au.hora_final,
                        au.id_instructor,
                        i.nombre              AS nombre_instructor,
                        a.nombre_ambiente,
                        au.estado,
                        au.rol_autorizado,
                        au.observaciones,
                        au.novedades,
                        GROUP_CONCAT(
                            DISTINCT DAYOFWEEK(au.fecha_inicio)
                            ORDER BY DAYOFWEEK(au.fecha_inicio)
                        ) AS dias_semana
                    FROM autorizaciones_ambientes au
                    JOIN instructores i ON au.id_instructor = i.id
                    JOIN ambientes a ON au.id_ambiente = a.id
                    WHERE au.id_ambiente = '" . $ambiente_info['id'] . "'
                    GROUP BY au.id_instructor, au.hora_inicio, au.hora_final,
                             au.estado, au.rol_autorizado, au.observaciones, au.novedades
                    ORDER BY MIN(au.fecha_inicio) DESC
                    LIMIT 50";
        $historial = mysqli_query($conexion, $sqlHist);
    }
}

/* DAYOFWEEK MySQL: 1=Dom, 2=Lun, 3=Mar, 4=Mié, 5=Jue, 6=Vie, 7=Sáb */
$abrevDias = [
    1 => 'Dom', 2 => 'Lun', 3 => 'Mar',
    4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ambiente</title>
    <link rel="stylesheet" href="../css/historial.css">
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

    <div class="search-section">
        <h3><i class="fa-solid fa-building"></i> Buscar Ambiente</h3>
        <form method="POST" class="search-form">
            <input type="text" name="nombre_ambiente"
                placeholder="Ej: 308, Laboratorio, Sala 101..."
                value="<?= htmlspecialchars($nombre_ambiente ?? '') ?>"
                required>
            <button type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
        </form>
    </div>

    <?php if ($nombre_ambiente && $ambiente_info): ?>
        <div class="ambiente-result">
            <h3 style="margin:0 0 20px 0; color:#333;">
                <i class="fa-solid fa-door-open" style="color:#355d91;"></i> Información del Ambiente
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

        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Historial de "<?= htmlspecialchars($ambiente_info['nombre_ambiente']) ?>"
                </h3>
            </div>

            <?php if ($historial && mysqli_num_rows($historial) > 0): ?>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Horario</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th>Autorizado Por</th>
                            <th>Observaciones</th>
                            <th>Novedades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($historial)):

                            // ── Construir array de días registrados (formato DAYOFWEEK MySQL) ──
                            $diasNums = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                                        ? array_map('intval', explode(',', $row['dias_semana']))
                                        : [];

                            // ── FIX 2: validar fecha + hora + DÍA DE LA SEMANA ────────────────
                            $estadoActual = 'desocupado';
                            $textoEstado  = 'Desocupado';
                            $iconoEstado  = '<i class="fa-solid fa-circle"></i>';

                            if ($row['estado'] === 'Aprobado') {

                                $enRangoFecha = ($fecha_actual >= $row['fecha_inicio'])
                                             && ($fecha_actual <= $row['fecha_fin']);

                                $enRangoHora  = ($hora_actual >= $row['hora_inicio'])
                                             && ($hora_actual <= $row['hora_final']);

                                // ¿El día de hoy (en formato DAYOFWEEK) está entre los días registrados?
                                $diaCoincide  = in_array($dia_actual_mysql, $diasNums);

                                if ($enRangoFecha && $enRangoHora && $diaCoincide) {
                                    // Fecha ✓  Hora ✓  Día ✓  → ocupado en este momento
                                    $estadoActual = 'ocupado-ahora';
                                    $textoEstado  = 'Ocupado Ahora';
                                    $iconoEstado  = '<i class="fa-solid fa-circle-dot"></i>';

                                } elseif ($enRangoFecha && $diaCoincide) {
                                    // Fecha ✓  Día ✓  pero fuera del horario → programado para hoy
                                    $estadoActual = 'programado';
                                    $textoEstado  = 'Programado ('
                                        . date('H:i', strtotime($row['hora_inicio']))
                                        . ' - '
                                        . date('H:i', strtotime($row['hora_final'])) . ')';
                                    $iconoEstado  = '<i class="fa-regular fa-clock"></i>';

                                } elseif ($enRangoFecha) {
                                    // Dentro del rango de fechas pero no es el día de la semana
                                    $estadoActual = 'programado';
                                    $textoEstado  = 'Programado';
                                    $iconoEstado  = '<i class="fa-regular fa-clock"></i>';
                                }

                            } elseif ($row['estado'] === 'Pendiente') {
                                $estadoActual = 'pendiente';
                                $textoEstado  = 'Pendiente';
                                $iconoEstado  = '<i class="fa-solid fa-hourglass-half"></i>';

                            } elseif ($row['estado'] === 'Rechazado') {
                                $estadoActual = 'rechazado';
                                $textoEstado  = 'Rechazado';
                                $iconoEstado  = '<i class="fa-solid fa-ban"></i>';
                            }

                            // ── Badges de días de la semana ───────────────────────────────────
                            $diasHtml = '';
                            if (count($diasNums) > 0) {
                                $diasHtml = '<div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">';
                                foreach ($diasNums as $dn) {
                                    $abrev     = $abrevDias[$dn] ?? '?';
                                    // Resaltar el día actual
                                    $highlight = ($dn === $dia_actual_mysql)
                                                 ? ' style="background:#172f63;color:white;border-color:#172f63;"'
                                                 : '';
                                    $diasHtml .= '<span class="dia-badge"' . $highlight . '>' . $abrev . '</span>';
                                }
                                $diasHtml .= '</div>';
                            } else {
                                $diasHtml = '<span style="color:#999;">—</span>';
                            }

                            $instructor_js = htmlspecialchars($row['nombre_instructor'], ENT_QUOTES);
                            $novedad_js    = htmlspecialchars($row['novedades'],         ENT_QUOTES);
                            $inicial       = strtoupper(substr($row['nombre_instructor'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                                <?= htmlspecialchars($row['nombre_instructor']) ?>
                            </td>
                            <td>
                                <span class="cell-fecha">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="cell-fecha">
                                    <i class="fa-regular fa-calendar-check"></i>
                                    <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="cell-horario">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('H:i', strtotime($row['hora_inicio'])) ?>
                                    &mdash;
                                    <?= date('H:i', strtotime($row['hora_final'])) ?>
                                </span>
                            </td>
                            <td><?= $diasHtml ?></td>
                            <td>
                                <span class="estado-badge estado-<?= $estadoActual ?>">
                                    <?= $iconoEstado ?> <?= $textoEstado ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                            <td><?= htmlspecialchars($row['observaciones'] ?: '—') ?></td>
                            <td>
                                <?php if ($row['novedades']): ?>
                                    <button
                                        class="btn-ver-novedades"
                                        onclick="abrirModal(this)"
                                        data-instructor="<?= $instructor_js ?>"
                                        data-inicial="<?= $inicial ?>"
                                        data-novedad="<?= $novedad_js ?>">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
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

    <?php elseif ($nombre_ambiente && !$ambiente_info): ?>
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
    <div class="modal-content">
        <pre id="modalTexto"></pre>
    </div>
</div>

<script>
function abrirModal(btn) {
    document.getElementById('modalAvatar').textContent = btn.dataset.inicial;
    document.getElementById('modalNombre').textContent = btn.dataset.instructor;
    document.getElementById('modalTexto').textContent  = btn.dataset.novedad;

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