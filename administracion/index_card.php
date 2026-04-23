<?php
/**
 * CARD: Gestión de Fichas
 * Insertar este bloque en el dashboard/index.php del panel de administración
 * Condición: solo visible para rol 'administracion'
 */
?>

<?php if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'administracion'): ?>
<div class="card-fichas">
    <div class="card-fichas__icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
            <rect x="9" y="3" width="6" height="4" rx="1"/>
            <line x1="9" y1="12" x2="15" y2="12"/>
            <line x1="9" y1="16" x2="13" y2="16"/>
        </svg>
    </div>
    <div class="card-fichas__body">
        <h3 class="card-fichas__title">Gestión de Fichas</h3>
        <p class="card-fichas__desc">Consultar programación de ambientes o registrar nuevas fichas en el sistema.</p>
        <div class="card-fichas__actions">
            <a href="programacion_fichas.php" class="btn-ficha btn-ficha--search">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                Buscar Programación
            </a>
            <a href="gestionar_fichas.php" class="btn-ficha btn-ficha--add">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Ingresar Fichas
            </a>
        </div>
    </div>
</div>

<style>
/* ── Card Gestión de Fichas ─────────────────────────────────── */
.card-fichas {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    transition: box-shadow .2s, transform .2s;
}
.card-fichas:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.10);
    transform: translateY(-2px);
}
.card-fichas__icon {
    flex-shrink: 0;
    width: 48px; height: 48px;
    background: #eef2ff;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #4f46e5;
}
.card-fichas__icon svg { width: 24px; height: 24px; }
.card-fichas__body { flex: 1; }
.card-fichas__title {
    font-size: 1rem; font-weight: 700;
    color: #1e293b; margin: 0 0 .3rem;
}
.card-fichas__desc {
    font-size: .85rem; color: #64748b;
    margin: 0 0 1rem; line-height: 1.5;
}
.card-fichas__actions { display: flex; gap: .6rem; flex-wrap: wrap; }
.btn-ficha {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .45rem .9rem; border-radius: 7px;
    font-size: .82rem; font-weight: 600;
    text-decoration: none; transition: background .15s, transform .1s;
}
.btn-ficha:active { transform: scale(.97); }
.btn-ficha--search { background: #eef2ff; color: #4f46e5; }
.btn-ficha--search:hover { background: #e0e7ff; }
.btn-ficha--add { background: #4f46e5; color: #fff; }
.btn-ficha--add:hover { background: #4338ca; }
</style>
<?php endif; ?>
