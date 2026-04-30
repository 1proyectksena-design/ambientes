<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$meses_espanol = [
    '01' => 'Enero',    '02' => 'Febrero',  '03' => 'Marzo',
    '04' => 'Abril',    '05' => 'Mayo',     '06' => 'Junio',
    '07' => 'Julio',    '08' => 'Agosto',   '09' => 'Septiembre',
    '10' => 'Octubre',  '11' => 'Noviembre','12' => 'Diciembre'
];

$abrevDias = [
    1 => 'Dom', 2 => 'Lun', 3 => 'Mar',
    4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb',
];

$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_mes    = $_GET['mes']    ?? date('m');
$filtro_anio   = $_GET['anio']   ?? date('Y');

$whereMain = [];
if ($filtro_estado != 'todos') {
    $estadoSeguro = mysqli_real_escape_string($conexion, $filtro_estado);
    $whereMain[] = "au.estado = '$estadoSeguro'";
}
$whereMain[] = "MONTH(au.fecha_inicio) = '$filtro_mes'";
$whereMain[] = "YEAR(au.fecha_inicio)  = '$filtro_anio'";
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
            f.numero_ficha,
            f.programa,
            GROUP_CONCAT(
                DISTINCT DAYOFWEEK(au.fecha_inicio)
                ORDER BY DAYOFWEEK(au.fecha_inicio)
            ) AS dias_semana
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        LEFT JOIN fichas  f ON au.id_ficha      = f.id
        WHERE $whereSQLMain
        GROUP BY au.id_instructor, au.id_ambiente, au.hora_inicio, au.hora_final,
                 au.estado, au.rol_autorizado, au.observaciones, au.novedades,
                 f.numero_ficha, f.programa
        ORDER BY MIN(au.fecha_inicio) DESC";

$resultado = mysqli_query($conexion, $sql);
if (!$resultado) die("Error en consulta: " . mysqli_error($conexion));

$total        = mysqli_num_rows($resultado);
$fecha_actual = date('Y-m-d');
$hora_actual  = date('H:i:s');
$nombre_mes   = $meses_espanol[$filtro_mes] ?? 'Mes';

