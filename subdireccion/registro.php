<?php
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* ===== CREAR AMBIENTE ===== */
if(isset($_POST['crear_ambiente'])){
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre_ambiente']);
    $estado = mysqli_real_escape_string($conexion, $_POST['estado']);
    $descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion']);
    $horario_fijo = mysqli_real_escape_string($conexion, $_POST['horario_fijo']);
    $horario_disponible = mysqli_real_escape_string($conexion, $_POST['horario_disponible']);
    
    $sql = "INSERT INTO ambientes (nombre_ambiente, estado, descripcion_general, horario_fijo, horario_disponible)
            VALUES ('$nombre', '$estado', '$descripcion', '$horario_fijo', '$horario_disponible')";
    
    if(mysqli_query($conexion, $sql)){
        echo "<script>alert('✅ Ambiente creado correctamente');</script>";
    } else {
        echo "<script>alert('❌ Error: ".mysqli_error($conexion)."');</script>";
    }
}

/* ===== CREAR INSTRUCTOR ===== */
if(isset($_POST['crear_instructor'])){
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $identificacion = mysqli_real_escape_string($conexion, $_POST['identificacion']);
    $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
    $fecha_fin = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
    $novedades = mysqli_real_escape_string($conexion, $_POST['novedades']);
    
    $sql = "INSERT INTO instructores (nombre, identificacion, fecha_inicio, fecha_fin, novedades)
            VALUES ('$nombre', '$identificacion', '$fecha_inicio', ".($fecha_fin ? "'$fecha_fin'" : "NULL").", '$novedades')";
    
    if(mysqli_query($conexion, $sql)){
        echo "<script>alert('✅ Instructor creado correctamente');</script>";
    } else {
        echo "<script>alert('❌ Error: ".mysqli_error($conexion)."');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Registros</title>
    <link rel="stylesheet" href="../css/permisos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Crear Registros</h1>
            <span>Ambientes e Instructores</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="permisos-container">

    <!-- SELECTOR DE FORMULARIO -->
    <div class="toggle-forms">
        <button class="toggle-btn active" onclick="showForm('ambiente')">
            <i class="fa-solid fa-building"></i> Crear Ambiente
        </button>
        <button class="toggle-btn" onclick="showForm('instructor')">
            <i class="fa-solid fa-chalkboard-user"></i> Crear Instructor
        </button>
    </div>

    <!-- FORMULARIO AMBIENTE -->
    <div class="form-card" id="form-ambiente">
        <div class="form-header">
            <h2><i class="fa-solid fa-building"></i> Nuevo Ambiente</h2>
            <p>Complete la información del ambiente</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre del Ambiente *</label>
                <input type="text" name="nombre_ambiente" placeholder="Ej: Laboratorio 308" required>
            </div>
            
            <div class="form-group">
                <label>Estado *</label>
                <select name="estado" required>
                    <option value="Habilitado">Habilitado</option>
                    <option value="Deshabilitado">Deshabilitado</option>
                    <option value="Mantenimiento">Mantenimiento</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Descripción General</label>
                <textarea name="descripcion" placeholder="Descripción del ambiente, equipamiento, capacidad..."></textarea>
            </div>
            
            <div class="time-grid">
                <div class="form-group">
                    <label>Horario Fijo</label>
                    <input type="text" name="horario_fijo" placeholder="Ej: 7AM - 12PM">
                </div>
                
                <div class="form-group">
                    <label>Horario Disponible</label>
                    <input type="text" name="horario_disponible" placeholder="Ej: 1PM - 6PM">
                </div>
            </div>
            
            <button type="submit" name="crear_ambiente" class="btn-submit">
                <i class="fa-solid fa-plus-circle"></i> Crear Ambiente
            </button>
        </form>
    </div>

    <!-- FORMULARIO INSTRUCTOR -->
    <div class="form-card" id="form-instructor" style="display:none;">
        <div class="form-header">
            <h2><i class="fa-solid fa-chalkboard-user"></i> Nuevo Instructor</h2>
            <p>Complete la información del instructor</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre Completo *</label>
                <input type="text" name="nombre" placeholder="Ej: Juan Carlos Pérez" required>
            </div>
            
            <div class="form-group">
                <label>Identificación *</label>
                <input type="text" name="identificacion" placeholder="Número de documento" required>
            </div>
            
            <div class="time-grid">
                <div class="form-group">
                    <label>Fecha Inicio *</label>
                    <input type="date" name="fecha_inicio" required>
                </div>
                
                <div class="form-group">
                    <label>Fecha Fin (Opcional)</label>
                    <input type="date" name="fecha_fin">
                </div>
            </div>
            
            <div class="form-group">
                <label>Novedades</label>
                <textarea name="novedades" placeholder="Observaciones, horarios especiales, etc."></textarea>
            </div>
            
            <button type="submit" name="crear_instructor" class="btn-submit">
                <i class="fa-solid fa-plus-circle"></i> Crear Instructor
            </button>
        </form>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
.toggle-forms {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 25px;
}

.toggle-btn {
    background: white;
    border: 2px solid #e5e7eb;
    padding: 15px 20px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.toggle-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

.toggle-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
}

.toggle-btn i {
    font-size: 1.2rem;
}

@media (max-width: 480px) {
    .toggle-forms {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showForm(tipo) {
    const formAmbiente = document.getElementById('form-ambiente');
    const formInstructor = document.getElementById('form-instructor');
    const btns = document.querySelectorAll('.toggle-btn');
    
    btns.forEach(b => b.classList.remove('active'));
    
    if(tipo === 'ambiente'){
        formAmbiente.style.display = 'block';
        formInstructor.style.display = 'none';
        btns[0].classList.add('active');
    } else {
        formAmbiente.style.display = 'none';
        formInstructor.style.display = 'block';
        btns[1].classList.add('active');
    }
}
</script>

</body>
</html>