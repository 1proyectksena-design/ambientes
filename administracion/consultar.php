<?php
include("../includes/conexion.php");

$sql = "SELECT 
            am.nombre_ambiente,
            i.nombre_completo,
            au.fecha,
            au.hora_inicio,
            au.hora_fin,
            au.rol_autorizado,
            am.estado
        FROM autorizaciones_ambientes au
        JOIN ambientes am ON au.id_ambiente = am.id_ambiente
        JOIN instructores i ON au.id_instructor = i.id_instructor";

$resultado = mysqli_query($conexion, $sql);
?>

<h2>Historial de Autorizaciones</h2>

<table border="1">
<tr>
    <th>Ambiente</th>
    <th>Instructor</th>
    <th>Fecha</th>
    <th>Hora inicio</th>
    <th>Hora fin</th>
    <th>Autorizado por</th>
    <th>Estado</th>
</tr>

<?php while($row = mysqli_fetch_assoc($resultado)){ ?>
<tr>
    <td><?= $row['nombre_ambiente'] ?></td>
    <td><?= $row['nombre_completo'] ?></td>
    <td><?= $row['fecha'] ?></td>
    <td><?= $row['hora_inicio'] ?></td>
    <td><?= $row['hora_fin'] ?></td>
    <td><?= $row['rol_autorizado'] ?></td>
    <td><?= $row['estado'] ?></td>
</tr>
<?php } ?>
</table>
