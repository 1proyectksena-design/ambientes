<?php
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* =========================
   FILTROS
   ========================= */
$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_anio = $_GET['anio'] ?? date('Y');

/* WHERE principal (con alias au) */
$whereMain = [];

if($filtro_estado != 'todos'){
    $estadoSeguro = mysqli_real_escape_string($conexion, $filtro_estado);
    $whereMain[] = "au.estado = '$estadoSeguro'";
}

$whereMain[] = "MONTH(au.fecha_inicio) = '$filtro_mes'";
$whereMain[] = "YEAR(au.fecha_inicio) = '$filtro_anio'";

$whereSQLMain = implode(' AND ', $whereMain);

/* WHERE estadísticas (sin alias) */
$whereStat = [];
$whereStat[] = "MONTH(fecha_inicio) = '$filtro_mes'";
$whereStat[] = "YEAR(fecha_inicio) = '$filtro_anio'";

$whereSQLStat = implode(' AND ', $whereStat);

/* =========================
   CONSULTA PRINCIPAL
   ========================= */
$sql = "SELECT 
            au.*,
            a.nombre_ambiente,
            i.nombre AS nombre_instructor
        FROM autorizaciones_ambientes au
        JOIN ambientes a ON au.id_ambiente = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE $whereSQLMain
        ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);

if(!$resultado){
    die("Error en consulta: " . mysqli_error($conexion));
}

$total = mysqli_num_rows($resultado);

/* =========================
   ESTADÍSTICAS
   ========================= */
$statPendiente = mysqli_fetch_row(mysqli_query($conexion, 
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE estado='Pendiente' AND $whereSQLStat"))[0];

$statAprobado = mysqli_fetch_row(mysqli_query($conexion, 
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE estado='Aprobado' AND $whereSQLStat"))[0];

$statRechazado = mysqli_fetch_row(mysqli_query($conexion, 
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE estado='Rechazado' AND $whereSQLStat"))[0];
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Autorizaciones</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Historial de Autorizaciones</h1>
            <span>Registro completo del sistema</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="consultar-container">

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="stats-mini">
        <div class="stat-mini pendiente">
            <div class="num"><?= $statPendiente ?></div>
            <div class="lbl">Pendientes</div>
        </div>
        <div class="stat-mini aprobado">
            <div class="num"><?= $statAprobado ?></div>
            <div class="lbl">Aprobados</div>
        </div>
        <div class="stat-mini rechazado">
            <div class="num"><?= $statRechazado ?></div>
            <div class="lbl">Rechazados</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="search-section">
        <h3><i class="fa-solid fa-filter"></i> Filtrar Autorizaciones</h3>
        <form method="GET" class="search-form">
            <select name="estado">
                <option value="todos" <?= $filtro_estado == 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                <option value="Pendiente" <?= $filtro_estado == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="Aprobado" <?= $filtro_estado == 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                <option value="Rechazado" <?= $filtro_estado == 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
            </select>
            
            <select name="mes">
                <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $filtro_mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                </option>
                <?php endfor; ?>
            </select>
            
            <select name="anio">
                <?php for($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                <option value="<?= $y ?>" <?= $filtro_anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            
            <button type="submit">
                <i class="fa-solid fa-search"></i> Filtrar
            </button>
        </form>
    </div>

    <!-- TABLA DE AUTORIZACIONES -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-list"></i> 
                Mostrando <?= $total ?> autorizaciones
            </h3>
        </div>
        
        <?php if($total > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Ambiente</th>
                    <th>Instructor</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Horario</th>
                    <th>Estado</th>
                    <th>Autorizado Por</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultado)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                    <td>
                        <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                        <?= htmlspecialchars($row['nombre_instructor']) ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></td>
                    <td>
                        <?= date('h:i A', strtotime($row['hora_inicio'])) ?> - 
                        <?= date('h:i A', strtotime($row['hora_final'])) ?>
                    </td>
                    <td>
                        <span class="estado-badge estado-<?= strtolower($row['estado']) ?>">
                            <?= htmlspecialchars($row['estado']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            <i class="fa-solid fa-inbox"></i>
            <p>No hay autorizaciones con estos filtros</p>
        </div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-mini {
    background: white;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.stat-mini .num {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 5px;
}
.stat-mini .lbl {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}
.stat-mini.pendiente .num { color: #fb8c00; }
.stat-mini.aprobado .num { color: #43a047; }
.stat-mini.rechazado .num { color: #e53935; }

.search-form select {
    padding: 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: white;
}
.search-form select:focus {
    outline: none;
    border-color: #667eea;
}
</style>

</body>
</html>
