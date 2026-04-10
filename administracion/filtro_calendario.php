<?php
session_start();
error_log("ROL EN SESION: " . ($_SESSION['rol'] ?? 'VACIO')); // <- aquí

if ($_SESSION['rol'] != 'administracion') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

include("../includes/conexion.php");
header('Content-Type: application/json; charset=UTF-8');

$type = $_GET['type'] ?? '';

if ($type === 'ambientes') {
    $res  = mysqli_query($conexion, "SELECT id, nombre_ambiente FROM ambientes ORDER BY nombre_ambiente ASC");
    $data = [];
    while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    echo json_encode($data);

} elseif ($type === 'instructores') {
    $res  = mysqli_query($conexion, "SELECT id, nombre FROM instructores ORDER BY nombre ASC");
    $data = [];
    while ($r = mysqli_fetch_assoc($res)) $data[] = $r;
    echo json_encode($data);

} else {
    echo json_encode([]);
}