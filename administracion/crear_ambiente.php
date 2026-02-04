<?php
include("../includes/conexion.php");

if(isset($_POST['guardar'])){
    $nombre = $_POST['nombre'];

    $sql = "INSERT INTO ambientes (nombre_ambiente, estado)
            VALUES ('$nombre', 'disponible')";
    mysqli_query($conexion, $sql);
}
?>

<h2>Crear Ambiente</h2>

<form method="POST">
    <label>Nombre del ambiente</label><br>
    <input type="text" name="nombre" required><br><br>

    <button type="submit" name="guardar">Crear</button>
</form>
