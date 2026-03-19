<?php
session_start();
date_default_timezone_set('America/Bogota');

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
   AUTORIZAR - CON MODO RECURRENTE
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
    
    // NUEVO: Modo único o recurrente
    $modo = $_POST['modo'] ?? 'unico';
    $dias_seleccionados = isset($_POST['dias']) ? $_POST['dias'] : [];

    /* VALIDAR AMBIENTE HABILITADO */
    $checkAmbiente = mysqli_query($conexion, "SELECT * FROM ambientes WHERE id='$ambiente'");
    $ambienteData  = mysqli_fetch_assoc($checkAmbiente);

    if(!$ambienteData || $ambienteData['estado'] != 'Habilitado'){
        echo "<script>alert('⚠️ No se puede autorizar: El ambiente no está habilitado'); window.history.back();</script>";
        exit;
    }

    /* NOTA: El horario fijo es solo informativo, NO bloquea */
    // Se puede autorizar sobre el horario fijo sin problemas

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

    /* ========================================
       LÓGICA MODO ÚNICO vs MODO RECURRENTE
       ======================================== */
    
    $fechas_a_autorizar = [];
    
    if($modo == 'unico'){
        // MODO ÚNICO: Solo una fecha (o rango continuo)
        $fechas_a_autorizar[] = [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin'    => $fecha_fin
        ];
    } else {
        // MODO RECURRENTE: Generar fechas individuales según días seleccionados
        
        if(empty($dias_seleccionados)){
            echo "<script>alert('⚠️ Debes seleccionar al menos un día de la semana'); window.history.back();</script>";
            exit;
        }
        
        // Mapeo de días en español a números (0=Domingo, 1=Lunes, ..., 6=Sábado)
        $mapa_dias = [
            'lunes'     => 1,
            'martes'    => 2,
            'miercoles' => 3,
            'jueves'    => 4,
            'viernes'   => 5,
            'sabado'    => 6
        ];
        
        $numeros_dias = [];
        foreach($dias_seleccionados as $dia){
            if(isset($mapa_dias[$dia])){
                $numeros_dias[] = $mapa_dias[$dia];
            }
        }
        
        if(empty($numeros_dias)){
            echo "<script>alert('⚠️ Días seleccionados no válidos'); window.history.back();</script>";
            exit;
        }
        
        // Recorrer cada día del rango
        $fecha_actual_loop = new DateTime($fecha_inicio);
        $fecha_fin_obj = new DateTime($fecha_fin);
        
        while($fecha_actual_loop <= $fecha_fin_obj){
            $dia_semana = (int)$fecha_actual_loop->format('N'); // 1=Lunes, 7=Domingo
            
            // Si este día está seleccionado, agregar a la lista
            if(in_array($dia_semana, $numeros_dias)){
                $fechas_a_autorizar[] = [
                    'fecha_inicio' => $fecha_actual_loop->format('Y-m-d'),
                    'fecha_fin'    => $fecha_actual_loop->format('Y-m-d')
                ];
            }
            
            $fecha_actual_loop->modify('+1 day');
        }
        
        if(empty($fechas_a_autorizar)){
            echo "<script>alert('⚠️ No hay fechas que coincidan con los días seleccionados en el rango especificado'); window.history.back();</script>";
            exit;
        }
    }
    
    /* ========================================
       INSERTAR AUTORIZACIONES
       ======================================== */
    
    $insertados = 0;
    $errores = [];
    
    foreach($fechas_a_autorizar as $fecha_item){
        $f_inicio = $fecha_item['fecha_inicio'];
        $f_fin    = $fecha_item['fecha_fin'];
        
        // VALIDAR CHOQUE CON OTRAS AUTORIZACIONES (por cada día)
        $sqlChoque = "SELECT * FROM autorizaciones_ambientes
                      WHERE id_ambiente = '$ambiente'
                      AND estado = 'Aprobado'
                      AND fecha_inicio = '$f_inicio'
                      AND (hora_inicio < '$hora_fin' AND hora_final > '$hora_inicio')";
        $resChoque = mysqli_query($conexion, $sqlChoque);
        
        if(mysqli_num_rows($resChoque) > 0){
            $errores[] = "Choque en $f_inicio con horario $hora_inicio - $hora_fin";
            continue; // Saltar este día
        }
        
        // INSERTAR
        $sqlInsert = "INSERT INTO autorizaciones_ambientes
            (id_ambiente, id_instructor, rol_autorizado, fecha_inicio, fecha_fin, 
             hora_inicio, hora_final, estado, observaciones, novedades)
            VALUES
            ('$ambiente', '$instructor', 'subdireccion', '$f_inicio', '$f_fin',
             '$hora_inicio', '$hora_fin', 'Aprobado', '$obs', '$novedades')";
        
        if(mysqli_query($conexion, $sqlInsert)){
            $insertados++;
        } else {
            $errores[] = "Error en $f_inicio: " . mysqli_error($conexion);
        }
    }
    
    // RESULTADO FINAL
    if($insertados > 0 && empty($errores)){
        echo "<script>alert('✅ Se autorizaron $insertados días correctamente'); window.location.href='index.php';</script>";
        exit;
    } elseif($insertados > 0 && !empty($errores)){
        $msg_errores = implode("\\n", $errores);
        echo "<script>alert('⚠️ Se autorizaron $insertados días correctamente\\n\\nErrores en algunos días:\\n$msg_errores'); window.location.href='index.php';</script>";
        exit;
    } else {
        $msg_errores = implode("\\n", $errores);
        echo "<script>alert('❌ No se pudo autorizar ningún día:\\n$msg_errores'); window.history.back();</script>";
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

            <!-- SELECTOR DE MODO -->
            <div class="modo-selector">
                <div class="modo-option">
                    <input type="radio" name="modo" id="modo-unico" value="unico" checked onchange="cambiarModo()">
                    <label for="modo-unico">
                        <i class="fa-solid fa-calendar-day"></i> Modo Único
                    </label>
                </div>
                <div class="modo-option">
                    <input type="radio" name="modo" id="modo-recurrente" value="recurrente" onchange="cambiarModo()">
                    <label for="modo-recurrente">
                        <i class="fa-solid fa-calendar-week"></i> Modo Recurrente
                    </label>
                </div>
            </div>

            <!-- SELECTOR DE DÍAS (solo para modo recurrente) -->
            <div class="dias-selector" id="dias-selector">
                <h4><i class="fa-solid fa-calendar-check"></i> Selecciona los días de la semana</h4>
                <div class="dias-grid">
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-lunes" value="lunes">
                        <label for="dia-lunes">Lunes</label>
                    </div>
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-martes" value="martes">
                        <label for="dia-martes">Martes</label>
                    </div>
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-miercoles" value="miercoles">
                        <label for="dia-miercoles">Miércoles</label>
                    </div>
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-jueves" value="jueves">
                        <label for="dia-jueves">Jueves</label>
                    </div>
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-viernes" value="viernes">
                        <label for="dia-viernes">Viernes</label>
                    </div>
                    <div class="dia-checkbox">
                        <input type="checkbox" name="dias[]" id="dia-sabado" value="sabado">
                        <label for="dia-sabado">Sábado</label>
                    </div>
                </div>
            </div>

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
                            <i class="fa-solid fa-info-circle"></i>
                            <span>Instructor fijo asignado: <strong><?= htmlspecialchars($ambiente_unico['horario_fijo']) ?></strong> (informativo)</span>
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
                        <i class="fa-solid fa-info-circle"></i>
                        <span>Instructor fijo asignado: <strong id="horario-fijo-texto"></strong> (informativo)</span>
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
                    <input type="date" name="fecha_inicio" id="fecha-inicio" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-regular fa-calendar-days"></i> Fecha Fin *</label>
                    <input type="date" name="fecha_fin" id="fecha-fin" min="<?= date('Y-m-d') ?>" required>
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

            <div id="aviso-horario" style="display:none; background:#fff3cd; color:#856404; border:1px solid #ffc107; border-radius:8px; padding:10px 15px; margin-bottom:15px; font-size:14px;">
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
/* ===========================
   CAMBIAR MODO ÚNICO/RECURRENTE
   =========================== */
function cambiarModo() {
    const modoRecurrente = document.getElementById('modo-recurrente').checked;
    const diasSelector = document.getElementById('dias-selector');
    const fechaInicio = document.getElementById('fecha-inicio');
    const fechaFin = document.getElementById('fecha-fin');
    
    if(modoRecurrente){
        diasSelector.classList.add('active');
        fechaFin.value = '';
    } else {
        diasSelector.classList.remove('active');
        if(fechaInicio.value){
            fechaFin.value = fechaInicio.value;
        }
    }
}

/* ===========================
   SINCRONIZAR FECHAS EN MODO ÚNICO
   =========================== */
document.getElementById('fecha-inicio')?.addEventListener('change', function(){
    const modoUnico = document.getElementById('modo-unico').checked;
    if(modoUnico){
        document.getElementById('fecha-fin').value = this.value;
    }
});

/* ===========================
   MOSTRAR HORARIO DEL AMBIENTE
   =========================== */
function mostrarHorario(sel) {
    const opcion   = sel.options[sel.selectedIndex];
    const horario  = opcion.getAttribute('data-horario');
    const fijo     = opcion.getAttribute('data-horario-fijo');

    const infoBox  = document.getElementById('horario-info-box');
    const infoTxt  = document.getElementById('horario-info-texto');
    const fijoBox  = document.getElementById('horario-fijo-box');
    const fijoTxt  = document.getElementById('horario-fijo-texto');
    const hidDis   = document.getElementById('horario_disponible_txt');
    const hidFijo  = document.getElementById('horario_fijo_txt');

    if(horario){ infoTxt.textContent = horario; infoBox.style.display = 'inline-flex'; hidDis.value = horario; }
    else        { infoBox.style.display = 'none'; hidDis.value = ''; }

    // HORARIO FIJO: Solo informativo, no bloquea
    if(fijo){ fijoTxt.textContent = fijo; fijoBox.style.display = 'inline-flex'; hidFijo.value = fijo; }
    else     { fijoBox.style.display = 'none'; hidFijo.value = ''; }

    validarHoraEnCliente();
}

/* ===========================
   PARSER HORA AM/PM
   =========================== */
function parsearHoraJS(txt) {
    txt = txt.trim().toUpperCase().replace(/\s+/g, '');
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

/* ===========================
   VALIDAR HORARIOS EN CLIENTE
   (HORARIO FIJO YA NO BLOQUEA)
   =========================== */
function validarHoraEnCliente() {
    // Ya no validamos horario fijo
    const disTxt   = document.getElementById('horario_disponible_txt')?.value || '';
    const hi       = document.getElementById('hora_inicio')?.value;
    const hf       = document.getElementById('hora_fin')?.value;
    const aviso    = document.getElementById('aviso-horario');
    const avisoTxt = document.getElementById('aviso-texto');

    if(!hi || !hf){ aviso.style.display='none'; return; }

    // Solo validar horario disponible
    if(disTxt){
        const pd = disTxt.split(/\s*-\s*/);
        if(pd.length >= 2){
            const rd0 = parsearHoraJS(pd[0]);
            const rd1 = parsearHoraJS(pd[1]);
            if(rd0 && rd1 && (hi < rd0 || hf > rd1)){
                avisoTxt.innerHTML = ` Las horas deben estar dentro del horario disponible <strong>${disTxt}</strong>. Estás ingresando ${hi} - ${hf}.`;
                aviso.style.display = 'block';
                aviso.style.background = '#fff3cd';
                aviso.style.color = '#856404';
                aviso.style.borderColor = '#ffc107';
                return;
            }
        }
    }

    aviso.style.display = 'none';
}

/* ===========================
   BLOQUEAR ENVÍO SI HAY AVISO
   =========================== */
document.getElementById('form-autorizacion')?.addEventListener('submit', function(e){
    const aviso = document.getElementById('aviso-horario');
    if(aviso && aviso.style.display === 'block'){
        e.preventDefault();
        alert(' Corrige el horario antes de continuar.');
    }
});
</script>

</body>
</html>