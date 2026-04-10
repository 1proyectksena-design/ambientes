<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

// Solo el admin puede procesar solicitudes
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: solicitudes.php");
    exit;
}

$id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';
$motivo = isset($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : '';

if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
    header("Location: solicitudes.php?msg=error");
    exit;
}

if ($accion === 'aprobar') {
    $stmt = $conexion->prepare("UPDATE autorizaciones_ambientes SET estado = 'Aprobado' WHERE id = ? AND estado = 'Pendiente'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: solicitudes.php?msg=aprobado");
    exit;

} elseif ($accion === 'rechazar') {
    // Si la tabla tiene campo motivo_rechazo, se incluye; si no, solo cambia estado
    // Comprobamos si la columna existe antes de usarla
    $col_check = mysqli_query($conexion, "SHOW COLUMNS FROM autorizaciones_ambientes LIKE 'motivo_rechazo'");
    $tiene_motivo = mysqli_num_rows($col_check) > 0;

    if ($tiene_motivo && $motivo !== '') {
        $stmt = $conexion->prepare("UPDATE autorizaciones_ambientes SET estado = 'Rechazado', motivo_rechazo = ? WHERE id = ? AND estado = 'Pendiente'");
        $stmt->bind_param("si", $motivo, $id);
    } else {
        $stmt = $conexion->prepare("UPDATE autorizaciones_ambientes SET estado = 'Rechazado' WHERE id = ? AND estado = 'Pendiente'");
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: solicitudes.php?msg=rechazado");
    exit;
}

header("Location: solicitudes.php");
exit;