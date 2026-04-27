<?php
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

/* ── Solicitudes pendientes ── */
$resPendientes = mysqli_query($conexion, "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE estado = 'Pendiente'");
$solicitudes_pendientes = mysqli_fetch_row($resPendientes)[0];

/* ── Búsqueda ── */
$busqueda        = trim($_GET['buscar'] ?? '');
$ficha_encontrada = null;
$programacion     = [];

if ($busqueda !== '') {
    $busqueda_esc = mysqli_real_escape_string($conexion, $busqueda);

    $res_ficha        = mysqli_query($conexion, "SELECT * FROM fichas WHERE numero_ficha LIKE '%$busqueda_esc%' LIMIT 1");
    $ficha_encontrada = $res_ficha ? mysqli_fetch_assoc($res_ficha) : null;

    if ($ficha_encontrada) {
        $id_ficha = (int)$ficha_encontrada['id'];

        $sql_prog = "SELECT
                        MIN(au.fecha_inicio)  AS fecha_inicio,
                        MAX(au.fecha_fin)     AS fecha_fin,
                        au.hora_inicio, au.hora_final, au.estado,
                        a.nombre_ambiente,
                        i.nombre              AS nombre_instructor,
                        GROUP_CONCAT(
                            DISTINCT DAYOFWEEK(au.fecha_inicio)
                            ORDER BY DAYOFWEEK(au.fecha_inicio)
                        ) AS dias_semana
                     FROM autorizaciones_ambientes au
                     JOIN ambientes    a ON au.id_ambiente   = a.id
                     JOIN instructores i ON au.id_instructor = i.id
                     WHERE au.id_ficha = $id_ficha
                     GROUP BY au.id_ambiente, au.id_instructor, au.hora_inicio, au.hora_final, au.estado
                     ORDER BY MIN(au.fecha_inicio) ASC, au.hora_inicio ASC";

        $res_prog = mysqli_query($conexion, $sql_prog);
        if ($res_prog) {
            while ($row = mysqli_fetch_assoc($res_prog)) $programacion[] = $row;
        }
    }
}

