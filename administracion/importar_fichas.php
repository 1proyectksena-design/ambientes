<?php
// VERSIÓN 4 — Jornadas: Diurna, Mixta, Noche
session_start();
include("../includes/conexion.php");

// ── Restricción de acceso ─────────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    header('Location: ../login.php');
    exit;
}

// ── Solo acepta POST con archivo ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['archivo'])) {
    header('Location: gestionar_fichas.php?tab=importar&error=no_file');
    exit;
}

$file = $_FILES['archivo'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'xlsx'])) {
    header('Location: gestionar_fichas.php?tab=importar&error=formato');
    exit;
}

// ── Resultados del proceso ────────────────────────────────────
$insertados   = 0;
$duplicados   = 0;
$errores      = [];
$filas_leidas = 0;
$filas        = [];

// ══════════════════════════════════════════════════════════════
// LEER CSV
// ══════════════════════════════════════════════════════════════
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        header('Location: gestionar_fichas.php?tab=importar&error=lectura');
        exit;
    }

    $primera     = fgets($handle);
    rewind($handle);
    $delimitador = (substr_count($primera, ';') > substr_count($primera, ',')) ? ';' : ',';

    $cabecera = null;
    while (($row = fgetcsv($handle, 1000, $delimitador)) !== false) {
        if ($cabecera === null) {
            $cabecera = array_map('strtolower', array_map('trim', $row));
            continue;
        }
        if (count($row) < 5) continue;
        $filas[] = array_combine($cabecera, array_map('trim', $row));
    }
    fclose($handle);
}

// ══════════════════════════════════════════════════════════════
// LEER XLSX
// ══════════════════════════════════════════════════════════════
if ($ext === 'xlsx') {

    $lib_path = __DIR__ . '/../libs/SimpleXLSX.php';

    if (!file_exists($lib_path)) {
        header('Location: gestionar_fichas.php?tab=importar&error=xlsx_lib');
        exit;
    }

    require_once '../libs/SimpleXLSX.php';

    if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
        $rows_raw = $xlsx->rows(0);
        $cabecera = null;
        foreach ($rows_raw as $row) {
            $row = array_map('trim', $row);
            if ($cabecera === null) {
                $cabecera = array_map('strtolower', $row);
                continue;
            }
            if (count($row) < 5) continue;
            $filas[] = array_combine($cabecera, $row);
        }
    } else {
        header('Location: gestionar_fichas.php?tab=importar&error=xlsx_parse');
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
// FUNCIÓN PARA NORMALIZAR FECHA — acepta CUALQUIER formato común
// ══════════════════════════════════════════════════════════════
function normalizarFecha($valor) {
    $valor = trim((string)$valor);

    if ($valor === '') return false;

    // ── 1. Número serial de Excel (ej. 45678) ────────────────
    if (is_numeric($valor)) {
        $serial = (float)$valor;
        $unix   = ($serial - 25569) * 86400;
        $fecha  = gmdate('Y-m-d', (int)$unix);
        if ($fecha >= '1900-01-01' && $fecha <= '2100-12-31') {
            return $fecha;
        }
        return false;
    }

    // ── 2. Lista de formatos a probar ────────────────────────
    $formatos = [
        'Y-m-d',
        'd/m/Y',
        'j/n/Y',
        'd-m-Y',
        'j-n-Y',
        'd.m.Y',
        'j.n.Y',
        'd/m/y',
        'j/n/y',
        'd-m-y',
        'Y/m/d',
        'Y/n/j',
        'm/d/Y',
        'n/j/Y',
        'd M Y',
        'd F Y',
        'F d, Y',
    ];

    foreach ($formatos as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $valor);
        if ($dt === false) continue;

        $errores = DateTime::getLastErrors();
        if ($errores && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
            continue;
        }

        $resultado = $dt->format('Y-m-d');
        if ($resultado >= '1900-01-01' && $resultado <= '2100-12-31') {
            return $resultado;
        }
    }

    // ── 3. Último recurso: strtotime ─────────────────────────
    $ts = @strtotime($valor);
    if ($ts !== false && $ts > 0) {
        $resultado = date('Y-m-d', $ts);
        if ($resultado >= '1900-01-01' && $resultado <= '2100-12-31') {
            return $resultado;
        }
    }

    return false;
}

// ══════════════════════════════════════════════════════════════
// FUNCIÓN PARA NORMALIZAR JORNADA
// Valores canónicos en BD: Diurna | Mixta | Noche
// ══════════════════════════════════════════════════════════════
function normalizarJornada($valor) {
    $v = mb_strtolower(trim($valor), 'UTF-8');

    $mapa = [
        // → Diurna
        'diurna'     => 'Diurna',
        'dia'        => 'Diurna',
        'día'        => 'Diurna',
        'mañana'     => 'Diurna',
        'manana'     => 'Diurna',
        // → Mixta
        'mixta'      => 'Mixta',
        'tarde'      => 'Mixta',
        'vespertina' => 'Mixta',
        // → Noche
        'noche'      => 'Noche',
        'nocturna'   => 'Noche',
        'nocturno'   => 'Noche',
    ];

    return $mapa[$v] ?? null;  // null = jornada no reconocida
}

// ══════════════════════════════════════════════════════════════
// CAMPOS REQUERIDOS
// ══════════════════════════════════════════════════════════════
$campos_req = ['numero_ficha', 'programa', 'jornada', 'fecha_inicio', 'fecha_fin'];

