<?php
session_start();
date_default_timezone_set('America/Bogota');

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

include("../includes/conexion.php");

/* MESES EN ESPAÑOL */
$meses_espanol = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$mes  = $_GET['mes']  ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

/*
 * Agrupamos por ambiente + instructor + horario para mostrar
 * el rango de fechas y los días de la semana recurrentes.
 * DAYOFWEEK MySQL: 1=Dom 2=Lun 3=Mar 4=Mié 5=Jue 6=Vie 7=Sáb
 */
$sql = "SELECT
            a.nombre_ambiente,
            i.nombre                          AS nombre_instructor,
            MIN(au.fecha_inicio)              AS fecha_inicio,
            MAX(au.fecha_fin)                 AS fecha_fin,
            au.hora_inicio,
            au.hora_final,
            au.rol_autorizado,
            GROUP_CONCAT(
                DISTINCT DAYOFWEEK(au.fecha_inicio)
                ORDER BY DAYOFWEEK(au.fecha_inicio)
            )                                 AS dias_semana
        FROM autorizaciones_ambientes au
        JOIN ambientes a    ON au.id_ambiente   = a.id
        JOIN instructores i ON au.id_instructor = i.id
        WHERE MONTH(au.fecha_inicio) = '$mes'
          AND YEAR(au.fecha_inicio)  = '$anio'
        GROUP BY a.nombre_ambiente, i.nombre, au.hora_inicio, au.hora_final, au.rol_autorizado
        ORDER BY MIN(au.fecha_inicio) DESC, au.hora_inicio DESC";

$resultado = mysqli_query($conexion, $sql);
$total     = mysqli_num_rows($resultado);

/* Abreviaciones de días */
$abrevDias = [
    1 => 'Dom', 2 => 'Lun', 3 => 'Mar',
    4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb',
];

/* Día actual en formato DAYOFWEEK para resaltar el badge de hoy */
$dia_actual_mysql = (int)date('w') + 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizaciones del Mes</title>
    <link rel="stylesheet" href="../css/consultar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Autorizaciones del Mes</h1>
            <span><?= $meses_espanol[$mes] ?> <?= $anio ?></span>
        </div>
    </div>
    <div class="header-user">
        <i class="fa-solid fa-user user-icon"></i> Subdirección
    </div>
</div>

<div class="consultar-container">

    <!-- SELECTOR DE MES -->
    <div class="search-section">
        <h3><i class="fa-regular fa-calendar"></i> Seleccionar Mes</h3>
        <form method="GET" class="search-form">
            <select name="mes">
                <?php for ($m = 1; $m <= 12; $m++):
                    $mes_num = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                <option value="<?= $mes_num ?>" <?= $mes == $mes_num ? 'selected' : '' ?>>
                    <?= $meses_espanol[$mes_num] ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="anio">
                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-search"></i> Buscar</button>
            <a href="exportar.php?mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn-exportar-excel">
                <i class="fa-solid fa-file-excel"></i> Exportar Excel
            </a>
        </form>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fa-solid fa-list-check"></i>
                <?= $total ?> autorizaciones en <?= $meses_espanol[$mes] ?> <?= $anio ?>
            </h3>
        </div>

        <?php if ($total > 0): ?>
        <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Ambiente</th>
                        <th>Instructor</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Horario</th>
                        <th>Días</th>
                        <th>Autorizado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($resultado)):

                        /* ── Badges de días recurrentes ── */
                        $diasNums = ($row['dias_semana'] !== null && $row['dias_semana'] !== '')
                                    ? array_map('intval', explode(',', $row['dias_semana']))
                                    : [];

                        $diasHtml = '';
                        if (count($diasNums) > 0) {
                            $diasHtml = '<div style="display:flex;flex-wrap:wrap;gap:3px;">';
                            foreach ($diasNums as $dn) {
                                $abrev     = $abrevDias[$dn] ?? '?';
                                /* resalta el día de hoy en azul oscuro */
                                $highlight = ($dn === $dia_actual_mysql)
                                             ? ' style="background:#172f63;color:white;border-color:#172f63;"'
                                             : '';
                                $diasHtml .= '<span class="dia-badge"' . $highlight . '>' . $abrev . '</span>';
                            }
                            $diasHtml .= '</div>';
                        } else {
                            $diasHtml = '<span style="color:#999;">—</span>';
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['nombre_ambiente']) ?></strong></td>
                        <td>
                            <i class="fa-solid fa-user" style="color:#355d91; margin-right:5px;"></i>
                            <?= htmlspecialchars($row['nombre_instructor']) ?>
                        </td>
                        <td>
                            <span class="cell-fecha">
                                <i class="fa-regular fa-calendar"></i>
                                <?= date('d/m/Y', strtotime($row['fecha_inicio'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="cell-fecha">
                                <i class="fa-regular fa-calendar-check"></i>
                                <?= date('d/m/Y', strtotime($row['fecha_fin'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="cell-horario">
                                <i class="fa-regular fa-clock"></i>
                                <?= date('h:i A', strtotime($row['hora_inicio'])) ?>
                                &mdash;
                                <?= date('h:i A', strtotime($row['hora_final'])) ?>
                            </span>
                        </td>
                        <td><?= $diasHtml ?></td>
                        <td><?= htmlspecialchars($row['rol_autorizado']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="no-results">
            <i class="fa-solid fa-inbox"></i>
            <p>No hay autorizaciones en este mes</p>
        </div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="btn-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al Panel
    </a>

</div>
</body>
</html>