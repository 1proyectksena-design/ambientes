<?php
include("../includes/conexion.php");

date_default_timezone_set("America/Bogota");

if(!isset($_GET['id'])){
    die("Ambiente no especificado");
}

$id_ambiente = intval($_GET['id']);
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

/* TRAER NOMBRE DEL AMBIENTE */
$resAmbiente = mysqli_query($conexion, "SELECT nombre_ambiente FROM ambientes WHERE id_ambiente = '$id_ambiente'");
$dataAmbiente = mysqli_fetch_assoc($resAmbiente);
$nombre_ambiente = $dataAmbiente['nombre_ambiente'] ?? "Ambiente $id_ambiente";

$sql = "SELECT a.*, i.nombre_completo AS nombre_instructor
        FROM autorizaciones_ambientes a
        INNER JOIN instructores i 
            ON a.id_instructor = i.id_instructor
        WHERE a.id_ambiente = '$id_ambiente'
        AND a.fecha = '$fecha_actual'
        ORDER BY a.hora_inicio ASC";

$resultado = mysqli_query($conexion, $sql);
$total = mysqli_num_rows($resultado);

/* ============================================================
   SI LA PETICIÓN ES AJAX → DEVOLVER SOLO EL JSON
   ============================================================ */
if(isset($_GET['ajax'])){
    $filas = [];
    while($f = mysqli_fetch_assoc($resultado)){
        $activa = ($hora_actual >= $f['hora_inicio'] && $hora_actual <= $f['hora_fin']);
        $filas[] = [
            'instructor'    => htmlspecialchars($f['nombre_instructor']),
            'hora_inicio'   => date('h:i A', strtotime($f['hora_inicio'])),
            'hora_fin'      => date('h:i A', strtotime($f['hora_fin'])),
            'rol'           => htmlspecialchars($f['rol_autorizado']),
            'observacion'   => htmlspecialchars($f['observacion'] ?: '—'),
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
        .header-title h1 { color: white; font-size: clamp(18px, 4vw, 24px); font-weight: 700; }
        .header-title span { color: rgba(255,255,255,0.85); font-size: 13px; }
        .header-badge {
            background: rgba(255,255,255,0.2);
            color: white; padding: 8px 18px;
            border-radius: 25px; font-weight: 600; font-size: 13px;
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }

        /* ===== BARRA DE ACTUALIZACIÓN ===== */
        .refresh-bar {
            background: #1a3a6b;
            color: rgba(255,255,255,0.85);
            text-align: center;
            padding: 8px 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .refresh-bar .countdown {
            background: rgba(255,255,255,0.2);
            padding: 2px 10px;
            border-radius: 10px;
            font-weight: 700;
            color: white;
        }
        .spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }
        .spinner.activo { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== CONTENEDOR ===== */
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }

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
        .ambiente-info h2 { font-size: clamp(20px, 5vw, 28px); color: #172f63; font-weight: 800; }
        .ambiente-info p { color: #666; font-size: 14px; margin-top: 5px; }
        .stat-pill {
            background: #f0f4ff; padding: 10px 20px;
            border-radius: 12px; text-align: center;
            border: 2px solid #e0e7ff;
        }
        .stat-pill .num { font-size: 24px; font-weight: 800; color: #172f63; }
        .stat-pill .lbl { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; }

        /* ===== HORA ACTUAL ===== */
        .hora-actual {
            background: linear-gradient(135deg, #172f63, #355d91);
            color: white; border-radius: 12px;
            padding: 15px 25px; text-align: center;
            margin-bottom: 25px; font-size: 13px;
            letter-spacing: 0.5px;
        }
        .hora-actual strong { font-size: 26px; display: block; margin-top: 4px; }

        /* ===== TABLA ===== */
        .table-container {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden; margin-bottom: 25px;
            transition: opacity 0.3s ease;
        }
        .table-container.cargando { opacity: 0.5; }
        .table-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 18px 25px;
            border-bottom: 2px solid #e0e0e0;
            display: flex; align-items: center;
            justify-content: space-between;
        }
        .table-header h3 { color: #333; font-size: 16px; font-weight: 700; }
        .ultima-actualizacion { font-size: 11px; color: #999; }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #172f63; color: white;
            padding: 14px 16px; text-align: left;
            font-size: 12px; text-transform: uppercase;
            letter-spacing: 0.5px; font-weight: 600;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #444; font-size: 14px;
        }
        tbody tr:hover { background: #f8f9ff; }
        tbody tr.activa-ahora { background: #e8f5e9; border-left: 4px solid #43a047; }
        tbody tr.activa-ahora td { color: #2e7d32; font-weight: 600; }

        /* ===== BADGES ===== */
        .badge-rol {
            display: inline-block; padding: 5px 14px;
            border-radius: 20px; font-size: 12px;
            font-weight: 700; text-transform: capitalize;
        }
        .badge-administracion { background: #e3f2fd; color: #1565c0; }
        .badge-subdireccion { background: #e8f5e9; color: #2e7d32; }

        .badge-activo {
            background: #43a047; color: white;
            padding: 4px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 700;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        /* ===== SIN RESULTADOS ===== */
        .no-results { text-align: center; padding: 60px 20px; color: #999; }
        .no-results i { font-size: 4rem; margin-bottom: 15px; color: #ddd; display: block; }
        .no-results p { font-size: 18px; font-weight: 600; }
        .no-results small { font-size: 13px; margin-top: 8px; display: block; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container { padding: 0 15px; margin: 20px auto; }
            .ambiente-card { flex-direction: column; align-items: flex-start; }
            .table-container { overflow-x: auto; }
            table { min-width: 550px; }
            th, td { padding: 12px 10px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .header { padding: 15px; flex-direction: column; text-align: center; }
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

<!-- BARRA DE ACTUALIZACIÓN AUTOMÁTICA -->
<div class="refresh-bar">
    <div class="spinner" id="spinner"></div>
    <i class="fa-solid fa-rotate"></i>
    Actualización automática en: <span class="countdown" id="countdown">30</span>s
    &nbsp;|&nbsp;
    <span id="estado-refresh">✅ Datos actualizados</span>
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

    <!-- HORA ACTUAL -->
    <div class="hora-actual">
        <span> Hora actual (Bogotá)</span>
        <strong id="reloj"><?= date('h:i:s A') ?></strong>
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
            <table>
                <thead>
                    <tr>
                        <th>Instructor</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Autorizado Por</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    mysqli_data_seek($resultado, 0);
                    while($fila = mysqli_fetch_assoc($resultado)):
                        $activa = ($hora_actual >= $fila['hora_inicio'] && $hora_actual <= $fila['hora_fin']);
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
                            <?= date('h:i A', strtotime($fila['hora_fin'])) ?>
                        </td>
                        <td>
                            <span class="badge-rol badge-<?= $fila['rol_autorizado'] ?>">
                                <?= htmlspecialchars($fila['rol_autorizado']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($fila['observacion'] ?: '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
    /* ============================================================
       RELOJ EN TIEMPO REAL
       ============================================================ */
    function actualizarReloj() {
        const ahora = new Date();
        const h = ahora.getHours();
        const m = String(ahora.getMinutes()).padStart(2, '0');
        const s = String(ahora.getSeconds()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        document.getElementById('reloj').textContent =
            `${String(h12).padStart(2, '0')}:${m}:${s} ${ampm}`;
    }
    setInterval(actualizarReloj, 1000);
    actualizarReloj();

    /* ============================================================
       COUNTDOWN + FETCH AUTOMÁTICO CADA 30 SEGUNDOS
       ============================================================ */
    let segundosRestantes = 30;

    function actualizarDatos() {
        const spinner  = document.getElementById('spinner');
        const estado   = document.getElementById('estado-refresh');
        const tabla    = document.getElementById('tabla-container');

        spinner.classList.add('activo');
        tabla.classList.add('cargando');
        estado.textContent = '🔄 Actualizando...';

        const url = `?id=<?= $id_ambiente ?>&ajax=1`;

        fetch(url)
            .then(r => r.json())
            .then(data => {

                /* Actualizar contador */
                document.getElementById('total-autorizaciones').textContent = data.total;
                document.getElementById('ultima-act').textContent =
                    'Última actualización: ' + data.hora;

                /* Reconstruir tabla */
                let html = '';
                if(data.filas.length > 0){
                    html += `<table>
                        <thead><tr>
                            <th>Instructor</th>
                            <th>Hora Inicio</th>
                            <th>Hora Fin</th>
                            <th>Autorizado Por</th>
                            <th>Observación</th>
                        </tr></thead><tbody>`;

                    data.filas.forEach(f => {
                        const claseActiva = f.activa ? 'activa-ahora' : '';
                        const badgeActivo = f.activa
                            ? '<span class="badge-activo">EN CURSO</span>' : '';

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
                        </tr>`;
                    });
                    html += '</tbody></table>';
                } else {
                    html = `<div class="no-results">
                        <i class="fa-solid fa-calendar-xmark"></i>
                        <p>No hay autorizaciones para hoy</p>
                        <small>Este ambiente no tiene permisos registrados para el día de hoy</small>
                    </div>`;
                }

                document.getElementById('tabla-body').innerHTML = html;
                spinner.classList.remove('activo');
                tabla.classList.remove('cargando');
                estado.textContent = '✅ Datos actualizados';
                segundosRestantes = 30;
            })
            .catch(() => {
                spinner.classList.remove('activo');
                tabla.classList.remove('cargando');
                estado.textContent = '❌ Error al actualizar';
                segundosRestantes = 30;
            });
    }

    /* Countdown visual */
    setInterval(() => {
        segundosRestantes--;
        document.getElementById('countdown').textContent = segundosRestantes;
        if(segundosRestantes <= 0){
            actualizarDatos();
        }
    }, 1000);
</script>

</body>
</html>