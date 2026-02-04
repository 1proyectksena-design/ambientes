<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = $_POST["usuario"] ?? "";
    $password = $_POST["password"] ?? "";

    $usuarios = [
        "Administrador" => "admin123",
        "Subdirección"  => "sub123",
        "Instructor"    => "inst123",
        "Celador"       => "cel123"
    ];

    if (isset($usuarios[$usuario]) && $usuarios[$usuario] === $password) {
        $_SESSION["usuario"] = $usuario;

        // Redirigir después del login
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
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

<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<form method="POST">

    <label>Usuario:</label><br>
    <select name="usuario" required>
        <option value="">Seleccione un usuario</option>
        <option value="Administrador">Administrador</option>
        <option value="Subdirección">Subdirección</option>
        <option value="Instructor">Instructor</option>
        <option value="Celador">Celador</option>
    </select>

    <br><br>

    <label>Contraseña:</label><br>
    <input type="password" name="password" required>

    <br><br>

    <button type="submit">Ingresar</button>

</form>

</body>
</html>
