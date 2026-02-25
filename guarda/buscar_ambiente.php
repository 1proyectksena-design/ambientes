<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'guarda') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$nombre_ambiente = $_POST['nombre_ambiente'] ?? null;

if(!$nombre_ambiente) {
    header("Location: index.php");
    exit;
}

$nombre_ambiente = mysqli_real_escape_string($conexion, $nombre_ambiente);

/* BUSCAR AMBIENTE POR NOMBRE O NÚMERO */
$sql = "SELECT id, nombre_ambiente FROM ambientes 
        WHERE nombre_ambiente LIKE '%$nombre_ambiente%' 
        LIMIT 1";

$resultado = mysqli_query($conexion, $sql);

if($resultado && mysqli_num_rows($resultado) > 0) {
    $ambiente = mysqli_fetch_assoc($resultado);
    
    // REDIRIGIR A VERIFICAR CON EL ID ENCONTRADO
    header("Location: verificar.php?id=" . $ambiente['id']);
    exit;
    
} else {
    // NO SE ENCONTRÓ EL AMBIENTE
    $_SESSION['error_busqueda'] = "No se encontró el ambiente '$nombre_ambiente'";
    header("Location: index.php");
    exit;
}
?>