<?php
include("../includes/conexion.php");
session_start();

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   OBTENER ID SI VIENE DE CONSULTAR
   ========================= */
$id_ambiente_seleccionado = $_GET['id_ambiente'] ?? null;

/* =========================
   CONSULTAS BASE
   ========================= */
$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes");
$instructores = mysqli_query($conexion, "SELECT * FROM instructores");

/* =========================
   AUTORIZAR
   ========================= */
if(isset($_POST['autorizar'])){

    $ambiente = $_POST['ambiente'];
    $instructor = $_POST['instructor'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $obs = $_POST['observacion'];

    /* VALIDAR QUE HORA FIN SEA MAYOR */
    if($hora_inicio >= $hora_fin){
        echo "<script>
                alert(' La hora fin debe ser mayor que la hora inicio');
                window.history.back();
              </script>";
        exit;
    }

    /* =========================
       VALIDAR CHOQUE DE HORARIO
       ========================= */
    $sqlChoque = "SELECT * FROM autorizaciones_ambientes
                  WHERE id_ambiente = '$ambiente'
                  AND fecha = '$fecha'
                  AND (
                        hora_inicio < '$hora_fin'
                        AND hora_fin > '$hora_inicio'
                      )";

    $resChoque = mysqli_query($conexion, $sqlChoque);

    if (mysqli_num_rows($resChoque) > 0) {
        echo "<script>
                alert(' El ambiente ya está ocupado en ese horario');
                window.history.back();
              </script>";
        exit;
    }

    /* =========================
       INSERTAR AUTORIZACIÓN
       ========================= */
    $sql = "INSERT INTO autorizaciones_ambientes 
            (id_ambiente, id_instructor, rol_autorizado, fecha, hora_inicio, hora_fin, observacion)
            VALUES 
            ('$ambiente', '$instructor', 'subdireccion', '$fecha', '$hora_inicio', '$hora_fin', '$obs')";

    mysqli_query($conexion, $sql);

    echo "<script>
            alert(' Ambiente autorizado correctamente');
            window.location.href='consultar.php';
          </script>";
}
?>

<h2>Autorizar Ambiente</h2>

<form method="POST">

    <label>Ambiente</label><br>

    <?php if($id_ambiente_seleccionado){ ?>

        <?php
        $ambiente_unico = mysqli_fetch_assoc(
            mysqli_query($conexion, "SELECT * FROM ambientes WHERE id_ambiente='$id_ambiente_seleccionado'")
        );
        ?>

        <input type="hidden" name="ambiente" value="<?= $ambiente_unico['id_ambiente'] ?>">
        <input type="text" value="<?= $ambiente_unico['nombre_ambiente'] ?>" readonly>

    <?php } else { ?>

        <select name="ambiente" required>
            <?php while($a = mysqli_fetch_assoc($ambientes)){ ?>
                <option value="<?= $a['id_ambiente'] ?>">
                    <?= $a['nombre_ambiente'] ?> (<?= $a['estado'] ?>)
                </option>
            <?php } ?>
        </select>

    <?php } ?>

    <br><br>

    <label>Instructor</label><br>
    <select name="instructor" required>
        <?php while($i = mysqli_fetch_assoc($instructores)){ ?>
            <option value="<?= $i['id_instructor'] ?>">
                <?= $i['nombre_completo'] ?>
            </option>
        <?php } ?>
    </select>
    <br><br>

    <label>Fecha</label><br>
    <input type="date" name="fecha" required>
    <br><br>

    <label>Hora inicio</label><br>
    <input type="time" name="hora_inicio" required>
    <br><br>

    <label>Hora fin</label><br>
    <input type="time" name="hora_fin" required>
    <br><br>

    <label>Observación</label><br>
    <textarea name="observacion"></textarea>
    <br><br>

    <button type="submit" name="autorizar">Autorizar</button>
</form>

<a href="index.php">⬅ Volver</a>
