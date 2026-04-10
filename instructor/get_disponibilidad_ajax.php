<?php
/*
 * get_disponibilidad_ajax.php  (v3 — sin campo capacidad)
 *
 * Retorna JSON con la disponibilidad de todos los ambientes habilitados
 * para UNA fecha puntual  O  para un rango recurrente de fechas.
 *
 * ── Modo simple ──────────────────────────────────────────────────────
 * GET params:
 *   fecha       → Y-m-d          (fecha única)
 *   hora_ini    → HH:MM
 *   hora_fin    → HH:MM
 *
 * ── Modo recurrente ──────────────────────────────────────────────────
 * GET params:
 *   recurrente  → "1"
 *   fecha_ini   → Y-m-d          (inicio del rango)
 *   fecha_fin   → Y-m-d          (fin del rango)
 *   hora_ini    → HH:MM
 *   hora_fin    → HH:MM
 *   dias[]      → 0-6 (lunes=0 … domingo=6)
 *
 * ── Respuesta JSON ───────────────────────────────────────────────────
 * [
 *   {
 *     id, nombre_ambiente, horario_disponible,
 *     libre           : bool
 *     conflictos      : [{ fecha, instructor, hora_ini, hora_fin, estado }]
 *     fechas_libres   : ["Y-m-d", ...]
 *     fechas_ocupadas : ["Y-m-d", ...]
 *     total_fechas    : int
 *   }, ...
 * ]
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
include("../includes/conexion.php");

/* ══════════════════════════════════════════════════════════
   LOAD AMBIENTES (público - sin verificación de rol)
   ══════════════════════════════════════════════════════════ */
if (isset($_GET['load_ambientes'])) {
    $stAmb = $conexion->prepare("SELECT id, nombre_ambiente FROM ambientes WHERE estado = 'Habilitado' ORDER BY nombre_ambiente");
    $stAmb->execute();
    $ambientes = $stAmb->get_result()->fetch_all(MYSQLI_ASSOC);
    $stAmb->close();
    echo json_encode($ambientes);
    exit;
}

