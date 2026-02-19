<?php
session_start();
include("../includes/conexion.php");

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../index.php");
    exit;
}

$resultado = null;

if (isset($_POST['buscar'])) {

    $id = $_POST['id'];

    $stmt = $conexion->prepare("
        SELECT * 
        FROM instructores 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
}
?>

<h2>Buscar Instructor por ID</h2>

<form method="POST">
    <input type="number" name="id" placeholder="Ingrese ID Instructor" required>
    <button type="submit" name="buscar">Buscar</button>
</form>

<hr>

<?php
if ($resultado && $resultado->num_rows > 0) {

    while ($row = $resultado->fetch_assoc()) {
        echo "<strong>Nombre:</strong> " . $row['nombre'] . "<br>";
        echo "<strong>Identificación:</strong> " . $row['identificacion'] . "<br>";
        echo "<strong>Fecha Inicio:</strong> " . $row['fecha_inicio'] . "<br>";
        echo "<strong>Fecha Fin:</strong> " . $row['fecha_fin'] . "<br>";
        echo "<strong>Novedades:</strong> " . $row['novedades'] . "<br>";
        echo "<hr>";
    }

} elseif ($resultado) {
    echo "No se encontró instructor.";
}
?>

<br>
<a href="index.php">⬅ Volver</a>
