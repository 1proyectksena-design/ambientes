<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* MESES EN ESPAÑOL */
$meses_espanol = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

$sql = "SELECT 
            au.*,
            a.nombre_ambiente,
            i.nombre AS nombre_instructor
        FROM autorizaciones_ambientes au
        JOIN ambientes a ON au.id_ambiente = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE MONTH(au.fecha_inicio) = '$mes'
        AND YEAR(au.fecha_inicio) = '$anio'
        ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);
$total = mysqli_num_rows($resultado);

/* Stats */
$pendiente = mysqli_fetch_row(mysqli_query($conexion,
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE MONTH(fecha_inicio)='$mes'
 AND YEAR(fecha_inicio)='$anio'
 AND estado='Pendiente'"))[0];

$aprobado = mysqli_fetch_row(mysqli_query($conexion,
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE MONTH(fecha_inicio)='$mes'
 AND YEAR(fecha_inicio)='$anio'
 AND estado='Aprobado'"))[0];

$rechazado = mysqli_fetch_row(mysqli_query($conexion,
"SELECT COUNT(*) FROM autorizaciones_ambientes 
 WHERE MONTH(fecha_inicio)='$mes'
 AND YEAR(fecha_inicio)='$anio'
 AND estado='Rechazado'"))[0];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizaciones del Mes</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Autorizaciones del Mes</h1>
            <span><?= $meses_espanol[$mes] ?> <?= $anio ?></span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="consultar-container">

    <!-- SELECTOR DE MES -->
    <div class="search-section">
        <h3><i class="fa-regular fa-calendar"></i> Seleccionar Mes</h3>
        <form method="GET" class="search-form">
            <select name="mes">
                <?php for($m=1; $m<=12; $m++): 
                    $mes_num = str_pad($m, 2, '0', STR_PAD_LEFT);
                ?>
                <option value="<?= $mes_num ?>" <?= $mes == $mes_num ? 'selected' : '' ?>>
                    <?= $meses_espanol[$mes_num] ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="anio">
                <?php for($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
        </form>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-list-check"></i> 
                <?= $total ?> autorizaciones en <?= $meses_espanol[$mes] ?> <?= $anio ?>
            </h3>
        </div>
        
        <?php if($total > 0): ?>
        <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ambiente</th>
                        <th>Instructor</th>
                        <th>Período</th>
                        <th>Horario</th>
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
                        <td>
                            <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?> - 
                            <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                        </td>
                        <td>
                            <?= date('h:i A', strtotime($row['hora_inicio'])) ?> - 
                            <?= date('h:i A', strtotime($row['hora_final'])) ?>
                        </td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="no-results">
            <i class="fa-solid fa-inbox"></i>
            <p>No hay autorizaciones en este mes</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- BOTONES -->
    <div class="btn-group">
        <a href="exportar.php?mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn-exportar">
            <i class="fa-solid fa-file-excel"></i> Exportar Excel
        </a>
        <a href="index.php" class="btn-volver">
            <i class="fa-solid fa-arrow-left"></i> Volver al Panel
        </a>
    </div>

</div>

<style>
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

.btn-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
}

.btn-exportar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #1D6F42;
    color: white;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 15px;
    transition: background 0.3s ease, transform 0.2s ease;
    box-shadow: 0 2px 8px rgba(29,111,66,0.3);
}
.btn-exportar:hover {
    background: #155230;
    transform: translateY(-1px);
}
.btn-exportar i {
    font-size: 16px;
}
</style>

</body>
</html>