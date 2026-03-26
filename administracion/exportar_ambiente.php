<?php
session_start();
date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != 'administracion' && $_SESSION['rol'] != 'subdireccion')) {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$fecha_actual = date('Y-m-d');
$hora_actual  = date('H:i:s');

$id_ambiente = $_GET['id'] ?? null;

if (!$id_ambiente) {
    die('Error: ID de ambiente no especificado');
}

$id_ambiente = mysqli_real_escape_string($conexion, $id_ambiente);

/* ── INFO AMBIENTE ── */
$sqlAmb = "SELECT a.*, 
                  i.nombre AS nombre_instructor_fijo
           FROM ambientes a
           LEFT JOIN instructores i ON a.instructor_id = i.id
           WHERE a.id = '$id_ambiente'";
$resAmb = mysqli_query($conexion, $sqlAmb);
$ambienteInfo = mysqli_fetch_assoc($resAmb);

if (!$ambienteInfo) {
    die('Ambiente no encontrado');
}

/* ── PRÓXIMOS USOS ── */
$sqlProximos = "SELECT 
                    MIN(au.fecha_inicio) AS fecha_inicio,
                    MAX(au.fecha_inicio) AS fecha_fin,
                    au.hora_inicio,
                    au.hora_final,
                    i.nombre AS nombre_instructor,
                    au.observaciones
                FROM autorizaciones_ambientes au
                JOIN instructores i ON au.id_instructor = i.id
                WHERE au.id_ambiente = '$id_ambiente'
                AND (
                    (au.fecha_inicio > '$fecha_actual')
                    OR (au.fecha_inicio = '$fecha_actual' AND au.hora_inicio > '$hora_actual')
                )
                AND au.estado = 'Aprobado'
                GROUP BY au.id_instructor, au.hora_inicio, au.hora_final, au.observaciones
                ORDER BY MIN(au.fecha_inicio) ASC";
$proximosUsos = mysqli_query($conexion, $sqlProximos);

/* ── HISTORIAL ── */
$sqlHistorial = "SELECT au.*, i.nombre AS nombre_instructor
                 FROM autorizaciones_ambientes au
                 JOIN instructores i ON au.id_instructor = i.id
                 WHERE au.id_ambiente = '$id_ambiente'
                 ORDER BY au.fecha_inicio DESC
                 LIMIT 50";
$historial = mysqli_query($conexion, $sqlHistorial);

/* ── HEADERS EXCEL ── */
$filename = "Ambiente_".$ambienteInfo['nombre_ambiente']."_".date('Y-m-d').".xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // UTF-8
?>

<html>
<body>

<!-- TÍTULO -->
<table>
<tr>
<td colspan="8" style="background:#0b2449;color:white;font-size:16pt;font-weight:bold;text-align:center;">
REPORTE DE AMBIENTE - SENA
</td>
</tr>

<tr>
<td colspan="8" style="background:#355d91;color:white;text-align:center;font-size:14pt;">
<?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?>
</td>
</tr>
</table>

<br>

<!-- INFORMACIÓN -->
<table border="1">
<tr style="background:#e8eef7;font-weight:bold;">
<td colspan="8">INFORMACIÓN GENERAL</td>
</tr>

<tr>
<td><b>Estado</b></td>
<td><?= $ambienteInfo['estado'] ?></td>
<td><b>Horario Disponible</b></td>
<td><?= $ambienteInfo['horario_disponible'] ?: 'No definido' ?></td>
</tr>

<tr>
<td><b>Horario Fijo</b></td>
<td><?= $ambienteInfo['horario_fijo'] ?: 'No definido' ?></td>
<td><b>Instructor</b></td>
<td><?= $ambienteInfo['nombre_instructor_fijo'] ?: 'No asignado' ?></td>
</tr>
</table>

<br>

<!-- PRÓXIMOS USOS -->
<?php if(mysqli_num_rows($proximosUsos) > 0): ?>
<table border="1">
<tr style="background:#43a047;color:white;font-weight:bold;">
<td colspan="7">PRÓXIMOS USOS</td>
</tr>

<tr style="background:#66bb6a;color:white;">
<td>Inicio</td>
<td>Fin</td>
<td>Instructor</td>
<td>Hora Inicio</td>
<td>Hora Fin</td>
<td>Observaciones</td>
</tr>

<?php while($p = mysqli_fetch_assoc($proximosUsos)): ?>
<tr>
<td><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></td>
<td><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></td>
<td><?= $p['nombre_instructor'] ?></td>
<td><?= date('h:i A', strtotime($p['hora_inicio'])) ?></td>
<td><?= date('h:i A', strtotime($p['hora_final'])) ?></td>
<td><?= $p['observaciones'] ?: '-' ?></td>
</tr>
<?php endwhile; ?>
</table>
<br>
<?php endif; ?>

<!-- HISTORIAL -->
<table border="1">
<tr style="background:#fb8c00;color:white;font-weight:bold;">
<td colspan="8">HISTORIAL</td>
</tr>

<tr style="background:#ffcc80;">
<td>Inicio</td>
<td>Fin</td>
<td>Instructor</td>
<td>Hora Inicio</td>
<td>Hora Fin</td>
<td>Estado</td>
<td>Autorizado</td>
</tr>

<?php while($h = mysqli_fetch_assoc($historial)): ?>
<tr>
<td><?= date('d/m/Y', strtotime($h['fecha_inicio'])) ?></td>
<td><?= date('d/m/Y', strtotime($h['fecha_fin'])) ?></td>
<td><?= $h['nombre_instructor'] ?></td>
<td><?= date('h:i A', strtotime($h['hora_inicio'])) ?></td>
<td><?= date('h:i A', strtotime($h['hora_final'])) ?></td>
<td><?= $h['estado'] ?></td>
<td><?= $h['rol_autorizado'] ?></td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>