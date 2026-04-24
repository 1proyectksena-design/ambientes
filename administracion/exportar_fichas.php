<?php
session_start();
include("../includes/conexion.php");

// ── Restricción de acceso ─────────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    http_response_code(403);
    exit('Acceso denegado.');
}

// ── PhpSpreadsheet ────────────────────────────────────────────
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
    if ($res_id && $row_id = mysqli_fetch_assoc($res_id)) {
        $id_ficha_filtro    = (int)$row_id['id'];
        $numero_ficha_label = 'Ficha ' . $row_id['numero_ficha'];
        $ficha_info         = $row_id;
    }
} elseif (isset($_GET['id_ficha']) && $_GET['id_ficha'] !== '') {
    $id_ficha_filtro = (int)$_GET['id_ficha'];
    $res_num = mysqli_query($conexion,
        "SELECT id, numero_ficha, programa, jornada, fecha_inicio, fecha_fin
         FROM fichas WHERE id = $id_ficha_filtro LIMIT 1");
    if ($res_num && $row_num = mysqli_fetch_assoc($res_num)) {
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

// ══════════════════════════════════════════════════════════════
//  CONSTRUCCIÓN DEL XLSX
// ══════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Programación');

// Colores
$C_DARK   = '172f63';
$C_MID    = '355d91';
$C_LIGHT  = 'EAF0F8';
$C_WHITE  = 'FFFFFF';
$C_ALT    = 'F1F5FB';
$C_BORDER = 'C8D6EA';
$C_APR    = 'D1FAE5';
$C_PEN    = 'FEF3C7';
$C_REC    = 'FEE2E2';

// ── Fila 1: Título ────────────────────────────────────────────
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', '   SENA — Programación de Ambientes por Fichas');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>13,'color'=>['rgb'=>$C_WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$C_DARK]],
    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ── Fila 2: Subtítulo ─────────────────────────────────────────
$sheet->mergeCells('A2:I2');
$sub = $id_ficha_filtro !== null
    ? $numero_ficha_label . ($ficha_info ? '   ·   ' . ($ficha_info['programa'] ?? '') : '')
    : 'Exportación completa — Todas las fichas';
$sheet->setCellValue('A2', '   ' . $sub);
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>$C_WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$C_MID]],
    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(2)->setRowHeight(20);

// ── Fila 3: Metadatos ─────────────────────────────────────────
$sheet->mergeCells('A3:E3');
$sheet->mergeCells('F3:I3');
$sheet->setCellValue('A3', '   Generado: ' . date('d/m/Y H:i'));
$sheet->setCellValue('F3', 'Total registros: ' . count($filas) . '   ');
$sheet->getStyle('A3:I3')->applyFromArray([
    'font'      => ['italic'=>true,'size'=>9,'color'=>['rgb'=>'444444']],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$C_LIGHT]],
    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
]);
$sheet->getStyle('F3')->applyFromArray([
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
]);
$sheet->getRowDimension(3)->setRowHeight(15);

// ── Fila 4: separador visual ──────────────────────────────────
$sheet->getRowDimension(4)->setRowHeight(5);

// ── Fila 5: Encabezados ───────────────────────────────────────
$cols = [
    'A' => 'N° Ficha',
    'B' => 'Programa',
    'C' => 'Jornada',
    'D' => 'Ambiente',
    'E' => 'Instructor',
    'F' => 'Fecha Inicio',
    'G' => 'Fecha Fin',
    'H' => 'Días / Horario',
    'I' => 'Estado',
];
foreach ($cols as $col => $label) {
    $sheet->setCellValue("{$col}5", $label);
}
$sheet->getStyle('A5:I5')->applyFromArray([
    'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>$C_WHITE]],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$C_DARK]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$C_DARK]]],
]);
$sheet->getRowDimension(5)->setRowHeight(20);

// ── Datos ─────────────────────────────────────────────────────
$fila = 6;
foreach ($filas as $idx => $f) {

    $diasNums  = ($f['dias_semana'] !== null && $f['dias_semana'] !== '')
                 ? array_map('intval', explode(',', $f['dias_semana'])) : [];
    $diasTexto = count($diasNums)
                 ? implode(' · ', array_map(fn($d) => $abrevDias[$d] ?? '?', $diasNums))
                 : '—';

    $horario = ($f['hora_inicio'] && $f['hora_final'])
        ? substr($f['hora_inicio'],0,5) . ' — ' . substr($f['hora_final'],0,5) . '  (' . $diasTexto . ')'
        : '—';

    $fechaIni = $f['fecha_inicio'] ? date('d/m/Y', strtotime($f['fecha_inicio'])) : '—';
    $fechaFin = $f['fecha_fin']    ? date('d/m/Y', strtotime($f['fecha_fin']))    : '—';

    $sheet->setCellValue("A{$fila}", $f['numero_ficha']      ?? '—');
    $sheet->setCellValue("B{$fila}", $f['programa']          ?? '—');
    $sheet->setCellValue("C{$fila}", $f['jornada']           ?? '—');
    $sheet->setCellValue("D{$fila}", $f['nombre_ambiente']   ?? '—');
    $sheet->setCellValue("E{$fila}", $f['nombre_instructor'] ?? '—');
    $sheet->setCellValue("F{$fila}", $fechaIni);
    $sheet->setCellValue("G{$fila}", $fechaFin);
    $sheet->setCellValue("H{$fila}", $horario);
    $sheet->setCellValue("I{$fila}", $f['estado']            ?? '—');

    $bgFila = ($idx % 2 === 0) ? $C_WHITE : $C_ALT;

    $sheet->getStyle("A{$fila}:I{$fila}")->applyFromArray([
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bgFila]],
        'font'      => ['size'=>9],
        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$C_BORDER]]],
    ]);

    // Número de ficha en azul negrita
    $sheet->getStyle("A{$fila}")->applyFromArray([
        'font' => ['bold'=>true,'color'=>['rgb'=>'1D4ED8']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);

    // Centrar jornada, fechas y estado
    foreach (['C','F','G','I'] as $c) {
        $sheet->getStyle("{$c}{$fila}")->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Color celda Estado
    $estadoBg = match($f['estado'] ?? '') {
        'Aprobado'  => $C_APR,
        'Pendiente' => $C_PEN,
        'Rechazado' => $C_REC,
        default     => $bgFila,
    };
    $sheet->getStyle("I{$fila}")->applyFromArray([
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$estadoBg]],
        'font' => ['bold'=>true,'size'=>9],
    ]);

    $sheet->getRowDimension($fila)->setRowHeight(17);
    $fila++;
}

// ── Anchos ───────────────────────────────────────────────────
$sheet->getColumnDimension('A')->setWidth(14);
$sheet->getColumnDimension('B')->setWidth(34);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(24);
$sheet->getColumnDimension('E')->setWidth(28);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(14);
$sheet->getColumnDimension('H')->setWidth(32);
$sheet->getColumnDimension('I')->setWidth(13);

// ── Inmovilizar encabezados + filtro automático ───────────────
$sheet->freezePane('A6');
$sheet->setAutoFilter('A5:I5');

// ── Nombre del archivo y salida ───────────────────────────────
$sufijo = $id_ficha_filtro !== null ? '_ficha' . $id_ficha_filtro : '_todas';
$nombre = 'programacion_fichas' . $sufijo . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;