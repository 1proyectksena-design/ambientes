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
$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes ORDER BY nombre_ambiente");
$instructores = mysqli_query($conexion, "SELECT * FROM instructores ORDER BY nombre_completo");

/* =========================
   AUTORIZAR
   ========================= */
if(isset($_POST['autorizar'])){

    $ambiente = mysqli_real_escape_string($conexion, $_POST['ambiente']);
    $instructor = mysqli_real_escape_string($conexion, $_POST['instructor']);
    $fecha = mysqli_real_escape_string($conexion, $_POST['fecha']);
    $hora_inicio = mysqli_real_escape_string($conexion, $_POST['hora_inicio']);
    $hora_fin = mysqli_real_escape_string($conexion, $_POST['hora_fin']);
    $obs = mysqli_real_escape_string($conexion, $_POST['observacion']);

    /* VALIDAR QUE HORA FIN SEA MAYOR */
    if($hora_inicio >= $hora_fin){
        echo "<script>
                alert('⚠️ La hora fin debe ser mayor que la hora inicio');
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
                alert('⚠️ El ambiente ya está ocupado en ese horario');
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
            alert('✅ Ambiente autorizado correctamente');
            window.location.href='consultar.php';
          </script>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizar Ambiente</title>
    <link rel="stylesheet" href="../css/permisos.css?v=<?php echo time(); ?>"></head>
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo Institución">
        <div class="header-title">
            <h1>Autorizar Ambiente</h1>
            <span>Gestionar permisos de uso</span>
        </div>
    </div>
    <div class="header-user">
        Subdirección
    </div>
</div>

<div class="permisos-container">

    <div class="form-card">
        <div class="form-header">
            <h2>📝 Nueva Autorización</h2>
            <p>Complete el formulario para autorizar el uso de un ambiente</p>
        </div>

        <?php if($id_ambiente_seleccionado){ ?>
            <div class="info-alert">
                <strong>✓ Ambiente Pre-seleccionado</strong>
                El ambiente ha sido seleccionado automáticamente desde la búsqueda
            </div>
        <?php } ?>

        <form method="POST">

            <!-- AMBIENTE -->
            <div class="form-group">
                <label>Ambiente</label>

                <?php if($id_ambiente_seleccionado){ ?>
                    <?php
                    $ambiente_unico = mysqli_fetch_assoc(
                        mysqli_query($conexion, "SELECT * FROM ambientes WHERE id_ambiente='$id_ambiente_seleccionado'")
                    );
                    ?>
                    <input type="hidden" name="ambiente" value="<?= $ambiente_unico['id_ambiente'] ?>">
                    <input type="text" class="ambiente-readonly" value="<?= htmlspecialchars($ambiente_unico['nombre_ambiente']) ?>" readonly>
                <?php } else { ?>
                    <select name="ambiente" required>
                        <option value="">-- Seleccione un ambiente --</option>
                        <?php while($a = mysqli_fetch_assoc($ambientes)){ ?>
                            <option value="<?= $a['id_ambiente'] ?>">
                                <?= htmlspecialchars($a['nombre_ambiente']) ?> (<?= htmlspecialchars($a['estado']) ?>)
                            </option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>

            <!-- INSTRUCTOR -->
            <div class="form-group">
                <label>Instructor</label>
                <select name="instructor" required>
                    <option value="">-- Seleccione un instructor --</option>
                    <?php while($i = mysqli_fetch_assoc($instructores)){ ?>
                        <option value="<?= $i['id_instructor'] ?>">
                            <?= htmlspecialchars($i['nombre_completo']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- FECHA -->
            <div class="form-group">
                <label>Fecha</label>
                <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- HORARIOS -->
            <div class="time-grid">
                <div class="form-group">
                    <label>Hora Inicio</label>
                    <input type="time" name="hora_inicio" required>
                </div>

                <div class="form-group">
                    <label>Hora Fin</label>
                    <input type="time" name="hora_fin" required>
                </div>
            </div>

            <!-- OBSERVACIÓN -->
            <div class="form-group">
                <label>Observación</label>
                <textarea name="observacion" placeholder="Ingrese cualquier observación o comentario adicional..."></textarea>
            </div>

            <!-- BOTÓN SUBMIT -->
            <button type="submit" name="autorizar" class="btn-submit">
                ✓ Autorizar Ambiente
            </button>
        </form>
    </div>

    <!-- BOTÓN VOLVER -->
    <a href="index.php" class="btn-volver">
        ← Volver al Panel
    </a>

</div>

</body>
</html>