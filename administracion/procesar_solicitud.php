<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: solicitudes.php");
    exit;
}

// ── Recibir IDs (puede venir como "ids" con múltiples separados por coma,
//    o como "id" singular para compatibilidad con formularios antiguos)
$ids_raw = '';
if (!empty($_POST['ids'])) {
    $ids_raw = $_POST['ids'];
} elseif (!empty($_POST['id'])) {
    $ids_raw = $_POST['id'];
}

// Convertir a array de enteros válidos
$ids = array_values(array_filter(array_map('intval', explode(',', $ids_raw))));

$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';
$motivo = isset($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : '';

if (empty($ids) || !in_array($accion, ['aprobar', 'rechazar'])) {
    header("Location: solicitudes.php?msg=error");
    exit;
}

// ── Determinar quién está procesando la solicitud
//    Usa el nombre si está en sesión, si no usa el rol formateado
$quien = '';
if (!empty($_SESSION['nombre'])) {
    $quien = $_SESSION['nombre'];
} else {
    $mapa_roles = [
        'administracion' => 'Administración',
        'subdireccion'   => 'Subdirección',
        'coordinacion'   => 'Coordinación',
    ];
    $quien = $mapa_roles[$_SESSION['rol']] ?? ucfirst($_SESSION['rol']);
}

// ── Verificar si existe la columna motivo_rechazo (una sola vez)
$col_check    = mysqli_query($conexion, "SHOW COLUMNS FROM autorizaciones_ambientes LIKE 'motivo_rechazo'");
$tiene_motivo = mysqli_num_rows($col_check) > 0;

$procesados = 0;

if ($accion === 'aprobar') {

    $stmt = $conexion->prepare(
        "UPDATE autorizaciones_ambientes
         SET estado = 'Aprobado', rol_autorizado = ?
         WHERE id = ? AND estado = 'Pendiente'"
    );
    foreach ($ids as $id) {
        $stmt->bind_param("si", $quien, $id);
        $stmt->execute();
        $procesados += $stmt->affected_rows;
    }
    $stmt->close();
    header("Location: solicitudes.php?msg=aprobado");
    exit;

} elseif ($accion === 'rechazar') {

    if ($tiene_motivo && $motivo !== '') {
        $stmt = $conexion->prepare(
            "UPDATE autorizaciones_ambientes
             SET estado = 'Rechazado', rol_autorizado = ?, motivo_rechazo = ?
             WHERE id = ? AND estado = 'Pendiente'"
        );
        foreach ($ids as $id) {
            $stmt->bind_param("ssi", $quien, $motivo, $id);
            $stmt->execute();
            $procesados += $stmt->affected_rows;
        }
    } else {
        $stmt = $conexion->prepare(
            "UPDATE autorizaciones_ambientes
             SET estado = 'Rechazado', rol_autorizado = ?
             WHERE id = ? AND estado = 'Pendiente'"
        );
        foreach ($ids as $id) {
            $stmt->bind_param("si", $quien, $id);
            $stmt->execute();
            $procesados += $stmt->affected_rows;
        }
    }
    $stmt->close();
    header("Location: solicitudes.php?msg=rechazado");
    exit;
}

header("Location: solicitudes.php");
exit;