<?php
include("../includes/conexion.php");
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   HISTORIAL DE AUTORIZACIONES
   ========================= */
$sql = "SELECT 
            am.nombre_ambiente,
            i.nombre_completo,
            au.fecha,
            au.hora_inicio,
            au.hora_fin,
            am.estado,
            au.rol_autorizado
        FROM autorizaciones_ambientes au
        JOIN ambientes am ON au.id_ambiente = am.id_ambiente
        JOIN instructores i ON au.id_instructor = i.id_instructor";

$resultado = mysqli_query($conexion, $sql);

/* =========================
   BUSCAR AMBIENTE
   ========================= */
$ambienteBuscado = $_GET['ambiente'] ?? null;
$ambienteInfo = null;

if ($ambienteBuscado) {
    $sqlAmb = "SELECT * FROM ambientes WHERE nombre_ambiente = '$ambienteBuscado'";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambienteInfo = mysqli_fetch_assoc($resAmb);
}
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

<br><br>

<h2>Consultar Ambiente</h2>

<form method="GET">
    <input type="text" name="ambiente" placeholder="Ej: 308" required>
    <button type="submit">Buscar</button>
</form>

<?php if ($ambienteInfo) { ?>
    <br>
    <table border="1">
        <tr>
            <th>Ambiente</th>
            <th>Horario fijo</th>
            <th>Horario disponible</th>
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
<a href="index.php">⬅ Volver</a>
