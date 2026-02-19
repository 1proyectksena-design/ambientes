<?php
session_start();
include("../includes/conexion.php");

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../index.php");
    exit;
}

$mensaje = "";

/* =========================
   TRAER INSTRUCTORES
   ========================= */
$instructores = $conexion->query("SELECT id, nombre FROM instructores ORDER BY nombre ASC");

/* =========================
   TRAER AMBIENTES
   ========================= */
$ambientes = $conexion->query("SELECT id, nombre_ambiente FROM ambientes ORDER BY nombre_ambiente ASC");

/* =========================
   PROCESAR FORMULARIO
   ========================= */
if (isset($_POST['guardar'])) {

    $id_instructor = $_POST['id_instructor'];
    $id_ambiente   = $_POST['id_ambiente'];
    $rol           = $_POST['rol'];
    $fecha_inicio  = $_POST['fecha_inicio'];
    $fecha_fin     = $_POST['fecha_fin'];
    $hora_inicio   = $_POST['hora_inicio'];
    $hora_final    = $_POST['hora_final'];
    $novedades     = $_POST['novedades'];
    $observaciones = $_POST['observaciones'];

    $stmt = $conexion->prepare("
        INSERT INTO autorizaciones_ambientes 
        (id_instructor, id_ambiente, rol_autorizado, 
         fecha_inicio, fecha_fin, hora_inicio, hora_final,
         novedades, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisssssss",
        $id_instructor,
        $id_ambiente,
        $rol,
        $fecha_inicio,
        $fecha_fin,
        $hora_inicio,
        $hora_final,
        $novedades,
        $observaciones
    );

    if ($stmt->execute()) {
        $mensaje = "Solicitud enviada correctamente ✅ (Estado: Pendiente)";
    } else {
        $mensaje = "Error al enviar solicitud ❌";
    }
}
?>

<h2>Solicitar Autorización de Ambiente</h2>

<?php if ($mensaje) echo "<p>$mensaje</p>"; ?>

<form method="POST">

<label>Instructor:</label>
<select name="id_instructor" required>
    <option value="">Seleccione Instructor</option>
    <?php while($row = $instructores->fetch_assoc()): ?>
        <option value="<?php echo $row['id']; ?>">
            <?php echo $row['nombre']; ?>
        </option>
    <?php endwhile; ?>
</select>

<br><br>

<label>Ambiente:</label>
<select name="id_ambiente" required>
    <option value="">Seleccione Ambiente</option>
    <?php while($row = $ambientes->fetch_assoc()): ?>
        <option value="<?php echo $row['id']; ?>">
            <?php echo $row['nombre_ambiente']; ?>
        </option>
    <?php endwhile; ?>
</select>

<br><br>

<label>Rol autorizado:</label>
<input type="text" name="rol" required>

<br><br>

<label>Fecha Inicio:</label>
<input type="date" name="fecha_inicio" required>

<br><br>

<label>Fecha Fin:</label>
<input type="date" name="fecha_fin" required>

<br><br>

<label>Hora Inicio:</label>
<input type="time" name="hora_inicio" required>

<br><br>

<label>Hora Final:</label>
<input type="time" name="hora_final" required>

<br><br>

<label>Novedades:</label>
<textarea name="novedades"></textarea>

<br><br>

<label>Observaciones:</label>
<textarea name="observaciones"></textarea>

<br><br>

<button type="submit" name="guardar">Enviar Solicitud</button>

</form>

<br>
<a href="index.php">⬅ Volver</a>
