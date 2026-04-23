<?php
session_start();
include("../includes/conexion.php");

require_once '../config/db.php';

// ── Restricción de acceso ─────────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administracion') {
    http_response_code(403);
    exit('Acceso denegado.');
}

// ── Filtro ────────────────────────────────────────────────────
$id_ficha_filtro = isset($_GET['id_ficha']) && $_GET['id_ficha'] !== '' ? (int)$_GET['id_ficha'] : null;

// ── Consulta (igual que programacion_fichas.php) ──────────────
$sql = "SELECT d.fecha,
               d.hora_inicio,
               d.hora_fin,
               a.nombre_ambiente,
               f.numero_ficha,
               f.programa,
               f.jornada,
        FROM disponibilidad_ambiente d
        JOIN ambientes a ON d.id_ambiente = a.id
        LEFT JOIN fichas f ON d.id_ficha = f.id";

$params = [];
if ($id_ficha_filtro !== null) {
    $sql .= " WHERE d.id_ficha = :id_ficha";
    $params[':id_ficha'] = $id_ficha_filtro;
}
$sql .= " ORDER BY d.fecha ASC, d.hora_inicio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Nombre de archivo ─────────────────────────────────────────
$sufijo = $id_ficha_filtro !== null ? "_ficha{$id_ficha_filtro}" : '_todas';
$nombre = 'programacion_fichas' . $sufijo . '_' . date('Ymd_His') . '.csv';

/*
 * NOTA: Para exportar .xlsx nativo instala PhpSpreadsheet:
 *   composer require phpoffice/phpspreadsheet
 * y reemplaza la sección CSV por el generador de Xlsx.
 *
 * La exportación CSV con BOM UTF-8 abre correctamente en Excel
 * sin necesidad de librerías adicionales.
 */

// ── Cabeceras HTTP ────────────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 para compatibilidad con Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ── Fila de encabezados ───────────────────────────────────────
fputcsv($out, [
    'Número de Ficha',
    'Programa',
    'Jornada',
    'Ambiente',
    'Fecha',
    'Hora Inicio',
    'Hora Fin',
], ';');

// ── Filas de datos ────────────────────────────────────────────
foreach ($filas as $f) {
    fputcsv($out, [
        $f['numero_ficha']    ?? '',
        $f['programa']        ?? '',
        $f['jornada']         ?? '',
        $f['nombre_ambiente'] ?? '',
        $f['fecha']           ? date('d/m/Y', strtotime($f['fecha'])) : '',
        $f['hora_inicio']     ? substr($f['hora_inicio'], 0, 5) : '',
        $f['hora_fin']        ? substr($f['hora_fin'], 0, 5)    : '',
    ], ';');
}

fclose($out);
exit;
