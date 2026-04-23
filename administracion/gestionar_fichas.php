<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ── Conexión ─────────────────────────────────────
include("../includes/conexion.php");

// ── Restricción de acceso ─────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

$mensaje   = '';
$tipo_msg  = '';
$tab_activo = $_GET['tab'] ?? 'manual'; // 'manual' | 'importar'

// ── Proceso: Registro manual ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'manual') {

    $numero_ficha = trim($_POST['numero_ficha'] ?? '');
    $programa     = trim($_POST['programa']     ?? '');
    $jornada      = trim($_POST['jornada']      ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin    = trim($_POST['fecha_fin']    ?? '');
    $tab_activo   = 'manual';

    // Validaciones
    if (empty($numero_ficha) || empty($programa) || empty($jornada) || empty($fecha_inicio) || empty($fecha_fin)) {
        $mensaje  = 'Todos los campos son obligatorios.';
        $tipo_msg = 'error';
    } else {

        $numero_ficha = mysqli_real_escape_string($conexion, $numero_ficha);
        $programa     = mysqli_real_escape_string($conexion, $programa);
        $jornada      = mysqli_real_escape_string($conexion, $jornada);
        $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
        $fecha_fin    = mysqli_real_escape_string($conexion, $fecha_fin);

        // ── Verificar duplicado ─────────────────────
        $query_check  = "SELECT id FROM fichas WHERE numero_ficha = '$numero_ficha'";
        $result_check = mysqli_query($conexion, $query_check);

        if (mysqli_num_rows($result_check) > 0) {
            $mensaje  = "La ficha <strong>{$numero_ficha}</strong> ya existe en el sistema.";
            $tipo_msg = 'warning';
        } else {

            $query_insert = "INSERT INTO fichas
                (numero_ficha, programa, jornada, fecha_inicio, fecha_fin)
                VALUES
                ('$numero_ficha', '$programa', '$jornada', '$fecha_inicio', '$fecha_fin')";

            if (mysqli_query($conexion, $query_insert)) {
                $mensaje  = "Ficha <strong>{$numero_ficha}</strong> registrada exitosamente.";
                $tipo_msg = 'success';
            } else {
                $mensaje  = "Error al registrar la ficha.";
                $tipo_msg = 'error';
            }
        }
    }
}

// ── Fichas existentes para tabla de resumen ───────
$query_fichas  = "SELECT * FROM fichas ORDER BY id DESC LIMIT 20";
$result_fichas = mysqli_query($conexion, $query_fichas);

