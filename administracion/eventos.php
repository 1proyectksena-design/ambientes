<?php
/*
 * eventos.php  —  Fuente de datos para FullCalendar
 * Devuelve JSON con los eventos de autorizaciones_ambientes.
 * Incluye numero_ficha y programa para mostrar en el modal del calendario.
 */
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'administracion') {
    http_response_code(403);
    echo json_encode(['error' => 'Sin acceso']);
    exit;
}

include("../includes/conexion.php");

header('Content-Type: application/json; charset=utf-8');

$start      = $_GET['start']      ?? date('Y-m-01');
$end        = $_GET['end']        ?? date('Y-m-t');
$ambiente   = $_GET['ambiente']   ?? 'todos';
$instructor = $_GET['instructor'] ?? 'todos';
$estado     = $_GET['estado']     ?? 'todos';

$start      = mysqli_real_escape_string($conexion, $start);
$end        = mysqli_real_escape_string($conexion, $end);

$where = ["au.fecha_inicio <= '$end'", "au.fecha_fin >= '$start'"];

if ($ambiente !== 'todos') {
    $ambiente = (int)$ambiente;
    $where[] = "au.id_ambiente = $ambiente";
}
if ($instructor !== 'todos') {
    $instructor = (int)$instructor;
    $where[] = "au.id_instructor = $instructor";
}
if ($estado !== 'todos') {
    $estado  = mysqli_real_escape_string($conexion, $estado);
    $where[] = "au.estado = '$estado'";
}

$whereSQL = implode(' AND ', $where);

$sql = "SELECT
            au.id,
            au.fecha_inicio,
            au.fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.estado,
            au.observaciones,
            a.nombre_ambiente,
            i.nombre         AS nombre_instructor,
            f.numero_ficha,
            f.programa
        FROM autorizaciones_ambientes au
        JOIN ambientes    a ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        LEFT JOIN fichas  f ON au.id_ficha      = f.id
        WHERE $whereSQL
        ORDER BY au.fecha_inicio ASC";

$res = mysqli_query($conexion, $sql);
if (!$res) {
    echo json_encode(['error' => mysqli_error($conexion)]);
    exit;
}

$colores = [
    'Aprobado'  => ['bg' => '#2e7d32', 'border' => '#1b5e20'],
    'Pendiente' => ['bg' => '#f9a825', 'border' => '#f57f17'],
    'Rechazado' => ['bg' => '#c62828', 'border' => '#b71c1c'],
];

$eventos = [];
while ($row = mysqli_fetch_assoc($res)) {
    $col    = $colores[$row['estado']] ?? ['bg' => '#607d8b', 'border' => '#455a64'];
    $ficha  = $row['numero_ficha'] ? " [Ficha {$row['numero_ficha']}]" : '';

    $eventos[] = [
        'id'              => $row['id'],
        'title'           => $row['nombre_ambiente'] . ' — ' . $row['nombre_instructor'] . $ficha,
        'start'           => $row['fecha_inicio'] . 'T' . $row['hora_inicio'],
        'end'             => $row['fecha_fin']    . 'T' . $row['hora_final'],
        'backgroundColor' => $col['bg'],
        'borderColor'     => $col['border'],
        'textColor'       => '#ffffff',
        'extendedProps'   => [
            'ambiente'      => $row['nombre_ambiente'],
            'instructor'    => $row['nombre_instructor'],
            'hora_inicio'   => substr($row['hora_inicio'], 0, 5),
            'hora_fin'      => substr($row['hora_final'],  0, 5),
            'estado'        => $row['estado'],
            'obs'           => $row['observaciones'],
            'numero_ficha'  => $row['numero_ficha'],
            'programa'      => $row['programa'],
        ],
    ];
}

echo json_encode($eventos);