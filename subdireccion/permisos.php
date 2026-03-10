<?php
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* =========================
   PARSEAR HORA TEXTO A HH:MM
   ========================= */
function parsearHora($texto) {
    $texto = strtoupper(trim($texto));
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $texto, $m)) {
        return sprintf('%02d:%02d', $m[1], $m[2]);
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(AM|PM)$/', $texto, $m)) {
        $h = (int)$m[1]; $min = (int)$m[2];
        if ($m[3] == 'PM' && $h != 12) $h += 12;
        if ($m[3] == 'AM' && $h == 12) $h = 0;
        return sprintf('%02d:%02d', $h, $min);
    }
    if (preg_match('/^(\d{1,2})(AM|PM)$/', $texto, $m)) {
        $h = (int)$m[1];
        if ($m[2] == 'PM' && $h != 12) $h += 12;
        if ($m[2] == 'AM' && $h == 12) $h = 0;
        return sprintf('%02d:00', $h);
    }
    return null;
}

function parsearRangoHorario($horario_texto) {
    if (empty($horario_texto)) return null;
    $partes = preg_split('/\s*-\s*/', trim($horario_texto));
    if (count($partes) < 2) return null;
    $inicio = parsearHora($partes[0]);
    $fin    = parsearHora($partes[1]);
    if (!$inicio || !$fin) return null;
    return ['inicio' => $inicio, 'fin' => $fin];
}

/* =========================
   ID AMBIENTE DESDE CONSULTAR
   ========================= */
$id_ambiente_seleccionado = $_GET['id_ambiente'] ?? null;

/* =========================
   CONSULTAS BASE
   ========================= */