// ══════════════════════════════════════════════════════════════
// PREPARED STATEMENTS
// ══════════════════════════════════════════════════════════════
$stmtCheck = $conexion->prepare("SELECT id FROM fichas WHERE numero_ficha = ?");
$stmtIns   = $conexion->prepare("
    INSERT INTO fichas (numero_ficha, programa, jornada, fecha_inicio, fecha_fin)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($filas as $i => $fila) {
    $filas_leidas++;
    $linea = $i + 2;

    // ── Verificar campos requeridos ──
    $faltantes = [];
    foreach ($campos_req as $campo) {
        if (!isset($fila[$campo]) || trim($fila[$campo]) === '') {
            $faltantes[] = $campo;
        }
    }
    if ($faltantes) {
        $errores[] = "Fila {$linea}: faltan columnas – " . implode(', ', $faltantes);
        continue;
    }

    // ── Normalizar fechas ──
    $f_ini = normalizarFecha($fila['fecha_inicio']);
    $f_fin = normalizarFecha($fila['fecha_fin']);

    if (!$f_ini) {
        $errores[] = "Fila {$linea}: fecha_inicio inválida – '{$fila['fecha_inicio']}'";
        continue;
    }
    if (!$f_fin) {
        $errores[] = "Fila {$linea}: fecha_fin inválida – '{$fila['fecha_fin']}'";
        continue;
    }

    // ── Normalizar jornada ──
    $jorn = normalizarJornada($fila['jornada']);
    if ($jorn === null) {
        $errores[] = "Fila {$linea}: jornada no reconocida – '{$fila['jornada']}' (use Diurna, Mixta o Noche)";
        continue;
    }

    // ── Verificar duplicado ──
    $num = trim($fila['numero_ficha']);
    $stmtCheck->bind_param("s", $num);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result->num_rows > 0) {
        $duplicados++;
        continue;
    }

    // ── Insertar ──
    $prog = trim($fila['programa']);
    $stmtIns->bind_param("sssss", $num, $prog, $jorn, $f_ini, $f_fin);

    if ($stmtIns->execute()) {
        $insertados++;
    } else {
        $errores[] = "Fila {$linea}: error de base de datos – " . $stmtIns->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado Importación</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:#f6f7fb; --surface:#fff; --border:#e4e8ef;
            --text-primary:#0f172a; --text-secondary:#64748b;
            --accent:#4f46e5; --accent-light:#eef2ff;
            --success:#059669; --success-light:#d1fae5;
            --warning:#d97706; --warning-light:#fef3c7;
            --danger:#dc2626; --danger-light:#fee2e2;
            --mono:'DM Mono',monospace; --sans:'DM Sans',sans-serif;
        }
        body { font-family: var(--sans); background: var(--bg); color: var(--text-primary); min-height: 100vh; padding: 2rem 1.5rem; }
        .container { max-width: 720px; margin: 0 auto; }
        .result-header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.75rem; }
        .result-header h1 { font-size: 1.3rem; font-weight: 700; }
        .stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .stat-card__num { font-size: 2rem; font-weight: 800; margin-bottom: .2rem; }
        .stat-card__label { font-size: .8rem; color: var(--text-secondary); font-weight: 500; }
        .stat-card--ok   .stat-card__num { color: var(--success); }
        .stat-card--dup  .stat-card__num { color: var(--warning); }
        .stat-card--err  .stat-card__num { color: var(--danger); }
        .errors-box { background: var(--surface); border: 1px solid #fca5a5; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .errors-box h3 { font-size: .9rem; font-weight: 700; color: var(--danger); margin-bottom: .75rem; }
        .errors-box ul { list-style: none; display: flex; flex-direction: column; gap: .4rem; }
        .errors-box li { font-size: .82rem; font-family: var(--mono); color: #7f1d1d; background: var(--danger-light); padding: .35rem .65rem; border-radius: 5px; }
        .btn-primary { display: inline-flex; align-items: center; gap: .45rem; padding: .65rem 1.4rem; background: var(--accent); color: #fff; border-radius: 7px; font-family: var(--sans); font-size: .9rem; font-weight: 600; text-decoration: none; transition: background .15s; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { display: inline-flex; align-items: center; gap: .45rem; padding: .65rem 1.1rem; background: var(--surface); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 7px; font-family: var(--sans); font-size: .88rem; text-decoration: none; transition: background .15s; }
        .btn-secondary:hover { background: var(--bg); }
        .actions { display: flex; gap: .75rem; flex-wrap: wrap; }
        @media(max-width:500px){ .stats-grid{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="container">
    <div class="result-header">
        <h1>Resultado de importación</h1>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-card--ok">
            <div class="stat-card__num"><?= $insertados ?></div>
            <div class="stat-card__label">Fichas insertadas</div>
        </div>
        <div class="stat-card stat-card--dup">
            <div class="stat-card__num"><?= $duplicados ?></div>
            <div class="stat-card__label">Duplicadas (omitidas)</div>
        </div>
        <div class="stat-card stat-card--err">
            <div class="stat-card__num"><?= count($errores) ?></div>
            <div class="stat-card__label">Errores</div>
        </div>
    </div>

    <?php if ($errores): ?>
    <div class="errors-box">
        <h3>Detalle de errores (<?= count($errores) ?>)</h3>
        <ul>
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="actions">
        <a href="gestionar_fichas.php" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Volver a Gestionar Fichas
        </a>
        <a href="programacion_fichas.php" class="btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            Ver Programación
        </a>
    </div>
</div>
</body>
</html> 