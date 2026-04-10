<?php
session_start();
include("../includes/conexion.php");

header('Content-Type: application/json');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin') {
    echo json_encode(['count' => 0]);
    exit;
}

$res = mysqli_query($conexion, "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE estado = 'Pendiente'");
$count = mysqli_fetch_row($res)[0];

echo json_encode(['count' => (int)$count]);