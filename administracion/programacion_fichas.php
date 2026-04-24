<?php
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

/* ── Búsqueda por número de ficha ─────────────────────────── */
$busqueda        = trim($_GET['buscar'] ?? '');
$ficha_encontrada = null;
$programacion     = [];

if ($busqueda !== '') {
    $busqueda_esc = mysqli_real_escape_string($conexion, $busqueda);

    /* Datos de la ficha buscada */
    $sql_ficha = "SELECT * FROM fichas
                  WHERE numero_ficha LIKE '%$busqueda_esc%'
                  LIMIT 1";
    $res_ficha        = mysqli_query($conexion, $sql_ficha);
    $ficha_encontrada = $res_ficha ? mysqli_fetch_assoc($res_ficha) : null;

    if ($ficha_encontrada) {
        $id_ficha = (int)$ficha_encontrada['id'];

        /*
         * Programación: viene de autorizaciones_ambientes
         * porque esa tabla tiene id_instructor, id_ambiente e id_ficha.
         */
        $sql_prog = "SELECT
                        MIN(au.fecha_inicio)  AS fecha_inicio,
                        MAX(au.fecha_fin)     AS fecha_fin,
                        au.hora_inicio,
                        au.hora_final,
                        au.estado,
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
                     GROUP BY au.id_ambiente, au.id_instructor,
                              au.hora_inicio, au.hora_final, au.estado
                     ORDER BY MIN(au.fecha_inicio) ASC, au.hora_inicio ASC";

        $res_prog = mysqli_query($conexion, $sql_prog);
        if ($res_prog) {
            while ($row = mysqli_fetch_assoc($res_prog)) {
                $programacion[] = $row;
            }
        }
    }
}

/* ── Listado de todas las fichas ──────────────────────────── */
$sql_todas  = "SELECT f.*,
                      COUNT(au.id) AS total_usos
               FROM fichas f
               LEFT JOIN autorizaciones_ambientes au ON au.id_ficha = f.id
               GROUP BY f.id
               ORDER BY f.numero_ficha ASC";
$res_todas  = mysqli_query($conexion, $sql_todas);
$todas_fichas = [];
if ($res_todas) {
    while ($row = mysqli_fetch_assoc($res_todas)) {
        $todas_fichas[] = $row;
    }
}

/* ── Abreviaciones de días ─────────────────────────────────── */
$abrevDias = [1=>'Dom',2=>'Lun',3=>'Mar',4=>'Mié',5=>'Jue',6=>'Vie',7=>'Sáb'];

