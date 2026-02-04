<?php
// ===============================
// PROCESO DEL LOGIN
// ===============================
include("../includes/conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = $_POST["usuario"];
    $password = $_POST["password"];

    /*
    =====================================
    AQUÍ DEFINES LAS CONTRASEÑAS
    Puedes cambiarlas cuando quieras
    =====================================
    */
    $usuarios = [
        "Administrador" => "admin123",
        "Subdirección"  => "sub123",
        "Instructor"    => "inst123",
        "Celador"       => "cel123"
    ];

    // Verificamos si el usuario existe y la contraseña coincide
    if (isset($usuarios[$usuario]) && $usuarios[$usuario] === $password) {
        echo "Bienvenido, has iniciado sesión como: " . $usuario;
    } else {
        echo "Usuario o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
</head>
<body>

    <h2>Iniciar sesión</h2>

    <form method="POST" action="login.php">

        <!-- LISTA DESPLEGABLE DE USUARIOS -->
        <label>Usuario:</label><br>
        <select name="usuario" required>
            <option value="">Seleccione un usuario</option>
            <option value="Administrador">Administrador</option>
            <option value="Subdirección">Subdirección</option>
            <option value="Instructor">Instructor</option>
            <option value="Celador">Celador</option>
        </select>

        <br><br>

        <!-- CONTRASEÑA -->
        <label>Contraseña:</label><br>
        <input type="password" name="password" required>

        <br><br>

        <!-- BOTÓN -->
        <button type="submit">Ingresar</button>

    </form>

</body>
</html>
