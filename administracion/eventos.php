<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

include("../includes/conexion.php");
header('Content-Type: application/json; charset=UTF-8');

/* ══════════════════════════════════════════════════════════
   PARÁMETROS DE FILTRO
   ══════════════════════════════════════════════════════════ */
$ambiente   = $_GET['ambiente']   ?? '';
$instructor = $_GET['instructor'] ?? '';
$estado     = $_GET['estado']     ?? '';
$start      = $_GET['start']      ?? '';
$end        = $_GET['end']        ?? '';

/* ══════════════════════════════════════════════════════════
   CONSTRUCCIÓN DINÁMICA DEL WHERE
   ══════════════════════════════════════════════════════════ */
$where  = [];
$params = [];
$types  = '';

if ($ambiente !== '' && $ambiente !== 'todos') {
    $where[]  = 'a.id = ?';
    $params[] = (int) $ambiente;
    $types   .= 'i';
}

if ($instructor !== '' && $instructor !== 'todos') {
    $where[]  = 'i.id = ?';
    $params[] = (int) $instructor;
    $types   .= 'i';
}

if ($estado !== '' && $estado !== 'todos') {
    $where[]  = 'au.estado = ?';
    $params[] = $estado;
    $types   .= 's';
}

if ($start !== '') {
    $where[]  = 'au.fecha_fin >= ?';
    $params[] = substr($start, 0, 10);
    $types   .= 's';
}

if ($end !== '') {
    $where[]  = 'au.fecha_inicio <= ?';
    $params[] = substr($end, 0, 10);
    $types   .= 's';
}

$whereStr = count($where) ? implode(' AND ', $where) : '1=1';

/* ══════════════════════════════════════════════════════════
   CONSULTA PRINCIPAL
   Incluye:
     · hora_inicio / hora_fin de ambientes (nueva estructura)
     · subconsulta para detectar solapamiento con disponibilidad_ambiente
   ══════════════════════════════════════════════════════════ */
$sql = "
    SELECT
        au.id,
        a.nombre_ambiente,
        a.hora_inicio        AS amb_hora_inicio,   -- horario base del ambiente
        a.hora_fin           AS amb_hora_fin,
        i.nombre             AS nombre_instructor,
        au.fecha_inicio,
        au.fecha_fin,
        au.hora_inicio       AS res_hora_inicio,   -- horario de la reserva
        au.hora_final        AS res_hora_fin,
        au.estado,
        au.rol_autorizado,
        au.observaciones,

        /* ── Detecta si alguna fecha de esta reserva tiene un bloque
              marcado como 'Ocupado' en disponibilidad_ambiente
              que solape con el horario de la reserva              ── */
        EXISTS (
            SELECT 1
            FROM disponibilidad_ambiente da
            WHERE da.id_ambiente  = a.id
              AND da.fecha        BETWEEN au.fecha_inicio AND au.fecha_fin
              AND da.hora_inicio  < au.hora_final
              AND da.hora_fin     > au.hora_inicio
              AND da.estado       = 'Ocupado'
        ) AS tiene_conflicto

    FROM autorizaciones_ambientes au
    JOIN ambientes    a ON au.id_ambiente   = a.id
    JOIN instructores i ON au.id_instructor = i.id
    WHERE $whereStr
    ORDER BY au.fecha_inicio ASC, au.hora_inicio ASC
";

/* ── Ejecutar con prepared statement ── */
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'error'   => 'Error al preparar consulta',
        'detalle' => $conexion->error,
    ]);
    exit;
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
    echo json_encode([
        'error'   => 'Error al ejecutar consulta',
        'detalle' => $stmt->error,
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   MAPEO DE FILAS → EVENTOS FULLCALENDAR
   ══════════════════════════════════════════════════════════ */
$events = [];

while ($row = $res->fetch_assoc()) {

    /* ── Color base según estado ── */
    switch ($row['estado']) {
        case 'Aprobado':
            $color  = '#2e7d32';
            $border = '#1b5e20';
            $text   = '#ffffff';
            break;
        case 'Pendiente':
            $color  = '#f9a825';
            $border = '#f57f17';
            $text   = '#1a1a1a';
            break;
        case 'Rechazado':
            $color  = '#c62828';
            $border = '#b71c1c';
            $text   = '#ffffff';
            break;
        default:
            $color  = '#757575';
            $border = '#424242';
            $text   = '#ffffff';
    }

    /* ── Si hay conflicto con disponibilidad_ambiente → tono apagado ── */
    if ($row['tiene_conflicto']) {
        $border = '#b71c1c';          // borde rojo llamativo
        $color  = $color . 'cc';      // misma paleta, semitransparente
    }

    /* ── Horarios en formato 24 h (TIME ya viene como HH:MM:SS) ── */
    $h_ini_24 = substr($row['res_hora_inicio'], 0, 5); // "HH:MM"
    $h_fin_24 = substr($row['res_hora_fin'],    0, 5);

    /* ── ISO 8601 para FullCalendar ── */
    $start_dt = $row['fecha_inicio'] . 'T' . $row['res_hora_inicio'];
    $end_dt   = $row['fecha_fin']    . 'T' . $row['res_hora_fin'];

    /* ── Horario base del ambiente (nueva estructura, 24 h) ── */
    $amb_ini_24 = $row['amb_hora_inicio'] ? substr($row['amb_hora_inicio'], 0, 5) : null;
    $amb_fin_24 = $row['amb_hora_fin']    ? substr($row['amb_hora_fin'],    0, 5) : null;

    $events[] = [
        'id'              => $row['id'],
        'title'           => $row['nombre_ambiente'] . ' — ' . $row['nombre_instructor'],
        'start'           => $start_dt,
        'end'             => $end_dt,
        'backgroundColor' => $color,
        'borderColor'     => $border,
        'textColor'       => $text,
        'extendedProps'   => [
            'ambiente'         => $row['nombre_ambiente'],
            'instructor'       => $row['nombre_instructor'],
            'estado'           => $row['estado'],
            /* horarios de la reserva en 24 h */
            'hora_inicio'      => $h_ini_24,
            'hora_fin'         => $h_fin_24,
            /* horario base del ambiente (puede ser null si no está definido) */
            'amb_hora_inicio'  => $amb_ini_24,
            'amb_hora_fin'     => $amb_fin_24,
            'autorizado'       => $row['rol_autorizado'],
            'obs'              => $row['observaciones'] ?? '',
            'tiene_conflicto'  => (bool) $row['tiene_conflicto'],
        ],
    ];
}

$stmt->close();

echo json_encode($events, JSON_UNESCAPED_UNICODE);