$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes WHERE estado = 'Habilitado' ORDER BY nombre_ambiente");
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
    $ambiente     = mysqli_real_escape_string($conexion, $_POST['ambiente']);
    $instructor   = mysqli_real_escape_string($conexion, $_POST['instructor']);
    $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
    $fecha_fin    = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
    $hora_inicio  = mysqli_real_escape_string($conexion, $_POST['hora_inicio']);
    $hora_fin     = mysqli_real_escape_string($conexion, $_POST['hora_fin']);
    $obs          = mysqli_real_escape_string($conexion, $_POST['observaciones']);
    $novedades    = mysqli_real_escape_string($conexion, $_POST['novedades']);

    /* VALIDAR AMBIENTE HABILITADO */
    $checkAmbiente = mysqli_query($conexion, "SELECT * FROM ambientes WHERE id='$ambiente'");
    $ambienteData  = mysqli_fetch_assoc($checkAmbiente);

    if(!$ambienteData || $ambienteData['estado'] != 'Habilitado'){
        echo "<script>alert('⚠️ No se puede autorizar: El ambiente no está habilitado'); window.history.back();</script>";
        exit;
    }

    /* VALIDAR QUE NO CHOQUE CON EL HORARIO FIJO */
    $horario_fijo = $ambienteData['horario_fijo'];
    $rangoFijo = parsearRangoHorario($horario_fijo);

    if($rangoFijo){
        if($hora_inicio < $rangoFijo['fin'] && $hora_fin > $rangoFijo['inicio']){
            echo "<script>
                alert('🔒 Horario bloqueado.\\nEste ambiente tiene un horario fijo de " . addslashes($horario_fijo) . " que no se puede usar.\\nSolo puedes autorizar fuera de ese horario.');
                window.history.back();
            </script>";
            exit;
        }
    }

    /* VALIDAR QUE ESTÉ DENTRO DEL HORARIO DISPONIBLE */
    $horario_disponible = $ambienteData['horario_disponible'];
    $rangoDisponible = parsearRangoHorario($horario_disponible);

    if($rangoDisponible){
        if($hora_inicio < $rangoDisponible['inicio'] || $hora_fin > $rangoDisponible['fin']){
            echo "<script>
                alert('⚠️ Horario fuera del rango permitido.\\nEste ambiente solo está disponible de " . addslashes($horario_disponible) . "\\nIngresaste: $hora_inicio - $hora_fin');
                window.history.back();
            </script>";
            exit;
        }
    }

    /* VALIDAR INSTRUCTOR ACTIVO */
    $checkInstructor = mysqli_query($conexion, "
        SELECT * FROM instructores 
        WHERE id='$instructor' 
        AND (fecha_fin IS NULL OR fecha_fin >= '$fecha_inicio')
        AND fecha_inicio <= '$fecha_fin'
    ");
    if(mysqli_num_rows($checkInstructor) == 0){
        echo "<script>alert('⚠️ El instructor no está activo en el período seleccionado'); window.history.back();</script>";
        exit;
    }

    /* VALIDAR FECHAS */
    if($fecha_inicio > $fecha_fin){
        echo "<script>alert('⚠️ La fecha fin debe ser igual o mayor que la fecha inicio'); window.history.back();</script>";
        exit;
    }

    /* VALIDAR HORA FIN MAYOR QUE INICIO */
    if($hora_inicio >= $hora_fin){
        echo "<script>alert('⚠️ La hora fin debe ser mayor que la hora inicio'); window.history.back();</script>";
        exit;
    }

    /* VALIDAR CHOQUE CON OTRAS AUTORIZACIONES */
    $sqlChoque = "SELECT * FROM autorizaciones_ambientes
                  WHERE id_ambiente = '$ambiente'
                  AND estado = 'Aprobado'
                  AND (
                        (fecha_inicio <= '$fecha_fin' AND fecha_fin >= '$fecha_inicio')
                        AND (hora_inicio < '$hora_fin' AND hora_final > '$hora_inicio')
                      )";
    $resChoque = mysqli_query($conexion, $sqlChoque);
    if(mysqli_num_rows($resChoque) > 0){
        echo "<script>alert('⚠️ El ambiente ya tiene una autorización aprobada en ese período y horario'); window.history.back();</script>";
        exit;
    }

    /* INSERTAR */
    $sqlInsert = "INSERT INTO autorizaciones_ambientes
        (id_ambiente, id_instructor, rol_autorizado, fecha_inicio, fecha_fin, 
         hora_inicio, hora_final, estado, observaciones, novedades)
        VALUES
        ('$ambiente', '$instructor', 'subdireccion', '$fecha_inicio', '$fecha_fin',
         '$hora_inicio', '$hora_fin', 'Aprobado', '$obs', '$novedades')";

    if(mysqli_query($conexion, $sqlInsert)){
        echo "<script>alert('✅ Ambiente autorizado correctamente'); window.location.href='index.php';</script>";
        exit;
    } else {
        echo "<script>alert('❌ Error al crear la autorización: ".mysqli_error($conexion)."'); window.history.back();</script>";
        exit;
    }
}

