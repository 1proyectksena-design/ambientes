<?php 
/* ============================================================
   SNIPPET PARA MOSTRAR NOVEDADES CON FECHA/HORA
   Copia este bloque donde se muestran las novedades
   ============================================================ */

// En el while() donde se recorren las filas:
if($row['novedades']): 
    /* EXTRAER FECHA/HORA SI EXISTE EN EL TEXTO */
    $novedad_texto = $row['novedades'];
    $fecha_novedad = '';
    
    // Buscar patrón [YYYY-MM-DD HH:MM] al inicio
    if(preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\]\s*(.*)$/s', $novedad_texto, $matches)){
        // Si existe, extraer fecha y texto limpio
        $fecha_novedad = date('d/m/Y h:i A', strtotime($matches[1]));
        $novedad_texto = $matches[2];
    } else {
        // Si no hay fecha en el texto, usar fecha_registro de la BD
        $fecha_novedad = date('d/m/Y h:i A', strtotime($row['fecha_registro']));
    }
?>
    <button onclick="mostrarModal(this)" class="btn-ver-novedades">
        <i class="fa-solid fa-eye"></i> Ver
    </button>
    <div class="novedades-modal" style="display:none;">
        <div class="modal-header">
            <strong>Novedades reportadas por:</strong>
            <span class="instructor-name"><?= htmlspecialchars($row['nombre_instructor']) ?></span>
            <!-- FECHA/HORA AGREGADA AQUÍ -->
            <div style="font-size: 0.85rem; color: #f57c00; margin-top: 4px;">
                <i class="fa-regular fa-clock"></i> <?= $fecha_novedad ?>
            </div>
        </div>
        <div class="modal-content">
            <!-- Texto limpio SIN la fecha -->
            <pre><?= htmlspecialchars($novedad_texto) ?></pre>
        </div>
    </div>
<?php else: ?>
    <span style="color:#999;">Sin novedades</span>
<?php endif; ?>