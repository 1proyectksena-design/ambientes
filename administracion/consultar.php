<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* =========================
   HISTORIAL DE AUTORIZACIONES
   ========================= */
$sql = "SELECT 
            am.nombre_ambiente,
            i.nombre_completo,
            au.fecha,
            au.hora_inicio,
            au.hora_fin,
            au.rol_autorizado,
            am.estado
        FROM autorizaciones_ambientes au
        JOIN ambientes am ON au.id_ambiente = am.id_ambiente
        JOIN instructores i ON au.id_instructor = i.id_instructor
        ORDER BY au.fecha DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);

/* =========================
   BUSCAR AMBIENTE
   ========================= */
$ambienteBuscado = $_GET['ambiente'] ?? null;
$ambienteInfo = null;

if ($ambienteBuscado) {
    $ambienteBuscado = mysqli_real_escape_string($conexion, $ambienteBuscado);
    $sql = "SELECT * FROM ambientes WHERE nombre_ambiente = '$ambienteBuscado'";
    $res = mysqli_query($conexion, $sql);
    $ambienteInfo = mysqli_fetch_assoc($res);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizaciones - Administración</title>
    <link rel="stylesheet" href="../css/consultar.css?v=<?php echo time(); ?>"></head>
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo Institución">
        <div class="header-title">
            <h1>Consultar Autorizaciones</h1>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administracion
    </div>
</div>

<div class="consultar-container">

    <!-- ========================= BUSCAR AMBIENTE ========================= -->
    <div class="search-section">
        <h3> Buscar Ambiente Específico</h3>
        <form method="GET" class="search-form">
            <input type="text" name="ambiente" placeholder="Ej: 308, Laboratorio de Química, Sala 101..." value="<?= htmlspecialchars($ambienteBuscado ?? '') ?>" required>
            <button type="submit">Buscar</button>
        </form>
    </div>

    <!-- ========================= RESULTADO DE BÚSQUEDA ========================= -->
    <?php if ($ambienteBuscado && $ambienteInfo) { ?>
        <div class="ambiente-result">
            <h3 style="margin: 0 0 20px 0; color: #333;">📍 Información del Ambiente</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nombre</label>
                    <span><?= htmlspecialchars($ambienteInfo['nombre_ambiente']) ?></span>
                </div>
                <div class="info-item">
                    <label>Horario Fijo</label>
                    <span><?= htmlspecialchars($ambienteInfo['horario_fijo'] ?: 'No definido') ?></span>
                </div>
                <div class="info-item">
                    <label>Horario Disponible</label>
                    <span><?= htmlspecialchars($ambienteInfo['horario_disponible'] ?: 'No definido') ?></span>
                </div>
                <div class="info-item">
                    <label>Estado</label>
                    <span class="estado-badge estado-<?= $ambienteInfo['estado'] ?>">
                        <?= htmlspecialchars($ambienteInfo['estado']) ?>
                    </span>
                </div>
            </div>
            <a href="permisos.php?id_ambiente=<?= $ambienteInfo['id_ambiente'] ?>" class="btn-permiso">
                ✓ Solicitar Permiso
            </a>
        </div>
    <?php } elseif ($ambienteBuscado && !$ambienteInfo) { ?>
        <div class="ambiente-result">
            <div class="no-results">
                ❌ No se encontró el ambiente "<?= htmlspecialchars($ambienteBuscado) ?>"
            </div>
        </div>
    <?php } ?>

    <!-- ========================= HISTORIAL DE AUTORIZACIONES ========================= -->
    <div class="table-container">
        <div class="table-header">
            <h3>Historial de Autorizaciones</h3>
        </div>
        
        <?php if (mysqli_num_rows($resultado) > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th>Ambiente</th>
                        <th>Instructor</th>
                        <th>Fecha</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Autorizado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($resultado)){ ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                        <td><?= htmlspecialchars($row['nombre_completo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                        <td><?= date('h:i A', strtotime($row['hora_inicio'])) ?></td>
                        <td><?= date('h:i A', strtotime($row['hora_fin'])) ?></td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div class="no-results">
                 No hay autorizaciones registradas
            </div>
        <?php } ?>
    </div>

    <!-- ========================= BOTÓN VOLVER ========================= -->
    <a href="index.php" class="btn-volver">
        ← Volver al Panel
    </a>

</div>

</body>
</html>