<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* =========================
   OBTENER ID SI VIENE DE CONSULTAR
   ========================= */
$id_ambiente_seleccionado = $_GET['id_ambiente'] ?? null;

/* =========================
   CONSULTAS BASE CON FILTROS
   ========================= */
// Solo ambientes HABILITADOS
$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes WHERE estado = 'Habilitado' ORDER BY nombre_ambiente");

// Solo instructores ACTIVOS (sin fecha_fin o con fecha_fin >= hoy)
$hoy = date('Y-m-d');
$instructores = mysqli_query($conexion, "
    SELECT * FROM instructores 
    WHERE (fecha_fin IS NULL OR fecha_fin >= '$hoy')
    AND fecha_inicio <= '$hoy'
    ORDER BY nombre
");

/* =========================
   AUTORIZAR
   ========================= */
if(isset($_POST['autorizar'])){
    $ambiente = mysqli_real_escape_string($conexion, $_POST['ambiente']);
    $instructor = mysqli_real_escape_string($conexion, $_POST['instructor']);
    $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
    $fecha_fin = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
    $hora_inicio = mysqli_real_escape_string($conexion, $_POST['hora_inicio']);
    $hora_fin = mysqli_real_escape_string($conexion, $_POST['hora_fin']);
    $obs = mysqli_real_escape_string($conexion, $_POST['observaciones']);
    $novedades = mysqli_real_escape_string($conexion, $_POST['novedades']);

    /* VALIDAR QUE EL AMBIENTE ESTÉ HABILITADO */
    $checkAmbiente = mysqli_query($conexion, "SELECT estado FROM ambientes WHERE id='$ambiente'");
    $ambienteData = mysqli_fetch_assoc($checkAmbiente);
    
    if(!$ambienteData || $ambienteData['estado'] != 'Habilitado'){
        echo "<script>
                alert('⚠️ No se puede autorizar: El ambiente no está habilitado');
                window.history.back();
              </script>";
        exit;
    }

    /* VALIDAR QUE EL INSTRUCTOR ESTÉ ACTIVO */
    $checkInstructor = mysqli_query($conexion, "
        SELECT * FROM instructores 
        WHERE id='$instructor' 
        AND (fecha_fin IS NULL OR fecha_fin >= '$fecha_inicio')
        AND fecha_inicio <= '$fecha_fin'
    ");
    
    if(mysqli_num_rows($checkInstructor) == 0){
        echo "<script>
                alert('⚠️ No se puede autorizar: El instructor no está activo en el período seleccionado');
                window.history.back();
              </script>";
        exit;
    }

    /* VALIDAR FECHAS */
    if($fecha_inicio > $fecha_fin){
        echo "<script>
                alert('⚠️ La fecha fin debe ser igual o mayor que la fecha inicio');
                window.history.back();
              </script>";
        exit;
    }

    /* VALIDAR QUE HORA FIN SEA MAYOR */
    if($hora_inicio >= $hora_fin){
        echo "<script>
                alert('⚠️ La hora fin debe ser mayor que la hora inicio');
                window.history.back();
              </script>";
        exit;
    }

    /* VALIDAR CHOQUE DE HORARIO */
    $sqlChoque = "SELECT * FROM autorizaciones_ambientes
                  WHERE id_ambiente = '$ambiente'
                  AND estado = 'Aprobado'
                  AND (
                        (fecha_inicio <= '$fecha_fin' AND fecha_fin >= '$fecha_inicio')
                        AND (hora_inicio < '$hora_fin' AND hora_final > '$hora_inicio')
                      )";
    
    $resChoque = mysqli_query($conexion, $sqlChoque);

    if (mysqli_num_rows($resChoque) > 0) {
        echo "<script>
                alert('⚠️ El ambiente ya tiene una autorización aprobada en ese período y horario');
                window.history.back();
              </script>";
        exit;
    }

    /* INSERTAR AUTORIZACIÓN */
    $sqlInsert = "INSERT INTO autorizaciones_ambientes
        (id_ambiente, id_instructor, rol_autorizado, fecha_inicio, fecha_fin, 
         hora_inicio, hora_final, estado, observaciones, novedades)
        VALUES
        ('$ambiente', '$instructor', 'administracion', '$fecha_inicio', '$fecha_fin',
         '$hora_inicio', '$hora_fin', 'Aprobado', '$obs', '$novedades')";
    
    if(mysqli_query($conexion, $sqlInsert)){
        echo "<script>
                alert('✅ Ambiente autorizado correctamente');
                window.location.href='index.php';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('❌ Error al crear la autorización: ".mysqli_error($conexion)."');
                window.history.back();
              </script>";
        exit;
    }
}

/* Verificar si hay ambientes disponibles */
$totalAmbientes = mysqli_num_rows($ambientes);
$totalInstructores = mysqli_num_rows($instructores);

// Reiniciar punteros
mysqli_data_seek($ambientes, 0);
mysqli_data_seek($instructores, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizar Ambiente - Administración</title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Autorizar Ambiente</h1>
            <span>Panel de Administración</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="permisos-container">

    <?php if($totalAmbientes == 0): ?>
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>No hay ambientes disponibles</strong>
            <p>Todos los ambientes están deshabilitados o en mantenimiento. No es posible crear autorizaciones en este momento.</p>
            <a href="index.php" class="btn-volver" style="margin-top:15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    <?php elseif($totalInstructores == 0): ?>
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>No hay instructores activos</strong>
            <p>No hay instructores disponibles para crear autorizaciones en este momento.</p>
            <a href="index.php" class="btn-volver" style="margin-top:15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    <?php else: ?>

    <div class="form-card">
        <div class="form-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Nueva Autorización</h2>
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
                <label><i class="fa-solid fa-building"></i> Ambiente * <small>(Solo habilitados)</small></label>

                <?php if($id_ambiente_seleccionado){ ?>
                    <?php
                    $ambiente_unico = mysqli_fetch_assoc(
                        mysqli_query($conexion, "SELECT * FROM ambientes WHERE id='$id_ambiente_seleccionado' AND estado='Habilitado'")
                    );
                    if($ambiente_unico):
                    ?>
                    <input type="hidden" name="ambiente" value="<?= $ambiente_unico['id'] ?>">
                    <input type="text" class="ambiente-readonly" value="<?= htmlspecialchars($ambiente_unico['nombre_ambiente']) ?>" readonly>
                    <?php else: ?>
                    <div class="alert-warning">Este ambiente no está habilitado</div>
                    <?php endif; ?>
                <?php } else { ?>
                    <select name="ambiente" required>
                        <option value="">-- Seleccione un ambiente --</option>
                        <?php while($a = mysqli_fetch_assoc($ambientes)){ ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['nombre_ambiente']) ?>
                            </option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>

            <!-- INSTRUCTOR -->
            <div class="form-group">
                <label><i class="fa-solid fa-user"></i> Instructor * <small>(Solo activos)</small></label>
                <select name="instructor" required>
                    <option value="">-- Seleccione un instructor --</option>
                    <?php while($i = mysqli_fetch_assoc($instructores)){ ?>
                        <option value="<?= $i['id'] ?>">
                            <?= htmlspecialchars($i['nombre']) ?>
                            (<?= htmlspecialchars($i['identificacion']) ?>)
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- RANGO DE FECHAS -->
            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-calendar-days"></i> Fecha Inicio *</label>
                    <input type="date" name="fecha_inicio" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fa-regular fa-calendar-days"></i> Fecha Fin *</label>
                    <input type="date" name="fecha_fin" min="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <!-- HORARIOS -->
            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Inicio *</label>
                    <input type="time" name="hora_inicio" required>
                </div>

                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Fin *</label>
                    <input type="time" name="hora_fin" required>
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div class="form-group">
                <label><i class="fa-solid fa-comment"></i> Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Observaciones generales sobre la autorización..."></textarea>
            </div>

            <!-- NOVEDADES -->
            <div class="form-group">
                <label><i class="fa-solid fa-circle-exclamation"></i> Novedades</label>
                <textarea name="novedades" rows="3" placeholder="Alguna novedad especial o restricción..."></textarea>
            </div>

            <!-- BOTÓN SUBMIT -->
            <button type="submit" name="autorizar" class="btn-submit">
                <i class="fa-solid fa-circle-check"></i> Autorizar Ambiente
            </button>
        </form>
    </div>

    <?php endif; ?>

    <!-- BOTÓN VOLVER -->
    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>


</body>
</html>