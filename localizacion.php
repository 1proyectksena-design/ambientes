<?php
/* ==========================================
   LOCALIZACIÓN EN ESPAÑOL
   ==========================================
   Agregar DESPUÉS de date_default_timezone_set('America/Bogota');
   ========================================== */

// Configurar locale a español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');

// Array de meses en español
$meses_espanol = [
    '01' => 'Enero',
    '02' => 'Febrero',
    '03' => 'Marzo',
    '04' => 'Abril',
    '05' => 'Mayo',
    '06' => 'Junio',
    '07' => 'Julio',
    '08' => 'Agosto',
    '09' => 'Septiembre',
    '10' => 'Octubre',
    '11' => 'Noviembre',
    '12' => 'Diciembre'
];

/* ==========================================
   USO EN SELECT:
   ==========================================
   
   ANTES:
   <select name="mes">
       <?php for($m=1; $m<=12; $m++): ?>
       <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>">
           <?= date('F', mktime(0,0,0,$m,1)) ?>  ← EN INGLÉS
       </option>
       <?php endfor; ?>
   </select>
   
   AHORA:
   <select name="mes">
       <?php for($m=1; $m<=12; $m++): 
           $mes_num = str_pad($m, 2, '0', STR_PAD_LEFT);
       ?>
       <option value="<?= $mes_num ?>" <?= $filtro_mes == $mes_num ? 'selected' : '' ?>>
           <?= $meses_espanol[$mes_num] ?>  ← EN ESPAÑOL
       </option>
       <?php endfor; ?>
   </select>
   
   ========================================== */
?>