<?php
session_start();
include("../includes/conexion.php");

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../index.php");
    exit;
}

$resultado = null;

if (isset($_POST['buscar'])) {

    $id_ambiente = $_POST['id_ambiente'];

    $stmt = $conexion->prepare("
        SELECT 
            aa.*,
            i.nombre AS nombre_instructor,
            am.nombre_ambiente
        FROM autorizaciones_ambientes aa
        INNER JOIN instructores i 
            ON aa.id_instructor = i.id
        INNER JOIN ambientes am
            ON aa.id_ambiente = am.id
        WHERE aa.id_ambiente = ?
        ORDER BY aa.fecha_inicio DESC
    ");

    $stmt->bind_param("i", $id_ambiente);
    $stmt->execute();
    $resultado = $stmt->get_result();
}
?>

<h2>Historial de Ambiente</h2>

<form method="POST">
    <input type="number" name="id_ambiente" placeholder="Ingrese ID Ambiente" required>
    <button type="submit" name="buscar">Buscar</button>
</form>

<hr>

<?php
if ($resultado && $resultado->num_rows > 0) {

    while ($row = $resultado->fetch_assoc()) {

        echo "<strong>Instructor:</strong> " . $row['nombre_instructor'] . "<br>";
        echo "<strong>Ambiente:</strong> " . $row['nombre_ambiente'] . "<br>";
        echo "<strong>Rol:</strong> " . $row['rol_autorizado'] . "<br>";
        echo "<strong>Fecha Inicio:</strong> " . $row['fecha_inicio'] . "<br>";
        echo "<strong>Fecha Fin:</strong> " . $row['fecha_fin'] . "<br>";
        echo "<strong>Hora Inicio:</strong> " . $row['hora_inicio'] . "<br>";
        echo "<strong>Hora Final:</strong> " . $row['hora_final'] . "<br>";
        echo "<strong>Estado:</strong> " . $row['estado'] . "<br>";
        echo "<strong>Observaciones:</strong> " . $row['observaciones'] . "<br>";
        echo "<hr>";
    }

} elseif ($resultado) {
    echo "No hay historial para ese ambiente.";
}
?>

<br>
<a href="index.php">⬅ Volver</a>
