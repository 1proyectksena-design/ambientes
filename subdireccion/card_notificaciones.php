<?php
/*
 * ============================================================
 *  FRAGMENTO PARA INSERTAR EN TU index.php DEL PANEL
 * ============================================================
 *
 *  1. Asegúrate de que $conexion ya esté disponible (include conexion.php).
 *  2. Pega el bloque PHP en la sección de queries de tu index.php.
 *  3. Pega el bloque HTML donde quieras mostrar la tarjeta.
 *  4. Pega el bloque <style> en el <head> o en tu CSS global.
 *  5. El bloque <script> va justo antes del </body>.
 *
 * ============================================================ */

/* ── QUERY: contar pendientes ── */
$stmtPend = $conexion->prepare("
    SELECT COUNT(*) AS total 
    FROM autorizaciones_ambientes 
    WHERE estado = 'Pendiente'
");
$stmtPend->execute();
$totalPendientes = $stmtPend->get_result()->fetch_assoc()['total'];
$stmtPend->close();
?>

<!-- ══════════════════════════════════════════════════════════════
     CSS — pegar en <head> o en tu archivo permisos.css / index.css
     ══════════════════════════════════════════════════════════════ -->
<style>
/* Campana navbar */
.notif-bell-nav {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    margin-right: 12px;
    text-decoration: none;
    color: inherit;
}
.notif-bell-nav .badge-nav {
    position: absolute;
    top: -7px;
    right: -9px;
    background: #e74c3c;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    min-width: 17px;
    height: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    animation: pulseBadge 1.6s ease-in-out infinite;
}
@keyframes pulseBadge {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.2); }
}

/* Tarjeta de notificaciones */
.card-notificacion {
    background: #fff;
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    border-left: 5px solid #f39c12;
    position: relative;
    overflow: hidden;
    transition: box-shadow .2s;
}
.card-notificacion:hover { box-shadow: 0 8px 32px rgba(0,0,0,.13); }
.card-notificacion::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(243,156,18,.08);
}

/* Sin pendientes → verde */
.card-notificacion.sin-pendientes { border-left-color: #39a900; }
.card-notificacion.sin-pendientes .card-notif-count { color: #39a900; }
.card-notificacion.sin-pendientes .card-notif-icon  { color: #39a900; }

.card-notif-icon {
    font-size: 40px;
    color: #f39c12;
    flex-shrink: 0;
}
.card-notif-info { flex: 1; }
.card-notif-info h3 {
    margin: 0 0 4px;
    font-size: 15px;
    color: #666;
    font-weight: 500;
}
.card-notif-count {
    font-size: 42px;
    font-weight: 800;
    color: #f39c12;
    line-height: 1;
    margin-bottom: 4px;
}
.card-notif-info p {
    margin: 0;
    font-size: 13px;
    color: #999;
}
.btn-ver-solicitudes {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f39c12;
    color: #fff;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s, transform .12s;
    white-space: nowrap;
}
.btn-ver-solicitudes:hover {
    background: #d68910;
    transform: translateY(-1px);
}
.btn-ver-solicitudes.sin-pendientes-btn { background: #39a900; }
.btn-ver-solicitudes.sin-pendientes-btn:hover { background: #2d8600; }
</style>

<!-- ══════════════════════════════════════════════════════════════
     HTML — CAMPANA EN EL NAVBAR
     Pega esto dentro de tu .header-user (junto al icono de usuario)
     ══════════════════════════════════════════════════════════════ -->
<?php if ($totalPendientes > 0): ?>
<a href="solicitudes.php" class="notif-bell-nav" title="<?= $totalPendientes ?> solicitudes pendientes">
    <i class="fa-solid fa-bell" style="font-size:20px; color:#fff;"></i>
    <span class="badge-nav"><?= $totalPendientes ?></span>
</a>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     HTML — TARJETA DE SOLICITUDES PENDIENTES
     Pega esto en el grid de tarjetas de tu index.php
     ══════════════════════════════════════════════════════════════ -->
<div class="card-notificacion <?= $totalPendientes == 0 ? 'sin-pendientes' : '' ?>" id="card-pendientes">

    <div class="card-notif-icon">
        <i class="fa-solid <?= $totalPendientes > 0 ? 'fa-bell' : 'fa-bell-slash' ?>"></i>
    </div>

    <div class="card-notif-info">
        <h3>Solicitudes de Ambiente</h3>
        <div class="card-notif-count"><?= $totalPendientes ?></div>
        <p>
            <?php if ($totalPendientes == 0): ?>
                Sin solicitudes pendientes
            <?php elseif ($totalPendientes == 1): ?>
                solicitud esperando revisión
            <?php else: ?>
                solicitudes esperando revisión
            <?php endif; ?>
        </p>
    </div>

    <a href="solicitudes.php"
       class="btn-ver-solicitudes <?= $totalPendientes == 0 ? 'sin-pendientes-btn' : '' ?>">
        <i class="fa-solid fa-eye"></i>
        Ver solicitudes
    </a>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SCRIPT — Actualiza el contador cada 15 segundos sin recargar
     Pega esto antes del </body> de tu index.php
     ══════════════════════════════════════════════════════════════ -->
<script>
(function actualizarPendientes() {
    setTimeout(function() {
        fetch('get_pendientes_count.php')
            .then(r => r.json())
            .then(data => {
                const n = data.total ?? 0;

                // Actualizar número en la tarjeta
                const countEl = document.querySelector('.card-notif-count');
                if (countEl) countEl.textContent = n;

                // Actualizar badge del navbar
                let badge = document.querySelector('.badge-nav');
                if (n > 0) {
                    if (!badge) {
                        // Crear badge si no existe
                        const bell = document.querySelector('.notif-bell-nav');
                        if (bell) {
                            badge = document.createElement('span');
                            badge.className = 'badge-nav';
                            bell.appendChild(badge);
                        }
                    }
                    if (badge) badge.textContent = n;
                } else {
                    badge?.remove();
                }

                // Colores según si hay pendientes
                const card = document.getElementById('card-pendientes');
                if (card) {
                    card.classList.toggle('sin-pendientes', n === 0);
                }
            })
            .catch(() => {/* silencioso */})
            .finally(actualizarPendientes);   // siguiente ciclo
    }, 15000); // cada 15 segundos
})();
</script>