/* ── Todas las fichas ── */
$res_todas  = mysqli_query($conexion, "SELECT f.*, COUNT(au.id) AS total_usos
               FROM fichas f LEFT JOIN autorizaciones_ambientes au ON au.id_ficha = f.id
               GROUP BY f.id ORDER BY f.numero_ficha ASC");
$todas_fichas = [];
if ($res_todas) while ($row = mysqli_fetch_assoc($res_todas)) $todas_fichas[] = $row;

$abrevDias  = [1=>'Dom',2=>'Lun',3=>'Mar',4=>'Mié',5=>'Jue',6=>'Vie',7=>'Sáb'];
$export_url = 'exportar_fichas.php' . ($busqueda !== '' ? '?buscar=' . urlencode($busqueda) : '');
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
        /* ══════════════ VARIABLES ══════════════ */
        :root {
            --primary:    #0b2449;
            --primary-mid:#355d91;
            --primary-lt: #e8eef7;
            --primary-bd: #c8d6ea;
            --surface:    #ffffff;
            --bg:         #f5f7fa;
            --border:     #e5e7eb;
            --text:       #1f2937;
            --muted:      #64748b;
            --success:    #2e7d32;
            --success-lt: #e8f5e9;
            --success-bd: #a5d6a7;
            --warning:    #ef6c00;
            --warning-lt: #fff3e0;
            --danger:     #c62828;
            --danger-lt:  #ffebee;
            --radius:     16px;
            --shadow:     0 2px 8px rgba(0,0,0,0.06);
        }
        *, *::before, *::after { box-sizing: border-box; }

        /* ══════════════ LAYOUT ══════════════ */
        .dashboard-container { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

        /* ══════════════ SEARCH CARD ══════════════ */
        .search-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.4rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        .search-card h3 {
            font-size: .82rem; font-weight: 700;
            color: var(--muted); letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 1rem;
            display: flex; align-items: center; gap: .45rem;
        }
        .search-card h3 i { color: var(--primary); }
        .search-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }

        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i {
            position: absolute; left: .9rem; top: 50%;
            transform: translateY(-50%);
            color: var(--muted); font-size: .88rem; pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: .62rem .9rem .62rem 2.4rem;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: .9rem; background: var(--bg); color: var(--text);
            font-family: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .search-input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,36,73,.12);
        }

        .btn-buscar {
            padding: .62rem 1.4rem;
            background: var(--primary); color: #fff;
            border: none; border-radius: 8px;
            font-size: .88rem; font-weight: 600;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: .45rem;
            transition: background .15s; white-space: nowrap;
            font-family: inherit;
        }
        .btn-buscar:hover { background: var(--primary-mid); }

        .btn-limpiar {
            padding: .62rem 1rem;
            background: transparent; color: var(--muted);
            border: 1px solid var(--border); border-radius: 8px;
            font-size: .85rem; text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
            white-space: nowrap; transition: background .15s;
        }
        .btn-limpiar:hover { background: var(--bg); }

        .btn-export {
            padding: .62rem 1.2rem;
            background: var(--success-lt); color: var(--success);
            border: 1px solid var(--success-bd); border-radius: 8px;
            font-size: .88rem; font-weight: 600;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
            white-space: nowrap; transition: background .15s;
        }
        .btn-export:hover { background: #c8e6c9; }

        /* ══════════════ FICHA INFO CARD ══════════════ */
        .ficha-info-card {
            background: var(--primary-lt);
            border: 1px solid var(--primary-bd);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap;
        }
        .ficha-info-card > i { font-size: 1.8rem; color: var(--primary); flex-shrink: 0; }
        .ficha-info-body { flex: 1; min-width: 0; }
        .ficha-info-num { font-size: 1.15rem; font-weight: 700; color: var(--primary); }
        .ficha-info-meta { font-size: .85rem; color: var(--primary-mid); margin-top: .2rem; }
        .ficha-stats { display: flex; gap: .65rem; flex-wrap: wrap; }
        .ficha-stat {
            background: var(--surface); border: 1px solid var(--primary-bd);
            border-radius: 7px; padding: .35rem .85rem;
            font-size: .8rem; color: var(--muted);
            white-space: nowrap;
        }
        .ficha-stat strong { color: var(--text); font-weight: 700; }

        /* ══════════════ ALERT NOT FOUND ══════════════ */
        .alert-notfound {
            background: var(--warning-lt); border: 1px solid var(--warning-bd);
            border-radius: 10px; padding: .9rem 1.2rem;
            display: flex; align-items: center; gap: .6rem;
            color: var(--warning); font-size: .88rem; margin-bottom: 1.25rem;
        }

        /* ══════════════ SECTION LABEL ══════════════ */
        .section-label {
            font-size: .78rem; font-weight: 700;
            color: var(--muted); letter-spacing: .06em;
            text-transform: uppercase; margin-bottom: .85rem;
            display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
        }
        .section-label .badge {
            background: var(--primary-lt); color: var(--primary);
            border: 1px solid var(--primary-bd);
            padding: .1rem .55rem; border-radius: 20px;
            font-size: .75rem; font-weight: 700;
        }

        /* ══════════════ TABLE ══════════════ */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden; overflow-x: auto;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }
        thead tr { background: var(--bg); }
        thead th {
            padding: .7rem 1rem;
            font-size: .74rem; font-weight: 700;
            color: var(--muted); letter-spacing: .05em;
            text-transform: uppercase; border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f0f4fa; }
        tbody td { padding: .85rem 1rem; font-size: .88rem; color: var(--text); vertical-align: middle; }

        .dia-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--primary-lt); color: var(--primary);
            border: 1px solid var(--primary-bd);
            border-radius: 4px; padding: .1rem .4rem;
            font-size: .72rem; font-weight: 700;
        }
        .hora-range { font-size: .83rem; color: var(--muted); white-space: nowrap; }

        .estado-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .22rem .65rem; border-radius: 6px;
            font-size: .78rem; font-weight: 600; white-space: nowrap;
        }
        .estado-aprobado  { background: var(--success-lt); color: var(--success); }
        .estado-pendiente { background: var(--warning-lt); color: var(--warning); }
        .estado-rechazado { background: var(--danger-lt);  color: var(--danger); }

        .section-divider { border: none; border-top: 2px solid var(--border); margin: 2rem 0; }

        /* ══════════════ FICHAS GRID ══════════════ */
        .fichas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(255px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .ficha-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1.1rem 1.2rem;
            box-shadow: var(--shadow);
            display: flex; flex-direction: column; gap: .45rem;
            transition: box-shadow .15s, border-color .15s, transform .15s;
            text-decoration: none; color: inherit;
        }
        .ficha-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--primary-bd);
            transform: translateY(-3px);
        }
        .ficha-card__num {
            font-weight: 700; font-size: 1rem; color: var(--primary);
            display: flex; align-items: center; gap: .4rem;
        }
        .ficha-card__programa {
            font-size: .84rem; color: var(--text);
            font-weight: 500; line-height: 1.35;
        }
        .ficha-card__meta {
            font-size: .78rem; color: var(--muted);
            display: flex; align-items: center; gap: .35rem;
        }
        .ficha-card__footer {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: .3rem; flex-wrap: wrap; gap: .4rem;
        }
        .ficha-card__usos {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 5px; padding: .15rem .55rem;
            font-size: .75rem; color: var(--muted);
        }
        .ficha-card__usos strong { color: var(--text); }
        .ficha-card__btn {
            display: inline-flex; align-items: center; gap: .3rem;
            background: var(--primary-lt); color: var(--primary);
            border-radius: 5px; padding: .22rem .65rem;
            font-size: .75rem; font-weight: 600;
            transition: background .15s;
        }
        .ficha-card:hover .ficha-card__btn { background: var(--primary-bd); }

        /* ══════════════ EMPTY STATE ══════════════ */
        .empty-state { text-align: center; padding: 3.5rem 2rem; color: var(--muted); }
        .empty-state i { font-size: 2.8rem; opacity: .2; margin-bottom: .85rem; display: block; }
        .empty-state p { font-size: .9rem; }

        /* ══════════════ HEADER BTN VOLVER ══════════════ */
        .btn-volver-header {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem .9rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 7px; color: #fff;
            font-size: .82rem; font-weight: 600;
            text-decoration: none;
            transition: background .15s; white-space: nowrap;
        }
        .btn-volver-header:hover { background: rgba(255,255,255,0.25); }

        /* ══════════════ RESPONSIVE ══════════════ */
        @media (max-width: 900px) {
            .fichas-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        }
        @media (max-width: 640px) {
            .dashboard-container { padding: 1rem; }
            .search-row { flex-direction: column; align-items: stretch; }
            .search-input-wrap { min-width: 0; width: 100%; }
            .btn-buscar, .btn-limpiar, .btn-export { width: 100%; justify-content: center; }
            .ficha-info-card { flex-direction: column; gap: .75rem; }
            .ficha-info-card > i { display: none; }
            .fichas-grid { grid-template-columns: 1fr; }
            .header-user { flex-wrap: wrap; gap: .5rem; }
            .ficha-stats { flex-direction: column; gap: .4rem; }
        }
        @media (max-width: 380px) {
            .ficha-card__footer { flex-direction: column; align-items: flex-start; }
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
        <a href="../logout.php" class="btn-logout-header" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
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