<?php
session_start();
include("../includes/conexion.php");

/* ── Seguridad ── */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

/* ── Solicitudes pendientes ── */
$resPendientes = mysqli_query($conexion, "
    SELECT COUNT(*) 
    FROM autorizaciones_ambientes 
    WHERE estado = 'Pendiente'
");
$solicitudes_pendientes = mysqli_fetch_row($resPendientes)[0];

/* ── Búsqueda por número de ficha ── */
$busqueda         = trim($_GET['buscar'] ?? '');
$ficha_encontrada = null;
$programacion     = [];

if ($busqueda !== '') {
    $busqueda_esc = mysqli_real_escape_string($conexion, $busqueda);

    /* Obtener ficha */
    $sql_ficha = "
        SELECT * 
        FROM fichas
        WHERE numero_ficha LIKE '%$busqueda_esc%'
        LIMIT 1
    ";

    $res_ficha        = mysqli_query($conexion, $sql_ficha);
    $ficha_encontrada = $res_ficha ? mysqli_fetch_assoc($res_ficha) : null;

    /* Si existe la ficha, traer programación */
    if ($ficha_encontrada) {
        $id_ficha = (int)$ficha_encontrada['id'];

        $sql_prog = "
            SELECT
                MIN(au.fecha_inicio) AS fecha_inicio,
                MAX(au.fecha_fin)    AS fecha_fin,
                au.hora_inicio,
                au.hora_final,
                au.estado,
                a.nombre_ambiente,
                i.nombre AS nombre_instructor,
                GROUP_CONCAT(
                    DISTINCT DAYOFWEEK(au.fecha_inicio)
                    ORDER BY DAYOFWEEK(au.fecha_inicio)
                ) AS dias_semana
            FROM autorizaciones_ambientes au
            JOIN ambientes    a ON au.id_ambiente   = a.id
            JOIN instructores i ON au.id_instructor = i.id
            WHERE au.id_ficha = $id_ficha
            GROUP BY 
                au.id_ambiente, 
                au.id_instructor, 
                au.hora_inicio, 
                au.hora_final, 
                au.estado
            ORDER BY 
                MIN(au.fecha_inicio) ASC, 
                au.hora_inicio ASC
        ";

        $res_prog = mysqli_query($conexion, $sql_prog);

        if ($res_prog) {
            while ($row = mysqli_fetch_assoc($res_prog)) {
                $programacion[] = $row;
            }
        }
    }
}

/* ── Todas las fichas ── */
$sql_todas = "
    SELECT 
        f.*, 
        COUNT(au.id) AS total_usos
    FROM fichas f
    LEFT JOIN autorizaciones_ambientes au 
        ON au.id_ficha = f.id
    GROUP BY f.id
    ORDER BY f.numero_ficha ASC
";

$res_todas   = mysqli_query($conexion, $sql_todas);
$todas_fichas = [];

if ($res_todas) {
    while ($row = mysqli_fetch_assoc($res_todas)) {
        $todas_fichas[] = $row;
    }
}

/* ── Utilidades ── */
$abrevDias = [
    1 => 'Dom',
    2 => 'Lun',
    3 => 'Mar',
    4 => 'Mié',
    5 => 'Jue',
    6 => 'Vie',
    7 => 'Sáb'
];

/* ── URL exportar ── */
$export_url = 'exportar_fichas.php' . (
    $busqueda !== '' 
        ? '?buscar=' . urlencode($busqueda) 
        : ''
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación por Fichas</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ══════════════ VARIABLES (alineadas con admin.css) ══════════════ */
        :root {
            --primary:    #172f63;
            --primary-mid:#355d91;
            --primary-lt: #eef2f9;
            --primary-bd: #c5d3e8;
            --surface:    #ffffff;
            --bg:         #f5f7fa;
            --border:     #e0e0e0;
            --text:       #333333;
            --muted:      #666666;
            --success:    #2e7d32;
            --success-lt: #e8f5e9;
            --success-bd: #a5d6a7;
            --warning:    #e65100;
            --warning-lt: #fff3e0;
            --warning-bd: #ffcc80;
            --danger:     #c62828;
            --danger-lt:  #ffebee;
            --danger-bd:  #ef9a9a;
            --radius:     16px;
            --shadow-sm:  0 2px 8px rgba(0,0,0,0.06);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.10);
        }
        *, *::before, *::after { box-sizing: border-box; }

        /* ══════════════ LAYOUT ══════════════ */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══════════════ SEARCH CARD ══════════════ */
        .search-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 30px;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }
        .search-card h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-card h3 i { color: var(--primary-mid); }

        .search-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted); font-size: .88rem; pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 14px 14px 14px 40px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            background: var(--surface);
            color: var(--text);
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-mid);
            box-shadow: 0 0 0 3px rgba(53,93,145,.12);
        }

        .btn-buscar {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .2s, box-shadow .2s;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-buscar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23,47,99,.35);
        }

        .btn-limpiar {
            padding: 14px 20px;
            background: transparent;
            color: var(--muted);
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: background .2s, border-color .2s;
            font-family: inherit;
        }
        .btn-limpiar:hover {
            background: var(--bg);
            border-color: var(--primary-bd);
        }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 22px;
            background: linear-gradient(135deg, #1D6F42 0%, #155230 100%);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .25s, box-shadow .25s;
            box-shadow: 0 3px 10px rgba(29,111,66,.3);
            white-space: nowrap;
        }
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(29,111,66,.4);
        }

        /* ══════════════ FICHA INFO CARD ══════════════ */
        .ficha-info-card {
            background: var(--primary-lt);
            border: 1px solid var(--primary-bd);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            animation: fadeInUp 0.4s ease-out;
        }
        .ficha-info-card > i { font-size: 1.8rem; color: var(--primary); flex-shrink: 0; }
        .ficha-info-body { flex: 1; min-width: 0; }
        .ficha-info-num  { font-size: 1.15rem; font-weight: 700; color: var(--primary); }
        .ficha-info-meta { font-size: .88rem; color: var(--primary-mid); margin-top: 4px; }
        .ficha-stats { display: flex; gap: 8px; flex-wrap: wrap; }
        .ficha-stat {
            background: var(--surface);
            border: 1px solid var(--primary-bd);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: .82rem;
            color: var(--muted);
            white-space: nowrap;
        }
        .ficha-stat strong { color: var(--text); font-weight: 700; }

        /* ══════════════ ALERT ══════════════ */
        .alert-notfound {
            background: var(--warning-lt);
            border: 1px solid var(--warning-bd);
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--warning);
            font-size: .9rem;
            margin-bottom: 20px;
        }

        /* ══════════════ SECTION LABEL ══════════════ */
        .section-label {
            font-size: .8rem;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .section-label .badge {
            background: var(--primary-lt);
            color: var(--primary);
            border: 1px solid var(--primary-bd);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 700;
        }

        /* ══════════════ TABLE ══════════════ */
        .table-wrap {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            overflow-x: auto;
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease-out 0.2s both;
        }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }

        thead tr { background: var(--bg); border-bottom: 2px solid var(--border); }
        thead th {
            padding: 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: .05em;
            text-transform: uppercase;
            white-space: nowrap;
            background: var(--primary);
            color: #fff;
        }

        tbody tr { border-bottom: 1px solid #f0f0f0; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8f9ff; }
        tbody td { padding: 16px; font-size: 14px; color: var(--text); vertical-align: middle; }

        .dia-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-lt);
            color: var(--primary);
            border: 1px solid var(--primary-bd);
            border-radius: 12px;
            padding: 3px 8px;
            font-size: .75rem;
            font-weight: 700;
        }
        .hora-range { font-size: .85rem; color: var(--muted); white-space: nowrap; }

        .estado-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .estado-aprobado  { background: var(--success-lt); color: var(--success); }
        .estado-pendiente { background: var(--warning-lt); color: var(--warning); border: 2px solid var(--warning-bd); }
        .estado-rechazado { background: var(--danger-lt);  color: var(--danger);  border: 2px solid var(--danger-bd); }

        .section-divider { border: none; border-top: 2px solid var(--border); margin: 2.5rem 0; }

        /* ══════════════ FICHAS GRID ══════════════ */
        .fichas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
            gap: 16px;
            margin-bottom: 2rem;
        }
        .ficha-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: box-shadow .2s, border-color .2s, transform .2s;
            text-decoration: none;
            color: inherit;
        }
        .ficha-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-bd);
            transform: translateY(-3px);
        }
        .ficha-card__num {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ficha-card__programa {
            font-size: .88rem;
            color: var(--text);
            font-weight: 500;
            line-height: 1.4;
        }
        .ficha-card__meta {
            font-size: .8rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ficha-card__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 4px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ficha-card__usos {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: .78rem;
            color: var(--muted);
        }
        .ficha-card__usos strong { color: var(--text); }
        .ficha-card__btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary-lt);
            color: var(--primary);
            border-radius: 6px;
            padding: 4px 12px;
            font-size: .78rem;
            font-weight: 600;
            transition: background .15s;
        }
        .ficha-card:hover .ficha-card__btn { background: var(--primary-bd); }

        /* ══════════════ EMPTY STATE ══════════════ */
        .empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
        .empty-state i { font-size: 2.8rem; opacity: .25; margin-bottom: 14px; display: block; }
        .empty-state p { font-size: .9rem; }

        /* ══════════════ HEADER BTN VOLVER ══════════════ */
        .btn-volver-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 25px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
            white-space: nowrap;
            backdrop-filter: blur(10px);
        }
        .btn-volver-header:hover { background: rgba(255,255,255,0.25); }

        /* ══════════════ RESPONSIVE ══════════════ */
        @media (max-width: 1024px) {
            .dashboard-container { padding: 25px 20px; }
        }
        @media (max-width: 768px) {
            .dashboard-container { padding: 20px 15px; }
            .search-card { padding: 20px; }
            .search-row { flex-direction: column; align-items: stretch; }
            .search-input-wrap { min-width: 0; width: 100%; }
            .btn-buscar, .btn-limpiar, .btn-export { width: 100%; justify-content: center; }
            .ficha-info-card { flex-direction: column; gap: 12px; }
            .ficha-info-card > i { display: none; }
            .fichas-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
            .ficha-stats { flex-direction: column; gap: 6px; }
        }
        @media (max-width: 480px) {
            .dashboard-container { padding: 15px 12px; }
            .search-card { padding: 15px; }
            .search-card h3 { font-size: 16px; }
            .fichas-grid { grid-template-columns: 1fr; }
            .ficha-card__footer { flex-direction: column; align-items: flex-start; }
            thead th, tbody td { padding: 10px 8px; font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════ HEADER ═══════════════════════ -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Programación por Fichas</h1>
            <span>Consulta de ambientes e instructores por ficha</span>
        </div>
    </div>
    <div class="header-user">
        <a href="index.php" class="btn-volver-header">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
     
    </div>
</div>

<!-- ═══════════════════════ CONTENIDO ═══════════════════════ -->
<div class="dashboard-container">

    <!-- Buscador -->
    <div class="search-card">
        <h3><i class="fa-solid fa-magnifying-glass"></i> Buscar ficha por número</h3>
        <form method="GET" action="">
            <div class="search-row">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-hashtag"></i>
                    <input type="text" name="buscar" class="search-input"
                           placeholder="Ej: 2895621"
                           value="<?= htmlspecialchars($busqueda) ?>" autocomplete="off">
                </div>
                <button type="submit" class="btn-buscar">
                    <i class="fa-solid fa-search"></i> Buscar
                </button>
                <?php if ($busqueda !== ''): ?>
                    <a href="programacion_fichas.php" class="btn-limpiar">
                        <i class="fa-solid fa-xmark"></i> Limpiar
                    </a>
                    <a href="<?= htmlspecialchars($export_url) ?>" class="btn-export">
                        <i class="fa-solid fa-file-excel"></i> Exportar Excel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($busqueda !== ''): ?>

        <?php if ($ficha_encontrada): ?>

            <!-- Info de la ficha encontrada -->
            <div class="ficha-info-card">
                <i class="fa-solid fa-graduation-cap"></i>
                <div class="ficha-info-body">
                    <div class="ficha-info-num"><?= htmlspecialchars($ficha_encontrada['numero_ficha']) ?></div>
                    <div class="ficha-info-meta">
                        <?= htmlspecialchars($ficha_encontrada['programa'] ?? '') ?>
                        <?php if (!empty($ficha_encontrada['jornada'])): ?>
                            &nbsp;·&nbsp; <i class="fa-regular fa-clock" style="font-size:.8rem;"></i>
                            <?= htmlspecialchars($ficha_encontrada['jornada']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ficha-stats">
                    <div class="ficha-stat">
                        <i class="fa-regular fa-calendar" style="font-size:.75rem;margin-right:3px;"></i>
                        Inicio: <strong><?= date('d/m/Y', strtotime($ficha_encontrada['fecha_inicio'])) ?></strong>
                    </div>
                    <div class="ficha-stat">
                        <i class="fa-regular fa-calendar-check" style="font-size:.75rem;margin-right:3px;"></i>
                        Fin: <strong><?= date('d/m/Y', strtotime($ficha_encontrada['fecha_fin'])) ?></strong>
                    </div>
                    <div class="ficha-stat">
                        <i class="fa-solid fa-list-check" style="font-size:.75rem;margin-right:3px;"></i>
                        Usos: <strong><?= count($programacion) ?></strong>
                    </div>
                </div>
            </div>

            <?php if (count($programacion) > 0): ?>

                <div class="section-label">
                    <i class="fa-solid fa-list-check"></i>
                    Programación de ambientes
                    <span class="badge"><?= count($programacion) ?> registro(s)</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-door-open" style="margin-right:4px;"></i>Ambiente</th>
                                <th><i class="fa-solid fa-user" style="margin-right:4px;"></i>Instructor</th>
                                <th><i class="fa-regular fa-calendar" style="margin-right:4px;"></i>Fecha Inicio</th>
                                <th><i class="fa-regular fa-calendar-check" style="margin-right:4px;"></i>Fecha Fin</th>
                                <th><i class="fa-regular fa-clock" style="margin-right:4px;"></i>Horario</th>
                                <th>Días</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programacion as $row):
                                $diasNums = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                                            ? array_map('intval', explode(',', $row['dias_semana'])) : [];
                                $diasHtml = '';
                                if ($diasNums) {
                                    $diasHtml = '<div style="display:flex;flex-wrap:wrap;gap:3px;">';
                                    foreach ($diasNums as $dn) {
                                        $diasHtml .= '<span class="dia-badge">' . ($abrevDias[$dn] ?? '?') . '</span>';
                                    }
                                    $diasHtml .= '</div>';
                                } else {
                                    $diasHtml = '<span style="color:#94a3b8;">—</span>';
                                }
                                switch ($row['estado']) {
                                    case 'Aprobado':   $eClase = 'aprobado';  $eIcon = 'fa-circle-check'; break;
                                    case 'Rechazado':  $eClase = 'rechazado'; $eIcon = 'fa-ban'; break;
                                    default:           $eClase = 'pendiente'; $eIcon = 'fa-hourglass-half'; break;
                                }
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:.35rem;">
                                        <i class="fa-solid fa-user" style="color:var(--primary-mid);font-size:.8rem;"></i>
                                        <?= htmlspecialchars($row['nombre_instructor']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);">
                                        <i class="fa-regular fa-calendar" style="font-size:.8rem;"></i>
                                        <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);">
                                        <i class="fa-regular fa-calendar-check" style="font-size:.8rem;"></i>
                                        <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="hora-range">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= date('H:i', strtotime($row['hora_inicio'])) ?>
                                        &mdash;
                                        <?= date('H:i', strtotime($row['hora_final'])) ?>
                                    </span>
                                </td>
                                <td><?= $diasHtml ?></td>
                                <td>
                                    <span class="estado-badge estado-<?= $eClase ?>">
                                        <i class="fa-solid <?= $eIcon ?>"></i>
                                        <?= htmlspecialchars($row['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="alert-notfound">
                    <i class="fa-solid fa-circle-info"></i>
                    Esta ficha no tiene programación de ambientes registrada aún.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert-notfound">
                <i class="fa-solid fa-triangle-exclamation"></i>
                No se encontró ninguna ficha con el número <strong>"<?= htmlspecialchars($busqueda) ?>"</strong>.
            </div>
        <?php endif; ?>

        <hr class="section-divider">

    <?php endif; ?>

    <!-- Todas las fichas -->
    <div class="section-label">
        <i class="fa-solid fa-layer-group"></i>
        Todas las fichas
        <span class="badge"><?= count($todas_fichas) ?></span>
    </div>

    <?php if (count($todas_fichas) > 0): ?>
    <div class="fichas-grid">
        <?php foreach ($todas_fichas as $f):
            $fechaInicio = !empty($f['fecha_inicio']) ? date('d/m/Y', strtotime($f['fecha_inicio'])) : '—';
            $fechaFin    = !empty($f['fecha_fin'])    ? date('d/m/Y', strtotime($f['fecha_fin']))    : '—';
        ?>
        <a href="?buscar=<?= urlencode($f['numero_ficha']) ?>" class="ficha-card">
            <div class="ficha-card__num">
                <i class="fa-solid fa-graduation-cap" style="font-size:.9rem;"></i>
                <?= htmlspecialchars($f['numero_ficha']) ?>
            </div>
            <div class="ficha-card__programa"><?= htmlspecialchars($f['programa'] ?? '—') ?></div>
            <?php if (!empty($f['jornada'])): ?>
            <div class="ficha-card__meta">
                <i class="fa-regular fa-clock"></i>
                <?= htmlspecialchars($f['jornada']) ?>
            </div>
            <?php endif; ?>
            <div class="ficha-card__meta">
                <i class="fa-regular fa-calendar"></i>
                <?= $fechaInicio ?> &mdash; <?= $fechaFin ?>
            </div>
            <div class="ficha-card__footer">
                <span class="ficha-card__usos">
                    <i class="fa-solid fa-list-check" style="font-size:.72rem;margin-right:2px;"></i>
                    Usos: <strong><?= (int)$f['total_usos'] ?></strong>
                </span>
                <span class="ficha-card__btn">
                    <i class="fa-solid fa-magnifying-glass"></i> Ver programación
                </span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-inbox"></i>
        <p>No hay fichas registradas en el sistema.</p>
    </div>
    <?php endif; ?>

</div><!-- /dashboard-container -->

<!-- ═══════════════════════ FOOTER ═══════════════════════ -->
<footer class="footer">
    <div class="footer-top-line"></div>
    <div class="footer-container">
        <div class="footer-brand">
            <div class="footer-logo"><span>&#94;</span></div>
            <div class="footer-brand-text">
                <span class="footer-label">INSTITUCIONAL</span>
                <h3 class="footer-title">Sistema de Gestión<br>de Ambientes</h3>
            </div>
        </div>
        <div class="footer-description">
            <p>Plataforma institucional para la administración y control de ambientes de aprendizaje.</p>
        </div>
        <div class="footer-nav">
            <span class="footer-section-title">NAVEGACIÓN</span>
            <ul>
                <li><a href="index.php">Inicio</a></li>
                <li><a href="consultar.php">Consultar Ambiente</a></li>
                <li><a href="historial.php">Historial Autorizaciones</a></li>
                <li><a href="registro.php">Crear Registros</a></li>
                <li><a href="calendario.php">Calendario de Ambientes</a></li>
            </ul>
        </div>
        <div class="footer-location">
            <span class="footer-section-title">UBICACIÓN</span>
            <ul>
                <li><span class="footer-icon">&#9679;</span>Centro de Industria y Servicios del Meta</li>
                <li><span class="footer-icon">&#9711;</span>Villavicencio, Meta — Colombia</li>
                <li><span class="footer-icon">&#9993;</span>sena.edu.co</li>
            </ul>
        </div>
    </div>
    <div class="footer-divider"></div>
    <div class="footer-bottom">
        <p>© <?= date('Y') ?> <strong>SENA</strong> — Gestión de Ambientes. Todos los derechos reservados.</p>
        <div class="footer-status">
            <span class="footer-status-dot"></span>
            Sistema operativo
        </div>
    </div>
</footer>

</body>
</html>