<?php
include("../includes/conexion.php");
session_start();

if ($_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   OBTENER ID SI VIENE DE CONSULTAR
   ========================= */
$id_ambiente_seleccionado = $_GET['id_ambiente'] ?? null;

/* =========================
   CONSULTAS BASE
   ========================= */
$ambientes = mysqli_query($conexion, "SELECT * FROM ambientes ORDER BY nombre_ambiente");
$instructores = mysqli_query($conexion, "SELECT * FROM instructores ORDER BY nombre_completo");

/* =========================
   AUTORIZAR
   ========================= */
if(isset($_POST['autorizar'])){

    $ambiente = mysqli_real_escape_string($conexion, $_POST['ambiente']);
    $instructor = mysqli_real_escape_string($conexion, $_POST['instructor']);
    $fecha = mysqli_real_escape_string($conexion, $_POST['fecha']);
    $hora_inicio = mysqli_real_escape_string($conexion, $_POST['hora_inicio']);
    $hora_fin = mysqli_real_escape_string($conexion, $_POST['hora_fin']);
    $obs = mysqli_real_escape_string($conexion, $_POST['observacion']);

    /* VALIDAR QUE HORA FIN SEA MAYOR */
    if($hora_inicio >= $hora_fin){
        echo "<script>
                alert('⚠️ La hora fin debe ser mayor que la hora inicio');
                window.history.back();
              </script>";
        exit;
    }

    /* =========================
       VALIDAR CHOQUE DE HORARIO
       ========================= */
    $sqlChoque = "SELECT * FROM autorizaciones_ambientes
                  WHERE id_ambiente = '$ambiente'
                  AND fecha = '$fecha'
                  AND (
                        hora_inicio < '$hora_fin'
                        AND hora_fin > '$hora_inicio'
                      )";

    $resChoque = mysqli_query($conexion, $sqlChoque);

    if (mysqli_num_rows($resChoque) > 0) {
        echo "<script>
                alert('⚠️ El ambiente ya está ocupado en ese horario');
                window.history.back();
              </script>";
        exit;
    }

    /* =========================
       INSERTAR AUTORIZACIÓN
       ========================= */
    $sql = "INSERT INTO autorizaciones_ambientes 
            (id_ambiente, id_instructor, rol_autorizado, fecha, hora_inicio, hora_fin, observacion)
            VALUES 
            ('$ambiente', '$instructor', 'subdireccion', '$fecha', '$hora_inicio', '$hora_fin', '$obs')";

    mysqli_query($conexion, $sql);

    echo "<script>
            alert('✅ Ambiente autorizado correctamente');
            window.location.href='consultar.php';
          </script>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizar Ambiente</title>
    <link rel="stylesheet" href="../css/subdire.css">
    <style>
        /* Estilos adicionales para permisos.php */
        .permisos-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 35px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .form-header h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }

        .form-header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
            animation: slideInLeft 0.4s ease-out backwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.15s; }
        .form-group:nth-child(3) { animation-delay: 0.2s; }
        .form-group:nth-child(4) { animation-delay: 0.25s; }
        .form-group:nth-child(5) { animation-delay: 0.3s; }
        .form-group:nth-child(6) { animation-delay: 0.35s; }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label::after {
            content: " *";
            color: #e53935;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input[readonly] {
            background: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group select {
            cursor: pointer;
        }

        .ambiente-readonly {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%) !important;
            padding: 15px 20px;
            border-radius: 10px;
            border: 2px solid #667eea !important;
            font-weight: 600;
            color: #333 !important;
        }

        .time-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(67, 160, 71, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(67, 160, 71, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            border: 2px solid #667eea;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-volver:hover {
            background: #667eea;
            color: white;
            transform: translateX(-4px);
        }

        .info-alert {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #fb8c00;
            margin-bottom: 25px;
            font-size: 14px;
            color: #e65100;
            animation: fadeIn 0.6s ease-out 0.4s both;
        }

        .info-alert strong {
            display: block;
            margin-bottom: 5px;
            font-size: 15px;
        }

        /* ================= RESPONSIVE PERMISOS ================= */

        @media (max-width: 768px) {
            .permisos-container {
                padding: 20px 15px;
            }

            .form-card {
                padding: 30px 20px;
                border-radius: 14px;
            }

            .form-header {
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 30px;
            }

            .form-header h2 {
                font-size: 22px;
            }

            .form-header p {
                font-size: 13px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                font-size: 13px;
                margin-bottom: 8px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 12px 16px;
                font-size: 14px;
            }

            .time-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .btn-submit {
                padding: 14px;
                font-size: 15px;
            }
            
            .btn-volver {
                width: 100%;
                justify-content: center;
                padding: 12px 24px;
            }
        }

        @media (max-width: 480px) {
            .permisos-container {
                padding: 15px 12px;
            }

            .form-card {
                padding: 25px 15px;
                border-radius: 12px;
            }

            .form-header {
                padding: 18px 15px;
                margin-bottom: 25px;
            }

            .form-header h2 {
                font-size: 20px;
            }

            .form-header p {
                font-size: 12px;
            }

            .form-group {
                margin-bottom: 18px;
            }

            .form-group label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 11px 14px;
                font-size: 13px;
                border-radius: 8px;
            }

            .form-group textarea {
                min-height: 100px;
            }

            .info-alert {
                padding: 12px 15px;
                font-size: 12px;
                margin-bottom: 20px;
            }

            .info-alert strong {
                font-size: 13px;
            }

            .btn-submit {
                padding: 13px;
                font-size: 14px;
                border-radius: 8px;
            }
            
            .btn-volver {
                padding: 11px 20px;
                font-size: 13px;
                margin-top: 15px;
            }
        }

        @media (max-width: 360px) {
            .permisos-container {
                padding: 12px 10px;
            }

            .form-card {
                padding: 20px 12px;
            }

            .form-header {
                padding: 15px 12px;
            }

            .form-header h2 {
                font-size: 18px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<!-- ========================= HEADER ========================= -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/logo.png" alt="Logo Institución">
        <div class="header-title">
            <h1>Autorizar Ambiente</h1>
            <span>Gestionar permisos de uso</span>
        </div>
    </div>
    <div class="header-user">
        Subdirección
    </div>
</div>

<div class="permisos-container">

    <div class="form-card">
        <div class="form-header">
            <h2>📝 Nueva Autorización</h2>
            <p>Complete el formulario para autorizar el uso de un ambiente</p>
        </div>

        <?php if($id_ambiente_seleccionado){ ?>
            <div class="info-alert">
                <strong>✓ Ambiente Pre-seleccionado</strong>
                El ambiente ha sido seleccionado automáticamente desde la búsqueda
            </div>
        <?php } ?>

        <form method="POST">

            <!-- AMBIENTE -->
            <div class="form-group">
                <label>Ambiente</label>

                <?php if($id_ambiente_seleccionado){ ?>
                    <?php
                    $ambiente_unico = mysqli_fetch_assoc(
                        mysqli_query($conexion, "SELECT * FROM ambientes WHERE id_ambiente='$id_ambiente_seleccionado'")
                    );
                    ?>
                    <input type="hidden" name="ambiente" value="<?= $ambiente_unico['id_ambiente'] ?>">
                    <input type="text" class="ambiente-readonly" value="<?= htmlspecialchars($ambiente_unico['nombre_ambiente']) ?>" readonly>
                <?php } else { ?>
                    <select name="ambiente" required>
                        <option value="">-- Seleccione un ambiente --</option>
                        <?php while($a = mysqli_fetch_assoc($ambientes)){ ?>
                            <option value="<?= $a['id_ambiente'] ?>">
                                <?= htmlspecialchars($a['nombre_ambiente']) ?> (<?= htmlspecialchars($a['estado']) ?>)
                            </option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>

            <!-- INSTRUCTOR -->
            <div class="form-group">
                <label>Instructor</label>
                <select name="instructor" required>
                    <option value="">-- Seleccione un instructor --</option>
                    <?php while($i = mysqli_fetch_assoc($instructores)){ ?>
                        <option value="<?= $i['id_instructor'] ?>">
                            <?= htmlspecialchars($i['nombre_completo']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- FECHA -->
            <div class="form-group">
                <label>Fecha</label>
                <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- HORARIOS -->
            <div class="time-grid">
                <div class="form-group">
                    <label>Hora Inicio</label>
                    <input type="time" name="hora_inicio" required>
                </div>

                <div class="form-group">
                    <label>Hora Fin</label>
                    <input type="time" name="hora_fin" required>
                </div>
            </div>

            <!-- OBSERVACIÓN -->
            <div class="form-group">
                <label>Observación</label>
                <textarea name="observacion" placeholder="Ingrese cualquier observación o comentario adicional..."></textarea>
            </div>

            <!-- BOTÓN SUBMIT -->
            <button type="submit" name="autorizar" class="btn-submit">
                ✓ Autorizar Ambiente
            </button>
        </form>
    </div>

    <!-- BOTÓN VOLVER -->
    <a href="index.php" class="btn-volver">
        ← Volver al Panel
    </a>

</div>

</body>
</html>