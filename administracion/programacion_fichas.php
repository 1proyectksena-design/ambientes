<?php
session_start();
include("../includes/conexion.php");

// ── Restricción de acceso ─────────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

// ── Cargar lista de fichas para el select ─────────────────────
$fichas = [];

$query_fichas = "SELECT id, numero_ficha, programa FROM fichas ORDER BY numero_ficha ASC";
$result_fichas = mysqli_query($conexion, $query_fichas);

if ($result_fichas) {
    while ($row = mysqli_fetch_assoc($result_fichas)) {
        $fichas[] = $row;
    }
}

// ── Filtro aplicado ───────────────────────────────────────────
$id_ficha_filtro = isset($_GET['id_ficha']) && $_GET['id_ficha'] !== '' 
    ? (int)$_GET['id_ficha'] 
    : null;

// ── Consulta principal ────────────────────────────────────────
$sql = "SELECT d.fecha, d.hora_inicio, d.hora_fin,
               a.nombre_ambiente,
               f.numero_ficha,
               f.programa,
               f.jornada
        FROM disponibilidad_ambiente d
        JOIN ambientes a ON d.id_ambiente = a.id
        LEFT JOIN fichas f ON d.id_ficha = f.id";

if ($id_ficha_filtro !== null) {
    $sql .= " WHERE d.id_ficha = $id_ficha_filtro";
}

$sql .= " ORDER BY d.fecha ASC, d.hora_inicio ASC";

$result_programacion = mysqli_query($conexion, $sql);

$programacion = [];

if ($result_programacion) {
    while ($row = mysqli_fetch_assoc($result_programacion)) {
        $programacion[] = $row;
    }
}

// ── Exportar: construir URL con filtro actual ─────────────────
$export_url = 'exportar_fichas.php';

