<?php
session_start();
if ($_SESSION['rol'] != 'sub') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* ── Parámetros del filtro ── */
$meses_espanol = [
    '01' => 'Enero',    '02' => 'Febrero',  '03' => 'Marzo',
    '04' => 'Abril',    '05' => 'Mayo',     '06' => 'Junio',
    '07' => 'Julio',    '08' => 'Agosto',   '09' => 'Septiembre',
    '10' => 'Octubre',  '11' => 'Noviembre','12' => 'Diciembre'
];

$mes  = $_GET['mes']  ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

$nombre_mes = $meses_espanol[$mes] ?? 'Mes';

/* ── Consulta ── */
$sql = "SELECT 
            a.nombre_ambiente,
            i.nombre            AS nombre_instructor,
            au.fecha_inicio,
            au.fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.rol_autorizado,
            au.estado
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE MONTH(au.fecha_inicio) = '$mes'
          AND YEAR(au.fecha_inicio)  = '$anio'
        ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);

/* ── Headers para descarga .xls ── */
echo "\xEF\xBB\xBF"; // BOM UTF-8
echo "\"#\",\"Ambiente\",\"Instructor\",\"Fecha Inicio\",\"Fecha Fin\",\"Hora Inicio\",\"Hora Fin\",\"Autorizado Por\",\"Estado\"\n";
while ($row = mysqli_fetch_assoc($resultado)) {
    $fila++;
    echo '"'.$fila.'",';
    echo '"'.addslashes($row['nombre_ambiente']).'",';
    echo '"'.addslashes($row['nombre_instructor']).'",';
    echo '"'.date('d/m/Y', strtotime($row['fecha_inicio'])).'",';
    echo '"'.date('d/m/Y', strtotime($row['fecha_fin'])).'",';
    echo '"'.date('h:i A', strtotime($row['hora_inicio'])).'",';
    echo '"'.date('h:i A', strtotime($row['hora_final'])).'",';
    echo '"'.addslashes($row['rol_autorizado']).'",';
    echo '"'.addslashes($row['estado']).'"'."\n";
}
exit;

/* BOM para que Excel reconozca UTF-8 */
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml><x:ExcelWorkbook><x:ExcelWorksheets>
        <x:ExcelWorksheet>
            <x:Name>Autorizaciones</x:Name>
            <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
        </x:ExcelWorksheet>
    </x:ExcelWorksheets></x:ExcelWorkbook></xml>
    <![endif]-->
</head>
<body>

<!-- ══ TÍTULO ══════════════════════════════════════════════════════════ -->
<table>
    <tr>
        <td colspan="8" style="
            font-size:16pt; font-weight:bold; color:#FFFFFF;
            background:#355d91; text-align:center;
            padding:10px; border:1px solid #2a4a75;">
            Autorizaciones de Ambientes — <?= $nombre_mes ?> <?= $anio ?>
        </td>
    </tr>
    <tr>
        <td colspan="8" style="
            font-size:10pt; color:#666666; text-align:center;
            padding:4px; border:1px solid #dddddd;">
            Generado el <?= date('d/m/Y H:i') ?> · Sistema de Gestión SENA
        </td>
    </tr>
    <tr><td colspan="8">&nbsp;</td></tr><!-- fila vacía separadora -->
</table>

<!-- ══ ESTADÍSTICAS RÁPIDAS ════════════════════════════════════════════ -->
<?php
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
?>
<table>
    <tr>
        <td style="font-weight:bold; background:#f0f4ff; padding:6px 12px; border:1px solid #c8d6ef;">Total</td>
        <td style="background:#f0f4ff; padding:6px 20px; border:1px solid #c8d6ef;"><?= $total ?></td>

        <td style="font-weight:bold; background:#fff8e1; padding:6px 12px; border:1px solid #ffe082;">Pendientes</td>
        <td style="background:#fff8e1; padding:6px 20px; border:1px solid #ffe082;"><?= $pendiente ?></td>

        <td style="font-weight:bold; background:#e8f5e9; padding:6px 12px; border:1px solid #a5d6a7;">Aprobados</td>
        <td style="background:#e8f5e9; padding:6px 20px; border:1px solid #a5d6a7;"><?= $aprobado ?></td>

        <td style="font-weight:bold; background:#ffebee; padding:6px 12px; border:1px solid #ef9a9a;">Rechazados</td>
        <td style="background:#ffebee; padding:6px 20px; border:1px solid #ef9a9a;"><?= $rechazado ?></td>
    </tr>
</table>
<table><tr><td colspan="8">&nbsp;</td></tr></table>

<!-- ══ TABLA PRINCIPAL ═════════════════════════════════════════════════ -->
<table>
    <!-- Encabezados -->
    <thead>
        <tr>
            <?php
            $headers = [
                '#', 'Ambiente', 'Instructor',
                'Fecha Inicio', 'Fecha Fin',
                'Hora Inicio', 'Hora Fin',
                'Autorizado Por', 'Estado'
            ];
            foreach ($headers as $h): ?>
            <th style="
                background:#355d91; color:#FFFFFF; font-weight:bold;
                padding:8px 12px; border:1px solid #2a4a75;
                text-align:center; white-space:nowrap;">
                <?= $h ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>

    <!-- Filas de datos -->
    <tbody>
    <?php
    $fila = 0;
    while ($row = mysqli_fetch_assoc($resultado)):
        $fila++;
        $bg = ($fila % 2 === 0) ? '#f7f9ff' : '#ffffff';

        /* Color del estado */
        switch ($row['estado']) {
            case 'Aprobado':
                $est_bg = '#e8f5e9'; $est_color = '#2e7d32'; break;
            case 'Rechazado':
                $est_bg = '#ffebee'; $est_color = '#c62828'; break;
            default: /* Pendiente */
                $est_bg = '#fff8e1'; $est_color = '#f57f17'; break;
        }

        $celda = "background:$bg; padding:7px 12px; border:1px solid #dde3ef;";
    ?>
        <tr>
            <td style="<?= $celda ?> text-align:center; color:#888;"><?= $fila ?></td>
            <td style="<?= $celda ?> font-weight:bold;">
                <?= htmlspecialchars($row['nombre_ambiente']) ?>
            </td>
            <td style="<?= $celda ?>">
                <?= htmlspecialchars($row['nombre_instructor']) ?>
            </td>
            <td style="<?= $celda ?> text-align:center;">
                <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
            </td>
            <td style="<?= $celda ?> text-align:center;">
                <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
            </td>
            <td style="<?= $celda ?> text-align:center;">
                <?= date('h:i A', strtotime($row['hora_inicio'])) ?>
            </td>
            <td style="<?= $celda ?> text-align:center;">
                <?= date('h:i A', strtotime($row['hora_final'])) ?>
            </td>
            <td style="<?= $celda ?>">
                <?= htmlspecialchars($row['rol_autorizado']) ?>
            </td>
            <td style="
                background:<?= $est_bg ?>; color:<?= $est_color ?>;
                font-weight:bold; padding:7px 12px;
                border:1px solid #dde3ef; text-align:center;">
                <?= htmlspecialchars($row['estado']) ?>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>

    <!-- Pie de totales -->
    <tfoot>
        <tr>
            <td colspan="9" style="
                background:#f0f4ff; padding:8px 12px;
                border:1px solid #c8d6ef; text-align:right;
                font-weight:bold; color:#355d91;">
                Total de registros: <?= $total ?>
            </td>
        </tr>
    </tfoot>
</table>

</body>
</html>