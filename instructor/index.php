<?php
session_start();

if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../index.php");
    exit;
}
?>

<h2>Bienvenido Instructor <?php echo $_SESSION['usuario']; ?></h2>

<hr>

<a href="buscar_instructor.php">🔍 Buscar Instructor</a>
<br><br>
<a href="historial_ambiente.php">🏫 Historial de Ambiente</a>
<br><br>
<a href="solicitar_autorizacion.php">📝 Solicitar Autorización</a>
<br><br>
<a href="logout.php">🚪 Cerrar Sesión</a>
