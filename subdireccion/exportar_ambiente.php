<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != 'administracion' && $_SESSION['rol'] != 'subdireccion')) {
    header("Location: ../login.php");
    exit;
}

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

include("../includes/conexion.php");

$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');

$id_ambiente = $_GET['id'] ?? null;

if (!$id_ambiente) {
    die('Error: ID de ambiente no especificado');
}

$id_ambiente = mysqli_real_escape_string($conexion, $id_ambiente);

// INFORMACIÓN DEL AMBIENTE
$sqlAmb = "SELECT a.*, 
                  i.nombre AS nombre_instructor_fijo,
                  i.identificacion AS doc_instructor_fijo
           FROM ambientes a
           LEFT JOIN instructores i ON a.instructor_id = i.id
           WHERE a.id = '$id_ambiente'";
$resAmb = mysqli_query($conexion, $sqlAmb);
$ambienteInfo = mysqli_fetch_assoc($resAmb);

if (!$ambienteInfo) {
    die('Error: Ambiente no encontrado');
}

// PRÓXIMOS USOS
$sqlProximos = "SELECT 
                    MIN(au.fecha_inicio) AS fecha_inicio,
                    MAX(au.fecha_inicio) AS fecha_fin,
                    au.hora_inicio,
                    au.hora_final,
                    i.nombre AS nombre_instructor,
                    i.identificacion AS doc_instructor,
                    au.observaciones,
                    GROUP_CONCAT(
                        DISTINCT DATE_FORMAT(au.fecha_inicio, '%d/%m/%Y')
                        ORDER BY au.fecha_inicio
                        SEPARATOR ', '
                    ) AS fechas_detalle
                FROM autorizaciones_ambientes au
                JOIN instructores i ON au.id_instructor = i.id
                WHERE au.id_ambiente = '$id_ambiente'
                  AND (
                    (au.fecha_inicio > '$fecha_actual')
                    OR (au.fecha_inicio = '$fecha_actual' AND au.hora_inicio > '$hora_actual')
                  )
                  AND au.estado = 'Aprobado'
                GROUP BY au.id_instructor, au.hora_inicio, au.hora_final, au.observaciones
                ORDER BY MIN(au.fecha_inicio) ASC";
$proximosUsos = mysqli_query($conexion, $sqlProximos);

// HISTORIAL RECIENTE
$sqlHistorial = "SELECT au.*, 
                        a.nombre_ambiente,
                        i.nombre AS nombre_instructor,
                        i.identificacion AS doc_instructor
                 FROM autorizaciones_ambientes au
                 JOIN ambientes a ON au.id_ambiente = a.id
                 JOIN instructores i ON au.id_instructor = i.id
                 WHERE au.id_ambiente = '$id_ambiente'
                 ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                 LIMIT 50";
$historial = mysqli_query($conexion, $sqlHistorial);

// CREAR SPREADSHEET
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// TÍTULO PRINCIPAL
$sheet->setCellValue('A1', 'REPORTE DE AMBIENTE - SENA');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0b2449');
$sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getRowDimension('1')->setRowHeight(35);

// NOMBRE DEL AMBIENTE
$sheet->setCellValue('A2', $ambienteInfo['nombre_ambiente']);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF355d91');
$sheet->getStyle('A2')->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getRowDimension('2')->setRowHeight(30);

// INFORMACIÓN GENERAL
$row = 4;
$sheet->setCellValue('A'.$row, 'INFORMACIÓN GENERAL');
$sheet->mergeCells('A'.$row.':H'.$row);
$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFe8eef7');
$sheet->getRowDimension($row)->setRowHeight(25);

$row++;
$sheet->setCellValue('A'.$row, 'Estado:');
$sheet->setCellValue('B'.$row, $ambienteInfo['estado']);
$sheet->setCellValue('D'.$row, 'Horario Disponible:');
$sheet->setCellValue('E'.$row, $ambienteInfo['horario_disponible'] ?: 'No definido');

$row++;
$sheet->setCellValue('A'.$row, 'Horario Fijo:');
$sheet->setCellValue('B'.$row, $ambienteInfo['horario_fijo'] ?: 'No definido');
$sheet->setCellValue('D'.$row, 'Instructor Fijo:');
$sheet->setCellValue('E'.$row, $ambienteInfo['nombre_instructor_fijo'] ?: 'No asignado');

