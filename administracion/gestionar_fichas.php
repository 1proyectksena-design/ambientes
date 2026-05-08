<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

/* ── Solicitudes pendientes (campana header) ── */
$resPendientes = mysqli_query($conexion, "SELECT COUNT(*) FROM autorizaciones_ambientes WHERE estado = 'Pendiente'");
$solicitudes_pendientes = mysqli_fetch_row($resPendientes)[0];

$mensaje   = '';
$tipo_msg  = '';
$tab_activo = $_GET['tab'] ?? 'manual';

// ── Proceso: Registro manual ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'manual') {

    $numero_ficha = trim($_POST['numero_ficha'] ?? '');
    $programa     = trim($_POST['programa']     ?? '');
    $jornada      = trim($_POST['jornada']      ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin    = trim($_POST['fecha_fin']    ?? '');
    $tab_activo   = 'manual';

    if (empty($numero_ficha) || empty($programa) || empty($jornada) || empty($fecha_inicio) || empty($fecha_fin)) {
        $mensaje  = 'Todos los campos son obligatorios.';
        $tipo_msg = 'error';
    } else {
        $numero_ficha = mysqli_real_escape_string($conexion, $numero_ficha);
        $programa     = mysqli_real_escape_string($conexion, $programa);
        $jornada      = mysqli_real_escape_string($conexion, $jornada);
        $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
        $fecha_fin    = mysqli_real_escape_string($conexion, $fecha_fin);

        $query_check  = "SELECT id FROM fichas WHERE numero_ficha = '$numero_ficha'";
        $result_check = mysqli_query($conexion, $query_check);

        if (mysqli_num_rows($result_check) > 0) {
            $mensaje  = "La ficha <strong>{$numero_ficha}</strong> ya existe en el sistema.";
            $tipo_msg = 'warning';
        } else {
            $query_insert = "INSERT INTO fichas (numero_ficha, programa, jornada, fecha_inicio, fecha_fin)
                             VALUES ('$numero_ficha', '$programa', '$jornada', '$fecha_inicio', '$fecha_fin')";

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

// ── Fichas recientes ──────────────────────────────
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
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══════════════ ALERT ══════════════ */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: .9rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeInUp 0.4s ease-out;
        }
        .alert--success { background: var(--success-lt); color: var(--success); border: 1px solid var(--success-bd); }
        .alert--warning { background: var(--warning-lt); color: var(--warning); border: 1px solid var(--warning-bd); }
        .alert--error   { background: var(--danger-lt);  color: var(--danger);  border: 1px solid var(--danger-bd); }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* ══════════════ TABS ══════════════ */
        .tabs-bar {
            display: flex;
            gap: 4px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 5px;
            width: fit-content;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 9px 20px;
            border-radius: 9px;
            border: none;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            background: transparent;
            transition: background .15s, color .15s;
            white-space: nowrap;
            font-family: inherit;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-mid) 100%);
            color: #fff;
        }
        .tab-btn:not(.active):hover { background: var(--bg); color: var(--text); }

        /* ══════════════ TAB CONTENT ══════════════ */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ══════════════ CARD ══════════════ */
        .card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 30px;
            margin-bottom: 24px;
            animation: fadeInUp 0.5s ease-out 0.1s both;
        }
        .card__title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card__title i { color: var(--primary-mid); }

        /* ══════════════ FORM ══════════════ */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px 20px;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label {
            font-size: .76rem;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .form-group input,
        .form-group select {
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            background: var(--surface);
            color: var(--text);
            transition: border-color .2s, box-shadow .2s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-mid);
            box-shadow: 0 0 0 3px rgba(53,93,145,.12);
        }

        .form-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-submit {
            padding: 12px 28px;
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
            font-family: inherit;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23,47,99,.35);
        }

        .btn-reset {
            padding: 12px 20px;
            background: transparent;
            color: var(--muted);
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, border-color .2s;
            font-family: inherit;
        }
        .btn-reset:hover { background: var(--bg); border-color: var(--primary-bd); }

        /* ══════════════ UPLOAD ZONE ══════════════ */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 40px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: var(--bg);
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: var(--primary-mid);
            background: var(--primary-lt);
        }
        .upload-zone i { font-size: 2.5rem; color: var(--muted); margin-bottom: 12px; display: block; }
        .upload-zone p { font-size: .9rem; color: var(--muted); margin-bottom: 6px; }
        .upload-zone small { font-size: .78rem; color: #94a3b8; }
        .upload-zone input[type="file"] { display: none; }

        .file-selected {
            display: none;
            margin-top: 14px;
            padding: 10px 16px;
            background: var(--success-lt);
            border: 1px solid var(--success-bd);
            border-radius: 8px;
            font-size: .88rem;
            color: var(--success);
            align-items: center;
            gap: 8px;
        }
        .file-selected.visible { display: flex; }

        /* ══════════════ FORMAT TABLE ══════════════ */
        .format-hint { margin-top: 20px; }
        .format-hint__title {
            font-size: .78rem;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .format-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .format-table th,
        .format-table td { padding: 8px 12px; text-align: left; border: 1px solid var(--border); }
        .format-table thead tr { background: var(--primary); }
        .format-table th { font-weight: 700; color: #fff; }
        .format-table td:first-child { font-family: 'Courier New', monospace; color: var(--primary); font-weight: 600; }
        .format-table tbody tr:hover { background: var(--primary-lt); }
        .format-note {
            margin-top: 12px;
            font-size: .82rem;
            color: var(--muted);
            background: var(--primary-lt);
            border: 1px solid var(--primary-bd);
            border-radius: 8px;
            padding: 10px 14px;
            line-height: 1.7;
        }
        .format-note strong { color: var(--primary); }

        /* ══════════════ PROGRESS ══════════════ */
        .progress-wrap { display: none; margin-top: 14px; }
        .progress-wrap.visible { display: block; }
        .progress-bar-bg { height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-mid)); border-radius: 99px; transition: width .3s; width: 0%; }
        .progress-label { font-size: .78rem; color: var(--muted); margin-top: 6px; }

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
            margin-bottom: 2rem;
            animation: fadeInUp 0.5s ease-out 0.2s both;
        }
        table { width: 100%; border-collapse: collapse; min-width: 560px; }

        thead th {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            white-space: nowrap;
            background: var(--primary);
            color: #fff;
        }
        tbody tr { border-bottom: 1px solid #f0f0f0; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8f9ff; }
        tbody td { padding: 14px 16px; font-size: 14px; color: var(--text); vertical-align: middle; }

        .ficha-num {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-lt);
            color: var(--primary);
            border: 1px solid var(--primary-bd);
            padding: 3px 10px;
            border-radius: 6px;
            font-size: .82rem;
            font-weight: 700;
        }

        /* ══════════════ EMPTY STATE ══════════════ */
        .empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
        .empty-state i { font-size: 2.8rem; opacity: .2; margin-bottom: 14px; display: block; }
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
        @media (max-width: 768px) {
            .dashboard-container { padding: 20px 15px; }
            .card { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .tabs-bar { width: 100%; }
            .tab-btn { flex: 1; justify-content: center; }
        }
        @media (max-width: 480px) {
            .dashboard-container { padding: 15px 12px; }
            .card { padding: 15px; }
            .form-actions { flex-direction: column; }
            .btn-submit, .btn-reset { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════ HEADER ═══════════════════════ -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Gestionar Fichas</h1>
            <span>Registro manual o importación masiva</span>
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

    <?php if ($mensaje): ?>
    <div class="alert alert--<?= $tipo_msg ?>">
        <?php if ($tipo_msg === 'success'): ?>
            <i class="fa-solid fa-circle-check"></i>
        <?php elseif ($tipo_msg === 'warning'): ?>
            <i class="fa-solid fa-triangle-exclamation"></i>
        <?php else: ?>
            <i class="fa-solid fa-circle-xmark"></i>
        <?php endif; ?>
        <span><?= $mensaje ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Tabs ── -->
    <div class="tabs-bar">
        <button type="button" class="tab-btn <?= $tab_activo === 'manual' ? 'active' : '' ?>" onclick="switchTab('manual')">
            <i class="fa-solid fa-pen-to-square"></i> Manual
        </button>
        <button type="button" class="tab-btn <?= $tab_activo === 'importar' ? 'active' : '' ?>" onclick="switchTab('importar')">
            <i class="fa-solid fa-file-arrow-up"></i> Importar Excel / CSV
        </button>
    </div>

    <!-- ══════════ TAB: MANUAL ══════════ -->
    <div id="tab-manual" class="tab-content <?= $tab_activo === 'manual' ? 'active' : '' ?>">
        <div class="card">
            <div class="card__title">
                <i class="fa-solid fa-circle-plus"></i>
                Registrar nueva ficha
            </div>
            <form method="POST" action="?tab=manual">
                <input type="hidden" name="accion" value="manual">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="numero_ficha"><i class="fa-solid fa-hashtag"></i> Número de Ficha *</label>
                        <input type="text" id="numero_ficha" name="numero_ficha"
                               placeholder="Ej. 2895621" required maxlength="20"
                               value="<?= htmlspecialchars($_POST['numero_ficha'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="programa"><i class="fa-solid fa-graduation-cap"></i> Programa *</label>
                        <input type="text" id="programa" name="programa"
                               placeholder="Ej. Técnico en Sistemas" required maxlength="150"
                               value="<?= htmlspecialchars($_POST['programa'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="jornada"><i class="fa-regular fa-clock"></i> Jornada *</label>
                        <select id="jornada" name="jornada" required>
                            <option value="">— Seleccionar —</option>
                            <?php foreach (['Diurna', 'Mixta', 'Noche'] as $j): ?>
                                <option value="<?= $j ?>" <?= ($_POST['jornada'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio"><i class="fa-regular fa-calendar"></i> Fecha Inicio *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required
                               value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin"><i class="fa-regular fa-calendar-check"></i> Fecha Fin *</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required
                               value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Ficha
                    </button>
                    <button type="reset" class="btn-reset">
                        <i class="fa-solid fa-rotate-left"></i> Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════ TAB: IMPORTAR ══════════ -->
    <div id="tab-importar" class="tab-content <?= $tab_activo === 'importar' ? 'active' : '' ?>">
        <div class="card">
            <div class="card__title">
                <i class="fa-solid fa-file-arrow-up"></i>
                Importar fichas desde archivo
            </div>

            <form method="POST" action="importar_fichas.php" enctype="multipart/form-data" id="form-import">
                <div class="upload-zone" id="upload-zone" onclick="document.getElementById('archivo').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p><strong>Haz clic para seleccionar</strong> o arrastra el archivo aquí</p>
                    <small>Formatos aceptados: .csv, .xlsx &nbsp;·&nbsp; Tamaño máximo: 5 MB</small>
                    <input type="file" id="archivo" name="archivo" accept=".csv,.xlsx">
                </div>

                <div class="file-selected" id="file-selected">
                    <i class="fa-solid fa-circle-check"></i>
                    <span id="file-name-label">Archivo seleccionado</span>
                </div>

                <div class="progress-wrap" id="progress-wrap">
                    <div class="progress-bar-bg"><div class="progress-bar-fill" id="progress-fill"></div></div>
                    <div class="progress-label" id="progress-label">Procesando…</div>
                </div>

                <div class="format-hint">
                    <p class="format-hint__title"><i class="fa-solid fa-table"></i> &nbsp;Formato esperado del archivo</p>
                    <div style="overflow-x:auto;">
                        <table class="format-table">
                            <thead>
                                <tr><th>Columna</th><th>Tipo</th><th>Ejemplos aceptados</th><th>Obligatorio</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>numero_ficha</td><td>Texto / Número</td><td>2895621</td><td>✓</td></tr>
                                <tr><td>programa</td><td>Texto</td><td>Técnico en Sistemas</td><td>✓</td></tr>
                                <tr><td>jornada</td><td>Texto</td><td>Diurna &nbsp; Mixta &nbsp; Noche</td><td>✓</td></tr>
                                <tr><td>fecha_inicio</td><td>Fecha</td><td>15/01/2026 · 2026-01-15 · 15-01-2026</td><td>✓</td></tr>
                                <tr><td>fecha_fin</td><td>Fecha</td><td>30/11/2026 · 2026-11-30 · serial Excel</td><td>✓</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="format-note">
                        <strong>Fechas flexibles:</strong> se aceptan DD/MM/YYYY, D/M/YYYY, YYYY-MM-DD, DD-MM-YYYY, DD.MM.YYYY y el serial numérico de Excel (ej. 45678).<br>
                        <strong>Jornada:</strong> "Diurna", "Mixta" o "Noche" — sin distinción de mayúsculas.
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btn-import">
                        <i class="fa-solid fa-file-import"></i> Importar Fichas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════ FICHAS RECIENTES ══════════ -->
    <div class="section-label">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Fichas registradas recientemente
        <span class="badge"><?= count($fichas_recientes) ?> últimas</span>
    </div>

    <div class="table-wrap">
        <?php if ($fichas_recientes): ?>
        <table>
            <thead>
                <tr>
                    <th>N° Ficha</th>
                    <th>Programa</th>
                    <th>Jornada</th>
                    <th>Fecha inicio</th>
                    <th>Fecha fin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichas_recientes as $f): ?>
                <tr>
                    <td>
                        <span class="ficha-num">
                            <i class="fa-solid fa-graduation-cap" style="font-size:.75rem;"></i>
                            <?= htmlspecialchars($f['numero_ficha']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($f['programa']) ?></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);font-size:.85rem;">
                            <i class="fa-regular fa-clock"></i>
                            <?= htmlspecialchars($f['jornada']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);font-size:.85rem;">
                            <i class="fa-regular fa-calendar"></i>
                            <?= htmlspecialchars(date('d/m/Y', strtotime($f['fecha_inicio']))) ?>
                        </span>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);font-size:.85rem;">
                            <i class="fa-regular fa-calendar-check"></i>
                            <?= htmlspecialchars(date('d/m/Y', strtotime($f['fecha_fin']))) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-inbox"></i>
            <p>No hay fichas registradas aún.</p>
        </div>
        <?php endif; ?>
    </div>

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

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => {
        const txt = b.textContent.trim().toLowerCase();
        if ((name === 'manual' && txt.includes('manual')) ||
            (name === 'importar' && txt.includes('importar'))) {
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

uploadZone.addEventListener('dragover',  e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

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