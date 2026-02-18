<?php
include("includes/conexion.php");
include("qrs/phpqrcode/qrlib.php");

$id_ambiente = intval($_GET['id']);

if(!$id_ambiente){
    die("ID inválido");
}

$contenido = "http://localhost/am bientes/guarda/verificar.php?id=".$id_ambiente;

$ruta = "qrs/ambiente_".$id_ambiente.".png";

QRcode::png($contenido, $ruta, QR_ECLEVEL_L, 6);

echo "<h2>QR generado correctamente</h2>";
echo "<img src='".$ruta."'>";
?>