$totalAmbientes    = mysqli_num_rows($ambientes);
$totalInstructores = mysqli_num_rows($instructores);
mysqli_data_seek($ambientes, 0);
mysqli_data_seek($instructores, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizar Ambiente - Subdirección</title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Autorizar Ambiente</h1>
            <span>Panel de Subdirección</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<div class="permisos-container">

    <?php if($totalAmbientes == 0): ?>
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>No hay ambientes disponibles</strong>
            <p>Todos los ambientes están deshabilitados o en mantenimiento.</p>
            <a href="index.php" class="btn-volver" style="margin-top:15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    <?php elseif($totalInstructores == 0): ?>
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>No hay instructores activos</strong>
            <p>No hay instructores disponibles para crear autorizaciones.</p>
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

        <form method="POST" id="form-autorizacion">

            <!-- AMBIENTE -->
            <div class="form-group">
                <label><i class="fa-solid fa-building"></i> Ambiente * <small>(Solo habilitados)</small></label>

                <?php if($id_ambiente_seleccionado): ?>
                    <?php
                    $ambiente_unico = mysqli_fetch_assoc(
                        mysqli_query($conexion, "SELECT * FROM ambientes WHERE id='$id_ambiente_seleccionado' AND estado='Habilitado'")
                    );
                    if($ambiente_unico):
                    ?>
                    <input type="hidden" name="ambiente" value="<?= $ambiente_unico['id'] ?>">
                    <input type="text" class="ambiente-readonly" value="<?= htmlspecialchars($ambiente_unico['nombre_ambiente']) ?>" readonly>

                    <input type="hidden" id="horario_fijo_txt"       value="<?= htmlspecialchars($ambiente_unico['horario_fijo'] ?? '') ?>">
                    <input type="hidden" id="horario_disponible_txt" value="<?= htmlspecialchars($ambiente_unico['horario_disponible'] ?? '') ?>">

                    <?php if(!empty($ambiente_unico['horario_fijo'])): ?>
                        <div class="horario-fijo-badge">
                            <i class="fa-solid fa-lock"></i>
                            Horario bloqueado (instructor fijo): <strong><?= htmlspecialchars($ambiente_unico['horario_fijo']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($ambiente_unico['horario_disponible'])): ?>
                        <div class="horario-info">
                            <i class="fa-solid fa-clock"></i>
                            Horario disponible: <strong><?= htmlspecialchars($ambiente_unico['horario_disponible']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php else: ?>
                        <div class="alert-warning">Este ambiente no está habilitado</div>
                    <?php endif; ?>

                <?php else: ?>
                    <select name="ambiente" id="select-ambiente" required onchange="mostrarHorario(this)">
                        <option value="">-- Seleccione un ambiente --</option>
                        <?php while($a = mysqli_fetch_assoc($ambientes)): ?>
                            <option value="<?= $a['id'] ?>"
                                    data-horario-fijo="<?= htmlspecialchars($a['horario_fijo'] ?: '') ?>"
                                    data-horario="<?= htmlspecialchars($a['horario_disponible'] ?: '') ?>">
                                <?= htmlspecialchars($a['nombre_ambiente']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <div class="horario-fijo-badge" id="horario-fijo-box" style="display:none;">
                        <i class="fa-solid fa-lock"></i>
                        Horario bloqueado (instructor fijo): <strong id="horario-fijo-texto"></strong>
                    </div>

                    <div class="horario-info" id="horario-info-box" style="display:none;">
                        <i class="fa-solid fa-clock"></i>
                        Horario disponible: <strong id="horario-info-texto"></strong>
                    </div>

                    <input type="hidden" id="horario_fijo_txt"       value="">
                    <input type="hidden" id="horario_disponible_txt" value="">
                <?php endif; ?>
            </div>

            <!-- INSTRUCTOR -->
            <div class="form-group">
                <label><i class="fa-solid fa-user"></i> Instructor * <small>(Solo activos)</small></label>
                <select name="instructor" required>
                    <option value="">-- Seleccione un instructor --</option>
                    <?php while($i = mysqli_fetch_assoc($instructores)): ?>
                        <option value="<?= $i['id'] ?>">
                            <?= htmlspecialchars($i['nombre']) ?>
                            (<?= htmlspecialchars($i['identificacion']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- FECHAS -->
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
                    <input type="time" name="hora_inicio" id="hora_inicio" required onchange="validarHoraEnCliente()">
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Hora Fin *</label>
                    <input type="time" name="hora_fin" id="hora_fin" required onchange="validarHoraEnCliente()">
                </div>
            </div>

            <!-- AVISO EN TIEMPO REAL -->
            <div id="aviso-horario" style="display:none; border-radius:8px; padding:10px 15px; margin-bottom:15px; font-size:14px;">
                <i class="fa-solid fa-triangle-exclamation"></i> <span id="aviso-texto"></span>
            </div>

            <!-- OBSERVACIONES -->
            <div class="form-group">
                <label><i class="fa-solid fa-comment"></i> Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Observaciones generales..."></textarea>
            </div>

            <!-- NOVEDADES -->
            <div class="form-group">
                <label><i class="fa-solid fa-circle-exclamation"></i> Novedades</label>
                <textarea name="novedades" rows="3" placeholder="Alguna novedad especial o restricción..."></textarea>
            </div>

            <button type="submit" name="autorizar" class="btn-submit">
                <i class="fa-solid fa-circle-check"></i> Autorizar Ambiente
            </button>
        </form>
    </div>

    <?php endif; ?>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<script>
function mostrarHorario(sel) {
    const opcion  = sel.options[sel.selectedIndex];
    const horario = opcion.getAttribute('data-horario');
    const fijo    = opcion.getAttribute('data-horario-fijo');

    const infoBox = document.getElementById('horario-info-box');
    const infoTxt = document.getElementById('horario-info-texto');
    const fijoBox = document.getElementById('horario-fijo-box');
    const fijoTxt = document.getElementById('horario-fijo-texto');
    const hidDis  = document.getElementById('horario_disponible_txt');
    const hidFijo = document.getElementById('horario_fijo_txt');

    if(horario){ infoTxt.textContent = horario; infoBox.style.display = 'inline-flex'; hidDis.value = horario; }
    else        { infoBox.style.display = 'none'; hidDis.value = ''; }

    if(fijo){ fijoTxt.textContent = fijo; fijoBox.style.display = 'inline-flex'; hidFijo.value = fijo; }
    else    { fijoBox.style.display = 'none'; hidFijo.value = ''; }

    validarHoraEnCliente();
}

function parsearHoraJS(txt) {
    txt = txt.trim().toUpperCase().replace(/\s+/g, ''); // ← QUITA ESPACIOS
    let m = txt.match(/^(\d{1,2}):(\d{2})$/);
    if(m) return m[1].padStart(2,'0') + ':' + m[2];
    m = txt.match(/^(\d{1,2}):(\d{2})(AM|PM)$/);
    if(m){
        let h = parseInt(m[1]), min = m[2];
        if(m[3]=='PM' && h!=12) h+=12;
        if(m[3]=='AM' && h==12) h=0;
        return String(h).padStart(2,'0')+':'+min;
    }
    m = txt.match(/^(\d{1,2})(AM|PM)$/);
    if(m){
        let h = parseInt(m[1]);
        if(m[2]=='PM' && h!=12) h+=12;
        if(m[2]=='AM' && h==12) h=0;
        return String(h).padStart(2,'0')+':00';
    }
    return null;
}

function validarHoraEnCliente() {
    const fijoTxt  = document.getElementById('horario_fijo_txt')?.value || '';
    const disTxt   = document.getElementById('horario_disponible_txt')?.value || '';
    const hi       = document.getElementById('hora_inicio')?.value;
    const hf       = document.getElementById('hora_fin')?.value;
    const aviso    = document.getElementById('aviso-horario');
    const avisoTxt = document.getElementById('aviso-texto');

    if(!hi || !hf){ aviso.style.display='none'; return; }

    // 1) Choque con horario fijo
    if(fijoTxt){
        const pf = fijoTxt.split(/\s*-\s*/);
        if(pf.length >= 2){
            const rf0 = parsearHoraJS(pf[0]);
            const rf1 = parsearHoraJS(pf[1]);
            if(rf0 && rf1 && hi < rf1 && hf > rf0){
                avisoTxt.innerHTML = `Horario bloqueado: Este ambiente tiene instructor fijo de <strong>${fijoTxt}</strong>. No puedes autorizar dentro de ese horario.`;
                aviso.style.cssText = 'display:block; background:#ffebee; color:#c62828; border:1px solid #e53935; border-radius:8px; padding:10px 15px; margin-bottom:15px; font-size:14px;';
                return;
            }
        }
    }

    // 2) Fuera del horario disponible
    if(disTxt){
        const pd = disTxt.split(/\s*-\s*/);
        if(pd.length >= 2){
            const rd0 = parsearHoraJS(pd[0]);
            const rd1 = parsearHoraJS(pd[1]);
            if(rd0 && rd1 && (hi < rd0 || hf > rd1)){
                avisoTxt.innerHTML = `⚠️ Las horas deben estar dentro del horario disponible <strong>${disTxt}</strong>. Estás ingresando ${hi} - ${hf}.`;
                aviso.style.cssText = 'display:block; background:#fff3cd; color:#856404; border:1px solid #ffc107; border-radius:8px; padding:10px 15px; margin-bottom:15px; font-size:14px;';
                return;
            }
        }
    }

    aviso.style.display = 'none';
}

document.getElementById('form-autorizacion')?.addEventListener('submit', function(e){
    const aviso = document.getElementById('aviso-horario');
    if(aviso && aviso.style.display === 'block'){
        e.preventDefault();
        alert('⚠️ Corrige el horario antes de continuar.');
    }
});
</script>

</body>
</html>