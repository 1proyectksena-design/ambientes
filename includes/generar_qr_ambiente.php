<?php
/**
 * HELPER PARA GENERAR QR AUTOMÁTICAMENTE
 * Incluir este archivo donde se necesite generar códigos QR
 */

/**
 * Genera un código QR para un ambiente específico
 * 
 * @param int $id_ambiente ID del ambiente
 * @param bool $forzar Si true, regenera aunque ya exista
 * @return bool True si se generó correctamente
 */
function generarQR($id_ambiente, $forzar = false) {
    // Incluir librería QR
    $qrlib_path = __DIR__ . "/../qrs/phpqrcode/qrlib.php";
    if(!file_exists($qrlib_path)){
        error_log("ERROR: No se encuentra qrlib.php en: $qrlib_path");
        return false;
    }
    
    require_once($qrlib_path);
    
    // Crear directorio si no existe
    $dir = __DIR__ . "/../qrs";
    if(!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $ruta = $dir . "/ambiente_" . $id_ambiente . ".png";
    
    // Si ya existe y no se fuerza regenerar, salir
    if(file_exists($ruta) && !$forzar){
        return true;
    }
    
    // URL para verificación del guarda
    // CAMBIAR localhost por tu dominio en producción
    $contenido = "http://localhost/ambientes/guarda/verificar.php?id=" . $id_ambiente;
    
    try {
        QRcode::png($contenido, $ruta, QR_ECLEVEL_L, 6);
        return true;
    } catch(Exception $e) {
        error_log("Error generando QR para ambiente $id_ambiente: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera QR para todos los ambientes que no lo tengan
 * 
 * @param mysqli $conexion Conexión a la base de datos
 * @return int Número de QR generados
 */
function generarTodosQR($conexion) {
    $sql = "SELECT id FROM ambientes ORDER BY id";
    $resultado = mysqli_query($conexion, $sql);
    
    if(!$resultado){
        error_log("Error en consulta generarTodosQR: " . mysqli_error($conexion));
        return 0;
    }
    
    $total = 0;
    
    while($row = mysqli_fetch_assoc($resultado)){
        if(generarQR($row['id'], false)){
            $total++;
        }
    }
    
    return $total;
}

/**
 * Verifica y genera QR faltantes (para llamar desde dashboard)
 * 
 * @param mysqli $conexion Conexión a la base de datos
 * @return array ['generados' => int, 'existentes' => int]
 */
function verificarQRFaltantes($conexion) {
    $sql = "SELECT id FROM ambientes ORDER BY id";
    $resultado = mysqli_query($conexion, $sql);
    
    $generados = 0;
    $existentes = 0;
    
    $dir = __DIR__ . "/../qrs";
    
    while($row = mysqli_fetch_assoc($resultado)){
        $id = $row['id'];
        $ruta = $dir . "/ambiente_" . $id . ".png";
        
        if(!file_exists($ruta)){
            if(generarQR($id)){
                $generados++;
            }
        } else {
            $existentes++;
        }
    }
    
    return [
        'generados' => $generados,
        'existentes' => $existentes,
        'total' => $generados + $existentes
    ];
}
?>