$fichas_recientes = [];
if ($result_fichas) {
    while ($row = mysqli_fetch_assoc($result_fichas)) {
        $fichas_recientes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Fichas</title>
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
            --danger-light: #fee2e2;
            --sans: 'DM Sans', sans-serif;
            --mono: 'DM Mono', monospace;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.07);
        }

        body { font-family: var(--sans); background: var(--bg); color: var(--text-primary); min-height: 100vh; padding: 2rem 1.5rem; }

        /* ── Page header ── */
        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; }
        .page-header__left { display: flex; align-items: center; gap: .75rem; }
        .page-header__icon { width: 44px; height: 44px; background: var(--accent-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--accent); }
        .page-header__icon svg { width: 22px; height: 22px; }
        .page-header h1 { font-size: 1.35rem; font-weight: 700; }
        .page-header__sub { font-size: .82rem; color: var(--text-secondary); margin-top: .1rem; }
        .btn-back { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .85rem; background: var(--surface); border: 1px solid var(--border); border-radius: 7px; color: var(--text-secondary); font-size: .82rem; font-weight: 500; text-decoration: none; transition: background .15s; }
        .btn-back:hover { background: var(--bg); }

        /* ── Alert ── */
        .alert { padding: .85rem 1.1rem; border-radius: 8px; margin-bottom: 1.4rem; font-size: .88rem; display: flex; align-items: flex-start; gap: .6rem; }
        .alert--success { background: var(--success-light); color: #065f46; border: 1px solid #a7f3d0; }
        .alert--warning { background: var(--warning-light); color: #92400e; border: 1px solid #fde68a; }
        .alert--error   { background: var(--danger-light);  color: #991b1b; border: 1px solid #fca5a5; }

        /* ── Tabs ── */
        .tabs { display: flex; gap: .25rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: .35rem; width: fit-content; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); }
        .tab-btn {
            padding: .55rem 1.4rem; border-radius: 7px; border: none;
            font-family: var(--sans); font-size: .88rem; font-weight: 600;
            cursor: pointer; transition: background .15s, color .15s;
            display: flex; align-items: center; gap: .5rem;
            color: var(--text-secondary); background: transparent;
        }
        .tab-btn.active { background: var(--accent); color: #fff; }
        .tab-btn:not(.active):hover { background: var(--bg); color: var(--text-primary); }

        /* ── Tab content ── */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ── Card ── */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.75rem; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card__title { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: .5rem; color: var(--text-primary); }
        .card__title svg { width: 18px; height: 18px; color: var(--accent); }

        /* ── Form grid ── */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: .35rem; }
        .form-group--full { grid-column: 1 / -1; }
        .form-group label { font-size: .78rem; font-weight: 600; color: var(--text-secondary); letter-spacing: .04em; text-transform: uppercase; }
        .form-group input,
        .form-group select {
            padding: .6rem .9rem;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-family: var(--sans); font-size: .9rem;
            background: var(--bg); color: var(--text-primary);
            transition: border-color .15s, box-shadow .15s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }
        .form-actions { margin-top: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .btn-submit { padding: .65rem 1.6rem; background: var(--accent); color: #fff; border: none; border-radius: 7px; font-family: var(--sans); font-size: .9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .45rem; transition: background .15s; }
        .btn-submit:hover { background: #4338ca; }
        .btn-reset { padding: .65rem 1.1rem; background: transparent; color: var(--text-secondary); border: 1px solid var(--border); border-radius: 7px; font-family: var(--sans); font-size: .88rem; cursor: pointer; transition: background .15s; }
        .btn-reset:hover { background: var(--bg); }

        /* ── Upload zone ── */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius); padding: 2.5rem 1.5rem;
            text-align: center; cursor: pointer;
            transition: border-color .2s, background .2s;
            background: var(--bg);
        }
        .upload-zone:hover, .upload-zone.drag-over { border-color: var(--accent); background: var(--accent-light); }
        .upload-zone svg { width: 40px; height: 40px; color: var(--text-secondary); margin-bottom: .75rem; }
        .upload-zone p { font-size: .9rem; color: var(--text-secondary); margin-bottom: .4rem; }
        .upload-zone small { font-size: .78rem; color: #94a3b8; }
        .upload-zone input[type="file"] { display: none; }

        .file-selected {
            display: none; margin-top: 1rem;
            padding: .6rem 1rem; background: var(--success-light);
            border: 1px solid #a7f3d0; border-radius: 7px;
            font-size: .85rem; color: #065f46;
            align-items: center; gap: .5rem;
        }
        .file-selected.visible { display: flex; }

        /* ── Formato hint ── */
        .format-hint { margin-top: 1.25rem; }
        .format-hint__title { font-size: .8rem; color: var(--text-secondary); margin-bottom: .5rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .format-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .format-table th, .format-table td { padding: .4rem .75rem; text-align: left; border: 1px solid var(--border); }
        .format-table th { background: var(--bg); font-weight: 600; color: var(--text-secondary); }
        .format-table td:first-child { font-family: var(--mono); color: var(--accent); }
        .format-note { margin-top: .75rem; font-size: .79rem; color: var(--text-secondary); background: var(--accent-light); border: 1px solid #c7d2fe; border-radius: 6px; padding: .5rem .8rem; line-height: 1.5; }
        .format-note strong { color: var(--accent); }

        /* ── Recent fichas table ── */
        .section-title { font-size: .95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
        .section-title span { font-size: .8rem; font-weight: 500; color: var(--text-secondary); background: var(--bg); border: 1px solid var(--border); padding: .15rem .55rem; border-radius: 20px; }
        .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: var(--bg); }
        thead th { padding: .7rem 1rem; font-size: .76rem; font-weight: 700; color: var(--text-secondary); letter-spacing: .05em; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8f9ff; }
        tbody td { padding: .8rem 1rem; font-size: .88rem; vertical-align: middle; }
        .ficha-num { font-family: var(--mono); background: var(--accent-light); color: var(--accent); padding: .15rem .5rem; border-radius: 4px; font-size: .82rem; }

        /* ── Progress bar (upload) ── */
        .progress-wrap { display: none; margin-top: 1rem; }
        .progress-wrap.visible { display: block; }
        .progress-bar-bg { height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--accent); border-radius: 99px; transition: width .3s; width: 0%; }
        .progress-label { font-size: .78rem; color: var(--text-secondary); margin-top: .4rem; }
    </style>
</head>
<body>

<div class="page-header">
    <div class="page-header__left">
        <div class="page-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
                <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
        </div>
        <div>
            <h1>Gestionar Fichas</h1>
            <div class="page-header__sub">Registro manual o importación masiva</div>
        </div>
    </div>
    <a href="index.php" class="btn-back">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Volver
    </a>
</div>

<?php if ($mensaje): ?>
<div class="alert alert--<?= $tipo_msg ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" flex-shrink="0">
        <?php if ($tipo_msg === 'success'): ?>
            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        <?php elseif ($tipo_msg === 'warning'): ?>
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        <?php else: ?>
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        <?php endif; ?>
    </svg>
    <span><?= $mensaje ?></span>
</div>
<?php endif; ?>

<!-- ── Tabs ── -->
<div class="tabs">
    <button type="button" class="tab-btn <?= $tab_activo === 'manual' ? 'active' : '' ?>" onclick="switchTab('manual')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Manual
    </button>
    <button type="button" class="tab-btn <?= $tab_activo === 'importar' ? 'active' : '' ?>" onclick="switchTab('importar')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Importar Excel / CSV
    </button>
</div>

<!-- ══════════════════════════════════════ TAB: MANUAL ═══════ -->
<div id="tab-manual" class="tab-content <?= $tab_activo === 'manual' ? 'active' : '' ?>">
    <div class="card">
        <div class="card__title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar nueva ficha
        </div>
        <form method="POST" action="?tab=manual">
            <input type="hidden" name="accion" value="manual">
            <div class="form-grid">
                <div class="form-group">
                    <label for="numero_ficha">Número de Ficha *</label>
                    <input type="text" id="numero_ficha" name="numero_ficha" placeholder="Ej. 2895621" required maxlength="20" value="<?= htmlspecialchars($_POST['numero_ficha'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="programa">Programa *</label>
                    <input type="text" id="programa" name="programa" placeholder="Ej. Técnico en Sistemas" required maxlength="150" value="<?= htmlspecialchars($_POST['programa'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="jornada">Jornada *</label>
                    <select id="jornada" name="jornada" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach (['Diurna', 'Mixta', 'Noche'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($_POST['jornada'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio *</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="fecha_fin">Fecha Fin *</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Guardar Ficha
                </button>
                <button type="reset" class="btn-reset">Limpiar</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════ TAB: IMPORTAR ═════ -->
<div id="tab-importar" class="tab-content <?= $tab_activo === 'importar' ? 'active' : '' ?>">
    <div class="card">
        <div class="card__title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar fichas desde archivo
        </div>

        <form method="POST" action="importar_fichas.php" enctype="multipart/form-data" id="form-import">
            <div class="upload-zone" id="upload-zone" onclick="document.getElementById('archivo').click()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
                <p><strong>Haz clic para seleccionar</strong> o arrastra el archivo aquí</p>
                <small>Formatos aceptados: .csv, .xlsx &nbsp;·&nbsp; Tamaño máximo: 5 MB</small>
                <input type="file" id="archivo" name="archivo" accept=".csv,.xlsx">
            </div>

            <div class="file-selected" id="file-selected">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span id="file-name-label">Archivo seleccionado</span>
            </div>

            <div class="progress-wrap" id="progress-wrap">
                <div class="progress-bar-bg"><div class="progress-bar-fill" id="progress-fill"></div></div>
                <div class="progress-label" id="progress-label">Procesando…</div>
            </div>

            <div class="format-hint">
                <p class="format-hint__title">Formato esperado del archivo</p>
                <table class="format-table">
                    <thead>
                        <tr>
                            <th>Columna</th><th>Tipo</th><th>Ejemplos aceptados</th><th>Obligatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>numero_ficha</td>
                            <td>Texto / Número</td>
                            <td>2895621</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>programa</td>
                            <td>Texto</td>
                            <td>Técnico en Sistemas</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>jornada</td>
                            <td>Texto</td>
                            <td>  Diurna  Mixta Noche </td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>fecha_inicio</td>
                            <td>Fecha</td>
                            <td>15/01/2026 · 1/1/2026 · 2026-01-15 · 15-01-2026</td>
                            <td>✓</td>
                        </tr>
                        <tr>
                            <td>fecha_fin</td>
                            <td>Fecha</td>
                            <td>30/11/2026 · 1/12/2026 · 2026-11-30 · serial Excel</td>
                            <td>✓</td>
                        </tr>
                    </tbody>
                </table>
                <div class="format-note">
                    <strong>Fechas flexibles:</strong> se aceptan DD/MM/YYYY, D/M/YYYY, YYYY-MM-DD, DD-MM-YYYY, DD.MM.YYYY y el serial numérico de Excel (ej. 45678). Con o sin ceros iniciales en día y mes.<br>
                    <strong>Jornada:</strong> "Diurna" se registra como <em>Diurna</em>; "Mixta" como <em>Mixta</em>; "Noche" como <em>Noche</em>. Sin distinción de mayúsculas.
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit" id="btn-import">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Importar Fichas
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Fichas recientes ── -->
<div class="section-title">
    Fichas registradas recientemente
    <span><?= count($fichas_recientes) ?> últimas</span>
</div>
<div class="table-wrap">
    <?php if ($fichas_recientes): ?>
    <table>
        <thead>
            <tr>
                <th>N° Ficha</th><th>Programa</th><th>Jornada</th>
                <th>Fecha inicio</th><th>Fecha fin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fichas_recientes as $f): ?>
            <tr>
                <td><span class="ficha-num"><?= htmlspecialchars($f['numero_ficha']) ?></span></td>
                <td><?= htmlspecialchars($f['programa']) ?></td>
                <td><?= htmlspecialchars($f['jornada']) ?></td>
                <td><?= htmlspecialchars(date('d/m/Y', strtotime($f['fecha_inicio']))) ?></td>
                <td><?= htmlspecialchars(date('d/m/Y', strtotime($f['fecha_fin']))) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center;padding:2rem;color:var(--text-secondary);font-size:.88rem;">No hay fichas registradas aún.</div>
    <?php endif; ?>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => {
        if (b.textContent.trim().toLowerCase().startsWith(name === 'manual' ? 'manual' : 'importar')) {
            b.classList.add('active');
        }
    });
}

// File upload UX
const fileInput   = document.getElementById('archivo');
const uploadZone  = document.getElementById('upload-zone');
const fileLabel   = document.getElementById('file-selected');
const fileNameLbl = document.getElementById('file-name-label');

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        fileNameLbl.textContent = fileInput.files[0].name + ' (' + (fileInput.files[0].size / 1024).toFixed(1) + ' KB)';
        fileLabel.classList.add('visible');
    }
});

// Drag and drop
uploadZone.addEventListener('dragover',  e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

// Submit feedback
document.getElementById('form-import').addEventListener('submit', function() {
    if (!fileInput.files.length) { alert('Selecciona un archivo primero.'); return false; }
    document.getElementById('progress-wrap').classList.add('visible');
    let w = 0;
    const iv = setInterval(() => {
        w = Math.min(w + Math.random() * 15, 90);
        document.getElementById('progress-fill').style.width = w + '%';
        document.getElementById('progress-label').textContent = 'Procesando… ' + Math.round(w) + '%';
    }, 200);
    document.getElementById('btn-import').disabled = true;
});
</script>

</body>
</html>