// Aplicar estilos a info general
$sheet->getStyle('A5:A6')->getFont()->setBold(true);
$sheet->getStyle('D5:D6')->getFont()->setBold(true);

$row += 2;

// PRÓXIMOS USOS
if (mysqli_num_rows($proximosUsos) > 0) {
    $sheet->setCellValue('A'.$row, 'PRÓXIMOS USOS');
    $sheet->mergeCells('A'.$row.':H'.$row);
    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF43a047');
    $sheet->getStyle('A'.$row)->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getRowDimension($row)->setRowHeight(25);

    $row++;
    $headers = ['Fecha Inicio', 'Fecha Fin', 'Instructor', 'Documento', 'Hora Inicio', 'Hora Fin', 'Observaciones'];
    $col = 'A';
    foreach($headers as $header){
        $sheet->setCellValue($col.$row, $header);
        $sheet->getStyle($col.$row)->getFont()->setBold(true);
        $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF66bb6a');
        $sheet->getStyle($col.$row)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $col++;
    }

    $row++;
    mysqli_data_seek($proximosUsos, 0);
    while($prox = mysqli_fetch_assoc($proximosUsos)){
        $sheet->setCellValue('A'.$row, date('d/m/Y', strtotime($prox['fecha_inicio'])));
        $sheet->setCellValue('B'.$row, date('d/m/Y', strtotime($prox['fecha_fin'])));
        $sheet->setCellValue('C'.$row, $prox['nombre_instructor']);
        $sheet->setCellValue('D'.$row, $prox['doc_instructor']);
        $sheet->setCellValue('E'.$row, date('h:i A', strtotime($prox['hora_inicio'])));
        $sheet->setCellValue('F'.$row, date('h:i A', strtotime($prox['hora_final'])));
        $sheet->setCellValue('G'.$row, $prox['observaciones'] ?: '—');
        
        if($row % 2 == 0){
            $sheet->getStyle('A'.$row.':G'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFe8f5e9');
        }
        $row++;
    }
    $row++;
}

// HISTORIAL COMPLETO
$sheet->setCellValue('A'.$row, 'HISTORIAL COMPLETO');
$sheet->mergeCells('A'.$row.':H'.$row);
$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFfb8c00');
$sheet->getStyle('A'.$row)->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getRowDimension($row)->setRowHeight(25);

$row++;
$headers = ['Fecha Inicio', 'Fecha Fin', 'Instructor', 'Documento', 'Hora Inicio', 'Hora Fin', 'Estado', 'Autorizado Por'];
$col = 'A';
foreach($headers as $header){
    $sheet->setCellValue($col.$row, $header);
    $sheet->getStyle($col.$row)->getFont()->setBold(true);
    $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFffcc80');
    $sheet->getStyle($col.$row)->getFont()->getColor()->setARGB('FF000000');
    $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
}

$row++;
while($hist = mysqli_fetch_assoc($historial)){
    $sheet->setCellValue('A'.$row, date('d/m/Y', strtotime($hist['fecha_inicio'])));
    $sheet->setCellValue('B'.$row, date('d/m/Y', strtotime($hist['fecha_fin'])));
    $sheet->setCellValue('C'.$row, $hist['nombre_instructor']);
    $sheet->setCellValue('D'.$row, $hist['doc_instructor']);
    $sheet->setCellValue('E'.$row, date('h:i A', strtotime($hist['hora_inicio'])));
    $sheet->setCellValue('F'.$row, date('h:i A', strtotime($hist['hora_final'])));
    $sheet->setCellValue('G'.$row, $hist['estado']);
    $sheet->setCellValue('H'.$row, ucfirst($hist['rol_autorizado']));
    
    if($row % 2 == 0){
        $sheet->getStyle('A'.$row.':H'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFfff8f0');
    }
    $row++;
}

// BORDES
$lastRow = $row - 1;
$sheet->getStyle('A4:H'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ANCHOS DE COLUMNA
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(18);

// DESCARGAR
$filename = 'Ambiente_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $ambienteInfo['nombre_ambiente']).'_'.date('Y-m-d').'.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>