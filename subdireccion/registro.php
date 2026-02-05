<?php
include("../includes/conexion.php");
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

if(isset($_POST['guardar'])){
    $nombre = $_POST['nombre_completo'];
    $tipo = $_POST['tipo'];
    $correo = $_POST['correo'];

    $sql = "INSERT INTO instructores (nombre_completo, tipo, correo)
            VALUES ('$nombre', '$tipo', '$correo')";

    mysqli_query($conexion, $sql);
}
?>

<h2>Registrar Instructor</h2>

<form method="POST">
    <label>Nombre completo</label><br>
    <input type="text" name="nombre_completo" required><br><br>

    <label>Tipo</label><br>
    <select name="tipo" required>
        <option value="planta">Planta</option>
        <option value="contratista">Contratista</option>
    </select><br><br>

    <label>Correo</label><br>
    <input type="email" name="correo"><br><br>

    <button type="submit" name="guardar">Registrar</button>
</form>
<a href="index.php">⬅ Volver</a>
