<?php
session_start();
include("../includes/conexion.php");

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'instructor') {
    header("Location: ../login.php");
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   AJAX ▸ Autocompletar instructores
═══════════════════════════════════════════════════════════════════════ */
if (isset($_GET['buscar_instructor'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) { echo json_encode([]); exit; }
    $like = '%' . $q . '%';
    $stmt = $conexion->prepare(
        "SELECT id, nombre, identificacion
         FROM instructores
         WHERE identificacion LIKE ? OR nombre LIKE ?
         ORDER BY nombre LIMIT 10"
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   AJAX ▸ Datos del calendario
═══════════════════════════════════════════════════════════════════════ */
if (isset($_GET['get_cal'])) {
    header('Content-Type: application/json; charset=utf-8');
    $fi = trim($_GET['fi'] ?? date('Y-m-d'));
    $ff = trim($_GET['ff'] ?? date('Y-m-d', strtotime('+90 days')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi)) $fi = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff)) $ff = date('Y-m-d', strtotime('+90 days'));

    $stA = $conexion->query(
        "SELECT id, nombre_ambiente, horario_disponible
         FROM ambientes WHERE estado = 'Habilitado'
         ORDER BY nombre_ambiente"
    );
    $ambientes = $stA->fetch_all(MYSQLI_ASSOC);

    $stR = $conexion->prepare(
        "SELECT id_ambiente, fecha_inicio, fecha_fin,
                TIME_FORMAT(hora_inicio,'%H:%i') AS hora_inicio,
                TIME_FORMAT(hora_final, '%H:%i') AS hora_final
         FROM autorizaciones_ambientes
         WHERE estado IN ('Aprobado','Pendiente')
           AND fecha_inicio <= ? AND fecha_fin >= ?"
    );
    $stR->bind_param('ss', $ff, $fi);
    $stR->execute();
    $reservas = $stR->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ambientes' => $ambientes, 'reservas' => $reservas], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   POST ▸ Insertar solicitud
═══════════════════════════════════════════════════════════════════════ */
$msg_success = $msg_error = '';
$inserted_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar'])) {
    $id_instructor  = (int)($_POST['id_instructor']  ?? 0);
    $id_ambiente    = (int)($_POST['id_ambiente']    ?? 0);
    $tipo_solicitud = trim($_POST['tipo_solicitud']  ?? 'unico');
    $observaciones  = trim($_POST['observaciones']   ?? '');
    $hora_ini       = trim($_POST['hora_ini']        ?? '');
    $hora_fin       = trim($_POST['hora_fin']        ?? '');

    $err = '';

    if (!$id_instructor || !$id_ambiente || !$hora_ini || !$hora_fin)
        $err = 'Datos incompletos. Por favor reinicie el proceso.';
    elseif ($hora_ini >= $hora_fin)
        $err = 'La hora fin debe ser mayor que la hora inicio.';

    if (!$err) {
        $st = $conexion->prepare("SELECT id FROM instructores WHERE id = ?");
        $st->bind_param('i', $id_instructor); $st->execute();
        if (!$st->get_result()->num_rows) $err = 'El instructor no existe en el sistema.';
    }
    if (!$err) {
        $st = $conexion->prepare("SELECT id FROM ambientes WHERE id = ? AND estado = 'Habilitado'");
        $st->bind_param('i', $id_ambiente); $st->execute();
        if (!$st->get_result()->num_rows) $err = 'El ambiente no está habilitado.';
    }

    /* MODO ÚNICO */
    if (!$err && $tipo_solicitud === 'unico') {
        $fecha = trim($_POST['fecha'] ?? '');

        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
            $err = 'Fecha inválida.';
        elseif ($fecha < date('Y-m-d'))
            $err = 'La fecha no puede ser en el pasado.';

        if (!$err) {
            $st = $conexion->prepare(
                "SELECT COUNT(*) cnt FROM autorizaciones_ambientes
                 WHERE id_ambiente = ? AND estado IN ('Aprobado','Pendiente')
                   AND hora_inicio < ? AND hora_final > ?
                   AND fecha_inicio <= ? AND fecha_fin >= ?"
            );
            $st->bind_param('issss', $id_ambiente, $hora_fin, $hora_ini, $fecha, $fecha);
            $st->execute();
            if ($st->get_result()->fetch_assoc()['cnt'] > 0)
                $err = 'El ambiente ya tiene una reserva en ese horario. Seleccione otro espacio.';
        }

        if (!$err) {
            $st = $conexion->prepare(
                "INSERT INTO autorizaciones_ambientes
                   (id_ambiente, id_instructor, rol_autorizado,
                    fecha_inicio, fecha_fin, hora_inicio, hora_final,
                    estado, observaciones, novedades)
                 VALUES (?, ?, 'instructor', ?, ?, ?, ?, 'Pendiente', ?, '')"
            );
            $st->bind_param('iisssss', $id_ambiente, $id_instructor, $fecha, $fecha, $hora_ini, $hora_fin, $observaciones);
            if ($st->execute()) {
                $inserted_count = 1;
                $msg_success = "Solicitud única enviada exitosamente. Puede revisar su estado en el panel de solicitudes.";
            } else {
                $msg_error = 'Error al guardar la solicitud. Por favor intente nuevamente.';
            }
        }
    }

    /* MODO RECURRENTE */
    elseif (!$err && $tipo_solicitud === 'recurrente') {
        $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
        $fecha_fin_r  = trim($_POST['fecha_fin_r']  ?? '');
        $dias_sel     = $_POST['dias'] ?? [];

        if (!$fecha_inicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio))
            $err = 'Fecha inicio inválida.';
        elseif (!$fecha_fin_r || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin_r))
            $err = 'Fecha fin inválida.';
        elseif ($fecha_inicio < date('Y-m-d'))
            $err = 'La fecha inicio no puede ser en el pasado.';
        elseif ($fecha_fin_r < $fecha_inicio)
            $err = 'La fecha fin debe ser mayor o igual a la fecha inicio.';
        elseif (empty($dias_sel))
            $err = 'Debe seleccionar al menos un día de la semana.';

        $dias_validos = array_map('intval', $dias_sel);
        $dias_validos = array_filter($dias_validos, fn($d) => $d >= 1 && $d <= 6);

        if (!$err && empty($dias_validos))
            $err = 'Los días seleccionados no son válidos.';

        if (!$err) {
            $fechas_a_insertar = [];
            $cursor = new DateTime($fecha_inicio);
            $limite = new DateTime($fecha_fin_r);
            $limite->modify('+1 day');

            while ($cursor < $limite) {
                $dow = (int)$cursor->format('N');
                if (in_array($dow, $dias_validos))
                    $fechas_a_insertar[] = $cursor->format('Y-m-d');
                $cursor->modify('+1 day');
            }

            if (empty($fechas_a_insertar))
                $err = 'No se encontraron fechas que coincidan con los días seleccionados en el rango indicado.';

            if (!$err) {
                $conflictos = [];
                foreach ($fechas_a_insertar as $f) {
                    $st = $conexion->prepare(
                        "SELECT COUNT(*) cnt FROM autorizaciones_ambientes
                         WHERE id_ambiente = ? AND estado IN ('Aprobado','Pendiente')
                           AND hora_inicio < ? AND hora_final > ?
                           AND fecha_inicio <= ? AND fecha_fin >= ?"
                    );
                    $st->bind_param('issss', $id_ambiente, $hora_fin, $hora_ini, $f, $f);
                    $st->execute();
                    if ($st->get_result()->fetch_assoc()['cnt'] > 0)
                        $conflictos[] = $f;
                }
                if (!empty($conflictos)) {
                    $err = 'Las siguientes fechas tienen conflicto de horario: ' .
                           implode(', ', array_slice($conflictos, 0, 5)) .
                           (count($conflictos) > 5 ? ' (y más...)' : '') .
                           '. Ajuste el rango o los días seleccionados.';
                }
            }

            if (!$err) {
                $st = $conexion->prepare(
                    "INSERT INTO autorizaciones_ambientes
                       (id_ambiente, id_instructor, rol_autorizado,
                        fecha_inicio, fecha_fin, hora_inicio, hora_final,
                        estado, observaciones, novedades)
                     VALUES (?, ?, 'instructor', ?, ?, ?, ?, 'Pendiente', ?, '')"
                );
                foreach ($fechas_a_insertar as $f) {
                    $st->bind_param('iisssss', $id_ambiente, $id_instructor, $f, $f, $hora_ini, $hora_fin, $observaciones);
                    if ($st->execute()) $inserted_count++;
                }
                if ($inserted_count > 0) {
                    $msg_success = "Se generaron {$inserted_count} solicitud(es) recurrente(s) exitosamente.";
                } else {
                    $msg_error = 'No se pudo guardar ninguna solicitud. Por favor intente nuevamente.';
                }
            }
        }
    }

    if ($err) $msg_error = $err;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitar Ambiente — SENA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --navy:      #0c1f38;
    --navy-2:    #163357;
    --green:     #1a9e50;
    --green-dk:  #137a3c;
    --green-lt:  #eaf8f0;
    --green-mid: #a8e4bf;
    --red-lt:    #fdf0ef;
    --bg:        #eef1f7;
    --surface:   #ffffff;
    --border:    #dde5f0;
    --text:      #172840;
    --muted:     #6b7e98;
    --r:         13px;
    --shadow:    0 4px 24px rgba(12,31,56,0.09);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.hidden { display: none !important; }

.hdr {
    background: var(--navy);
    height: 60px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 2px 14px rgba(0,0,0,0.28);
}
.hdr-left { display: flex; align-items: center; gap: 14px; }
.hdr-logo { height: 34px; }
.hdr-title h1 { font-size: 14px; font-weight: 700; color: #fff; }
.hdr-title p  { font-size: 11px; color: rgba(255,255,255,0.45); }
.hdr-back {
    color: rgba(255,255,255,0.75); text-decoration: none;
    font-size: 13px; font-weight: 600;
    padding: 7px 14px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.15);
    display: flex; align-items: center; gap: 6px;
    transition: all .15s;
}
.hdr-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

.steps-wrap {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
}
.steps-bar {
    max-width: 760px; margin: 0 auto;
    display: flex; align-items: center;
    padding: 16px 24px; gap: 0;
}
.step-item { display: flex; align-items: center; gap: 9px; }
.step-num {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--border); color: var(--muted);
    font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all .25s;
}
.step-lbl { font-size: 13px; font-weight: 600; color: var(--muted); white-space: nowrap; transition: color .25s; }
.step-connector { flex: 1; height: 2px; background: var(--border); margin: 0 10px; transition: background .3s; }
.step-item.active .step-num  { background: var(--green); color: #fff; }
.step-item.active .step-lbl  { color: var(--text); }
.step-item.done   .step-num  { background: var(--green-lt); color: var(--green); }
.step-item.done   .step-lbl  { color: var(--green); }
.step-connector.done { background: var(--green); }

.container { max-width: 1100px; margin: 0 auto; padding: 26px 18px 80px; }

.alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 18px; border-radius: var(--r);
    font-size: 14px; margin-bottom: 20px;
}
.alert i { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.alert-success { background: var(--green-lt); border: 1.5px solid var(--green-mid); color: #145f31; }
.alert-error   { background: var(--red-lt);   border: 1.5px solid #f5bfba;         color: #8b2117; }

.card {
    background: var(--surface);
    border-radius: var(--r); border: 1px solid var(--border);
    box-shadow: var(--shadow);
    padding: 24px 26px; margin-bottom: 18px;
}
.card-title {
    font-size: 15px; font-weight: 700; color: var(--navy);
    display: flex; align-items: center; gap: 9px;
    padding-bottom: 15px; border-bottom: 2px solid var(--green-lt);
    margin-bottom: 20px;
}
.card-title i { color: var(--green); }

.btn {
    padding: 11px 20px; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 700; font-family: inherit;
    cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    transition: all .15s; text-decoration: none;
}
.btn-navy    { background: var(--navy);  color: #fff; }
.btn-navy:hover { background: var(--navy-2); }
.btn-green   { background: var(--green); color: #fff; }
.btn-green:hover { background: var(--green-dk); }
.btn-green:disabled { background: #aacfba; cursor: not-allowed; opacity: .7; }
.btn-outline { background: transparent; color: var(--text); border: 1.5px solid var(--border); }
.btn-outline:hover { border-color: var(--navy); background: #f4f6fa; }
.btn-sm { padding: 8px 14px; font-size: 13px; }

.ac-wrap { position: relative; flex: 1; }
.search-input {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-size: 15px; font-family: inherit; background: #f6f8fc;
    transition: border-color .15s;
}
.search-input:focus { outline: none; border-color: var(--navy-2); background: #fff; }
.ac-drop {
    position: absolute; top: calc(100% + 5px); left: 0; right: 0;
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: 12px; box-shadow: 0 10px 32px rgba(0,0,0,0.13);
    z-index: 300; max-height: 260px; overflow-y: auto;
}
.ac-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; cursor: pointer; transition: background .1s;
}
.ac-item:hover { background: var(--green-lt); }
.ac-item:first-child { border-radius: 10px 10px 0 0; }
.ac-item:last-child  { border-radius: 0 0 10px 10px; }
.ac-av {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--green-lt); color: var(--green);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.ac-name { font-size: 14px; font-weight: 600; color: var(--text); }
.ac-cc   { font-size: 12px; color: var(--muted); }
.ac-empty { padding: 18px; text-align: center; color: var(--muted); font-size: 14px; }

.inst-chip {
    display: flex; align-items: center; gap: 14px;
    background: var(--green-lt); border: 1.5px solid var(--green-mid);
    border-radius: 12px; padding: 14px 18px;
}
.inst-av {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--green-dk), var(--green));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.inst-info strong { font-size: 15px; color: var(--navy); display: block; }
.inst-info span   { font-size: 13px; color: var(--muted); }
.inst-chip .check { margin-left: auto; color: var(--green); font-size: 20px; }

.inst-bar {
    display: flex; align-items: center; gap: 10px;
    background: #f4f7fb; border: 1px solid var(--border);
    border-radius: 10px; padding: 10px 14px; margin-bottom: 18px;
    font-size: 13px; color: var(--muted);
}
.inst-bar strong { color: var(--text); font-weight: 700; }
.inst-bar i { color: var(--green); }

.cal-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px; gap: 12px; flex-wrap: wrap;
}
.cal-nav { display: flex; align-items: center; gap: 8px; }
.cal-period {
    font-size: 16px; font-weight: 700; color: var(--navy);
    min-width: 200px; text-align: center;
}
.cal-views {
    display: flex; border: 1.5px solid var(--border);
    border-radius: 9px; overflow: hidden;
}
.view-btn {
    padding: 7px 18px; font-size: 13px; font-weight: 700;
    font-family: inherit; border: none; background: transparent;
    cursor: pointer; color: var(--muted); transition: all .15s;
}
.view-btn.active { background: var(--navy); color: #fff; }

.day-scroll { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
.day-grid   { min-width: 500px; }

.day-hdr { display: flex; background: #f4f7fb; border-bottom: 2px solid var(--border); }
.day-hdr-time { width: 68px; flex-shrink: 0; padding: 10px 8px; border-right: 1px solid var(--border); }
.day-hdr-amb {
    flex: 1; min-width: 150px;
    padding: 10px 12px; font-size: 12px; font-weight: 700; color: var(--navy);
    border-right: 1px solid var(--border); text-align: center;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.day-hdr-amb:last-child { border-right: none; }

.day-row { display: flex; border-bottom: 1px solid var(--border); }
.day-row:last-child { border-bottom: none; }
.day-time {
    width: 68px; flex-shrink: 0; border-right: 1px solid var(--border);
    display: flex; align-items: center; justify-content: flex-end;
    padding: 0 10px; font-size: 11px; font-weight: 700; color: var(--muted);
    height: 54px; background: #fafbfd;
}
.cal-cell {
    flex: 1; min-width: 150px; height: 54px;
    display: flex; align-items: center; justify-content: center; gap: 5px;
    font-size: 12px; font-weight: 700;
    border-right: 1px solid var(--border); transition: all .12s;
}
.cal-cell:last-child { border-right: none; }
.cal-cell.free {
    background: var(--green-lt); color: var(--green-dk); cursor: pointer;
}
.cal-cell.free:hover { background: #c4f0d5; transform: scale(1.03); z-index: 1; position: relative; }
.cal-cell.occupied   { background: var(--red-lt); color: #9e3128; cursor: not-allowed; }
.cal-cell.selected   { background: var(--green); color: #fff; cursor: pointer; }
.cal-cell.selected:hover { background: var(--green-dk); }
.cal-cell.past       { background: #f5f6f8; color: #ccd0da; cursor: not-allowed; }
.cal-cell.no-sched   { background: #fafbfd; color: #d0d5e0; cursor: not-allowed; }

.week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
.week-card {
    border: 1.5px solid var(--border); border-radius: 10px;
    overflow: hidden; cursor: pointer; transition: all .15s;
}
.week-card:hover { border-color: var(--green); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(26,158,80,0.15); }
.week-card.today  { border-color: var(--navy); }
.week-card.past   { opacity: .45; cursor: default; }
.week-card.past:hover { transform: none; box-shadow: none; border-color: var(--border); }
.week-hdr {
    background: var(--navy); color: #fff; text-align: center;
    padding: 8px 4px; font-size: 12px; font-weight: 700;
}
.week-card.today .week-hdr { background: var(--green); }
.week-body { padding: 8px 10px; }
.week-stat { font-size: 12px; display: flex; align-items: center; gap: 5px; margin-bottom: 3px; }
.week-stat.libre { color: var(--green-dk); }
.week-stat.occ   { color: #9e3128; }

.month-day-names {
    display: grid; grid-template-columns: repeat(7, 1fr);
    margin-bottom: 6px;
}
.month-day-name { text-align: center; font-size: 12px; font-weight: 700; color: var(--muted); padding: 5px 0; }
.month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
.month-day {
    border-radius: 9px; border: 1.5px solid var(--border);
    min-height: 64px; padding: 7px 8px;
    cursor: pointer; transition: all .15s; position: relative;
}
.month-day:hover:not(.past):not(.empty) { border-color: var(--green); background: var(--green-lt); }
.month-day.today  { border-color: var(--navy-2); }
.month-day.past   { opacity: .4; cursor: not-allowed; }
.month-day.empty  { border: none; background: transparent; cursor: default; }
.month-num { font-size: 14px; font-weight: 700; color: var(--text); }
.month-day.today .month-num { color: var(--navy-2); }
.month-avail { margin-top: 5px; display: flex; gap: 3px; flex-wrap: wrap; }
.m-dot { width: 7px; height: 7px; border-radius: 50%; }
.m-dot.libre { background: var(--green); }
.m-dot.occ   { background: #e05050; }

.slot-bar {
    background: var(--navy); color: #fff; border-radius: 12px;
    padding: 18px 22px; display: flex; align-items: center; gap: 16px;
    margin-top: 18px; flex-wrap: wrap;
}
.slot-bar-icon {
    width: 46px; height: 46px; border-radius: 12px;
    background: rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.slot-bar-info { flex: 1; }
.slot-bar-info strong { display: block; font-size: 15px; margin-bottom: 3px; }
.slot-bar-info span   { font-size: 13px; color: rgba(255,255,255,0.65); }
.slot-bar .btn-green  { flex-shrink: 0; }

.summary {
    background: #f6f9fc; border: 1.5px solid var(--border);
    border-radius: 12px; padding: 18px 20px; margin-bottom: 22px;
}
.summary-ttl {
    font-size: 11px; font-weight: 800; color: var(--muted);
    text-transform: uppercase; letter-spacing: .8px; margin-bottom: 14px;
}
.sum-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid var(--border);
}
.sum-row:last-child { border-bottom: none; }
.sum-icon {
    width: 34px; height: 34px; border-radius: 8px;
    background: var(--green-lt); color: var(--green);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
}
.sum-lbl { font-size: 12px; color: var(--muted); }
.sum-val { font-size: 14px; font-weight: 700; color: var(--text); }

.form-group  { margin-bottom: 18px; }
.form-label  { display: block; font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 7px; }
.form-ctrl {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-size: 14px; font-family: inherit; background: #f6f8fc;
    transition: border-color .15s;
}
.form-ctrl:focus    { outline: none; border-color: var(--navy-2); background: #fff; }
.form-ctrl[readonly]{ color: var(--muted); background: #f0f2f7; cursor: default; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-hint { font-size: 12px; color: var(--muted); margin-top: 5px; }

.cal-loading { text-align: center; padding: 56px 20px; color: var(--muted); }
.cal-loading i   { font-size: 26px; margin-bottom: 12px; display: block; }
.cal-loading span{ font-size: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }
.fa-spin { animation: spin .8s linear infinite; }

.tipo-toggle {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
    margin-bottom: 24px;
}
.tipo-option { position: relative; }
.tipo-option input[type="radio"] { display: none; }
.tipo-option label {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border-radius: 12px;
    border: 2px solid var(--border); background: #f6f8fc;
    cursor: pointer; transition: all .18s;
    font-size: 14px; font-weight: 600; color: var(--muted);
}
.tipo-option label:hover { border-color: var(--green-mid); background: var(--green-lt); color: var(--text); }
.tipo-option input:checked + label {
    border-color: var(--green); background: var(--green-lt); color: var(--green-dk);
    box-shadow: 0 0 0 3px rgba(26,158,80,0.12);
}
.tipo-icon {
    width: 38px; height: 38px; border-radius: 9px;
    background: var(--border); color: var(--muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0; transition: all .18s;
}
.tipo-option input:checked + label .tipo-icon { background: var(--green); color: #fff; }
.tipo-text strong { display: block; font-size: 14px; }
.tipo-text span   { font-size: 12px; font-weight: 400; color: var(--muted); }
.tipo-option input:checked + label .tipo-text span { color: var(--green-dk); opacity: .75; }

.dias-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
    margin-top: 8px;
}
.dia-option { position: relative; }
.dia-option input[type="checkbox"] { display: none; }
.dia-option label {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 10px 8px; border-radius: 9px;
    border: 1.5px solid var(--border); background: #f6f8fc;
    cursor: pointer; transition: all .15s;
    font-size: 13px; font-weight: 700; color: var(--muted);
    text-align: center;
}
.dia-option label:hover { border-color: var(--green-mid); background: var(--green-lt); color: var(--text); }
.dia-option input:checked + label { border-color: var(--green); background: var(--green); color: #fff; }

.recurrente-block {
    background: #f6f9fc; border: 1.5px solid var(--border);
    border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;
    animation: slideDown .2s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.recurrente-block .block-ttl {
    font-size: 12px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .7px; color: var(--muted); margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
}
.recurrente-block .block-ttl i { color: var(--green); }
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-left">
        <img src="../css/img/senab.png" alt="SENA" class="hdr-logo">
        <div class="hdr-title">
            <h1>Solicitar Ambiente</h1>
            <p>Flujo guiado de reserva</p>
        </div>
    </div>
    <a href="index.php" class="hdr-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<div class="steps-wrap">
    <div class="steps-bar">
        <div class="step-item active" id="si-1">
            <div class="step-num" id="sn-1">1</div>
            <span class="step-lbl">Instructor</span>
        </div>
        <div class="step-connector" id="sc-1"></div>
        <div class="step-item" id="si-2">
            <div class="step-num" id="sn-2">2</div>
            <span class="step-lbl">Calendario</span>
        </div>
        <div class="step-connector" id="sc-2"></div>
        <div class="step-item" id="si-3">
            <div class="step-num" id="sn-3">3</div>
            <span class="step-lbl">Espacio</span>
        </div>
        <div class="step-connector" id="sc-3"></div>
        <div class="step-item" id="si-4">
            <div class="step-num" id="sn-4">4</div>
            <span class="step-lbl">Confirmar</span>
        </div>
    </div>
</div>

<div class="container">

<?php if ($msg_success): ?>
<div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i>
    <div><strong>¡Listo!</strong> <?= htmlspecialchars($msg_success) ?></div>
</div>
<?php endif; ?>
<?php if ($msg_error): ?>
<div class="alert alert-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span><?= htmlspecialchars($msg_error) ?></span>
</div>
<?php endif; ?>

<!-- PASO 1 -->
<div id="panel-1" class="card">
    <div class="card-title"><i class="fa-solid fa-id-card"></i> Paso 1 — Identificar al Instructor</div>
    <p style="font-size:14px;color:var(--muted);margin-bottom:16px;">
        Busque al instructor por nombre o número de identificación.
    </p>
    <div style="display:flex;gap:10px;margin-bottom:14px;">
        <div class="ac-wrap">
            <input type="text" id="ac-input" class="search-input"
                   placeholder="Escriba nombre o cédula del instructor..."
                   autocomplete="off" oninput="onSearchInput(this.value)">
            <div id="ac-drop" class="ac-drop hidden"></div>
        </div>
    </div>
    <div id="inst-found" class="hidden">
        <div class="inst-chip">
            <div class="inst-av"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div class="inst-info">
                <strong id="inst-nombre-txt"></strong>
                <span id="inst-cc-txt"></span>
            </div>
            <i class="fa-solid fa-circle-check check"></i>
        </div>
        <div style="display:flex;gap:10px;margin-top:14px;">
            <button class="btn btn-green" onclick="goToStep(2)">
                <i class="fa-solid fa-calendar-days"></i> Ver Calendario
            </button>
            <button class="btn btn-outline" onclick="clearInstructor()">
                <i class="fa-solid fa-rotate-left"></i> Cambiar instructor
            </button>
        </div>
    </div>
</div>

<!-- PASO 2 -->
<div id="panel-2" class="card hidden">
    <div class="card-title"><i class="fa-solid fa-calendar-days"></i> Paso 2 — Seleccionar espacio disponible</div>
    <div class="inst-bar">
        <i class="fa-solid fa-user-check"></i>
        Instructor: <strong id="ibar-nombre"></strong>&nbsp;·&nbsp;
        <a href="#" onclick="goToStep(1);return false;" style="color:var(--green);font-size:12px;font-weight:700;">Cambiar</a>
    </div>
    <div class="cal-toolbar">
        <div class="cal-nav">
            <button class="btn btn-outline btn-sm" onclick="navCal(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="cal-period" id="cal-period">—</span>
            <button class="btn btn-outline btn-sm" onclick="navCal(1)"><i class="fa-solid fa-chevron-right"></i></button>
            <button class="btn btn-outline btn-sm" onclick="goToday()" style="font-family:inherit;font-weight:700;">Hoy</button>
        </div>
        <div class="cal-views">
            <button class="view-btn active" id="vb-day"   onclick="setView('day')">Día</button>
            <button class="view-btn"        id="vb-week"  onclick="setView('week')">Semana</button>
            <button class="view-btn"        id="vb-month" onclick="setView('month')">Mes</button>
        </div>
    </div>
    <div id="cal-content">
        <div class="cal-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Cargando disponibilidad...</span></div>
    </div>
    <div id="slot-bar" class="slot-bar hidden">
        <div class="slot-bar-icon"><i class="fa-solid fa-map-pin"></i></div>
        <div class="slot-bar-info">
            <strong id="slot-bar-txt">—</strong>
            <span>Espacio seleccionado — haga clic en «Nueva solicitud» para continuar</span>
        </div>
        <button class="btn btn-green" onclick="goToStep(4)">
            <i class="fa-solid fa-plus"></i> Nueva solicitud
        </button>
    </div>
    <div style="margin-top:16px;">
        <button class="btn btn-outline" onclick="goToStep(1)"><i class="fa-solid fa-arrow-left"></i> Volver</button>
    </div>
</div>

<!-- PASO 4 -->
<div id="panel-4" class="card hidden">
    <div class="card-title"><i class="fa-solid fa-paper-plane"></i> Paso 4 — Confirmar solicitud</div>
    <div class="inst-bar">
        <i class="fa-solid fa-user-check"></i>
        Instructor: <strong id="ibar2-nombre"></strong>
    </div>

    <div class="summary">
        <div class="summary-ttl">Resumen de la reserva</div>
        <div class="sum-row">
            <div class="sum-icon"><i class="fa-solid fa-building"></i></div>
            <div><div class="sum-lbl">Ambiente</div><div class="sum-val" id="sum-ambiente">—</div></div>
        </div>
        <div class="sum-row" id="sum-tipo-row">
            <div class="sum-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div><div class="sum-lbl">Tipo de solicitud</div><div class="sum-val" id="sum-tipo">Único</div></div>
        </div>
        <div class="sum-row" id="sum-fecha-row">
            <div class="sum-icon"><i class="fa-regular fa-calendar"></i></div>
            <div><div class="sum-lbl">Fecha</div><div class="sum-val" id="sum-fecha">—</div></div>
        </div>
        <div class="sum-row hidden" id="sum-recurrente-row">
            <div class="sum-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
            <div><div class="sum-lbl">Recurrencia</div><div class="sum-val" id="sum-recurrente">—</div></div>
        </div>
        <div class="sum-row">
            <div class="sum-icon"><i class="fa-regular fa-clock"></i></div>
            <div><div class="sum-lbl">Horario</div><div class="sum-val" id="sum-horario">—</div></div>
        </div>
    </div>

    <form method="POST" id="form-sol">
        <input type="hidden" name="enviar"        value="1">
        <input type="hidden" name="id_instructor" id="f-id-inst">
        <input type="hidden" name="id_ambiente"   id="f-id-amb">

        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-layer-group"></i> Tipo de solicitud</label>
            <div class="tipo-toggle">
                <div class="tipo-option">
                    <input type="radio" name="tipo_solicitud" id="tipo-unico" value="unico" checked onchange="onTipoChange()">
                    <label for="tipo-unico">
                        <div class="tipo-icon"><i class="fa-regular fa-calendar-check"></i></div>
                        <div class="tipo-text"><strong>Único</strong><span>Una sola fecha</span></div>
                    </label>
                </div>
                <div class="tipo-option">
                    <input type="radio" name="tipo_solicitud" id="tipo-recurrente" value="recurrente" onchange="onTipoChange()">
                    <label for="tipo-recurrente">
                        <div class="tipo-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
                        <div class="tipo-text"><strong>Recurrente</strong><span>Varios días / semanas</span></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- BLOQUE ÚNICO -->
        <div id="bloque-unico">
            <input type="hidden" name="fecha" id="f-fecha">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fa-regular fa-clock"></i> Hora inicio</label>
                    <input type="time" name="hora_ini" id="f-hora-ini" class="form-ctrl" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fa-regular fa-clock"></i> Hora fin</label>
                    <input type="time" name="hora_fin" id="f-hora-fin" class="form-ctrl" required>
                </div>
            </div>
            <p class="form-hint" style="margin-top:-10px;margin-bottom:18px;">
                <i class="fa-solid fa-circle-info"></i>
                Puede ajustar el horario. El sistema validará conflictos antes de guardar.
            </p>
        </div>

        <!-- BLOQUE RECURRENTE -->
        <div id="bloque-recurrente" class="hidden">
            <div class="recurrente-block">
                <div class="block-ttl"><i class="fa-solid fa-arrows-rotate"></i> Configuración de recurrencia</div>
                <div class="form-row" style="margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><i class="fa-regular fa-calendar"></i> Fecha inicio</label>
                        <input type="date" name="fecha_inicio" id="f-fecha-inicio" class="form-ctrl"
                               min="<?= date('Y-m-d') ?>" oninput="updateRecurrenteSummary()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><i class="fa-regular fa-calendar-check"></i> Fecha fin</label>
                        <input type="date" name="fecha_fin_r" id="f-fecha-fin-r" class="form-ctrl"
                               min="<?= date('Y-m-d') ?>" oninput="updateRecurrenteSummary()">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label"><i class="fa-solid fa-calendar-week"></i> Días de la semana</label>
                    <div class="dias-grid">
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-1" value="1" onchange="updateRecurrenteSummary()">
                            <label for="dia-1"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Lunes</label>
                        </div>
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-2" value="2" onchange="updateRecurrenteSummary()">
                            <label for="dia-2"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Martes</label>
                        </div>
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-3" value="3" onchange="updateRecurrenteSummary()">
                            <label for="dia-3"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Miércoles</label>
                        </div>
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-4" value="4" onchange="updateRecurrenteSummary()">
                            <label for="dia-4"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Jueves</label>
                        </div>
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-5" value="5" onchange="updateRecurrenteSummary()">
                            <label for="dia-5"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Viernes</label>
                        </div>
                        <div class="dia-option">
                            <input type="checkbox" name="dias[]" id="dia-6" value="6" onchange="updateRecurrenteSummary()">
                            <label for="dia-6"><i class="fa-solid fa-circle-dot" style="font-size:9px;"></i> Sábado</label>
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i> Seleccione los días en que se repetirá la reserva.
                    </p>
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><i class="fa-regular fa-clock"></i> Hora inicio</label>
                        <input type="time" name="hora_ini" id="f-hora-ini-r" class="form-ctrl" oninput="updateRecurrenteSummary()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><i class="fa-regular fa-clock"></i> Hora fin</label>
                        <input type="time" name="hora_fin" id="f-hora-fin-r" class="form-ctrl" oninput="updateRecurrenteSummary()">
                    </div>
                </div>
                <p class="form-hint" style="margin-top:8px;margin-bottom:0;">
                    <i class="fa-solid fa-circle-info"></i>
                    El sistema verificará conflictos en cada fecha antes de guardar.
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fa-solid fa-comment-dots"></i> Observaciones
                <span style="font-weight:400;color:var(--muted);">(opcional)</span>
            </label>
            <textarea name="observaciones" class="form-ctrl" rows="3"
                      placeholder="Describa el propósito de la reserva u otras observaciones..."></textarea>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;">
            <button type="submit" class="btn btn-green" style="flex:1;justify-content:center;min-width:180px;">
                <i class="fa-solid fa-circle-check"></i> Confirmar solicitud
            </button>
            <button type="button" class="btn btn-outline" onclick="goToStep(2)">
                <i class="fa-solid fa-arrow-left"></i> Volver al calendario
            </button>
        </div>
    </form>
</div>

</div>

<script>
/* ─── GLOBAL STATE ─── */
const S = {
    step: 1, instructor: null, slot: null,
    view: 'day', date: new Date(),
    ambientes: [], reservas: [],
    loaded: false, loading: false
};

/* ─── UTILITIES ─── */
const fmt      = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
const today    = () => fmt(new Date());

function addHour(t, n) {
    const [h, m] = t.split(':').map(Number);
    return `${String(h + n).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
}
function fmtDisplay(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-CO', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
const DIAS_LABELS = { '1':'Lunes','2':'Martes','3':'Miércoles','4':'Jueves','5':'Viernes','6':'Sábado' };

/* ─── STEP NAVIGATION ─── */
function goToStep(n) {
    if (n >= 2 && !S.instructor) return;
    if (n >= 4 && !S.slot) { alert('Seleccione un espacio en el calendario.'); return; }
    S.step = n;
    ['panel-1','panel-2','panel-4'].forEach(id => document.getElementById(id).classList.add('hidden'));
    if      (n === 1) document.getElementById('panel-1').classList.remove('hidden');
    else if (n === 2) { document.getElementById('panel-2').classList.remove('hidden'); initCalendar(); }
    else if (n === 4) { document.getElementById('panel-4').classList.remove('hidden'); fillForm(); }
    updateStepBar(n);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepBar(n) {
    for (let i = 1; i <= 4; i++) {
        const item = document.getElementById('si-' + i);
        const conn = document.getElementById('sc-' + i);
        item.className = 'step-item' + (i === n ? ' active' : i < n ? ' done' : '');
        if (conn) conn.className = 'step-connector' + (i < n ? ' done' : '');
    }
    if (S.slot && n >= 4) {
        const si3 = document.getElementById('si-3');
        if (si3) si3.className = 'step-item done';
        const sc3 = document.getElementById('sc-3');
        if (sc3) sc3.className = 'step-connector done';
    }
}

/* ─── AUTOCOMPLETE ─── */
let searchTimer = null;
function onSearchInput(val) {
    clearTimeout(searchTimer);
    const drop = document.getElementById('ac-drop');
    if (val.trim().length < 2) { drop.classList.add('hidden'); return; }
    searchTimer = setTimeout(() => doSearch(val.trim()), 280);
}
async function doSearch(q) {
    const drop = document.getElementById('ac-drop');
    drop.innerHTML = '<div class="ac-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
    drop.classList.remove('hidden');
    try {
        const res  = await fetch(`solicitar_ambiente.php?buscar_instructor=1&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.length) {
            drop.innerHTML = '<div class="ac-empty"><i class="fa-solid fa-face-meh"></i> Sin resultados</div>';
            return;
        }
        drop.innerHTML = data.map(r => `
            <div class="ac-item" onclick="selectInstructor(${r.id},'${esc(r.nombre)}','${esc(r.identificacion)}')">
                <div class="ac-av"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div class="ac-name">${esc(r.nombre)}</div>
                    <div class="ac-cc">C.C. ${esc(r.identificacion)}</div>
                </div>
            </div>`).join('');
    } catch(e) {
        drop.innerHTML = '<div class="ac-empty">Error al buscar. Intente de nuevo.</div>';
    }
}
function selectInstructor(id, nombre, cc) {
    S.instructor = { id, nombre, identificacion: cc };
    document.getElementById('ac-drop').classList.add('hidden');
    document.getElementById('ac-input').value = nombre;
    document.getElementById('inst-nombre-txt').textContent = nombre;
    document.getElementById('inst-cc-txt').textContent     = 'C.C. ' + cc;
    document.getElementById('inst-found').classList.remove('hidden');
}
function clearInstructor() {
    S.instructor = null; S.slot = null; S.loaded = false;
    document.getElementById('ac-input').value = '';
    document.getElementById('inst-found').classList.add('hidden');
    document.getElementById('ac-drop').classList.add('hidden');
    goToStep(1);
}
document.addEventListener('click', e => {
    if (!e.target.closest('.ac-wrap'))
        document.getElementById('ac-drop').classList.add('hidden');
});

/* ─── CALENDAR DATA ─── */
async function initCalendar() {
    document.getElementById('ibar-nombre').textContent = S.instructor?.nombre || '';
    if (S.loaded) { renderCalendar(); return; }
    if (S.loading) return;
    S.loading = true;
    const d  = S.date;
    const fi = fmt(new Date(d.getFullYear(), d.getMonth() - 1, 1));
    const ff = fmt(new Date(d.getFullYear(), d.getMonth() + 3, 0));
    document.getElementById('cal-content').innerHTML =
        '<div class="cal-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Cargando disponibilidad...</span></div>';
    try {
        const r    = await fetch(`solicitar_ambiente.php?get_cal=1&fi=${fi}&ff=${ff}`);
        const data = await r.json();
        S.ambientes = data.ambientes || [];
        S.reservas  = data.reservas  || [];
        S.loaded    = true;
    } catch(e) {
        document.getElementById('cal-content').innerHTML =
            '<div class="alert alert-error" style="margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> Error al cargar el calendario.</div>';
        S.loading = false; return;
    }
    S.loading = false;
    renderCalendar();
}

/* ─── CALENDAR RENDER ─── */
function renderCalendar() {
    updatePeriodLabel();
    if      (S.view === 'day')   renderDayView();
    else if (S.view === 'week')  renderWeekView();
    else if (S.view === 'month') renderMonthView();
}

function updatePeriodLabel() {
    const d = S.date;
    const lbl = document.getElementById('cal-period');
    if (S.view === 'day') {
        lbl.textContent = d.toLocaleDateString('es-CO', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    } else if (S.view === 'week') {
        const ws = getWeekStart(d);
        const we = new Date(ws); we.setDate(we.getDate() + 6);
        lbl.textContent = `${ws.getDate()} ${ws.toLocaleDateString('es-CO',{month:'short'})} – ${we.getDate()} ${we.toLocaleDateString('es-CO',{month:'short',year:'numeric'})}`;
    } else {
        lbl.textContent = d.toLocaleDateString('es-CO', { month:'long', year:'numeric' });
    }
}

/* ─── IS OCCUPIED ─── */
function isOccupied(ambId, dateStr, hourStr) {
    const hEnd = addHour(hourStr, 1);
    return S.reservas.some(r =>
        parseInt(r.id_ambiente) === parseInt(ambId) &&
        dateStr >= r.fecha_inicio &&
        dateStr <= r.fecha_fin &&
        r.hora_inicio < hEnd &&
        r.hora_final  > hourStr
    );
}

/* ─── DAY VIEW — horario 06:00 a 22:00 ─── */
function renderDayView() {
    const dateStr  = fmt(S.date);
    const todayStr = today();
    const isPast   = dateStr < todayStr;
    const hours    = [];
    for (let h = 6; h < 22; h++) hours.push(`${String(h).padStart(2,'0')}:00`); // ← 06:00–22:00

    if (!S.ambientes.length) {
        document.getElementById('cal-content').innerHTML =
            '<div class="cal-loading"><i class="fa-solid fa-building-circle-xmark"></i><span>No hay ambientes habilitados.</span></div>';
        return;
    }

    let html = `<div class="day-scroll"><div class="day-grid">`;
    html += `<div class="day-hdr"><div class="day-hdr-time"></div>`;
    html += S.ambientes.map(a => `<div class="day-hdr-amb">${esc(a.nombre_ambiente)}</div>`).join('');
    html += `</div>`;

    hours.forEach(h => {
        const hEnd    = addHour(h, 1);
        const nowStr  = new Date().toTimeString().slice(0,5);
        const slotPast = isPast || (dateStr === todayStr && h < nowStr);

        html += `<div class="day-row"><div class="day-time">${h}</div>`;
        S.ambientes.forEach(a => {
            const selMatch = S.slot &&
                parseInt(S.slot.id_ambiente) === parseInt(a.id) &&
                S.slot.fecha    === dateStr &&
                S.slot.hora_ini === h;

            if (slotPast) {
                html += `<div class="cal-cell past">—</div>`;
            } else if (selMatch) {
                html += `<div class="cal-cell selected" onclick="deselectSlot()">
                            <i class="fa-solid fa-check"></i> Seleccionado
                         </div>`;
            } else if (isOccupied(a.id, dateStr, h)) {
                html += `<div class="cal-cell occupied">
                            <i class="fa-solid fa-lock"></i> Ocupado
                         </div>`;
            } else {
                html += `<div class="cal-cell free"
                              onclick="selectSlot(${a.id},'${esc(a.nombre_ambiente)}','${dateStr}','${h}','${hEnd}')">
                            <i class="fa-solid fa-plus"></i> Libre
                         </div>`;
            }
        });
        html += `</div>`;
    });

    html += `</div></div>`;
    document.getElementById('cal-content').innerHTML = html;
    updateSlotBar();
}

/* ─── WEEK VIEW ─── */
function renderWeekView() {
    const ws       = getWeekStart(S.date);
    const todayStr = today();
    const days     = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(ws); d.setDate(d.getDate() + i);
        days.push(d);
    }
    const dayNames = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    const hours    = [];
    for (let h = 6; h < 22; h++) hours.push(`${String(h).padStart(2,'0')}:00`); // ← 06:00–22:00

    let html = `<div class="week-grid">`;
    days.forEach((d, i) => {
        const ds     = fmt(d);
        const isPast = ds < todayStr;
        const isToday= ds === todayStr;
        let libre = 0, ocupado = 0;
        S.ambientes.forEach(a => {
            hours.forEach(h => {
                if (isOccupied(a.id, ds, h)) ocupado++;
                else if (!isPast) libre++;
            });
        });
        html += `<div class="week-card${isToday?' today':''}${isPast?' past':''}"
                      onclick="${isPast ? '' : `goDayView('${ds}')`}">
            <div class="week-hdr">${dayNames[i]}<br>${d.getDate()}</div>
            <div class="week-body">`;
        if (!isPast) html += `<div class="week-stat libre"><i class="fa-solid fa-circle" style="font-size:8px;color:var(--green);"></i> ${libre} libres</div>`;
        if (ocupado) html += `<div class="week-stat occ"><i class="fa-solid fa-circle" style="font-size:8px;color:#e05050;"></i> ${ocupado} ocupados</div>`;
        if (isPast)  html += `<div class="week-stat" style="color:var(--muted);font-size:11px;">Pasado</div>`;
        html += `</div></div>`;
    });
    html += `</div><p style="font-size:12px;color:var(--muted);margin-top:12px;"><i class="fa-solid fa-hand-pointer"></i> Haga clic en un día para ver sus espacios disponibles.</p>`;
    document.getElementById('cal-content').innerHTML = html;
    updateSlotBar();
}

function goDayView(dateStr) {
    const [y,m,d] = dateStr.split('-').map(Number);
    S.date = new Date(y, m - 1, d);
    S.view = 'day';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('vb-day').classList.add('active');
    renderCalendar();
}

/* ─── MONTH VIEW ─── */
function renderMonthView() {
    const y        = S.date.getFullYear();
    const m        = S.date.getMonth();
    const todayStr = today();
    const hours    = [];
    for (let h = 6; h < 22; h++) hours.push(`${String(h).padStart(2,'0')}:00`); // ← 06:00–22:00

    const firstDay    = new Date(y, m, 1).getDay();
    const startOffset = (firstDay === 0) ? 6 : firstDay - 1;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const dayNames    = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

    let html = `<div class="month-day-names">`;
    html += dayNames.map(n => `<div class="month-day-name">${n}</div>`).join('');
    html += `</div><div class="month-grid">`;

    for (let i = 0; i < startOffset; i++) html += `<div class="month-day empty"></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
        const ds      = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isPast  = ds < todayStr;
        const isToday = ds === todayStr;
        let libre = 0, occ = 0;
        if (!isPast) {
            S.ambientes.forEach(a => {
                hours.forEach(h => {
                    if (isOccupied(a.id, ds, h)) occ++;
                    else libre++;
                });
            });
        }
        html += `<div class="month-day${isToday?' today':''}${isPast?' past':''}"
                      onclick="${isPast ? '' : `goDayView('${ds}')`}">
                    <div class="month-num">${d}</div>`;
        if (!isPast && S.ambientes.length) {
            html += `<div class="month-avail">`;
            if (libre) html += `<span class="m-dot libre" title="${libre} espacios libres"></span>`;
            if (occ)   html += `<span class="m-dot occ"   title="${occ} ocupados"></span>`;
            html += `</div>`;
        }
        html += `</div>`;
    }
    html += `</div><p style="font-size:12px;color:var(--muted);margin-top:12px;"><i class="fa-solid fa-circle" style="color:var(--green);font-size:8px;"></i> Libre &nbsp;<i class="fa-solid fa-circle" style="color:#e05050;font-size:8px;"></i> Ocupado — Haga clic en un día para ver el detalle.</p>`;
    document.getElementById('cal-content').innerHTML = html;
    updateSlotBar();
}

/* ─── SLOT ─── */
function selectSlot(ambId, ambNombre, fecha, hIni, hFin) {
    S.slot = { id_ambiente: ambId, nombre: ambNombre, fecha, hora_ini: hIni, hora_fin: hFin };
    renderDayView();
    updateSlotBar();
    document.getElementById('slot-bar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function deselectSlot() {
    S.slot = null;
    renderDayView();
    updateSlotBar();
}
function updateSlotBar() {
    const bar = document.getElementById('slot-bar');
    if (!S.slot) { bar.classList.add('hidden'); return; }
    bar.classList.remove('hidden');
    document.getElementById('slot-bar-txt').textContent =
        `${S.slot.nombre} · ${fmtDisplay(S.slot.fecha)} · ${S.slot.hora_ini} – ${S.slot.hora_fin}`;
}

/* ─── NAV ─── */
function setView(v) {
    S.view = v;
    ['day','week','month'].forEach(x => document.getElementById('vb-'+x).classList.toggle('active', x===v));
    renderCalendar();
}
function navCal(dir) {
    const d = S.date;
    if      (S.view === 'day')   S.date = new Date(d.getFullYear(), d.getMonth(), d.getDate() + dir);
    else if (S.view === 'week')  S.date = new Date(d.getFullYear(), d.getMonth(), d.getDate() + dir*7);
    else                         S.date = new Date(d.getFullYear(), d.getMonth() + dir, 1);
    renderCalendar();
}
function goToday() { S.date = new Date(); renderCalendar(); }
function getWeekStart(d) {
    const day = d.getDay();
    const diff = (day === 0) ? -6 : 1 - day;
    const ws = new Date(d); ws.setDate(d.getDate() + diff);
    return ws;
}

/* ─── TIPO SOLICITUD ─── */
function onTipoChange() {
    const tipo = document.querySelector('input[name="tipo_solicitud"]:checked')?.value || 'unico';
    const isRec = tipo === 'recurrente';
    document.getElementById('bloque-unico').classList.toggle('hidden', isRec);
    document.getElementById('bloque-recurrente').classList.toggle('hidden', !isRec);
    document.getElementById('f-hora-ini').required   = !isRec;
    document.getElementById('f-hora-fin').required   = !isRec;
    document.getElementById('f-hora-ini-r').required = isRec;
    document.getElementById('f-hora-fin-r').required = isRec;
    document.getElementById('sum-tipo').textContent = isRec ? 'Recurrente' : 'Único';
    document.getElementById('sum-fecha-row').classList.toggle('hidden', isRec);
    document.getElementById('sum-recurrente-row').classList.toggle('hidden', !isRec);
    if (isRec) updateRecurrenteSummary();
    else updateUnicoSummary();
}

/* ─── FILL FORM ─── */
function fillForm() {
    if (!S.instructor || !S.slot) return;
    document.getElementById('ibar2-nombre').textContent = S.instructor.nombre;
    document.getElementById('sum-ambiente').textContent  = S.slot.nombre;
    document.getElementById('f-id-inst').value           = S.instructor.id;
    document.getElementById('f-id-amb').value            = S.slot.id_ambiente;
    document.getElementById('f-fecha').value    = S.slot.fecha;
    document.getElementById('f-hora-ini').value = S.slot.hora_ini;
    document.getElementById('f-hora-fin').value = S.slot.hora_fin;
    document.getElementById('f-hora-ini-r').value = S.slot.hora_ini;
    document.getElementById('f-hora-fin-r').value = S.slot.hora_fin;
    if (!document.getElementById('f-fecha-inicio').value)
        document.getElementById('f-fecha-inicio').value = S.slot.fecha;
    updateUnicoSummary();
    onTipoChange();
}

function updateUnicoSummary() {
    const fecha = document.getElementById('f-fecha').value;
    const hIni  = document.getElementById('f-hora-ini').value;
    const hFin  = document.getElementById('f-hora-fin').value;
    document.getElementById('sum-fecha').textContent   = fmtDisplay(fecha) || '—';
    document.getElementById('sum-horario').textContent = (hIni && hFin) ? `${hIni} – ${hFin}` : '—';
}

function updateRecurrenteSummary() {
    const fi   = document.getElementById('f-fecha-inicio').value;
    const ff   = document.getElementById('f-fecha-fin-r').value;
    const hIni = document.getElementById('f-hora-ini-r').value;
    const hFin = document.getElementById('f-hora-fin-r').value;
    const dias = Array.from(document.querySelectorAll('input[name="dias[]"]:checked'))
                      .map(cb => DIAS_LABELS[cb.value] || cb.value);
    let recTxt = '';
    if (fi && ff)    recTxt += `${fmtDisplay(fi)} al ${fmtDisplay(ff)}`;
    if (dias.length) recTxt += ` · ${dias.join(', ')}`;
    if (!recTxt)     recTxt = '—';
    document.getElementById('sum-recurrente').textContent = recTxt;
    document.getElementById('sum-horario').textContent    = (hIni && hFin) ? `${hIni} – ${hFin}` : '—';
}

/* ─── FORM VALIDATION ─── */
document.getElementById('form-sol').addEventListener('submit', function(e) {
    const tipo = document.querySelector('input[name="tipo_solicitud"]:checked')?.value || 'unico';
    if (tipo === 'unico') {
        const hIni = document.getElementById('f-hora-ini').value;
        const hFin = document.getElementById('f-hora-fin').value;
        if (hIni >= hFin) { e.preventDefault(); alert('La hora fin debe ser mayor que la hora inicio.'); return; }
    } else {
        const fi   = document.getElementById('f-fecha-inicio').value;
        const ff   = document.getElementById('f-fecha-fin-r').value;
        const hIni = document.getElementById('f-hora-ini-r').value;
        const hFin = document.getElementById('f-hora-fin-r').value;
        const dias = document.querySelectorAll('input[name="dias[]"]:checked');
        if (!fi)         { e.preventDefault(); alert('Ingrese la fecha inicio de la recurrencia.'); return; }
        if (!ff)         { e.preventDefault(); alert('Ingrese la fecha fin de la recurrencia.'); return; }
        if (fi > ff)     { e.preventDefault(); alert('La fecha fin debe ser mayor o igual a la fecha inicio.'); return; }
        if (fi < '<?= date('Y-m-d') ?>') { e.preventDefault(); alert('La fecha inicio no puede ser en el pasado.'); return; }
        if (hIni >= hFin){ e.preventDefault(); alert('La hora fin debe ser mayor que la hora inicio.'); return; }
        if (!dias.length){ e.preventDefault(); alert('Seleccione al menos un día de la semana.'); return; }
    }
});

document.getElementById('f-hora-ini').addEventListener('input', updateUnicoSummary);
document.getElementById('f-hora-fin').addEventListener('input', updateUnicoSummary);

/* ─── INIT ─── */
(function init() {
    updateStepBar(1);
    <?php if ($msg_success || $msg_error): ?>
    goToStep(1);
    <?php endif; ?>
})();
</script>
</body>
</html>