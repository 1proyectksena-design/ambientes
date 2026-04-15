<?php
/*
 * get_disponibilidad_ajax.php
 * Verifica disponibilidad usando:
 *   - autorizaciones_ambientes (reservas aprobadas/pendientes)
 *   - disponibilidad_ambiente  (bloques explícitos 'Ocupado')
 * Horarios en formato 24 h. Sin columnas horario_fijo / horario_disponible.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
include("../includes/conexion.php");

/* ── Carga pública de ambientes para dropdowns ── */
if (isset($_GET['load_ambientes'])) {
    $st = $conexion->prepare(
        "SELECT id, nombre_ambiente
         FROM ambientes
         WHERE estado = 'Habilitado'
         ORDER BY nombre_ambiente"
    );
    $st->execute();
    echo json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC));
    $st->close();
    exit;
}

/* ── Validación de sesión ── */
$es_publico = true;
if (!$es_publico) {
    $roles_ok = ['instructor', 'subdireccion', 'admin', 'administracion'];
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_ok)) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
}

/* ══════════════════════════════════════════════════════════
   1. PARÁMETROS Y VALIDACIÓN
   ══════════════════════════════════════════════════════════ */
$modo_recurrente = ($_GET['recurrente'] ?? '0') === '1';
$hora_ini = trim($_GET['hora_ini'] ?? '');
$hora_fin = trim($_GET['hora_fin'] ?? '');

if (!preg_match('/^\d{2}:\d{2}$/', $hora_ini)) { echo json_encode(['error' => 'Hora inicio inválida']); exit; }
if (!preg_match('/^\d{2}:\d{2}$/', $hora_fin))  { echo json_encode(['error' => 'Hora fin inválida']);   exit; }
if ($hora_fin <= $hora_ini)                      { echo json_encode(['error' => 'Hora fin debe ser mayor que hora inicio']); exit; }

/* Normalizar a HH:MM:SS para comparar con columnas TIME de MySQL */
$hora_ini_sql = $hora_ini . ':00';
$hora_fin_sql = $hora_fin . ':00';

$fechas_a_verificar = [];

if (!$modo_recurrente) {
    $fecha = trim($_GET['fecha'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { echo json_encode(['error' => 'Fecha inválida']); exit; }
    if ($fecha < date('Y-m-d'))                         { echo json_encode(['error' => 'La fecha no puede ser pasada']); exit; }
    $fechas_a_verificar = [$fecha];
} else {
    $fecha_ini_rango = trim($_GET['fecha_ini'] ?? '');
    $fecha_fin_rango = trim($_GET['fecha_fin'] ?? '');
    $dias_raw        = $_GET['dias'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_ini_rango)) { echo json_encode(['error' => 'Fecha inicio rango inválida']); exit; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin_rango)) { echo json_encode(['error' => 'Fecha fin rango inválida']);    exit; }
    if ($fecha_fin_rango < $fecha_ini_rango)                     { echo json_encode(['error' => 'Fecha fin rango debe ser >= fecha inicio']); exit; }
    if ($fecha_ini_rango < date('Y-m-d'))                        { echo json_encode(['error' => 'El rango no puede iniciar en el pasado']); exit; }

    $dias_permitidos = array_unique(array_map('intval', (array) $dias_raw));
    $dias_permitidos = array_filter($dias_permitidos, fn($d) => $d >= 0 && $d <= 6);
    if (empty($dias_permitidos)) { echo json_encode(['error' => 'Debes seleccionar al menos un día']); exit; }

    $dt_ini = new DateTime($fecha_ini_rango);
    $dt_fin = new DateTime($fecha_fin_rango);
    if ($dt_ini->diff($dt_fin)->days > 366) { echo json_encode(['error' => 'El rango no puede superar 1 año']); exit; }

    $dt_cur = clone $dt_ini;
    while ($dt_cur <= $dt_fin) {
        $dow = (int) $dt_cur->format('N') - 1; // 0=Lun … 6=Dom
        if (in_array($dow, $dias_permitidos)) {
            $fechas_a_verificar[] = $dt_cur->format('Y-m-d');
        }
        $dt_cur->modify('+1 day');
    }

    if (empty($fechas_a_verificar))         { echo json_encode(['error' => 'Ningún día del rango coincide con los días seleccionados']); exit; }
    if (count($fechas_a_verificar) > 200)   { echo json_encode(['error' => 'Demasiadas fechas. Reduce el período.']); exit; }
}

/* ══════════════════════════════════════════════════════════
   2. CARGAR AMBIENTES — usa hora_inicio / hora_fin
   ══════════════════════════════════════════════════════════ */
$stAmb = $conexion->prepare(
    "SELECT id, nombre_ambiente,
            TIME_FORMAT(hora_inicio, '%H:%i') AS fmt_hora_inicio,
            TIME_FORMAT(hora_fin,    '%H:%i') AS fmt_hora_fin
     FROM ambientes
     WHERE estado = 'Habilitado'
     ORDER BY nombre_ambiente"
);
$stAmb->execute();
$ambientes = $stAmb->get_result()->fetch_all(MYSQLI_ASSOC);
$stAmb->close();

