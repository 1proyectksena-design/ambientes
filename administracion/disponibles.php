<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

/* Ambientes DISPONIBLES = Habilitados SIN autorizaciones activas AHORA */
$sql = "SELECT 
            a.*,
            (SELECT COUNT(*) 
             FROM autorizaciones_ambientes au 
             WHERE au.id_ambiente = a.id 
             AND au.fecha_inicio <= '$hoy'
             AND au.fecha_fin >= '$hoy'
             AND au.hora_inicio <= '$hora_actual'
             AND au.hora_final >= '$hora_actual'
             AND au.estado = 'Aprobado'
            ) AS en_uso
        FROM ambientes a
        WHERE a.estado = 'Habilitado'
        HAVING en_uso = 0
        ORDER BY a.nombre_ambiente ASC";

$resultado = mysqli_query($conexion, $sql);
$total = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambientes Disponibles</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Ambientes Disponibles</h1>
            <span>Libres en este momento</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Administración
    </div>
</div>

<div class="consultar-container">

    

    <!-- TABLA -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-check-circle" style="color:#43a047;"></i> 
                <?= $total ?> ambientes disponibles ahora
            </h3>
        </div>
        
        <?php if($total > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Ambiente</th>
                    <th>Horario Fijo</th>
                    <th>Horario Disponible</th>
                    <th>Descripción</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($resultado)): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong>
                        <span class="badge-disponible">Libre</span>
                    </td>
                    <td><?= htmlspecialchars($row['horario_fijo'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($row['horario_disponible'] ?: '—') ?></td>
                    <td><?= htmlspecialchars(substr($row['descripcion_general'], 0, 40)) ?><?= strlen($row['descripcion_general']) > 40 ? '...' : '' ?></td>
                    <td>
                        <a href="permisos.php?id_ambiente=<?= $row['id'] ?>" class="btn-accion">
                            <i class="fa-solid fa-plus-circle"></i> Autorizar
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-results">
            <i class="fa-solid fa-circle-xmark"></i>
            <p>No hay ambientes disponibles en este momento</p>
            <small>Todos los ambientes habilitados están ocupados</small>
        </div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
.hora-actual-banner {
    background: linear-gradient(135deg, #43a047, #66bb6a);
    color: white;
    padding: 20px 30px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(67, 160, 71, 0.3);
}
.hora-actual-banner i { font-size: 1.5rem; margin-right: 10px; }
.hora-actual-banner strong { font-size: 2rem; display: block; margin: 8px 0; }
.hora-actual-banner span { font-size: 0.9rem; opacity: 0.9; }

.badge-disponible {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
}

.btn-accion {
    background: #43a047;
    color: white;
    padding: 6px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: 0.3s;
}
.btn-accion:hover { background: #2e7d32; transform: translateY(-2px); }
</style>


</body>
</html>