<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'guarda') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

date_default_timezone_set("America/Bogota");

if(!isset($_GET['id'])){
    die("Ambiente no especificado");
}

$id_ambiente = intval($_GET['id']);
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

/* TRAER NOMBRE DEL AMBIENTE */
$resAmbiente = mysqli_query($conexion, "SELECT nombre_ambiente FROM ambientes WHERE id = '$id_ambiente'");
$dataAmbiente = mysqli_fetch_assoc($resAmbiente);
$nombre_ambiente = $dataAmbiente['nombre_ambiente'] ?? "Ambiente $id_ambiente";

/* CONSULTA */
$sql = "SELECT 
            au.*,
            i.nombre AS nombre_instructor
        FROM autorizaciones_ambientes au
        INNER JOIN instructores i ON au.id_instructor = i.id
        WHERE au.id_ambiente = '$id_ambiente'
        AND au.fecha_inicio <= '$fecha_actual'
        AND au.fecha_fin >= '$fecha_actual'
        AND au.estado = 'Aprobado'
        ORDER BY au.hora_inicio ASC";

$resultado = mysqli_query($conexion, $sql);
$total = mysqli_num_rows($resultado);

/* SI ES AJAX → DEVOLVER JSON */
if(isset($_GET['ajax'])){
    $filas = [];
    while($f = mysqli_fetch_assoc($resultado)){
        $activa = ($hora_actual >= $f['hora_inicio'] && $hora_actual <= $f['hora_final']);
        $filas[] = [
            'instructor'    => htmlspecialchars($f['nombre_instructor']),
            'hora_inicio'   => date('h:i A', strtotime($f['hora_inicio'])),
            'hora_fin'      => date('h:i A', strtotime($f['hora_final'])),
            'rol'           => htmlspecialchars($f['rol_autorizado']),
            'observacion'   => htmlspecialchars($f['observaciones'] ?: '—'),
            'novedades'     => htmlspecialchars($f['novedades'] ?: ''),
            'activa'        => $activa,
        ];
    }
    header('Content-Type: application/json');
    echo json_encode([
        'total'  => $total,
        'hora'   => date('h:i:s A'),
        'filas'  => $filas,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación - <?= htmlspecialchars($nombre_ambiente) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }

        /* ===== HEADER ===== */
        .header {
            background: linear-gradient(135deg, #172f63 0%, #355d91 100%);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .header-left img {
            width: 50px; height: 50px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }
        .header-title h1 { color: white; font-size: clamp(18px, 4vw, 24px); font-weight: 700; margin: 0; }
        .header-title span { color: rgba(255,255,255,0.85); font-size: 13px; }
        .header-badge {
            background: rgba(255,255,255,0.2);
            color: white; padding: 8px 18px;
            border-radius: 25px; font-weight: 600; font-size: 13px;
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }

        /* ===== CONTENEDOR ===== */
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        /* ===== AMBIENTE CARD ===== */
        .ambiente-card {
            background: white; border-radius: 16px;
            padding: 25px 30px; margin-bottom: 25px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: flex; align-items: center;
            justify-content: space-between;
            flex-wrap: wrap; gap: 15px;
            border-left: 6px solid #172f63;
        }
        .ambiente-info h2 { font-size: clamp(20px, 5vw, 28px); color: #172f63; font-weight: 800; margin: 0; }
        .ambiente-info p { color: #666; font-size: 14px; margin-top: 5px; }
        .stat-pill {
            background: #f0f4ff; padding: 10px 20px;
            border-radius: 12px; text-align: center;
            border: 2px solid #e0e7ff;
        }
        .stat-pill .num { font-size: 24px; font-weight: 800; color: #172f63; }
        .stat-pill .lbl { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }

        /* ===== TABLA ===== */
        .table-container {
            background: white; 
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
            transition: opacity 0.3s ease;
        }
        .table-container.cargando { opacity: 0.5; }
        
        .table-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 18px 25px;
            border-bottom: 2px solid #e0e0e0;
            display: flex; 
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 { color: #333; font-size: 16px; font-weight: 700; margin: 0; }
        .ultima-actualizacion { font-size: 11px; color: #999; }

        /* SCROLL WRAPPER - CRÍTICO PARA RESPONSIVE */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table { 
            width: 100%; 
            min-width: 800px; /* Ancho mínimo para no romper columnas */
            border-collapse: collapse; 
        }
        
        th {
            background: #172f63; 
            color: white;
            padding: 14px 16px; 
            text-align: left;
            font-size: 12px; 
            text-transform: uppercase;
            letter-spacing: 0.5px; 
            font-weight: 600;
            white-space: nowrap;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #444; 
            font-size: 14px;
        }
        
        tbody tr { transition: background-color 0.2s ease; }
        tbody tr:hover { background: #f8f9ff; }
        tbody tr.activa-ahora { background: #e8f5e9; border-left: 4px solid #43a047; }
        tbody tr.activa-ahora td { color: #2e7d32; font-weight: 600; }

        /* ===== BADGES ===== */
        .badge-rol {
            display: inline-block; 
            padding: 5px 14px;
            border-radius: 20px; 
            font-size: 12px;
            font-weight: 700; 
            text-transform: capitalize;
            white-space: nowrap;
        }
        .badge-administracion { background: #e3f2fd; color: #1565c0; }
        .badge-subdireccion { background: #e8f5e9; color: #2e7d32; }

        .badge-activo {
            background: #43a047; 
            color: white;
            padding: 4px 10px; 
            border-radius: 12px;
            font-size: 11px; 
            font-weight: 700;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
            white-space: nowrap;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        /* BOTÓN VER NOVEDADES */
        .btn-ver-nov {
            background: #fb8c00;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .btn-ver-nov:hover { background: #f57c00; }

        /* ===== SIN RESULTADOS ===== */
        .no-results { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999; 
        }
        .no-results i { 
            font-size: 4rem; 
            margin-bottom: 15px; 
            color: #ddd; 
            display: block; 
        }
        .no-results p { font-size: 18px; font-weight: 600; }
        .no-results small { font-size: 13px; margin-top: 8px; display: block; }
        /* ==================== FOOTER ==================== */
        .footer {
            background: linear-gradient(135deg, #2c5282 0%, #2d3e63 100%);
            color: white;
            padding: 28px 30px;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: center;
            gap: 16px;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .footer-logo {
            width: 38px;
            height: 38px;
            object-fit: contain;
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
        }

        .footer-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }

        .footer-sub {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            margin: 3px 0 0 0;
        }

        .footer-center {
            text-align: center;
            font-size: 13px;
            color: rgba(255,255,255,0.85);
        }

        .footer-center p {
            margin: 3px 0;
        }

        .footer-year {
            font-size: 11px;
            color: rgba(255,255,255,0.55);
            margin-top: 4px !important;
        }

        .footer-right {
            text-align: right;
            font-size: 12px;
            color: rgba(255,255,255,0.75);
        }

        .footer-right p {
            margin: 2px 0;
        }

        .footer-right strong {
            color: white;
            font-weight: 700;
        }
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 0 15px; margin: 20px auto; }
            .ambiente-card { flex-direction: column; align-items: flex-start; }
            
            /* Compensar padding en móviles */
            .table-scroll-wrapper {
                margin: 0 -20px;
                padding: 0 20px;
            }
        }
        
        @media (max-width: 480px) {
            .header { 
                padding: 15px; 
                flex-direction: column; 
                text-align: center; 
            }
            .header-left {
                flex-direction: column;
                gap: 10px;
            }
            th, td { 
                padding: 12px 8px; 
                font-size: 12px; 
            }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA">
        <div class="header-title">
            <h1>Verificación de Ambiente</h1>
            <span>Sistema de Control de Acceso</span>
        </div>
    </div>
    <div class="header-badge">
        <i class="fa-solid fa-shield-halved"></i> Guarda de Seguridad
    </div>
</div>

<div class="container">

    <!-- INFO DEL AMBIENTE -->
    <div class="ambiente-card">
        <div class="ambiente-info">
            <h2>
                <i class="fa-solid fa-door-open" style="color:#355d91; margin-right:10px;"></i>
                <?= htmlspecialchars($nombre_ambiente) ?>
            </h2>
            <p>
                <i class="fa-regular fa-calendar"></i> <?= date('d/m/Y') ?>
                &nbsp;|&nbsp;
                <i class="fa-regular fa-clock"></i> Consulta en tiempo real
            </p>
        </div>
        <div class="ambiente-stats">
            <div class="stat-pill">
                <div class="num" id="total-autorizaciones"><?= $total ?></div>
                <div class="lbl">Autorizaciones hoy</div>
            </div>
        </div>
    </div>

    <!-- TABLA DE AUTORIZACIONES -->
    <div class="table-container" id="tabla-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-clipboard-list" style="color:#355d91; margin-right:8px;"></i>
                Autorizaciones del día
            </h3>
            <span class="ultima-actualizacion" id="ultima-act">
                Última actualización: <?= date('h:i:s A') ?>
            </span>
        </div>
        
        <div id="tabla-body">
            <?php if($total > 0): ?>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Autorizado Por</th>
                            <th>Observación</th>
                            <th>Novedades</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($resultado, 0);
                        while($fila = mysqli_fetch_assoc($resultado)):
                            $activa = ($hora_actual >= $fila['hora_inicio'] && $hora_actual <= $fila['hora_final']);
                        ?>
                        <tr class="<?= $activa ? 'activa-ahora' : '' ?>">
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91; margin-right:6px;"></i>
                                <?= htmlspecialchars($fila['nombre_instructor']) ?>
                                <?php if($activa): ?>
                                    <span class="badge-activo">EN CURSO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fa-regular fa-clock" style="margin-right:4px; color:#666;"></i>
                                <?= date('h:i A', strtotime($fila['hora_inicio'])) ?>
                            </td>
                            <td>
                                <i class="fa-regular fa-clock" style="margin-right:4px; color:#666;"></i>
                                <?= date('h:i A', strtotime($fila['hora_final'])) ?>
                            </td>
                            <td>
                                <span class="badge-rol badge-<?= $fila['rol_autorizado'] ?>">
                                    <?= htmlspecialchars($fila['rol_autorizado']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($fila['observaciones'] ?: '—') ?></td>
                            <td>
                                <?php if($fila['novedades']): ?>
                                    <button onclick="alert('<?= htmlspecialchars(str_replace(["\r", "\n", "'"], [' ', ' ', "\\'"], $fila['novedades'])) ?>')" class="btn-ver-nov">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-results">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>No hay autorizaciones para hoy</p>
                <small>Este ambiente no tiene permisos registrados para el día de hoy</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
   

</div>

<script>
    /* AUTO-ACTUALIZACIÓN CADA 30 SEGUNDOS 
     */
    let segundosRestantes = 30;

    function actualizarDatos() {
        const tabla = document.getElementById('tabla-container');
        tabla.classList.add('cargando');

        const url = `?id=<?= $id_ambiente ?>&ajax=1`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                document.getElementById('total-autorizaciones').textContent = data.total;
                document.getElementById('ultima-act').textContent =
                    'Última actualización: ' + data.hora;

                let html = '';
                if(data.filas.length > 0){
                    html += `<div class="table-scroll-wrapper"><table>
                        <thead><tr>
                            <th>Instructor</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Autorizado Por</th>
                            <th>Observación</th>
                            <th>Novedades</th>
                        </tr></thead><tbody>`;

                    data.filas.forEach(f => {
                        const claseActiva = f.activa ? 'activa-ahora' : '';
                        const badgeActivo = f.activa
                            ? '<span class="badge-activo">EN CURSO</span>' : '';

                        const btnNov = f.novedades 
                            ? `<button onclick="alert('${f.novedades.replace(/'/g, "\\'")}')" class="btn-ver-nov"><i class="fa-solid fa-eye"></i> Ver</button>`
                            : '<span style="color:#999;">—</span>';

                        html += `<tr class="${claseActiva}">
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91; margin-right:6px;"></i>
                                ${f.instructor} ${badgeActivo}
                            </td>
                            <td>
                                <i class="fa-regular fa-clock" style="margin-right:4px; color:#666;"></i>
                                ${f.hora_inicio}
                            </td>
                            <td>
                                <i class="fa-regular fa-clock" style="margin-right:4px; color:#666;"></i>
                                ${f.hora_fin}
                            </td>
                            <td>
                                <span class="badge-rol badge-${f.rol}">
                                    ${f.rol}
                                </span>
                            </td>
                            <td>${f.observacion}</td>
                            <td>${btnNov}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = `<div class="no-results">
                        <i class="fa-solid fa-calendar-xmark"></i>
                        <p>No hay autorizaciones para hoy</p>
                        <small>Este ambiente no tiene permisos registrados para el día de hoy</small>
                    </div>`;
                }

                document.getElementById('tabla-body').innerHTML = html;
                tabla.classList.remove('cargando');
                segundosRestantes = 30;
            })
            .catch(() => {
                tabla.classList.remove('cargando');
                segundosRestantes = 30;
            });
    }

    /* COUNTDOWN Y AUTO-REFRESH */
    setInterval(() => {
        segundosRestantes--;
        if(segundosRestantes <= 0){
            actualizarDatos();
        }
    }, 1000);
</script>

</body>
</html>