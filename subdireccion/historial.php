<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* =========================
   FILTROS
   ========================= */
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_anio = $_GET['anio'] ?? date('Y');

$whereMain = [];
$whereMain[] = "MONTH(au.fecha_inicio) = '$filtro_mes'";
$whereMain[] = "YEAR(au.fecha_inicio) = '$filtro_anio'";
$whereSQLMain = implode(' AND ', $whereMain);

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

/* Fecha y hora actual para calcular estados */
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
                        /* CALCULAR ESTADO ACTUAL */
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
                                    $textoEstado = 'Programado (' . date('h:i A', strtotime($row['hora_inicio'])) . ' - ' . date('h:i A', strtotime($row['hora_final'])) . ')';
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
                        <td style="position: relative;">
                            <?php if($row['novedades']): ?>
                                <button onclick="verNovedades(this)" class="btn-ver-novedades">
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

.estado-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.estado-badge i { font-size: 10px; }

.estado-badge.estado-ocupado-ahora {
    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(67, 160, 71, 0.3);
    animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
    0%, 100% { box-shadow: 0 2px 8px rgba(67, 160, 71, 0.3); }
    50%       { box-shadow: 0 4px 16px rgba(67, 160, 71, 0.5); }
}

.estado-badge.estado-programado {
    background: #fff3e0;
    color: #e65100;
    border: 2px solid #fb8c00;
}

.estado-badge.estado-desocupado {
    background: #f5f5f5;
    color: #757575;
    border: 2px solid #e0e0e0;
}

.estado-badge.estado-pendiente {
    background: #fff3e0;
    color: #f57c00;
    border: 2px solid #ffa726;
}

.estado-badge.estado-rechazado {
    background: #ffebee;
    color: #c62828;
    border: 2px solid #e53935;
}

.btn-ver-novedades {
    background: #fb8c00;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
}

.btn-ver-novedades:hover { background: #f57c00; }

.novedades-modal {
    position: absolute;
    top: 40px;
    right: 0;
    background: white;
    border: 2px solid #fb8c00;
    border-radius: 12px;
    padding: 0;
    min-width: 350px;
    max-width: 450px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 100;
}

.modal-header {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    padding: 12px 15px;
    border-bottom: 2px solid #fb8c00;
    border-radius: 10px 10px 0 0;
}

.modal-header strong {
    display: block;
    color: #e65100;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.instructor-name {
    color: #333;
    font-weight: 600;
    font-size: 1.05rem;
}

.modal-content {
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.modal-content pre {
    margin: 0;
    white-space: pre-wrap;
    font-family: inherit;
    font-size: 0.9rem;
    color: #333;
    line-height: 1.6;
}
</style>

<script>
function verNovedades(btn) {
    const modal = btn.nextElementSibling;
    const allModals = document.querySelectorAll('.novedades-modal');

    allModals.forEach(m => {
        if(m !== modal) m.style.display = 'none';
    });

    if(modal.style.display === 'none') {
        modal.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar';
    } else {
        modal.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
    }
}

document.addEventListener('click', function(e) {
    if(!e.target.closest('td')) {
        document.querySelectorAll('.novedades-modal').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.btn-ver-novedades').forEach(b => {
            b.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
        });
    }
});
</script>

</body>
</html>