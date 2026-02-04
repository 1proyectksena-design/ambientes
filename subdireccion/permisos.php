<?php
include("../includes/conexion.php");

$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes");
$instructores = mysqli_query($conexion, "SELECT * FROM instructores");

if(isset($_POST['autorizar'])){
    $ambiente = $_POST['ambiente'];
    $instructor = $_POST['instructor'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $obs = $_POST['observacion'];

    // Insertar autorización
    $sql = "INSERT INTO autorizaciones_ambientes 
            (id_ambiente, id_instructor, rol_autorizado, fecha, hora_inicio, hora_fin, observacion)
            VALUES 
            ('$ambiente', '$instructor', 'subdireccion', '$fecha', '$hora_inicio', '$hora_fin', '$obs')";

    mysqli_query($conexion, $sql);

    // Cambiar estado del ambiente
    mysqli_query($conexion, 
        "UPDATE ambientes SET estado='ocupado' WHERE id_ambiente='$ambiente'"
    );
}
?>

<h2>Autorizar Ambiente</h2>

<form method="POST">
    <label>Ambiente</label><br>
    <select name="ambiente" required>
        <?php while($a = mysqli_fetch_assoc($ambientes)){ ?>
            <option value="<?= $a['id_ambiente'] ?>">
                <?= $a['nombre_ambiente'] ?> (<?= $a['estado'] ?>)
            </option>
        <?php } ?>
    </select><br><br>

    <label>Instructor</label><br>
    <select name="instructor" required>
        <?php while($i = mysqli_fetch_assoc($instructores)){ ?>
            <option value="<?= $i['id_instructor'] ?>">
                <?= $i['nombre_completo'] ?>
            </option>
        <?php } ?>
    </select><br><br>

    <label>Fecha</label><br>
    <input type="date" name="fecha" required><br><br>

    <label>Hora inicio</label><br>
    <input type="time" name="hora_inicio" required><br><br>

    <label>Hora fin</label><br>
    <input type="time" name="hora_fin" required><br><br>

    <label>Observación</label><br>
    <textarea name="observacion"></textarea><br><br>

    <button type="submit" name="autorizar">Autorizar</button>
</form>