/* ══════════════════════════════════════════════════════════
   VERIFICAR SESIÓN PARA CONSULTAS DE DISPONIBILIDAD
   ══════════════════════════════════════════════════════════ */
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['instructor', 'subdireccion', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

/* ══════════════════════════════════════════════════════════
   1. LEER Y VALIDAR PARÁMETROS
   ══════════════════════════════════════════════════════════ */
$modo_recurrente = ($_GET['recurrente'] ?? '0') === '1';

$hora_ini = trim($_GET['hora_ini'] ?? '');
$hora_fin = trim($_GET['hora_fin'] ?? '');

if (!preg_match('/^\d{2}:\d{2}$/', $hora_ini)) { echo json_encode(['error' => 'Hora inicio inválida']); exit; }
if (!preg_match('/^\d{2}:\d{2}$/', $hora_fin)) { echo json_encode(['error' => 'Hora fin inválida']);   exit; }
if ($hora_fin <= $hora_ini)                     { echo json_encode(['error' => 'Hora fin debe ser mayor que hora inicio']); exit; }

$fechas_a_verificar = [];

if (!$modo_recurrente) {
    /* ── Modo simple: una sola fecha ── */
    $fecha = trim($_GET['fecha'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { echo json_encode(['error' => 'Fecha inválida']); exit; }
    if ($fecha < date('Y-m-d'))                         { echo json_encode(['error' => 'La fecha no puede ser pasada']); exit; }
    $fechas_a_verificar = [$fecha];

} else {
    /* ── Modo recurrente ── */
    $fecha_ini_rango = trim($_GET['fecha_ini'] ?? '');
    $fecha_fin_rango = trim($_GET['fecha_fin'] ?? '');
    $dias_raw        = $_GET['dias'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_ini_rango)) { echo json_encode(['error' => 'Fecha inicio rango inválida']); exit; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin_rango)) { echo json_encode(['error' => 'Fecha fin rango inválida']);    exit; }
    if ($fecha_fin_rango < $fecha_ini_rango)                     { echo json_encode(['error' => 'Fecha fin rango debe ser >= fecha inicio']); exit; }
    if ($fecha_ini_rango < date('Y-m-d'))                        { echo json_encode(['error' => 'El rango no puede iniciar en el pasado']); exit; }

    $dias_permitidos = array_unique(array_map('intval', (array) $dias_raw));
    $dias_permitidos = array_filter($dias_permitidos, fn($d) => $d >= 0 && $d <= 6);

    if (empty($dias_permitidos)) { echo json_encode(['error' => 'Debes seleccionar al menos un día de semana']); exit; }

    $dt_ini = new DateTime($fecha_ini_rango);
    $dt_fin = new DateTime($fecha_fin_rango);
    if ($dt_ini->diff($dt_fin)->days > 366) {
        echo json_encode(['error' => 'El rango no puede superar 1 año']); exit;
    }

    // Expandir fechas según días de semana
    // PHP date('N'): 1=Lun … 7=Dom → nuestra conv: 0=Lun … 6=Dom
    $dt_cur = clone $dt_ini;
    while ($dt_cur <= $dt_fin) {
        $dow_php = (int) $dt_cur->format('N') - 1;
        if (in_array($dow_php, $dias_permitidos)) {
            $fechas_a_verificar[] = $dt_cur->format('Y-m-d');
        }
        $dt_cur->modify('+1 day');
    }

    if (empty($fechas_a_verificar)) {
        echo json_encode(['error' => 'Ningún día del rango coincide con los días seleccionados']); exit;
    }
    if (count($fechas_a_verificar) > 200) {
        echo json_encode(['error' => 'Demasiadas fechas en el rango. Reduce el período o los días.']); exit;
    }
}

/* ══════════════════════════════════════════════════════════
   2. CARGAR AMBIENTES HABILITADOS (sin capacidad)
   ══════════════════════════════════════════════════════════ */
$stAmb = $conexion->prepare("
    SELECT id, nombre_ambiente, horario_disponible
    FROM ambientes
    WHERE estado = 'Habilitado'
    ORDER BY nombre_ambiente
");
$stAmb->execute();
$ambientes = $stAmb->get_result()->fetch_all(MYSQLI_ASSOC);
$stAmb->close();

/* ══════════════════════════════════════════════════════════
   3. VERIFICAR CONFLICTOS POR AMBIENTE
   ══════════════════════════════════════════════════════════ */
$min_fecha = min($fechas_a_verificar);
$max_fecha = max($fechas_a_verificar);

$resultado = [];

foreach ($ambientes as $amb) {
    $id_amb = (int) $amb['id'];

    /*
     * Buscar reservas (Aprobado o Pendiente) que:
     *  - Sean del ambiente actual
     *  - Solapen en hora con [hora_ini, hora_fin)
     *  - Cuyo rango de fechas se cruce con [min_fecha, max_fecha]
     */
    $stOc = $conexion->prepare("
        SELECT
            aa.fecha_inicio,
            aa.fecha_fin,
            aa.hora_inicio,
            aa.hora_final,
            aa.estado,
            CONCAT(i.nombre, ' ', COALESCE(i.apellido,'')) AS instructor
        FROM autorizaciones_ambientes aa
        JOIN instructores i ON aa.id_instructor = i.id
        WHERE aa.id_ambiente = ?
          AND aa.estado IN ('Aprobado','Pendiente')
          AND aa.hora_inicio < ?
          AND aa.hora_final  > ?
          AND aa.fecha_inicio <= ?
          AND aa.fecha_fin   >= ?
        ORDER BY aa.fecha_inicio ASC
    ");
    $stOc->bind_param('issss', $id_amb, $hora_fin, $hora_ini, $max_fecha, $min_fecha);
    $stOc->execute();
    $reservas_raw = $stOc->get_result()->fetch_all(MYSQLI_ASSOC);
    $stOc->close();

    /*
     * Para cada reserva existente, determinar con cuáles de las
     * $fechas_a_verificar colisiona:
     *   F >= reserva.fecha_inicio  AND  F <= reserva.fecha_fin
     */
    $fechas_ocupadas = [];
    $conflictos      = [];

    foreach ($reservas_raw as $res) {
        $fi = $res['fecha_inicio'];
        $ff = $res['fecha_fin'];

        foreach ($fechas_a_verificar as $f) {
            if ($f >= $fi && $f <= $ff) {
                $fechas_ocupadas[] = $f;
                $conflictos[] = [
                    'fecha'      => $f,
                    'instructor' => trim($res['instructor']),
                    'hora_ini'   => date('h:i A', strtotime($res['hora_inicio'])),
                    'hora_fin'   => date('h:i A', strtotime($res['hora_final'])),
                    'estado'     => $res['estado'],
                ];
            }
        }
    }

    $fechas_ocupadas = array_values(array_unique($fechas_ocupadas));
    sort($fechas_ocupadas);
    $fechas_libres   = array_values(array_diff($fechas_a_verificar, $fechas_ocupadas));
    sort($fechas_libres);

    $resultado[] = [
        'id'                 => $id_amb,
        'nombre_ambiente'    => $amb['nombre_ambiente'],
        'horario_disponible' => $amb['horario_disponible'],
        'libre'              => empty($fechas_ocupadas),
        'conflictos'         => $conflictos,
        'fechas_libres'      => $fechas_libres,
        'fechas_ocupadas'    => $fechas_ocupadas,
        'total_fechas'       => count($fechas_a_verificar),
    ];
}

/* Ordenar: libres → parciales → ocupados; dentro de cada grupo, alfabético */
usort($resultado, function ($a, $b) {
    $score_a = empty($a['fechas_ocupadas']) ? 0 : (count($a['fechas_libres']) > 0 ? 1 : 2);
    $score_b = empty($b['fechas_ocupadas']) ? 0 : (count($b['fechas_libres']) > 0 ? 1 : 2);
    if ($score_a !== $score_b) return $score_a - $score_b;
    return strcmp($a['nombre_ambiente'], $b['nombre_ambiente']);
});

echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);