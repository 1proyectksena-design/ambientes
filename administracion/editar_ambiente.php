<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$id_ambiente = $_GET['id'] ?? null;

if(!$id_ambiente){
    header("Location: consultar.php");
    exit;
}

/* Obtener info del ambiente */
$sql = "SELECT * FROM ambientes WHERE id = '".mysqli_real_escape_string($conexion, $id_ambiente)."'";
$res = mysqli_query($conexion, $sql);
$ambiente = mysqli_fetch_assoc($res);

if(!$ambiente){
    echo "<script>alert('Ambiente no encontrado'); window.location.href='consultar.php';</script>";
    exit;
}

/* ACTUALIZAR ESTADO */
if(isset($_POST['actualizar'])){
    $nuevo_estado = mysqli_real_escape_string($conexion, $_POST['estado']);
    $descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion_general']);
    $horario_fijo = mysqli_real_escape_string($conexion, $_POST['horario_fijo']);
    $horario_disponible = mysqli_real_escape_string($conexion, $_POST['horario_disponible']);
    
    $sqlUpdate = "UPDATE ambientes 
                  SET estado = '$nuevo_estado',
                      descripcion_general = '$descripcion',
                      horario_fijo = '$horario_fijo',
                      horario_disponible = '$horario_disponible'
                  WHERE id = '$id_ambiente'";
    
    if(mysqli_query($conexion, $sqlUpdate)){
        echo "<script>
                alert('✅ Ambiente actualizado correctamente');
                window.location.href='consultar.php?ambiente=".urlencode($ambiente['nombre_ambiente'])."';
              </script>";
        exit;
    } else {
        echo "<script>alert('❌ Error al actualizar: ".mysqli_error($conexion)."');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ambiente - <?= htmlspecialchars($ambiente['nombre_ambiente']) ?></title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Editar Ambiente</h1>
            <span>Modificar estado y configuración</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="permisos-container">

    <div class="form-card">
        <div class="form-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Editar: <?= htmlspecialchars($ambiente['nombre_ambiente']) ?></h2>
            <p>Modifica el estado y la información del ambiente</p>
        </div>

        <!-- ESTADO ACTUAL -->
        <div class="estado-actual">
            <strong>Estado Actual:</strong>
            <span class="estado-badge estado-<?= strtolower($ambiente['estado']) ?>">
                <?= htmlspecialchars($ambiente['estado']) ?>
            </span>
        </div>

        <form method="POST">

            <!-- NUEVO ESTADO -->
            <div class="form-group">
                <label><i class="fa-solid fa-toggle-on"></i> Cambiar Estado *</label>
                <select name="estado" required>
                    <option value="Habilitado" <?= $ambiente['estado'] == 'Habilitado' ? 'selected' : '' ?>>
                        Habilitado (Disponible para autorizaciones)
                    </option>
                    <option value="Deshabilitado" <?= $ambiente['estado'] == 'Deshabilitado' ? 'selected' : '' ?>>
                        Deshabilitado (Fuera de servicio)
                    </option>
                    <option value="Mantenimiento" <?= $ambiente['estado'] == 'Mantenimiento' ? 'selected' : '' ?>>
                        Mantenimiento (En reparación)
                    </option>
                </select>
            </div>

            <!-- DESCRIPCIÓN -->
            <div class="form-group">
                <label><i class="fa-solid fa-file-lines"></i> Descripción General</label>
                <textarea name="descripcion_general" rows="4" placeholder="Descripción del ambiente, equipamiento, capacidad..."><?= htmlspecialchars($ambiente['descripcion_general']) ?></textarea>
            </div>

            <!-- HORARIOS -->
            <div class="time-grid">
                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Horario Fijo</label>
                    <input type="text" name="horario_fijo" value="<?= htmlspecialchars($ambiente['horario_fijo']) ?>" placeholder="Ej: 7AM - 12PM">
                </div>

                <div class="form-group">
                    <label><i class="fa-regular fa-clock"></i> Horario Disponible</label>
                    <input type="text" name="horario_disponible" value="<?= htmlspecialchars($ambiente['horario_disponible']) ?>" placeholder="Ej: 1PM - 6PM">
                </div>
            </div>

            <!-- ADVERTENCIAS SEGÚN ESTADO -->
            <div class="estado-warnings">
                <div class="warning-item" id="warn-habilitado" style="display: none;">
                    <i class="fa-solid fa-circle-check"></i>
                    <p>El ambiente estará disponible para nuevas autorizaciones</p>
                </div>
                
                <div class="warning-item warning-danger" id="warn-deshabilitado" style="display: none;">
                    <i class="fa-solid fa-ban"></i>
                    <p><strong>Advertencia:</strong> No se podrán crear nuevas autorizaciones mientras esté deshabilitado</p>
                </div>
                
                <div class="warning-item warning-warning" id="warn-mantenimiento" style="display: none;">
                    <i class="fa-solid fa-wrench"></i>
                    <p><strong>Nota:</strong> El ambiente estará temporalmente fuera de servicio</p>
                </div>
            </div>

            <!-- BOTONES -->
            <div class="form-buttons">
                <button type="submit" name="actualizar" class="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
                
                <a href="consultar.php?ambiente=<?= urlencode($ambiente['nombre_ambiente']) ?>" class="btn-cancel">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </a>
            </div>
        </form>
    </div>

</div>

<style>
.estado-actual {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.estado-actual strong {
    color: #333;
    font-size: 1.05rem;
}

.estado-warnings {
    margin: 20px 0;
}

.warning-item {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 12px 15px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.warning-item i {
    font-size: 1.3rem;
    color: #2196f3;
}

.warning-item.warning-danger {
    background: #ffebee;
    border-left-color: #e53935;
}

.warning-item.warning-danger i {
    color: #e53935;
}

.warning-item.warning-warning {
    background: #fff3e0;
    border-left-color: #fb8c00;
}

.warning-item.warning-warning i {
    color: #fb8c00;
}

.warning-item p {
    margin: 0;
    font-size: 0.95rem;
    color: #555;
}

.form-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 25px;
}

.btn-cancel {
    background: #6c757d;
    color: white;
    padding: 14px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .form-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Mostrar advertencia según el estado seleccionado
const estadoSelect = document.querySelector('select[name="estado"]');
const warnings = {
    'Habilitado': document.getElementById('warn-habilitado'),
    'Deshabilitado': document.getElementById('warn-deshabilitado'),
    'Mantenimiento': document.getElementById('warn-mantenimiento')
};

function updateWarning() {
    Object.values(warnings).forEach(w => w.style.display = 'none');
    const selected = estadoSelect.value;
    if(warnings[selected]) {
        warnings[selected].style.display = 'flex';
    }
}

estadoSelect.addEventListener('change', updateWarning);
updateWarning(); // Mostrar al cargar
</script>

</body>
</html>