/* ── URL exportar ─────────────────────────────────────────── */
$export_url = 'exportar_fichas.php' . ($busqueda !== '' ? '?buscar=' . urlencode($busqueda) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación por Fichas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:            #f6f7fb;
            --surface:       #ffffff;
            --border:        #e4e8ef;
            --text-primary:  #0f172a;
            --text-secondary:#64748b;
            --accent:        #4f46e5;
            --accent-light:  #eef2ff;
            --success:       #059669;
            --success-light: #d1fae5;
            --warning:       #d97706;
            --warning-light: #fef3c7;
            --danger:        #dc2626;
            --danger-light:  #fee2e2;
            --mono:          'DM Mono', monospace;
            --sans:          'DM Sans', sans-serif;
            --radius:        10px;
            --shadow-sm:     0 1px 3px rgba(0,0,0,.07);
            --shadow-md:     0 4px 16px rgba(0,0,0,.09);
        }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem 1.5rem;
        }

        /* ── Header ─────────────────────────────────────────── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;
        }
        .page-header__left { display: flex; align-items: center; gap: .75rem; }
        .page-header__icon {
            width: 44px; height: 44px;
            background: var(--accent-light); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent); font-size: 1.25rem;
        }
        .page-header h1   { font-size: 1.35rem; font-weight: 700; }
        .page-header__sub { font-size: .82rem; color: var(--text-secondary); margin-top: .1rem; }
        .btn-back {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem .85rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 7px; color: var(--text-secondary);
            font-size: .82rem; font-weight: 500;
            text-decoration: none; transition: background .15s;
        }
        .btn-back:hover { background: var(--bg); }

        /* ── Buscador ────────────────────────────────────────── */
        .search-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.4rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .search-card h3 {
            font-size: .92rem; font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            display: flex; align-items: center; gap: .45rem;
        }
        .search-row {
            display: flex; gap: .75rem; flex-wrap: wrap; align-items: center;
        }
        .search-input-wrap {
            position: relative; flex: 1; min-width: 220px;
        }
        .search-input-wrap i {
            position: absolute; left: .9rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary); font-size: .9rem;
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: .6rem .9rem .6rem 2.4rem;
            border: 1px solid var(--border); border-radius: 7px;
            font-family: var(--mono); font-size: .9rem;
            background: var(--bg); color: var(--text-primary);
            transition: border-color .15s, box-shadow .15s;
        }
        .search-input:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }
        .btn-buscar {
            padding: .62rem 1.4rem;
            background: var(--accent); color: #fff;
            border: none; border-radius: 7px;
            font-family: var(--sans); font-size: .88rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: .45rem;
            transition: background .15s; white-space: nowrap;
        }
        .btn-buscar:hover { background: #4338ca; }
        .btn-limpiar {
            padding: .62rem 1rem;
            background: transparent; color: var(--text-secondary);
            border: 1px solid var(--border); border-radius: 7px;
            font-family: var(--sans); font-size: .85rem;
            text-decoration: none; white-space: nowrap;
            transition: background .15s;
        }
        .btn-limpiar:hover { background: var(--bg); }
        .btn-export {
            padding: .62rem 1.2rem;
            background: var(--success-light); color: var(--success);
            border: 1px solid #a7f3d0; border-radius: 7px;
            font-family: var(--sans); font-size: .88rem; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
            white-space: nowrap; transition: background .15s;
        }
        .btn-export:hover { background: #bbf7d0; }

        /* ── Ficha encontrada: info card ─────────────────────── */
        .ficha-info-card {
            background: var(--accent-light);
            border: 1px solid #c7d2fe;
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap;
        }
        .ficha-info-card i { font-size: 1.6rem; color: var(--accent); }
        .ficha-info-body { flex: 1; }
        .ficha-info-num {
            font-family: var(--mono); font-size: 1.2rem;
            font-weight: 700; color: var(--accent);
        }
        .ficha-info-meta { font-size: .85rem; color: #4338ca; margin-top: .2rem; }
        .ficha-stats {
            display: flex; gap: .75rem; flex-wrap: wrap;
        }
        .ficha-stat {
            background: var(--surface); border: 1px solid #c7d2fe;
            border-radius: 7px; padding: .35rem .85rem;
            font-size: .8rem; color: var(--text-secondary);
        }
        .ficha-stat strong { color: var(--text-primary); font-weight: 700; }

        /* ── No encontrada / Sin resultados ──────────────────── */
        .alert-notfound {
            background: var(--warning-light); border: 1px solid #fde68a;
            border-radius: 8px; padding: .9rem 1.2rem;
            display: flex; align-items: center; gap: .6rem;
            color: #92400e; font-size: .88rem; margin-bottom: 1.25rem;
        }

        /* ── Tabla de programación ──────────────────────────── */
        .section-label {
            font-size: .78rem; font-weight: 700;
            color: var(--text-secondary); letter-spacing: .06em;
            text-transform: uppercase; margin-bottom: .75rem;
            display: flex; align-items: center; gap: .4rem;
        }
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden; overflow-x: auto;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }
        thead tr { background: var(--bg); }
        thead th {
            padding: .7rem 1rem;
            text-align: left; font-size: .75rem; font-weight: 700;
            color: var(--text-secondary); letter-spacing: .05em;
            text-transform: uppercase; border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8f9ff; }
        tbody td {
            padding: .85rem 1rem; font-size: .88rem;
            color: var(--text-primary); vertical-align: middle;
        }

        .dia-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--accent-light); color: var(--accent);
            border: 1px solid #c7d2fe;
            border-radius: 4px; padding: .1rem .4rem;
            font-size: .73rem; font-weight: 600; font-family: var(--mono);
        }
        .hora-range { font-family: var(--mono); font-size: .83rem; color: var(--text-secondary); }

        .estado-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .2rem .6rem; border-radius: 5px;
            font-size: .78rem; font-weight: 600;
        }
        .estado-aprobado  { background: var(--success-light); color: var(--success); }
        .estado-pendiente { background: var(--warning-light); color: var(--warning); }
        .estado-rechazado { background: var(--danger-light);  color: var(--danger);  }

        /* ── Divisor entre secciones ─────────────────────────── */
        .section-divider {
            border: none; border-top: 2px solid var(--border);
            margin: 2rem 0;
        }

        /* ── Listado de todas las fichas ─────────────────────── */
        .fichas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .ficha-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1.1rem 1.2rem;
            box-shadow: var(--shadow-sm);
            display: flex; flex-direction: column; gap: .5rem;
            transition: box-shadow .15s, border-color .15s;
            text-decoration: none; color: inherit;
        }
        .ficha-card:hover {
            box-shadow: var(--shadow-md); border-color: #c7d2fe;
        }
        .ficha-card__num {
            font-family: var(--mono); font-weight: 700;
            font-size: 1rem; color: var(--accent);
            display: flex; align-items: center; gap: .4rem;
        }
        .ficha-card__programa {
            font-size: .84rem; color: var(--text-primary);
            font-weight: 500; line-height: 1.35;
        }
        .ficha-card__meta {
            font-size: .78rem; color: var(--text-secondary);
            display: flex; align-items: center; gap: .35rem;
        }
        .ficha-card__footer {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: .3rem;
        }
        .ficha-card__usos {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 5px; padding: .15rem .55rem;
            font-size: .75rem; color: var(--text-secondary);
        }
        .ficha-card__usos strong { color: var(--text-primary); }
        .ficha-card__btn {
            display: inline-flex; align-items: center; gap: .3rem;
            background: var(--accent-light); color: var(--accent);
            border-radius: 5px; padding: .2rem .6rem;
            font-size: .75rem; font-weight: 600;
            transition: background .15s;
        }
        .ficha-card:hover .ficha-card__btn { background: #e0e7ff; }

        /* ── Empty state ─────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 3.5rem 2rem;
            color: var(--text-secondary);
        }
        .empty-state i { font-size: 2.8rem; opacity: .25; margin-bottom: .85rem; display: block; }
        .empty-state p { font-size: .9rem; }

        @media (max-width: 640px) {
            body { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .search-row { flex-direction: column; }
            .search-input-wrap { width: 100%; }
        }
    </style>
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header__left">
        <div class="page-header__icon">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div>
            <h1>Programación por Fichas</h1>
            <div class="page-header__sub">Consulta de ambientes e instructores por ficha</div>
        </div>
    </div>
    <a href="index.php" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<!-- ── Buscador ───────────────────────────────────────────── -->
<div class="search-card">
    <h3><i class="fa-solid fa-magnifying-glass"></i> Buscar ficha por número</h3>
    <form method="GET" action="">
        <div class="search-row">
            <div class="search-input-wrap">
                <i class="fa-solid fa-hashtag"></i>
                <input
                    type="text"
                    name="buscar"
                    class="search-input"
                    placeholder="Ej: 2895621"
                    value="<?= htmlspecialchars($busqueda) ?>"
                    autocomplete="off">
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

        <!-- Info de la ficha -->
        <div class="ficha-info-card">
            <i class="fa-solid fa-graduation-cap"></i>
            <div class="ficha-info-body">
                <div class="ficha-info-num"><?= htmlspecialchars($ficha_encontrada['numero_ficha']) ?></div>
                <div class="ficha-info-meta">
                    <?= htmlspecialchars($ficha_encontrada['programa'] ?? '') ?>
                    <?php if (!empty($ficha_encontrada['jornada'])): ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($ficha_encontrada['jornada']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ficha-stats">
                <div class="ficha-stat">
                    Inicio: <strong><?= date('d/m/Y', strtotime($ficha_encontrada['fecha_inicio'])) ?></strong>
                </div>
                <div class="ficha-stat">
                    Fin: <strong><?= date('d/m/Y', strtotime($ficha_encontrada['fecha_fin'])) ?></strong>
                </div>
                <div class="ficha-stat">
                    Usos: <strong><?= count($programacion) ?></strong>
                </div>
            </div>
        </div>

        <!-- Tabla de programación -->
        <?php if (count($programacion) > 0): ?>

            <div class="section-label">
                <i class="fa-solid fa-list-check"></i>
                Programación de ambientes — <?= count($programacion) ?> registro(s)
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ambiente</th>
                            <th>Instructor</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Horario</th>
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
                                $diasHtml = '<span style="color:#999;">—</span>';
                            }

                            switch ($row['estado']) {
                                case 'Aprobado':
                                    $eClase = 'aprobado'; $eIcon = 'fa-circle-check'; break;
                                case 'Rechazado':
                                    $eClase = 'rechazado'; $eIcon = 'fa-ban'; break;
                                default:
                                    $eClase = 'pendiente'; $eIcon = 'fa-hourglass-half'; break;
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                            <td>
                                <i class="fa-solid fa-user" style="color:#355d91;margin-right:5px;"></i>
                                <?= htmlspecialchars($row['nombre_instructor']) ?>
                            </td>
                            <td>
                                <span style="display:flex;align-items:center;gap:.35rem;">
                                    <i class="fa-regular fa-calendar" style="color:#64748b;"></i>
                                    <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                                </span>
                            </td>
                            <td>
                                <span style="display:flex;align-items:center;gap:.35rem;">
                                    <i class="fa-regular fa-calendar-check" style="color:#64748b;"></i>
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

<!-- ── Listado de todas las fichas ──────────────────────── -->
<div class="section-label" style="margin-bottom:1rem;">
    <i class="fa-solid fa-layer-group"></i>
    Todas las fichas &nbsp;<span style="background:var(--accent-light);color:var(--accent);border:1px solid #c7d2fe;padding:.1rem .5rem;border-radius:20px;font-size:.75rem;">
        <?= count($todas_fichas) ?>
    </span>
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
                <i class="fa-solid fa-list-check" style="font-size:.7rem;"></i>
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

</body>
</html>