if ($id_ficha_filtro !== null) {
    $export_url .= '?id_ficha=' . $id_ficha_filtro;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación por Fichas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #f6f7fb;
            --surface: #ffffff;
            --border: #e4e8ef;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --accent: #4f46e5;
            --accent-light: #eef2ff;
            --success: #059669;
            --success-light: #d1fae5;
            --warning: #d97706;
            --warning-light: #fef3c7;
            --danger: #dc2626;
            --mono: 'DM Mono', monospace;
            --sans: 'DM Sans', sans-serif;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.07);
            --shadow-md: 0 4px 16px rgba(0,0,0,.09);
        }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem 1.5rem;
        }

        /* ── Header ── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .page-header__left { display: flex; align-items: center; gap: .75rem; }
        .page-header__icon {
            width: 44px; height: 44px;
            background: var(--accent-light);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent);
        }
        .page-header__icon svg { width: 22px; height: 22px; }
        .page-header h1 { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); }
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

        /* ── Filter bar ── */
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .filter-bar form {
            display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;
        }
        .filter-group { display: flex; flex-direction: column; gap: .35rem; flex: 1; min-width: 200px; }
        .filter-group label {
            font-size: .78rem; font-weight: 600;
            color: var(--text-secondary); letter-spacing: .04em; text-transform: uppercase;
        }
        .filter-group select {
            padding: .55rem .85rem;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-family: var(--sans); font-size: .88rem;
            background: var(--bg); color: var(--text-primary);
            cursor: pointer; transition: border-color .15s;
        }
        .filter-group select:focus { outline: none; border-color: var(--accent); }
        .filter-actions { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; }
        .btn-primary {
            padding: .55rem 1.2rem;
            background: var(--accent); color: #fff;
            border: none; border-radius: 7px;
            font-family: var(--sans); font-size: .88rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: .4rem;
            transition: background .15s;
        }
        .btn-primary:hover { background: #4338ca; }
        .btn-export {
            padding: .55rem 1.2rem;
            background: var(--success-light); color: var(--success);
            border: 1px solid #a7f3d0; border-radius: 7px;
            font-family: var(--sans); font-size: .88rem; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
            transition: background .15s;
        }
        .btn-export:hover { background: #bbf7d0; }
        .btn-clear {
            padding: .55rem .9rem;
            background: transparent; color: var(--text-secondary);
            border: 1px solid var(--border); border-radius: 7px;
            font-family: var(--sans); font-size: .85rem;
            text-decoration: none; transition: background .15s;
        }
        .btn-clear:hover { background: var(--bg); }

        /* ── Stats bar ── */
        .stats-bar {
            display: flex; gap: 1rem; margin-bottom: 1.2rem; flex-wrap: wrap;
        }
        .stat-pill {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 8px; padding: .5rem 1rem;
            font-size: .82rem; color: var(--text-secondary);
            display: flex; align-items: center; gap: .4rem;
            box-shadow: var(--shadow-sm);
        }
        .stat-pill strong { color: var(--text-primary); font-weight: 700; font-size: .9rem; }

        /* ── Table ── */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: var(--bg); }
        thead th {
            padding: .75rem 1rem;
            text-align: left;
            font-size: .76rem; font-weight: 700;
            color: var(--text-secondary);
            letter-spacing: .05em; text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .1s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8f9ff; }
        tbody td {
            padding: .85rem 1rem;
            font-size: .88rem; color: var(--text-primary);
            vertical-align: middle;
        }

        .ficha-badge {
            display: inline-flex; align-items: center;
            font-family: var(--mono);
            background: var(--accent-light); color: var(--accent);
            padding: .2rem .6rem; border-radius: 5px;
            font-size: .8rem; font-weight: 500; letter-spacing: .02em;
        }
        .jornada-badge {
            display: inline-flex;
            padding: .2rem .6rem; border-radius: 5px;
            font-size: .78rem; font-weight: 600;
        }
        .jornada-badge--manana { background: #fef3c7; color: var(--warning); }
        .jornada-badge--tarde  { background: #dbeafe; color: #2563eb; }
        .jornada-badge--noche  { background: #ede9fe; color: #7c3aed; }

        .hora-range {
            font-family: var(--mono); font-size: .82rem;
            color: var(--text-secondary);
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center; padding: 4rem 2rem; color: var(--text-secondary);
        }
        .empty-state svg { width: 48px; height: 48px; opacity: .3; margin-bottom: 1rem; }
        .empty-state p { font-size: .9rem; }

        /* ── Responsive ── */
        @media (max-width: 640px) {
            body { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            thead { display: none; }
            tbody tr { display: block; padding: .75rem 1rem; }
            tbody td { display: flex; justify-content: space-between; padding: .3rem 0; border-bottom: none; font-size: .83rem; }
            tbody td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-header__left">
        <div class="page-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                <line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/>
                <line x1="16" y1="14" x2="16" y2="14"/>
            </svg>
        </div>
        <div>
            <h1>Programación por Fichas</h1>
            <div class="page-header__sub">Consulta de disponibilidad de ambientes</div>
        </div>
    </div>
    <a href="index.php" class="btn-back">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Volver
    </a>
</div>

<!-- ── Filtros ── -->
<div class="filter-bar">
    <form method="GET" action="">
        <div class="filter-group">
            <label for="id_ficha">Filtrar por ficha</label>
            <select name="id_ficha" id="id_ficha">
                <option value="">— Todas las fichas —</option>
                <?php foreach ($fichas as $f): ?>
                    <option value="<?= htmlspecialchars($f['id']) ?>"
                        <?= $id_ficha_filtro === (int)$f['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['numero_ficha']) ?>
                        <?php if (!empty($f['programa'])): ?> – <?= htmlspecialchars($f['programa']) ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                Buscar
            </button>
            <?php if ($id_ficha_filtro !== null): ?>
                <a href="programacion_fichas.php" class="btn-clear">Limpiar</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($export_url) ?>" class="btn-export">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar Excel
            </a>
        </div>
    </form>
</div>

<!-- ── Stats ── -->
<div class="stats-bar">
    <div class="stat-pill">
        Registros encontrados: <strong><?= count($programacion) ?></strong>
    </div>
    <?php if ($id_ficha_filtro !== null):
        foreach ($fichas as $f) {
            if ((int)$f['id'] === $id_ficha_filtro) {
                echo '<div class="stat-pill">Ficha: <strong>' . htmlspecialchars($f['numero_ficha']) . '</strong></div>';
                if (!empty($f['programa'])) echo '<div class="stat-pill">Programa: <strong>' . htmlspecialchars($f['programa']) . '</strong></div>';
                break;
            }
        }
    endif; ?>
</div>

<!-- ── Tabla de resultados ── -->
<div class="table-wrap">
    <?php if (count($programacion) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Ficha</th>
                <th>Ambiente</th>
                <th>Programa</th>
                <th>Jornada</th>
                <th>Fecha</th>
                <th>Horario</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($programacion as $row): ?>
            <tr>
                <td data-label="Ficha">
                    <span class="ficha-badge"><?= htmlspecialchars($row['numero_ficha'] ?? '—') ?></span>
                </td>
                <td data-label="Ambiente"><?= htmlspecialchars($row['nombre_ambiente']) ?></td>
                <td data-label="Programa"><?= htmlspecialchars($row['programa'] ?? '—') ?></td>
                <td data-label="Jornada">
                    <?php
                        $j = strtolower($row['jornada'] ?? '');
                        $cls = match($j) {
                            'mañana', 'manana' => 'manana',
                            'tarde'            => 'tarde',
                            'noche'            => 'noche',
                            default            => 'manana'
                        };
                        $label = ucfirst($row['jornada'] ?? '—');
                    ?>
                    <span class="jornada-badge jornada-badge--<?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                </td>
                <td data-label="Fecha">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha']))) ?>
                </td>
                <td data-label="Horario">
                    <span class="hora-range">
                        <?= htmlspecialchars(substr($row['hora_inicio'], 0, 5)) ?> – <?= htmlspecialchars(substr($row['hora_fin'], 0, 5)) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/><line x1="8" y1="11" x2="14" y2="11"/>
        </svg>
        <p>No se encontraron registros<?= $id_ficha_filtro !== null ? ' para la ficha seleccionada' : '' ?>.</p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
