<?php
session_start();
if ($_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

$nombre_ambiente = $_POST['nombre_ambiente'] ?? null;
$ambiente_info = null;
$historial = null;

if($nombre_ambiente){
    $nombre_ambiente = mysqli_real_escape_string($conexion, $nombre_ambiente);
    
    /* Buscar ambiente */
    $sqlAmb = "SELECT * FROM ambientes WHERE nombre_ambiente LIKE '%$nombre_ambiente%' LIMIT 1";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambiente_info = mysqli_fetch_assoc($resAmb);
    
    /* Buscar historial */
    if($ambiente_info){
        $sqlHist = "SELECT 
                        au.*,
                        i.nombre AS nombre_instructor,
                        a.nombre_ambiente
                    FROM autorizaciones_ambientes au
                    JOIN instructores i ON au.id_instructor = i.id
                    JOIN ambientes a ON au.id_ambiente = a.id
                    WHERE au.id_ambiente = '".$ambiente_info['id']."'
                    ORDER BY au.fecha_inicio DESC, au.hora_inicio DESC
                    LIMIT 50";
        
        $historial = mysqli_query($conexion, $sqlHist);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ambiente</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Historial de Ambiente</h1>
            <span>Consulta de autorizaciones por ambiente</span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-chalkboard-user user-icon"></i> Instructor
    </div>
</div>

<div class="consultar-container">

    <!-- BUSCADOR POR AMBIENTE -->
    <div class="search-section">
        <h3><i class="fa-solid fa-building"></i> Buscar Ambiente</h3>
        <form method="POST" class="search-form">
            <input 
                type="text" 
                name="nombre_ambiente" 
                placeholder="Ej: 308, Laboratorio, Sala 101..." 
                value="<?= htmlspecialchars($nombre_ambiente ?? '') ?>" 
                required
            >
            <button type="submit">
                <i class="fa-solid fa-search"></i> Buscar
            </button>
        </form>
    </div>

    <!-- INFORMACIÓN DEL AMBIENTE -->
    <?php if($nombre_ambiente && $ambiente_info): ?>
        <div class="ambiente-result">
            <h3 style="margin: 0 0 20px 0; color: #333;">
                <i class="fa-solid fa-door-open" style="color:#355d91;"></i> 
                Información del Ambiente
            </h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nombre</label>
                    <span><?= htmlspecialchars($ambiente_info['nombre_ambiente']) ?></span>
                </div>
                <div class="info-item">
                    <label>Estado</label>
                    <span class="estado-badge estado-<?= strtolower($ambiente_info['estado']) ?>">
                        <?= htmlspecialchars($ambiente_info['estado']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Horario Fijo</label>
                    <span><?= htmlspecialchars($ambiente_info['horario_fijo'] ?: 'No definido') ?></span>
                </div>
                <div class="info-item">
                    <label>Horario Disponible</label>
                    <span><?= htmlspecialchars($ambiente_info['horario_disponible'] ?: 'No definido') ?></span>
                </div>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left"></i> 
                    Historial de "<?= htmlspecialchars($ambiente_info['nombre_ambiente']) ?>"
                </h3>
            </div>
            
            <?php if($historial && mysqli_num_rows($historial) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Instructor</th>
                        <th>Período</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th>Autorizado Por</th>
                        <th>Observaciones</th>
                        <th>Novedades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($historial)): ?>
                    <tr>
                        <td>
                            <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                            <?= htmlspecialchars($row['nombre_instructor']) ?>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?><br>
                            <small style="color:#999;">al <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?></small>
                        </td>
                        <td>
                            <?= date('h:i A', strtotime($row['hora_inicio'])) ?> -<br>
                            <?= date('h:i A', strtotime($row['hora_final'])) ?>
                        </td>
                        <td>
                            <span class="estado-badge estado-<?= strtolower($row['estado']) ?>">
                                <?= htmlspecialchars($row['estado']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                        <td><?= htmlspecialchars($row['observaciones'] ?: '—') ?></td>
                        <td>
                            <?php if($row['novedades']): ?>
                                <div class="novedades-cell">
                                    <button onclick="verNovedades(this)" class="btn-ver-novedades">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                    <div class="novedades-content" style="display:none;">
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
            <?php else: ?>
            <div class="no-results">
                <i class="fa-solid fa-inbox"></i>
                <p>No hay historial para este ambiente</p>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif($nombre_ambiente && !$ambiente_info): ?>
        <div class="ambiente-result">
            <div class="no-results">
                <i class="fa-solid fa-building-slash"></i>
                <p>No se encontró el ambiente "<?= htmlspecialchars($nombre_ambiente) ?>"</p>
                <small>Intenta con otro nombre o revisa la escritura</small>
            </div>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>

<style>
.novedades-cell {
    position: relative;
}

.btn-ver-novedades {
    background: #667eea;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-ver-novedades:hover {
    background: #5568d3;
}

.novedades-content {
    background: #fff3e0;
    border: 2px solid #fb8c00;
    border-radius: 8px;
    padding: 12px;
    margin-top: 8px;
    max-width: 400px;
}

.novedades-content pre {
    margin: 0;
    white-space: pre-wrap;
    font-family: inherit;
    font-size: 0.9rem;
    color: #333;
}
</style>

<script>
function verNovedades(btn) {
    const content = btn.nextElementSibling;
    if(content.style.display === 'none'){
        content.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Ocultar';
    } else {
        content.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Ver';
    }
}
</script>

</body>
</html>