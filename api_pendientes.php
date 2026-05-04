<?php
header('Content-Type: application/json');

include("includes/conexion.php");

// Validar conexión
if (!$conexion) {
    echo json_encode(["count" => 0]);
    exit;
}

// 🔹 MISMA LÓGICA QUE TU SISTEMA
$sql = "SELECT COUNT(*) AS total 
        FROM autorizaciones_ambientes 
        WHERE estado = 'Pendiente'";

$result = mysqli_query($conexion, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        "count" => (int)$row['total']
    ]);
} else {
    echo json_encode(["count" => 0]);
}

mysqli_close($conexion);
?>