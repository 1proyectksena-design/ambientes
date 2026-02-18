<?php
include("../includes/conexion.php");

date_default_timezone_set("America/Bogota");

if(!isset($_GET['id'])){
    die("Ambiente no especificado");
}

$id_ambiente = intval($_GET['id']);
$fecha_actual = date("Y-m-d");

$sql = "SELECT a.*, i.nombre_completo AS nombre_instructor
        FROM autorizaciones_ambientes a
        INNER JOIN instructores i 
            ON a.id_instructor = i.id_instructor
        WHERE a.id_ambiente = '$id_ambiente'
        AND a.fecha = '$fecha_actual'
        ORDER BY a.hora_inicio ASC";

$resultado = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Autorizaciones del día</title>
    <style>
        body { font-family: Arial; text-align: center; }
        table { margin: auto; border-collapse: collapse; width: 85%; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        th { background: #355d85; color: white; }
        .subdireccion { color: green; font-weight: bold; }
        .pendiente { color: orange; font-weight: bold; }
        .rechazado { color: red; font-weight: bold; }
    </style>
</head>
<body>

<h2>Ambiente <?php echo $id_ambiente; ?></h2>
<h3>Autorizaciones del día: <?php echo $fecha_actual; ?></h3>

<?php
if(mysqli_num_rows($resultado) > 0){

    echo "<table>";
    echo "<tr>
            <th>Instructor</th>
            <th>Hora Inicio</th>
            <th>Hora Fin</th>
            <th>Rol Autorizado</th>
            <th>Observación</th>
          </tr>";

    while($fila = mysqli_fetch_assoc($resultado)){

        echo "<tr>";
        echo "<td>".$fila['nombre_instructor']."</td>";
        echo "<td>".$fila['hora_inicio']."</td>";
        echo "<td>".$fila['hora_fin']."</td>";
        echo "<td class='".$fila['rol_autorizado']."'>".$fila['rol_autorizado']."</td>";
        echo "<td>".$fila['observacion']."</td>";
        echo "</tr>";
    }

    echo "</table>";

}else{
    echo "<p><strong>No hay autorizaciones para hoy.</strong></p>";
}
?>

</body>
</html>