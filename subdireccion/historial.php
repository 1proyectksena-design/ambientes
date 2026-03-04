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

/* FILTROS */
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_anio = $_GET['anio'] ?? date('Y');

$whereMain = [];
$whereMain[] = "MONTH(au.fecha_inicio) = '$filtro_mes'";
$whereMain[] = "YEAR(au.fecha_inicio) = '$filtro_anio'";
$whereSQLMain = implode(' AND ', $whereMain);

/* CONSULTA */
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

/* Fecha y hora actual */
$fecha_actual = date('Y-m-d');
$hora_actual = date('H:i:s');
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
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<div class="consultar-container">

    <!-- FILTROS -->
    <div class="search-section">
        <h3><i class="fa-solid fa-filter"></i> Filtrar Autorizaciones</h3>
        <form method="GET" class="search-form">
            <select name="mes">
                <?php for($m=1; $m<=12; $m++): 
                    $mes_num = str_pad($m, 2, '0', STR_PAD_LEFT);
                ?>
                <option value="<?= $mes_num ?>" <?= $filtro_mes == $mes_num ? 'selected' : '' ?>>
                    <?= $meses_espanol[$mes_num] ?>
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

    <!-- TABLA -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-list"></i>
                Mostrando <?= $total ?> autorizaciones
            </h3>
        </div>

        <?php if($total > 0): ?>
        <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ambiente</th>
                        <th>Instructor</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Horario</th>
                        <th>Estado Actual</th>
                        <th>Autorizado Por</th>
                        <th>Novedades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($resultado)): 
                        /* CALCULAR ESTADO */
                        $estadoActual = 'desocupado';
                        $textoEstado = 'Desocupado';
                        $iconoEstado = '<i class="fa-solid fa-circle"></i>';

                        if($row['estado'] == 'Aprobado') {
                            if($fecha_actual >= $row['fecha_inicio'] && $fecha_actual <= $row['fecha_fin']) {
                                if($hora_actual >= $row['hora_inicio'] && $hora_actual <= $row['hora_final']) {
                                    $estadoActual = 'ocupado-ahora';
                                    $textoEstado = 'Ocupado Ahora';
                                    $iconoEstado = '<i class="fa-solid fa-circle-dot"></i>';
                                } else {
                                    $estadoActual = 'programado';
                                    $textoEstado = 'Programado';
                                    $iconoEstado = '<i class="fa-regular fa-clock"></i>';
                                }
                            }
                        } elseif($row['estado'] == 'Pendiente') {
                            $estadoActual = 'pendiente';
                            $textoEstado = 'Pendiente';
                            $iconoEstado = '<i class="fa-solid fa-hourglass-half"></i>';
                        } elseif($row['estado'] == 'Rechazado') {
                            $estadoActual = 'rechazado';
                            $textoEstado = 'Rechazado';
                            $iconoEstado = '<i class="fa-solid fa-ban"></i>';
                        }
                    ?>
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
                            <span class="estado-badge estado-<?= $estadoActual ?>">
                                <?= $iconoEstado ?> <?= $textoEstado ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                        <td>
                            <?php if($row['novedades']): ?>
                                <button onclick="mostrarModal(this)" class="btn-ver-novedades">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </button>
                                <div class="novedades-modal" style="display:none;">
                                    <div class="modal-header">
                                        <strong>Novedades reportadas por:</strong>
                                        <span class="instructor-name"><?= htmlspecialchars($row['nombre_instructor']) ?></span>
                                    </div>
                                    <div class="modal-content">
                                        <pre><?= htmlspecialchars($row['novedades']) ?></pre>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="color:#999;">Sin novedades</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
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

<!-- OVERLAY -->
<div class="novedades-overlay" id="modalOverlay" onclick="cerrarTodosModales()"></div>

<script>
function mostrarModal(btn) {
    cerrarTodosModales();
    
    const modal = btn.nextElementSibling;
    const overlay = document.getElementById('modalOverlay');
    
    overlay.style.display = 'block';
    modal.style.display = 'block';
    
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar';
    btn.dataset.abierto = 'true';
}

function cerrarTodosModales() {
    const overlay = document.getElementById('modalOverlay');
    const modales = document.querySelectorAll('.novedades-modal');
    const botones = document.querySelectorAll('.btn-ver-novedades');
    
    overlay.style.display = 'none';
    modales.forEach(m => m.style.display = 'none');
    botones.forEach(b => {
        b.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
        delete b.dataset.abierto;
    });
}

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') cerrarTodosModales();
});
</script>

</body>
</html>