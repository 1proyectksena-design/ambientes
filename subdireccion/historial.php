<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$meses_espanol = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

/* DAYOFWEEK MySQL: 1=Dom, 2=Lun, 3=Mar, 4=Mié, 5=Jue, 6=Vie, 7=Sáb */
$abrevDias = [
    1 => 'Dom', 2 => 'Lun', 3 => 'Mar',
    4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb',
];

$filtro_mes  = $_GET['mes']  ?? date('m');
$filtro_anio = $_GET['anio'] ?? date('Y');

$whereMain   = [];
$whereMain[] = "MONTH(au.fecha_inicio) = '$filtro_mes'";
$whereMain[] = "YEAR(au.fecha_inicio) = '$filtro_anio'";
$whereSQLMain = implode(' AND ', $whereMain);

$sql = "SELECT 
            MIN(au.fecha_inicio)  AS fecha_inicio,
            MAX(au.fecha_inicio)  AS fecha_fin,
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
        JOIN ambientes a ON au.id_ambiente = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE $whereSQLMain
        GROUP BY au.id_instructor, au.id_ambiente, au.hora_inicio, au.hora_final, au.estado, au.rol_autorizado, au.observaciones, au.novedades
        ORDER BY MIN(au.fecha_inicio) DESC";

$resultado = mysqli_query($conexion, $sql);
if (!$resultado) die("Error en consulta: " . mysqli_error($conexion));

$total        = mysqli_num_rows($resultado);
$fecha_actual = date('Y-m-d');
$hora_actual  = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Autorizaciones</title>
    <link rel="stylesheet" href="../css/historial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Historial de Autorizaciones</h1>
            <span>Registro completo del sistema</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<div class="consultar-container">

    <div class="search-section">
        <h3><i class="fa-solid fa-filter"></i> Filtrar Autorizaciones</h3>
        <form method="GET" class="search-form">
            <select name="mes">
                <?php for ($m = 1; $m <= 12; $m++):
                    $mes_num = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                <option value="<?= $mes_num ?>" <?= $filtro_mes == $mes_num ? 'selected' : '' ?>>
                    <?= $meses_espanol[$mes_num] ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="anio">
                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $filtro_anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-search"></i> Filtrar</button>
        </form>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3><i class="fa-solid fa-list"></i> Mostrando <?= $total ?> autorizaciones</h3>
        </div>

        <?php if ($total > 0): ?>
        <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ambiente</th>
                        <th>Instructor</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Horario</th>
                        <th>Días</th>
                        <th>Estado Actual</th>
                        <th>Autorizado Por</th>
                        <th>Novedades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($resultado)):

                        /* --- Estado visual --- */
                        $estadoActual = 'desocupado';
                        $textoEstado  = 'Desocupado';
                        $iconoEstado  = '<i class="fa-solid fa-circle"></i>';

                        if ($row['estado'] == 'Aprobado') {
                            if ($fecha_actual >= $row['fecha_inicio'] && $fecha_actual <= $row['fecha_fin']) {
                                if ($hora_actual >= $row['hora_inicio'] && $hora_actual <= $row['hora_final']) {
                                    $estadoActual = 'ocupado-ahora';
                                    $textoEstado  = 'Ocupado Ahora';
                                    $iconoEstado  = '<i class="fa-solid fa-circle-dot"></i>';
                                } else {
                                    $estadoActual = 'programado';
                                    $textoEstado  = 'Programado (' . date('h:i A', strtotime($row['hora_inicio'])) . ' - ' . date('h:i A', strtotime($row['hora_final'])) . ')';
                                    $iconoEstado  = '<i class="fa-regular fa-clock"></i>';
                                }
                            }
                        } elseif ($row['estado'] == 'Pendiente') {
                            $estadoActual = 'pendiente';
                            $textoEstado  = 'Pendiente';
                            $iconoEstado  = '<i class="fa-solid fa-hourglass-half"></i>';
                        } elseif ($row['estado'] == 'Rechazado') {
                            $estadoActual = 'rechazado';
                            $textoEstado  = 'Rechazado';
                            $iconoEstado  = '<i class="fa-solid fa-ban"></i>';
                        }

                        /* --- Días de la semana --- */
                        $diasNums = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                                    ? explode(',', $row['dias_semana']) : [];
                        $diasHtml = '';
                        if (count($diasNums) > 0) {
                            $diasHtml = '<div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">';
                            foreach ($diasNums as $dn) {
                                $dn    = (int)$dn;
                                $abrev = $abrevDias[$dn] ?? '?';
                                $diasHtml .= '<span class="dia-badge">' . $abrev . '</span>';
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
                        <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
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
                                <?= date('h:i A', strtotime($row['hora_inicio'])) ?>
                                &mdash;
                                <?= date('h:i A', strtotime($row['hora_final'])) ?>
                            </span>
                        </td>
                        <td><?= $diasHtml ?></td>
                        <td>
                            <span class="estado-badge estado-<?= $estadoActual ?>">
                                <?= $iconoEstado ?> <?= $textoEstado ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
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
            <p>No hay autorizaciones con estos filtros</p>
        </div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>
</div>

<!-- ✅ OVERLAY Y MODAL GLOBAL: fuera de la tabla, directamente en body -->
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