/* ══════════════════════════════════════════════════════
   EXPORTAR A EXCEL CON DISEÑO
   ══════════════════════════════════════════════════════ */
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    $resExport = mysqli_query($conexion, $sql);

    /* Conteos para estadísticas */
    $cnt_aprobado  = mysqli_fetch_row(mysqli_query($conexion,
        "SELECT COUNT(*) FROM autorizaciones_ambientes
         WHERE MONTH(fecha_inicio)='$filtro_mes' AND YEAR(fecha_inicio)='$filtro_anio'
           AND estado='Aprobado'"))[0];
    $cnt_pendiente = mysqli_fetch_row(mysqli_query($conexion,
        "SELECT COUNT(*) FROM autorizaciones_ambientes
         WHERE MONTH(fecha_inicio)='$filtro_mes' AND YEAR(fecha_inicio)='$filtro_anio'
           AND estado='Pendiente'"))[0];
    $cnt_rechazado = mysqli_fetch_row(mysqli_query($conexion,
        "SELECT COUNT(*) FROM autorizaciones_ambientes
         WHERE MONTH(fecha_inicio)='$filtro_mes' AND YEAR(fecha_inicio)='$filtro_anio'
           AND estado='Rechazado'"))[0];

    $filename = "Historial_Autorizaciones_{$nombre_mes}_{$filtro_anio}.xls";
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml>
  <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
    <x:Name>Historial</x:Name>
    <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
  </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
<style>
  body { font-family: Arial, sans-serif; font-size: 10pt; }

  .titulo    { font-family:Arial; font-size:16pt; font-weight:bold; color:#FFFFFF;
               background-color:#355d91; text-align:center; vertical-align:middle;
               border:1px solid #2a4a75; }
  .subtitulo { font-family:Arial; font-size:9pt; color:#666666;
               text-align:center; border:1px solid #dddddd; }

  .stat-lbl-total { font-family:Arial;font-weight:bold;background-color:#dce6f1;color:#1f3864;border:1px solid #9dc3e6;padding:5px 10px; }
  .stat-val-total { font-family:Arial;background-color:#dce6f1;color:#1f3864;border:1px solid #9dc3e6;text-align:center; }
  .stat-lbl-pend  { font-family:Arial;font-weight:bold;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;padding:5px 10px; }
  .stat-val-pend  { font-family:Arial;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;text-align:center; }
  .stat-lbl-apro  { font-family:Arial;font-weight:bold;background-color:#e2efda;color:#375623;border:1px solid #70ad47;padding:5px 10px; }
  .stat-val-apro  { font-family:Arial;background-color:#e2efda;color:#375623;border:1px solid #70ad47;text-align:center; }
  .stat-lbl-rech  { font-family:Arial;font-weight:bold;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;padding:5px 10px; }
  .stat-val-rech  { font-family:Arial;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;text-align:center; }

  .th { font-family:Arial;font-size:10pt;font-weight:bold;color:#FFFFFF;
        background-color:#355d91;text-align:center;vertical-align:middle;
        border:1px solid #2a4a75;white-space:nowrap; }

  .td-par   { font-family:Arial;font-size:9pt;background-color:#dce6f1;border:1px solid #9dc3e6;vertical-align:middle; }
  .td-impar { font-family:Arial;font-size:9pt;background-color:#FFFFFF;border:1px solid #bdd7ee;vertical-align:middle; }
  .td-ctr   { text-align:center; }
  .td-bold  { font-weight:bold; }
  .td-dias  { text-align:center;color:#1f3864;font-weight:bold; }
  .td-num   { text-align:center;color:#888888; }

  .estado-aprobado  { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#e2efda;color:#375623;border:1px solid #70ad47;text-align:center;vertical-align:middle; }
  .estado-rechazado { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;text-align:center;vertical-align:middle; }
  .estado-pendiente { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;text-align:center;vertical-align:middle; }

  .footer { font-family:Arial;font-size:9pt;font-weight:bold;
            background-color:#355d91;color:#FFFFFF;
            text-align:right;border:1px solid #2a4a75; }
</style>
</head>
<body>

<!-- TÍTULO -->
<table border="0" cellpadding="6" cellspacing="0" width="100%">
  <tr height="36">
    <td colspan="13" class="titulo">
      Historial de Autorizaciones &mdash; <?= $nombre_mes ?> <?= $filtro_anio ?>
    </td>
  </tr>
  <tr height="20">
    <td colspan="13" class="subtitulo">
      Generado el <?= date('d/m/Y H:i') ?> &nbsp;&bull;&nbsp; Sistema de Gestión SENA
      <?php if ($filtro_estado != 'todos'): ?>
        &nbsp;&bull;&nbsp; Filtro: <?= htmlspecialchars($filtro_estado) ?>
      <?php endif; ?>
    </td>
  </tr>
  <tr><td colspan="13" height="8">&nbsp;</td></tr>
</table>

<!-- ESTADÍSTICAS -->
<table border="0" cellpadding="5" cellspacing="2">
  <tr height="28">
    <td class="stat-lbl-total">&nbsp;Total&nbsp;</td>
    <td class="stat-val-total" width="40"><b><?= $total ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-pend">&nbsp;Pendientes&nbsp;</td>
    <td class="stat-val-pend" width="40"><b><?= $cnt_pendiente ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-apro">&nbsp;Aprobados&nbsp;</td>
    <td class="stat-val-apro" width="40"><b><?= $cnt_aprobado ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-rech">&nbsp;Rechazados&nbsp;</td>
    <td class="stat-val-rech" width="40"><b><?= $cnt_rechazado ?></b></td>
  </tr>
</table>
<table><tr><td height="10">&nbsp;</td></tr></table>

<!-- TABLA PRINCIPAL -->
<table border="0" cellpadding="7" cellspacing="0" width="100%">
  <thead>
    <tr height="30">
      <th class="th" width="35">#</th>
      <th class="th" width="140">Ambiente</th>
      <th class="th" width="140">Instructor</th>
      <th class="th" width="85">Fecha Inicio</th>
      <th class="th" width="85">Fecha Fin</th>
      <th class="th" width="75">Hora Inicio</th>
      <th class="th" width="75">Hora Fin</th>
      <th class="th" width="110">Días</th>
      <th class="th" width="80">Ficha</th>
      <th class="th" width="160">Programa</th>
      <th class="th" width="90">Estado</th>
      <th class="th" width="120">Autorizado Por</th>
      <th class="th" width="160">Novedades</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $fila = 0;
  while ($row = mysqli_fetch_assoc($resExport)):
      $fila++;
      $clase = ($fila % 2 === 0) ? 'td-par' : 'td-impar';

      switch ($row['estado']) {
          case 'Aprobado':  $claseEstado = 'estado-aprobado';  break;
          case 'Rechazado': $claseEstado = 'estado-rechazado'; break;
          default:          $claseEstado = 'estado-pendiente'; break;
      }

      $diasNums  = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                   ? array_map('intval', explode(',', $row['dias_semana'])) : [];
      $diasTexto = implode(', ', array_map(fn($d) => $abrevDias[$d] ?? '?', $diasNums));
  ?>
    <tr height="22">
      <td class="<?= $clase ?> td-num"><?= $fila ?></td>
      <td class="<?= $clase ?> td-bold"><?= htmlspecialchars($row['nombre_ambiente']) ?></td>
      <td class="<?= $clase ?>"><?= htmlspecialchars($row['nombre_instructor']) ?></td>
      <td class="<?= $clase ?> td-ctr"><?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?></td>
      <td class="<?= $clase ?> td-ctr"><?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></td>
      <td class="<?= $clase ?> td-ctr"><?= date('H:i', strtotime($row['hora_inicio'])) ?></td>
      <td class="<?= $clase ?> td-ctr"><?= date('H:i', strtotime($row['hora_final'])) ?></td>
      <td class="<?= $clase ?> td-dias"><?= htmlspecialchars($diasTexto ?: '—') ?></td>
      <td class="<?= $clase ?> td-ctr"><?= htmlspecialchars($row['numero_ficha'] ?: '—') ?></td>
      <td class="<?= $clase ?>"><?= htmlspecialchars($row['programa'] ?: '—') ?></td>
      <td class="<?= $claseEstado ?>"><?= htmlspecialchars($row['estado']) ?></td>
      <td class="<?= $clase ?>"><?= htmlspecialchars($row['rol_autorizado']) ?></td>
      <td class="<?= $clase ?>"><?= htmlspecialchars($row['novedades'] ?: '—') ?></td>
    </tr>
  <?php endwhile; ?>
  </tbody>
  <tfoot>
    <tr height="24">
      <td colspan="13" class="footer">&nbsp;&nbsp;Total de registros: <?= $total ?> &nbsp;&nbsp;</td>
    </tr>
  </tfoot>
</table>

</body>
</html>
<?php
    exit;
}
/* ══ FIN EXPORTAR ══ */
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
        <a href="index.php" class="btn-volver">
            <i class="fa-solid fa-arrow-left"></i> Volver al Panel
        </a>
        <i class="fa-solid fa-user user-icon"></i> Subdirección 
    </div>
</div>

<div class="consultar-container">

    <!-- ══ FILTROS ══ -->
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
            <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro_estado) ?>">
            <button type="submit"><i class="fa-solid fa-search"></i> Filtrar</button>
            <a href="?mes=<?= $filtro_mes ?>&anio=<?= $filtro_anio ?>&estado=<?= urlencode($filtro_estado) ?>&exportar=excel"
               class="btn-exportar-excel">
                <i class="fa-solid fa-file-excel"></i> Exportar Excel
            </a>
        </form>

        <div class="filtro-estado-row">
            <span class="filtro-estado-label"><i class="fa-solid fa-tags"></i> Estado:</span>
            <?php
            $chips = [
                'todos'     => ['label' => 'Todos',     'icon' => 'fa-solid fa-list',           'clase' => 'chip-todos'],
                'Aprobado'  => ['label' => 'Aprobado',  'icon' => 'fa-solid fa-circle-check',   'clase' => 'chip-aprobado'],
                'Pendiente' => ['label' => 'Pendiente', 'icon' => 'fa-solid fa-hourglass-half', 'clase' => 'chip-pendiente'],
                'Rechazado' => ['label' => 'Rechazado', 'icon' => 'fa-solid fa-ban',            'clase' => 'chip-rechazado'],
            ];
            foreach ($chips as $val => $info):
                $activo = ($filtro_estado === $val) ? ' activo' : '';
            ?>
            <a href="?mes=<?= $filtro_mes ?>&anio=<?= $filtro_anio ?>&estado=<?= urlencode($val) ?>"
               class="chip-estado <?= $info['clase'] . $activo ?>">
                <i class="<?= $info['icon'] ?>"></i> <?= $info['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ TABLA ══ -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-list"></i>
                Mostrando <?= $total ?> autorizaciones
                <?php if ($filtro_estado != 'todos'): ?>
                    &mdash; <em><?= htmlspecialchars($filtro_estado) ?></em>
                <?php endif; ?>
            </h3>
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
                        <th>Ficha</th>
                        <th>Estado Actual</th>
                        <th>Autorizado Por</th>
                        <th>Novedades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($resultado)):

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
                                    $textoEstado  = 'Programado (' . date('H:i', strtotime($row['hora_inicio'])) . ' - ' . date('H:i', strtotime($row['hora_final'])) . ')';
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

                        /* Ficha HTML */
                        if ($row['numero_ficha']) {
                            $fichaHtml = '<span style="font-weight:600;color:#0d6efd;">'
                                       . '<i class="fa-solid fa-graduation-cap" style="margin-right:4px;"></i>'
                                       . htmlspecialchars($row['numero_ficha'])
                                       . '</span>';
                            if ($row['programa']) {
                                $fichaHtml .= '<br><small style="color:#555;">'
                                            . htmlspecialchars($row['programa'])
                                            . '</small>';
                            }
                        } else {
                            $fichaHtml = '<span style="color:#999;">—</span>';
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
                                <?= date('H:i', strtotime($row['hora_inicio'])) ?>
                                &mdash;
                                <?= date('H:i', strtotime($row['hora_final'])) ?>
                            </span>
                        </td>
                        <td><?= $diasHtml ?></td>
                        <td><?= $fichaHtml ?></td>
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

</div>

<!-- OVERLAY Y MODAL -->
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