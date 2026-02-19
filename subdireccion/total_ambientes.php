<?php
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* Filtro por estado */
$filtro = $_GET['estado'] ?? 'todos';

$where = $filtro != 'todos' ? "WHERE estado = '".mysqli_real_escape_string($conexion, $filtro)."'" : '';

$sql = "SELECT * FROM ambientes $where ORDER BY nombre_ambiente ASC";
$resultado = mysqli_query($conexion, $sql);
$total = mysqli_num_rows($resultado);

/* Contadores */
$habilitados = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Habilitado'"))[0];
$deshabilitados = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Deshabilitado'"))[0];
$mantenimiento = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM ambientes WHERE estado='Mantenimiento'"))[0];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Total de Ambientes</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Total de Ambientes</h1>
            <span>Gestión de espacios</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="consultar-container">

    <!-- STATS -->
    <div class="stats-mini">
        <div class="stat-mini habilitado">
            <div class="num"><?= $habilitados ?></div>
            <div class="lbl">Habilitados</div>
        </div>
        <div class="stat-mini deshabilitado">
            <div class="num"><?= $deshabilitados ?></div>
            <div class="lbl">Deshabilitados</div>
        </div>
        <div class="stat-mini mantenimiento">
            <div class="num"><?= $mantenimiento ?></div>
            <div class="lbl">Mantenimiento</div>
        </div>
    </div>

    <!-- FILTRO -->
    <div class="search-section">
        <h3><i class="fa-solid fa-filter"></i> Filtrar por Estado</h3>
        <form method="GET" class="search-form">
            <select name="estado">
                <option value="todos" <?= $filtro == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="Habilitado" <?= $filtro == 'Habilitado' ? 'selected' : '' ?>>Habilitado</option>
                <option value="Deshabilitado" <?= $filtro == 'Deshabilitado' ? 'selected' : '' ?>>Deshabilitado</option>
                <option value="Mantenimiento" <?= $filtro == 'Mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
            </select>
            <button type="submit"><i class="fa-solid fa-search"></i> Filtrar</button>
        </form>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fa-solid fa-building"></i> Mostrando <?= $total ?> ambientes</h3>
        </div>
        
        <?php if($total > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Horario Fijo</th>
                    <th>Horario Disponible</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultado)): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                    <td>
                        <span class="estado-badge estado-<?= strtolower($row['estado']) ?>">
                            <?= htmlspecialchars($row['estado']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['horario_fijo'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($row['horario_disponible'] ?: '—') ?></td>
                    <td><?= htmlspecialchars(substr($row['descripcion_general'], 0, 50)) ?><?= strlen($row['descripcion_general']) > 50 ? '...' : '' ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            <i class="fa-solid fa-inbox"></i>
            <p>No hay ambientes con este filtro</p>
        </div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
.stats-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-mini { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.stat-mini .num { font-size: 32px; font-weight: 800; margin-bottom: 5px; }
.stat-mini .lbl { font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600; }
.stat-mini.habilitado .num { color: #43a047; }
.stat-mini.deshabilitado .num { color: #e53935; }
.stat-mini.mantenimiento .num { color: #fb8c00; }
.search-form select { padding: 14px 20px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; transition: all 0.3s ease; background: white; }
.search-form select:focus { outline: none; border-color: #667eea; }
</style>

</body>
</html>