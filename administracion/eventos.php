<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}
ini_set('display_errors', 1);
error_reporting(E_ALL);
include("../includes/conexion.php");
header('Content-Type: application/json; charset=UTF-8');

/* ── Parámetros de filtro ── */
$ambiente   = $_GET['ambiente']   ?? '';
$instructor = $_GET['instructor'] ?? '';
$estado     = $_GET['estado']     ?? '';
$start      = $_GET['start']      ?? '';
$end        = $_GET['end']        ?? '';

/* ── Construcción dinámica del WHERE ── */
$where = [];

if ($ambiente && $ambiente !== 'todos') {
    $where[] = "a.id = " . intval($ambiente);
}

if ($instructor && $instructor !== 'todos') {
    $where[] = "i.id = " . intval($instructor);
}

if ($estado && $estado !== 'todos') {
    $est = mysqli_real_escape_string($conexion, $estado);
    $where[] = "au.estado = '$est'";
}

if ($start) {
    $s = substr($start, 0, 10);
    $where[] = "au.fecha_fin >= '$s'";
}

if ($end) {
    $e = substr($end, 0, 10);
    $where[] = "au.fecha_inicio <= '$e'";
}

$whereStr = count($where) ? implode(' AND ', $where) : '1=1';

$sql = "SELECT
            au.id,
            a.nombre_ambiente,
            i.nombre        AS nombre_instructor,
            au.fecha_inicio,
            au.fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.estado,
            au.rol_autorizado,
            au.observaciones
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE $whereStr
        ORDER BY au.fecha_inicio ASC, au.hora_inicio ASC";

$res    = mysqli_query($conexion, $sql);
$events = [];
if (!$res) {
    echo json_encode([
        "error" => "Error en SQL",
        "detalle" => mysqli_error($conexion)
    ]);
    exit;
}
while ($row = mysqli_fetch_assoc($res)) {

    /* Color según estado */
    switch ($row['estado']) {
        case 'Aprobado':
            $color  = '#2e7d32';
            $border = '#1b5e20';
            $text   = '#ffffff';
            break;
        case 'Pendiente':
            $color  = '#f9a825';
            $border = '#f57f17';
            $text   = '#1a1a1a';
            break;
        case 'Rechazado':
            $color  = '#c62828';
            $border = '#b71c1c';
            $text   = '#ffffff';
            break;
        default:
            $color  = '#757575';
            $border = '#424242';
            $text   = '#ffffff';
    }

    /* start / end en formato ISO que FullCalendar espera */
    $hora_inicio = date('H:i:s', strtotime($row['hora_inicio']));
    $hora_fin    = date('H:i:s', strtotime($row['hora_final']));

    $start_dt = $row['fecha_inicio'] . 'T' . $hora_inicio;
    $end_dt   = $row['fecha_fin']    . 'T' . $hora_fin;
    
    $events[] = [
        'id'              => $row['id'],
        'title'           => $row['nombre_ambiente'] . ' — ' . $row['nombre_instructor'],
        'start'           => $start_dt,
        'end'             => $end_dt,
        'backgroundColor' => $color,
        'borderColor'     => $border,
        'textColor'       => $text,
        'extendedProps'   => [
            'ambiente'    => $row['nombre_ambiente'],
            'instructor'  => $row['nombre_instructor'],
            'estado'      => $row['estado'],
            'hora_inicio' => date('h:i A', strtotime($row['hora_inicio'])),
            'hora_fin'    => date('h:i A', strtotime($row['hora_final'])),
            'autorizado'  => $row['rol_autorizado'],
            'obs'         => $row['observaciones'] ?? '',
        ],
    ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);