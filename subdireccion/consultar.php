<?php
include("../includes/conexion.php");
session_start();
if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   HISTORIAL DE AUTORIZACIONES
   ========================= */
$sql = "SELECT 
            am.nombre_ambiente,
            i.nombre_completo,
            au.fecha,
            au.hora_inicio,
            au.hora_fin,
            am.estado,
            au.rol_autorizado
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
    $sqlAmb = "SELECT * FROM ambientes WHERE nombre_ambiente = '$ambienteBuscado'";
    $resAmb = mysqli_query($conexion, $sqlAmb);
    $ambienteInfo = mysqli_fetch_assoc($resAmb);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Autorizaciones</title>
    <link rel="stylesheet" href="../css/subdire.css">
    <style>
                /* Estilos adicionales para consultar.php */
                .consultar-container {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 40px;
                    animation: fadeInUp 0.6s ease-out;
                }

                .page-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px 40px;
                    border-radius: 16px;
                    margin-bottom: 30px;
                    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
                    animation: slideDown 0.6s ease-out;
                }

                .page-header h2 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: 700;
                }

                .page-header p {
                    margin: 8px 0 0 0;
                    opacity: 0.9;
                    font-size: 14px;
                }

                .search-section {
                    background: white;
                    padding: 30px;
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                    margin-bottom: 30px;
                    animation: fadeInUp 0.6s ease-out 0.1s both;
                }

                .search-section h3 {
                    margin: 0 0 20px 0;
                    color: #333;
                    font-size: 20px;
                    font-weight: 600;
                }

                .search-form {
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                }

                .search-form input {
                    flex: 1;
                    min-width: 250px;
                    padding: 14px 20px;
                    border: 2px solid #e0e0e0;
                    border-radius: 10px;
                    font-size: 15px;
                    transition: all 0.3s ease;
                }

                .search-form input:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }

                .search-form button {
                    padding: 14px 32px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 10px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .search-form button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
                }

                .table-container {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                    overflow: hidden;
                    margin-bottom: 30px;
                    animation: fadeInUp 0.6s ease-out 0.2s both;
                }

                .table-header {
                    background: linear-gradient(135deg, #f5f7fa 0%, #e8ebf0 100%);
                    padding: 20px 30px;
                    border-bottom: 2px solid #e0e0e0;
                }

                .table-header h3 {
                    margin: 0;
                    color: #333;
                    font-size: 18px;
                    font-weight: 600;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                th {
                    background: #667eea;
                    color: white;
                    padding: 16px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                td {
                    padding: 16px;
                    border-bottom: 1px solid #f0f0f0;
                    color: #555;
                    font-size: 14px;
                }

                tr {
                    transition: background-color 0.2s ease;
                }

                tbody tr:hover {
                    background-color: #f8f9ff;
                }

                .estado-badge {
                    display: inline-block;
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: capitalize;
                }

                .estado-disponible {
                    background: #e8f5e9;
                    color: #2e7d32;
                }

                .estado-ocupado {
                    background: #fff3e0;
                    color: #f57c00;
                }

                .estado-mantenimiento {
                    background: #ffebee;
                    color: #c62828;
                }

                .ambiente-result {
                    background: white;
                    padding: 30px;
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                    margin-bottom: 30px;
                    animation: fadeInUp 0.4s ease-out;
                }

                .btn-permiso {
                    display: inline-block;
                    padding: 10px 24px;
                    background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 13px;
                    transition: all 0.3s ease;
                }

                .btn-permiso:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(67, 160, 71, 0.4);
                }

                .btn-volver {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 28px;
                    background: white;
                    color: #667eea;
                    text-decoration: none;
                    border-radius: 10px;
                    font-weight: 600;
                    border: 2px solid #667eea;
                    transition: all 0.3s ease;
                    animation: fadeInUp 0.6s ease-out 0.3s both;
                }

                .btn-volver:hover {
                    background: #667eea;
                    color: white;
                    transform: translateX(-4px);
                }

                .no-results {
                    text-align: center;
                    padding: 40px;
                    color: #999;
                    font-style: italic;
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }

                .info-item {
                    background: #f8f9ff;
                    padding: 15px;
                    border-radius: 10px;
                    border-left: 4px solid #667eea;
                }

                .info-item label {
                    display: block;
                    font-size: 12px;
                    color: #666;
                    text-transform: uppercase;
                    font-weight: 600;
                    margin-bottom: 5px;
                }

                .info-item span {
                    font-size: 16px;
                    color: #333;
                    font-weight: 600;
                }

                @media (max-width: 768px) {
                    .consultar-container {
                        padding: 20px;
                    }

                    .page-header {
                        padding: 20px;
                    }

                    .search-form {
                        flex-direction: column;
                    }

                    .search-form input {
                        min-width: 100%;
                    }

                    table {
                        font-size: 12px;
                    }

                    th, td {
                        padding: 12px 8px;
                    }
                }
                /* ================= RESPONSIVE CONSULTAR ================= */

            @media (max-width: 1024px) {
                .consultar-container {
                    padding: 25px;
                }
                
                table {
                    font-size: 13px;
                }
            }

            @media (max-width: 768px) {
                .consultar-container {
                    padding: 15px;
                }
                
                .page-header {
                    padding: 20px;
                    border-radius: 12px;
                }
                
                .page-header h2 {
                    font-size: 22px;
                }
                
                .search-section {
                    padding: 20px;
                }
                
                .search-section h3 {
                    font-size: 18px;
                }
                
                .search-form {
                    flex-direction: column;
                }
                
                .search-form input {
                    min-width: 100%;
                }
                
                .search-form button {
                    width: 100%;
                }
                
                .table-container {
                    overflow-x: auto;
                }
                
                table {
                    font-size: 12px;
                    min-width: 600px;
                }
                
                th, td {
                    padding: 12px 10px;
                    white-space: nowrap;
                }
                
                .info-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 480px) {
                .consultar-container {
                    padding: 12px;
                }
                
                .page-header {
                    padding: 15px;
                }
                
                .page-header h2 {
                    font-size: 20px;
                }
                
                .page-header p {
                    font-size: 12px;
                }
                
                .search-section {
                    padding: 15px;
                }
                
                .search-section h3 {
                    font-size: 16px;
                    margin-bottom: 15px;
                }
                
                .search-form input {
                    padding: 12px 16px;
                    font-size: 14px;
                }
                
                .search-form button {
                    padding: 12px 24px;
                    font-size: 14px;
                }
                
                .table-header {
                    padding: 15px 20px;
                }
                
                .table-header h3 {
                    font-size: 16px;
                }
                
                table {
                    font-size: 11px;
                }
                
                th, td {
                    padding: 10px 8px;
                }
                
                .estado-badge {
                    padding: 4px 10px;
                    font-size: 10px;
                }
                
                .ambiente-result {
                    padding: 20px;
                }
                
                .info-item {
                    padding: 12px;
                }
                
                .info-item label {
                    font-size: 11px;
                }
                
                .info-item span {
                    font-size: 14px;
                }
                
                .btn-permiso {
                    padding: 8px 20px;
                    font-size: 12px;
                    display: block;
                    text-align: center;
                }
                
                .btn-volver {
                    padding: 10px 20px;
                    font-size: 14px;
                    width: 100%;
                    justify-content: center;
                }
        }
    </style>
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/logo.png" alt="Logo Institución">
        <div class="header-title">
            <h1>Consultar Autorizaciones</h1>
            <span>Historial y disponibilidad de ambientes</span>
        </div>
    </div>
    <div class="header-user">
        Subdirección
    </div>
</div>

<div class="consultar-container">

    <!-- ========================= BUSCAR AMBIENTE ========================= -->
    <div class="search-section">
        <h3>🔍 Buscar Ambiente Específico</h3>
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
            <h3> Historial de Autorizaciones</h3>
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
                        <th>Estado</th>
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
                        <td>
                            <span class="estado-badge estado-<?= $row['estado'] ?>">
                                <?= htmlspecialchars($row['estado']) ?>
                            </span>
                        </td>
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