<?php
include("../includes/conexion.php");
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}


$sql = "SELECT 
            a.nombre_ambiente,
            i.nombre_completo,
            au.fecha,
            au.hora_inicio,
            au.hora_fin,
            am.estado,
            au.rol_autorizado
        FROM autorizaciones_ambientes au
        JOIN ambientes am ON au.id_ambiente = am.id_ambiente
        JOIN instructores i ON au.id_instructor = i.id_instructor
        JOIN ambientes a ON au.id_ambiente = a.id_ambiente";

$resultado = mysqli_query($conexion, $sql);
?>

<h2>Consulta de Autorizaciones</h2>

<table border="1">
<tr>
    <th>Ambiente</th>
    <th>Instructor</th>
    <th>Fecha</th>
    <th>Hora inicio</th>
    <th>Hora fin</th>
    <th>Estado</th>
    <th>Autorizado por</th>
</tr>



<?php while($row = mysqli_fetch_assoc($resultado)){ ?>
<tr>
    <td><?= $row['nombre_ambiente'] ?></td>
    <td><?= $row['nombre_completo'] ?></td>
    <td><?= $row['fecha'] ?></td>
    <td><?= $row['hora_inicio'] ?></td>
    <td><?= $row['hora_fin'] ?></td>
    <td><?= $row['estado'] ?></td>
    <td><?= $row['rol_autorizado'] ?></td>
</tr>
<?php } ?>
</table>
<a href="index.php">⬅ Volver</a>
