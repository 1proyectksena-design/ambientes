<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

<h1>Panel de Administración</h1>

<div class="menu-admin">
    <a href="consultar.php">📄Consultar historial</a><br><br>
    <a href="crear_ambiente.php"> Crear ambiente</a><br><br>
    <a href="permisos.php"> Autorizar ambiente</a><br><br>
    <a href="../logout.php"> Cerrar sesión</a>
</div>

</body>
</html>
