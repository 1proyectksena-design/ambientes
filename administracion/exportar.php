<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
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

$mes        = $_GET['mes']  ?? date('m');
$anio       = $_GET['anio'] ?? date('Y');
$nombre_mes = $meses_espanol[$mes] ?? 'Mes';

/* ── Consulta ── */
$sql = "SELECT
            a.nombre_ambiente,
            i.nombre                          AS nombre_instructor,
            MIN(au.fecha_inicio)              AS fecha_inicio,
            MAX(au.fecha_fin)                 AS fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.rol_autorizado,
            au.estado,
            GROUP_CONCAT(
                DISTINCT DAYOFWEEK(au.fecha_inicio)
                ORDER BY DAYOFWEEK(au.fecha_inicio)
            )                                 AS dias_semana
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE MONTH(au.fecha_inicio) = '$mes'
          AND YEAR(au.fecha_inicio)  = '$anio'
        GROUP BY a.nombre_ambiente, i.nombre,
                 au.hora_inicio, au.hora_final,
                 au.rol_autorizado, au.estado
        ORDER BY MIN(au.fecha_inicio) DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);

/* ── Conteos ── */
$total     = mysqli_num_rows($resultado);
$pendiente = mysqli_fetch_row(mysqli_query($conexion,
    "SELECT COUNT(*) FROM autorizaciones_ambientes
     WHERE MONTH(fecha_inicio)='$mes' AND YEAR(fecha_inicio)='$anio'
       AND estado='Pendiente'"))[0];
$aprobado  = mysqli_fetch_row(mysqli_query($conexion,
    "SELECT COUNT(*) FROM autorizaciones_ambientes
     WHERE MONTH(fecha_inicio)='$mes' AND YEAR(fecha_inicio)='$anio'
       AND estado='Aprobado'"))[0];
$rechazado = mysqli_fetch_row(mysqli_query($conexion,
    "SELECT COUNT(*) FROM autorizaciones_ambientes
     WHERE MONTH(fecha_inicio)='$mes' AND YEAR(fecha_inicio)='$anio'
       AND estado='Rechazado'"))[0];

/* ── Headers ── */
$filename = "Autorizaciones_{$nombre_mes}_{$anio}.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml>
  <x:ExcelWorkbook>
    <x:ExcelWorksheets>
      <x:ExcelWorksheet>
        <x:Name>Autorizaciones</x:Name>
        <x:WorksheetOptions>
          <x:DisplayGridlines/>
          <x:FreezePanes/>
          <x:FrozenNoSplit/>
          <x:SplitHorizontal>5</x:SplitHorizontal>
          <x:TopRowBottomPane>5</x:TopRowBottomPane>
          <x:ActivePane>2</x:ActivePane>
        </x:WorksheetOptions>
      </x:ExcelWorksheet>
    </x:ExcelWorksheets>
  </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  /* Estilos globales que Excel sí respeta dentro del bloque <style> */
  body { font-family: Arial, sans-serif; font-size: 10pt; }

  .titulo {
    font-family: Arial, sans-serif;
    font-size: 16pt;
    font-weight: bold;
    color: #FFFFFF;
    background-color: #355d91;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #2a4a75;
  }
  .subtitulo {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    color: #666666;
    text-align: center;
    border: 1px solid #dddddd;
  }

  /* Estadísticas */
  .stat-lbl-total  { font-family:Arial;font-weight:bold;background-color:#dce6f1;color:#1f3864;border:1px solid #9dc3e6;padding:5px 10px; }
  .stat-val-total  { font-family:Arial;background-color:#dce6f1;color:#1f3864;border:1px solid #9dc3e6;text-align:center; }
  .stat-lbl-pend   { font-family:Arial;font-weight:bold;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;padding:5px 10px; }
  .stat-val-pend   { font-family:Arial;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;text-align:center; }
  .stat-lbl-apro   { font-family:Arial;font-weight:bold;background-color:#e2efda;color:#375623;border:1px solid #70ad47;padding:5px 10px; }
  .stat-val-apro   { font-family:Arial;background-color:#e2efda;color:#375623;border:1px solid #70ad47;text-align:center; }
  .stat-lbl-rech   { font-family:Arial;font-weight:bold;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;padding:5px 10px; }
  .stat-val-rech   { font-family:Arial;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;text-align:center; }

  /* Encabezados de tabla */
  .th {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    font-weight: bold;
    color: #FFFFFF;
    background-color: #355d91;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #2a4a75;
    white-space: nowrap;
  }

  /* Filas de datos */
  .td-par  { font-family:Arial;font-size:9pt;background-color:#dce6f1;border:1px solid #9dc3e6;vertical-align:middle; }
  .td-impar{ font-family:Arial;font-size:9pt;background-color:#FFFFFF;border:1px solid #bdd7ee;vertical-align:middle; }
  .td-num  { text-align:center;color:#888888; }
  .td-bold { font-weight:bold; }
  .td-ctr  { text-align:center; }
  .td-dias { text-align:center;color:#1f3864;font-weight:bold; }

  /* Estados */
  .estado-aprobado  { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#e2efda;color:#375623;border:1px solid #70ad47;text-align:center;vertical-align:middle; }
  .estado-rechazado { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#fce4d6;color:#843c0c;border:1px solid #ff5252;text-align:center;vertical-align:middle; }
  .estado-pendiente { font-family:Arial;font-size:9pt;font-weight:bold;background-color:#fff2cc;color:#7d6608;border:1px solid #ffc000;text-align:center;vertical-align:middle; }

  /* Footer */
  .footer {
    font-family:Arial;font-size:9pt;font-weight:bold;
    background-color:#355d91;color:#FFFFFF;
    text-align:right;border:1px solid #2a4a75;
  }
</style>
</head>
<body>

<!-- ══════════════════════ TÍTULO ══════════════════════ -->
<table border="0" cellpadding="6" cellspacing="0" width="100%">
  <tr height="36">
    <td colspan="10" class="titulo">
      &#128197; Autorizaciones de Ambientes &mdash; <?= $nombre_mes ?> <?= $anio ?>
    </td>
  </tr>
  <tr height="22">
    <td colspan="10" class="subtitulo">
      Generado el <?= date('d/m/Y H:i') ?> &nbsp;&bull;&nbsp; Sistema de Gestión SENA
    </td>
  </tr>
  <tr><td colspan="10">&nbsp;</td></tr>
</table>

<!-- ══════════════════════ ESTADÍSTICAS ══════════════════════ -->
<table border="0" cellpadding="5" cellspacing="2">
  <tr height="28">
    <td class="stat-lbl-total">&nbsp;Total&nbsp;</td>
    <td class="stat-val-total" width="40"><b><?= $total ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-pend">&nbsp;Pendientes&nbsp;</td>
    <td class="stat-val-pend" width="40"><b><?= $pendiente ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-apro">&nbsp;Aprobados&nbsp;</td>
    <td class="stat-val-apro" width="40"><b><?= $aprobado ?></b></td>
    <td width="10"></td>
    <td class="stat-lbl-rech">&nbsp;Rechazados&nbsp;</td>
    <td class="stat-val-rech" width="40"><b><?= $rechazado ?></b></td>
  </tr>
</table>

<table><tr><td colspan="10" height="10">&nbsp;</td></tr></table>

<!-- ══════════════════════ TABLA PRINCIPAL ══════════════════════ -->
<table border="0" cellpadding="7" cellspacing="0" width="100%">
  <thead>
    <tr height="30">
      <?php
      $headers = [
          '#'              => 40,
          'Ambiente'       => 150,
          'Instructor'     => 140,
          'Fecha Inicio'   => 90,
          'Fecha Fin'      => 90,
          'Hora Inicio'    => 80,
          'Hora Fin'       => 80,
          'Días'           => 110,
          'Autorizado Por' => 120,
          'Estado'         => 90,
      ];
      foreach ($headers as $h => $w): ?>
      <th class="th" width="<?= $w ?>"><?= $h ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php
  $fila = 0;
  while ($row = mysqli_fetch_assoc($resultado)):
      $fila++;
      $clase = ($fila % 2 === 0) ? 'td-par' : 'td-impar';

      /* Clase del estado */
      switch ($row['estado']) {
          case 'Aprobado':  $claseEstado = 'estado-aprobado';  break;
          case 'Rechazado': $claseEstado = 'estado-rechazado'; break;
          default:          $claseEstado = 'estado-pendiente'; break;
      }

      /* Días */
      $diasNums  = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                   ? array_map('intval', explode(',', $row['dias_semana']))
                   : [];
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
      <td class="<?= $clase ?>"><?= htmlspecialchars($row['rol_autorizado']) ?></td>
      <td class="<?= $claseEstado ?>"><?= htmlspecialchars($row['estado']) ?></td>
    </tr>
  <?php endwhile; ?>
  </tbody>
  <tfoot>
    <tr height="24">
      <td colspan="10" class="footer">
        &nbsp;&nbsp;Total de registros: <?= $total ?> &nbsp;&nbsp;
      </td>
    </tr>
  </tfoot>
</table>

</body>
</html>