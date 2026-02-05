<?php
session_start();

/* =========================
   PROTEGER VISTA SUBDIRECCIÓN
   ========================= */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subdirección</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

<h1>Panel de Subdirección</h1>

<div class="menu-subdireccion">
    <a href="consultar.php"> Consultar</a><br><br>
    <a href="permisos.php"> Permisos</a><br><br>
    <a href="registro.php">Registro</a><br><br>
    <a href="../logout.php"> Cerrar sesión</a>
</div>

</body>
</html>