/* ══════════════════════════════════════════════════════════
   3. VERIFICAR CONFLICTOS POR AMBIENTE
      Fuentes de conflicto:
        A) autorizaciones_ambientes  (estado Aprobado o Pendiente)
        B) disponibilidad_ambiente   (estado Ocupado)
   ══════════════════════════════════════════════════════════ */
$min_fecha = min($fechas_a_verificar);
$max_fecha = max($fechas_a_verificar);

$resultado = [];

foreach ($ambientes as $amb) {
    $id_amb = (int) $amb['id'];

    /* ── A) Reservas de autorizaciones_ambientes ── */
    $stAu = $conexion->prepare("
        SELECT au.fecha_inicio,
               au.fecha_fin,
               au.hora_inicio,
               au.hora_final,
               au.estado,
               CONCAT(i.nombre, ' ', COALESCE(i.apellido,'')) AS instructor
        FROM autorizaciones_ambientes au
        JOIN instructores i ON au.id_instructor = i.id
        WHERE au.id_ambiente  = ?
          AND au.estado       IN ('Aprobado','Pendiente')
          AND au.hora_inicio  < ?
          AND au.hora_final   > ?
          AND au.fecha_inicio <= ?
          AND au.fecha_fin    >= ?
        ORDER BY au.fecha_inicio ASC
    ");
    $stAu->bind_param('issss', $id_amb, $hora_fin_sql, $hora_ini_sql, $max_fecha, $min_fecha);
    $stAu->execute();
    $reservas_au = $stAu->get_result()->fetch_all(MYSQLI_ASSOC);
    $stAu->close();

    /* ── B) Bloques explícitos de disponibilidad_ambiente ── */
    $stDa = $conexion->prepare("
        SELECT fecha,
               hora_inicio,
               hora_fin
        FROM disponibilidad_ambiente
        WHERE id_ambiente  = ?
          AND estado       = 'Ocupado'
          AND hora_inicio  < ?
          AND hora_fin     > ?
          AND fecha        BETWEEN ? AND ?
        ORDER BY fecha ASC
    ");
    $stDa->bind_param('issss', $id_amb, $hora_fin_sql, $hora_ini_sql, $min_fecha, $max_fecha);
    $stDa->execute();
    $bloques_da = $stDa->get_result()->fetch_all(MYSQLI_ASSOC);
    $stDa->close();

    /* ── Cruzar ambas fuentes con las fechas a verificar ── */
    $fechas_ocupadas = [];
    $conflictos      = [];

    /* Fuente A: rangos de autorizaciones */
    foreach ($reservas_au as $res) {
        foreach ($fechas_a_verificar as $f) {
            if ($f >= $res['fecha_inicio'] && $f <= $res['fecha_fin']) {
                $fechas_ocupadas[] = $f;
                $conflictos[] = [
                    'fecha'      => $f,
                    'instructor' => trim($res['instructor']),
                    'hora_ini'   => date('H:i', strtotime($res['hora_inicio'])),
                    'hora_fin'   => date('H:i', strtotime($res['hora_final'])),
                    'estado'     => $res['estado'],
                    'fuente'     => 'reserva',
                ];
            }
        }
    }

    /* Fuente B: bloques de disponibilidad_ambiente */
    foreach ($bloques_da as $bloque) {
        if (in_array($bloque['fecha'], $fechas_a_verificar)) {
            $fechas_ocupadas[] = $bloque['fecha'];
            $conflictos[] = [
                'fecha'      => $bloque['fecha'],
                'instructor' => '—',
                'hora_ini'   => date('H:i', strtotime($bloque['hora_inicio'])),
                'hora_fin'   => date('H:i', strtotime($bloque['hora_fin'])),
                'estado'     => 'Bloqueado',
                'fuente'     => 'disponibilidad',
            ];
        }
    }

    $fechas_ocupadas = array_values(array_unique($fechas_ocupadas));
    sort($fechas_ocupadas);
    $fechas_libres = array_values(array_diff($fechas_a_verificar, $fechas_ocupadas));
    sort($fechas_libres);

    $resultado[] = [
        'id'              => $id_amb,
        'nombre_ambiente' => $amb['nombre_ambiente'],
        'hora_inicio'     => $amb['fmt_hora_inicio'],  // 24 h
        'hora_fin'        => $amb['fmt_hora_fin'],      // 24 h
        'libre'           => empty($fechas_ocupadas),
        'conflictos'      => $conflictos,
        'fechas_libres'   => $fechas_libres,
        'fechas_ocupadas' => $fechas_ocupadas,
        'total_fechas'    => count($fechas_a_verificar),
    ];
}

/* Ordenar: libres → parciales → completamente ocupados */
usort($resultado, function ($a, $b) {
    $sa = empty($a['fechas_ocupadas']) ? 0 : (count($a['fechas_libres']) > 0 ? 1 : 2);
    $sb = empty($b['fechas_ocupadas']) ? 0 : (count($b['fechas_libres']) > 0 ? 1 : 2);
    return $sa !== $sb ? $sa - $sb : strcmp($a['nombre_ambiente'], $b['nombre_ambiente']);
});

echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);