<?php
/**
 * API para verificar y generar QR faltantes
 * Llamar desde el dashboard con: fetch('qrs/api_verificar_qr.php')
 */

header('Content-Type: application/json');

include("../includes/conexion.php");
include("../includes/generar_qr.php");

$resultado = verificarQRFaltantes($conexion);

echo json_encode([
    'status' => 'success',
    'generados' => $resultado['generados'],
    'existentes' => $resultado['existentes'],
    'total' => $resultado['total'],
    'mensaje' => $resultado['generados'] > 0 
        ? " {$resultado['generados']} QR generados automáticamente" 
        : "✓ Todos los QR están actualizados"
]);
?>