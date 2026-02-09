<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

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

<h2>TABLA DE REGISTROS</h2>

<table border="1">
<tr>
    <th>Ambiente</th>
    <th>Instructor</th>
    <th>Fecha</th>
    <th>Hora inicio</th>
    <th>Hora fin</th>
    <th>Autorizado por</th>
</tr>

<?php while($row = mysqli_fetch_assoc($resultado)){ ?>
<tr>
    <td><?= $row['nombre_ambiente'] ?></td>
    <td><?= $row['nombre_completo'] ?></td>
    <td><?= $row['fecha'] ?></td>
    <td><?= $row['hora_inicio'] ?></td>
    <td><?= $row['hora_fin'] ?></td>
    <td><?= $row['rol_autorizado'] ?></td>
</tr>
<?php } ?>
</table>



<?php
$ambienteBuscado = $_GET['ambiente'] ?? null;
$ambienteInfo = null;

if ($ambienteBuscado) {
    $sql = "SELECT * FROM ambientes WHERE nombre_ambiente = '$ambienteBuscado'";
    $res = mysqli_query($conexion, $sql);
    $ambienteInfo = mysqli_fetch_assoc($res);
}
?>

<h2>CONSULTAR AMBIENTE</h2>

<form method="GET">
    <input type="text" name="ambiente" placeholder="Ej: 308" required>
    <button type="submit">Buscar</button>
</form>


<?php if ($ambienteInfo) { ?>
    <table border="1">
        <tr>
            <th>Ambiente</th>
            <th>Horario fijo</th>
            <th>Disponible</th>
            <th>Acción</th>
        </tr>
        <tr>
            <td><?= $ambienteInfo['nombre_ambiente'] ?></td>
            <td><?= $ambienteInfo['horario_fijo'] ?></td>
            <td><?= $ambienteInfo['horario_disponible'] ?></td>
            <td>
                
                <a href="permisos.php?id_ambiente=<?= $ambienteInfo['id_ambiente'] ?>">
                    Sacar permiso
                </a>
            </td>
        </tr>
    </table>
<?php } ?>
<br>


<br>
<a href="index.php">⬅ Volver</a>
