<?php
session_start();
include("includes/conexion.php");

$error = "";

/* =========================
   VERIFICAR CONEXIÓN
   ========================= */
if (!$conexion) {
    die("Error de conexión");
}

/* =========================
   TRAER USUARIOS PARA EL SELECT
   ========================= */
$usuarios = [];
$consultaUsuarios = $conexion->query(
    "SELECT usuario 
     FROM usuarios 
     WHERE usuario IS NOT NULL 
     AND usuario != '' 
     ORDER BY usuario ASC"
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

    // Limpiamos datos del formulario
    $usuario  = trim($_POST["usuario"]);
    $password = $_POST["password"];

    // Preparamos consulta segura
    $stmt = $conexion->prepare(
        "SELECT id_usuario, usuario, password, rol 
         FROM usuarios 
         WHERE usuario = ?"
    );
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    // Verificamos si el usuario existe
    if ($resultado->num_rows === 1) {

        $fila = $resultado->fetch_assoc();

        // Verificamos la contraseña encriptada
        if (password_verify($password, $fila["password"])) {

            /* =========================
               GUARDAR DATOS EN SESIÓN
               ========================= */
            $_SESSION["id_usuario"] = $fila["id_usuario"];
            $_SESSION["usuario"]    = $fila["usuario"];
            $_SESSION["rol"]        = $fila["rol"];

            /* =========================
               REDIRECCIÓN SEGÚN EL ROL
               ========================= */
            if ($fila["rol"] === "administracion") {
                // Si es admin → panel de administración
                header("Location: administracion/index.php");
            } elseif ($fila["rol"] === "subdireccion") {
             header("Location: subdireccion/index.php");

            } 
            else {
                // Si es usuario normal → panel usuario
                header("Location: usuario/index.php");
            }
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
   <link rel="stylesheet" href="/AMBIENTES/css/login.css">
</head>
<body>

<div class="login-container">


    <div class="login-header">
    <div class="logo-large">
        <img src="css/img/senab.png" alt="Logo">
    </div>
    <h2>Iniciar sesión</h2>
    <p class="subtitle">Ingresa tus credenciales para continuar</p>
</div>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">

            <label for="usuario">Usuario</label>
            <select name="usuario" id="usuario" required>
                <option value="">Seleccione un usuario</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?php echo $u; ?>">
                        <?php echo ucfirst($u); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" required>


        <button type="submit">Ingresar</button>
        <body class="login-bg">


</body>

    </form>

</div>

</body>

</html>
