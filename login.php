<?php
session_start();
include("includes/conexion.php");
$error = "";

// Verificar conexión
if (!$conexion) {
    die("Error de conexión");
}

/* =========================
   TRAER USUARIOS PARA SELECT
   ========================= */
$usuarios = [];
$consultaUsuarios = $conexion->query(
    "SELECT usuario FROM usuarios WHERE usuario IS NOT NULL AND usuario != '' ORDER BY usuario ASC"
);

if ($consultaUsuarios) {
    while ($row = $consultaUsuarios->fetch_assoc()) {
        $usuarios[] = $row['usuario'];
    }
}

/* =========================
   PROCESAR LOGIN
   ========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario  = trim($_POST["usuario"]);
    $password = $_POST["password"];

    $stmt = $conexion->prepare(
        "SELECT id_usuario, usuario, password, rol 
         FROM usuarios 
         WHERE usuario = ?"
    );
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();

        if (password_verify($password, $fila["password"])) {
            $_SESSION["id_usuario"] = $fila["id_usuario"];
            $_SESSION["usuario"]    = $fila["usuario"];
            $_SESSION["rol"]        = $fila["rol"];

            // Redirección general (luego la separamos por rol si quieres)
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no existe";
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
        <?php foreach ($usuarios as $u): ?>
            <option value="<?php echo $u; ?>">
                <?php echo ucfirst($u); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Contraseña:</label><br>
    <input type="password" name="password" required>

    <br><br>

    <button type="submit">Ingresar</button>

</form>

</body>
</html>
