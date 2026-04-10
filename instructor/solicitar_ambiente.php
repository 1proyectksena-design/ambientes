<?php
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

/* ══════════════════════════════════════════════════════════
   AJAX BUSCAR INSTRUCTOR
   ══════════════════════════════════════════════════════════ */
if (isset($_GET['buscar'])) {
    header('Content-Type: application/json');

    $identificacion = $_GET['documento'] ?? '';

    if (!$identificacion) {
        echo json_encode(["error" => "Identificación vacía"]);
        exit;
    }

    $sql = "SELECT id, nombre, identificacion FROM instructores WHERE identificacion = ?";
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        echo json_encode(["error" => "Error en la consulta"]);
        exit;
    }

    $stmt->bind_param("s", $identificacion);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(["error" => "Instructor no encontrado"]);
        exit;
    }

    $row = $res->fetch_assoc();

    echo json_encode([
        "id" => $row['id'],
        "nombre" => $row['nombre'],
        "identificacion" => $row['identificacion']
    ]);

    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitar Ambiente</title>

<style>
body {
    font-family: Arial;
    background: #0b1220;
    color: white;
}

.card {
    max-width: 600px;
    margin: 50px auto;
    background: #111827;
    padding: 20px;
    border-radius: 10px;
}

input {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: none;
}

button {
    padding: 10px 15px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.error {
    background: #7f1d1d;
    padding: 10px;
    margin-top: 10px;
    border-radius: 6px;
}

.ok {
    background: #064e3b;
    padding: 10px;
    margin-top: 10px;
    border-radius: 6px;
}
</style>

</head>
<body>

<div class="card">
    <h2>Solicitar Ambiente</h2>
    <p>Busca el instructor, define el horario y selecciona el ambiente</p>

    <label>NÚMERO DE IDENTIFICACIÓN DEL INSTRUCTOR *</label>

    <div style="display:flex; gap:10px;">
        <input type="text" id="identificacion" placeholder="Ej: 123456">
        <button onclick="buscarInstructor()">Buscar</button>
    </div>

    <div id="mensaje"></div>

    <div id="resultado" style="display:none;">
        <h3>Instructor encontrado:</h3>
        <p id="nombre"></p>
    </div>
</div>

<script>
async function buscarInstructor() {
    const doc = document.getElementById('identificacion').value.trim();
    const mensaje = document.getElementById('mensaje');
    const resultado = document.getElementById('resultado');
    const nombre = document.getElementById('nombre');

    mensaje.innerHTML = "";
    resultado.style.display = "none";

    if (!doc) {
        mensaje.innerHTML = '<div class="error">Ingrese un documento</div>';
        return;
    }

    try {
        const res = await fetch('solicitar_ambiente.php?buscar=1&documento=' + doc);
        const data = await res.json();

        if (data.error) {
            mensaje.innerHTML = '<div class="error">' + data.error + '</div>';
            return;
        }

        nombre.innerHTML = data.nombre;
        resultado.style.display = "block";

    } catch (e) {
        mensaje.innerHTML = '<div class="error">Error al buscar el instructor</div>';
    }
}
</script>

</body>
</html>
