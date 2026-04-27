<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != 'administracion' && $_SESSION['rol'] != 'subdireccion')) {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$fecha_actual = date('Y-m-d');
$hora_actual  = date('H:i:s');

// ── Filtro ────────────────────────────────────────────────────
$buscar          = trim($_GET['buscar'] ?? '');
$id_ficha_filtro = null;
$numero_ficha_label = 'Todas las fichas';
$ficha_info      = null;

if ($buscar !== '') {
    $buscar_esc = mysqli_real_escape_string($conexion, $buscar);
    $res_id = mysqli_query($conexion,
        "SELECT id, numero_ficha, programa, jornada, fecha_inicio, fecha_fin
         FROM fichas WHERE numero_ficha LIKE '%$buscar_esc%' LIMIT 1");
    if ($res_id && ($row_id = mysqli_fetch_assoc($res_id))) {
        $id_ficha_filtro    = (int)$row_id['id'];
        $numero_ficha_label = 'Ficha ' . $row_id['numero_ficha'];
        $ficha_info         = $row_id;
    }
} elseif (isset($_GET['id_ficha']) && $_GET['id_ficha'] !== '') {
    $id_ficha_filtro = (int)$_GET['id_ficha'];
    $res_num = mysqli_query($conexion,
        "SELECT id, numero_ficha, programa, jornada, fecha_inicio, fecha_fin
         FROM fichas WHERE id = $id_ficha_filtro LIMIT 1");
    if ($res_num && ($row_num = mysqli_fetch_assoc($res_num))) {
        $numero_ficha_label = 'Ficha ' . $row_num['numero_ficha'];
        $ficha_info         = $row_num;
    }
}

// ── Abreviaciones de días ─────────────────────────────────────
$abrevDias = [1=>'Dom',2=>'Lun',3=>'Mar',4=>'Mié',5=>'Jue',6=>'Vie',7=>'Sáb'];

// ── Consulta: programación desde autorizaciones_ambientes ─────
$where = $id_ficha_filtro !== null ? "WHERE au.id_ficha = $id_ficha_filtro" : '';

$sql = "SELECT
            f.numero_ficha,
            f.programa,
            f.jornada,
            a.nombre_ambiente,
            i.nombre              AS nombre_instructor,
            MIN(au.fecha_inicio)  AS fecha_inicio,
            MAX(au.fecha_fin)     AS fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.estado,
            au.observaciones,
            GROUP_CONCAT(
                DISTINCT DAYOFWEEK(au.fecha_inicio)
                ORDER BY DAYOFWEEK(au.fecha_inicio)
            ) AS dias_semana
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        JOIN fichas       f ON au.id_ficha      = f.id
        $where
        GROUP BY au.id_ficha, au.id_ambiente, au.id_instructor,
                 au.hora_inicio, au.hora_final, au.estado, au.observaciones
        ORDER BY f.numero_ficha ASC, MIN(au.fecha_inicio) ASC, au.hora_inicio ASC";

$resultado = mysqli_query($conexion, $sql);
if (!$resultado) {
    die('Error en consulta: ' . mysqli_error($conexion));
}

$filas = [];
while ($row = mysqli_fetch_assoc($resultado)) {
    $filas[] = $row;
}

// ── HEADERS EXCEL ─────────────────────────────────────────────
$sufijo   = $id_ficha_filtro !== null ? '_'.$numero_ficha_label : '_Todas';
$filename = "Programacion_Fichas".$sufijo."_".date('Y-m-d').".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // UTF-8 BOM para que Excel lea bien los acentos
?>

<html>
<body>

<!-- TÍTULO -->
<table>
<tr>
<td colspan="9" style="background:#0b2449;color:white;font-size:16pt;font-weight:bold;text-align:center;padding:6px;">
PROGRAMACIÓN DE FICHAS - SENA
</td>
</tr>

<tr>
<td colspan="9" style="background:#355d91;color:white;text-align:center;font-size:14pt;padding:4px;">
<?= htmlspecialchars($numero_ficha_label) ?>
</td>
</tr>
</table>

<br>

<!-- INFORMACIÓN GENERAL (solo si hay filtro por ficha) -->
<?php if($ficha_info): ?>
<table border="1">
<tr style="background:#e8eef7;font-weight:bold;">
<td colspan="6" style="padding:4px;font-family:Calibri,Arial;font-size:11pt;">INFORMACIÓN GENERAL DE LA FICHA</td>
</tr>

<tr style="font-family:Calibri,Arial;font-size:10pt;">
<td style="background:#f5f5f5;padding:3px;"><b>Programa</b></td>
<td><?= htmlspecialchars($ficha_info['programa'] ?? 'No definido') ?></td>
<td style="background:#f5f5f5;padding:3px;"><b>Jornada</b></td>
<td><?= htmlspecialchars($ficha_info['jornada'] ?? 'No definida') ?></td>
<td style="background:#f5f5f5;padding:3px;"><b>Fecha Inicio</b></td>
<td><?= $ficha_info['fecha_inicio'] ? date('d/m/Y', strtotime($ficha_info['fecha_inicio'])) : 'No definida' ?></td>
</tr>

<tr style="font-family:Calibri,Arial;font-size:10pt;">
<td style="background:#f5f5f5;padding:3px;"><b>Fecha Fin</b></td>
<td><?= $ficha_info['fecha_fin'] ? date('d/m/Y', strtotime($ficha_info['fecha_fin'])) : 'No definida' ?></td>
<td style="background:#f5f5f5;padding:3px;"><b>Total Registros</b></td>
<td colspan="3"><?= count($filas) ?></td>
</tr>
</table>

<br>
<?php endif; ?>

<!-- TABLA DE PROGRAMACIÓN -->
<table border="1">
<tr style="background:#0b2449;color:white;font-weight:bold;">
<td colspan="9" style="padding:4px;font-family:Calibri,Arial;font-size:11pt;">PROGRAMACIÓN DE AMBIENTES</td>
</tr>

<tr style="background:#355d91;color:white;font-family:Calibri,Arial;font-size:10pt;">
<td style="padding:3px;text-align:center;"><b>N° Ficha</b></td>
<td style="padding:3px;text-align:center;"><b>Programa</b></td>
<td style="padding:3px;text-align:center;"><b>Jornada</b></td>
<td style="padding:3px;text-align:center;"><b>Ambiente</b></td>
<td style="padding:3px;text-align:center;"><b>Instructor</b></td>
<td style="padding:3px;text-align:center;"><b>Fecha Inicio</b></td>
<td style="padding:3px;text-align:center;"><b>Fecha Fin</b></td>
<td style="padding:3px;text-align:center;"><b>Días / Horario</b></td>
<td style="padding:3px;text-align:center;"><b>Estado</b></td>
</tr>

<?php 
$abrevDias = [1=>'Dom',2=>'Lun',3=>'Mar',4=>'Mié',5=>'Jue',6=>'Vie',7=>'Sáb'];

foreach($filas as $f): 
    $diasNums  = ($f['dias_semana'] !== null && $f['dias_semana'] !== '')
                 ? array_map('intval', explode(',', $f['dias_semana'])) : [];
    $diasTexto = count($diasNums)
                 ? implode(' · ', array_map(function($d) use ($abrevDias) {
                     return $abrevDias[$d] ?? '?';
                 }, $diasNums))
                 : '—';

    $horario = ($f['hora_inicio'] && $f['hora_final'])
        ? substr($f['hora_inicio'],0,5) . ' — ' . substr($f['hora_final'],0,5) . '  (' . $diasTexto . ')'
        : '—';

    $fechaIni = $f['fecha_inicio'] ? date('d/m/Y', strtotime($f['fecha_inicio'])) : '—';
    $fechaFin = $f['fecha_fin']    ? date('d/m/Y', strtotime($f['fecha_fin']))    : '—';

    // Color según estado
    $estado = $f['estado'] ?? '';
    if ($estado === 'Aprobado') {
        $bgEst = '#d4edda';
        $colorEst = '#155724';
    } elseif ($estado === 'Pendiente') {
        $bgEst = '#fff3cd';
        $colorEst = '#856404';
    } elseif ($estado === 'Rechazado') {
        $bgEst = '#f8d7da';
        $colorEst = '#721c24';
    } else {
        $bgEst = '#ffffff';
        $colorEst = '#000000';
    }
?>
<tr style="font-family:Calibri,Arial;font-size:10pt;">
<td style="padding:3px;text-align:center;color:#1a56db;font-weight:bold;"><?= htmlspecialchars($f['numero_ficha'] ?? '—') ?></td>
<td style="padding:3px;"><?= htmlspecialchars($f['programa'] ?? '—') ?></td>
<td style="padding:3px;text-align:center;"><?= htmlspecialchars($f['jornada'] ?? '—') ?></td>
<td style="padding:3px;"><?= htmlspecialchars($f['nombre_ambiente'] ?? '—') ?></td>
<td style="padding:3px;"><?= htmlspecialchars($f['nombre_instructor'] ?? '—') ?></td>
<td style="padding:3px;text-align:center;"><?= $fechaIni ?></td>
<td style="padding:3px;text-align:center;"><?= $fechaFin ?></td>
<td style="padding:3px;"><?= htmlspecialchars($horario) ?></td>
<td style="padding:3px;text-align:center;background:<?= $bgEst ?>;color:<?= $colorEst ?>;font-weight:bold;"><?= htmlspecialchars($estado ?: '—') ?></td>
</tr>
<?php endforeach; ?>

<?php if(count($filas) === 0): ?>
<tr>
<td colspan="9" style="text-align:center;padding:8px;color:#666;font-family:Calibri,Arial;">No se encontraron registros de programación.</td>
</tr>
<?php endif; ?>
</table>

</body>
</html>