<?php
session_start();
if ($_SESSION['rol'] != 'administracion') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Ambientes</title>

    <!-- FullCalendar v6 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- CSS del calendario (incluye @import de DM Sans + Lora) -->
    <link rel="stylesheet" href="../css/calendario.css">
</head>
<body>

<!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Calendario de Ambientes</h1>
            <span>Vista interactiva de reservas y permisos</span>
        </div>
    </div>
    <div class="header-right">
        <div class="header-user">
            <i class="fa-solid fa-user"></i> Administración
        </div>
        <a href="index.php" class="btn-volver">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver al Panel</span>
        </a>
    </div>
</div>

<!-- ══ CONTENIDO ═══════════════════════════════════════════════════════ -->
<div class="cal-wrapper">

    <!-- FILTROS + LEYENDA -->
    <div class="controls-bar">

        <div class="filters-card">
            <div class="filters-title">
                <i class="fa-solid fa-sliders"></i> Filtros
            </div>
            <div class="filters-row">
                <div class="filter-group">
                    <label for="filtroAmbiente">Ambiente</label>
                    <select class="filter-select" id="filtroAmbiente">
                        <option value="todos">Todos los ambientes</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filtroInstructor">Instructor</label>
                    <select class="filter-select" id="filtroInstructor">
                        <option value="todos">Todos los instructores</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filtroEstado">Estado</label>
                    <select class="filter-select" id="filtroEstado">
                        <option value="todos">Todos los estados</option>
                        <option value="Aprobado">Aprobado</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Rechazado">Rechazado</option>
                    </select>
                </div>
                <button class="btn-reset" id="btnReset">
                    <i class="fa-solid fa-rotate-left"></i> Limpiar filtros
                </button>
            </div>
        </div>

        <div class="legend-card">
            <div class="legend-title"><i class="fa-solid fa-circle-info"></i> Leyenda</div>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="legend-dot" style="background:#2e7d32;"></span> Aprobado
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#f9a825;"></span> Pendiente
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#c62828;"></span> Rechazado
                </div>
            </div>
        </div>

    </div>

    <!-- STATS -->
    <div class="stats-strip">
        <div class="stat-chip total">
            <span class="label">Total:</span>
            <span class="count" id="statTotal">0</span>
        </div>
        <div class="stat-chip aprobado">
            <span class="label">Aprobados:</span>
            <span class="count" id="statAprobado">0</span>
        </div>
        <div class="stat-chip pendiente">
            <span class="label">Pendientes:</span>
            <span class="count" id="statPendiente">0</span>
        </div>
        <div class="stat-chip rechazado">
            <span class="label">Rechazados:</span>
            <span class="count" id="statRechazado">0</span>
        </div>
    </div>

    <!-- CALENDARIO -->
    <div class="calendar-card">
        <div class="cal-loading" id="calLoading">
            <div class="spinner"></div>
        </div>
        <div id="calendar"></div>
    </div>

</div>

<!-- ══ OVERLAY ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- ══ MODAL DETALLE EVENTO ════════════════════════════════════════════ -->
<div class="event-modal" id="eventModal">
    <div class="modal-stripe" id="modalStripe"></div>
    <div class="modal-head">
        <div class="modal-ambiente" id="mAmbiente"></div>
        <span class="modal-estado-badge" id="mEstadoBadge"></span>
    </div>
    <div class="modal-body">
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="modal-row-label">Instructor</div>
                <div class="modal-row-value" id="mInstructor"></div>
            </div>
        </div>
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-regular fa-calendar"></i></div>
            <div>
                <div class="modal-row-label">Período</div>
                <div class="modal-row-value" id="mPeriodo"></div>
            </div>
        </div>
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-regular fa-clock"></i></div>
            <div>
                <div class="modal-row-label">Horario</div>
                <div class="modal-row-value" id="mHorario"></div>
            </div>
        </div>
        <div class="modal-row" id="mObsRow" style="display:none;">
            <div class="modal-row-icon"><i class="fa-regular fa-comment"></i></div>
            <div>
                <div class="modal-row-label">Observaciones</div>
                <div class="modal-row-value" id="mObs"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="modal-btn-close" onclick="cerrarModales()">
            <i class="fa-solid fa-xmark"></i> Cerrar
        </button>
        <a href="#" class="modal-btn-edit" id="mBtnEditar">
            <i class="fa-solid fa-magnifying-glass"></i> Ver ambiente
        </a>
    </div>
</div>

<!-- ══ MODAL NUEVA RESERVA ═════════════════════════════════════════════ -->
<div class="new-modal" id="newModal">
    <div class="new-modal-head">
        <div>
            <h3><i class="fa-solid fa-calendar-plus"></i> Nueva Reserva</h3>
            <p id="newFechaDisplay">Fecha seleccionada</p>
        </div>
        <button class="modal-x" onclick="cerrarModales()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="new-modal-body">
        <div class="date-preview">
            <i class="fa-regular fa-calendar-check"></i>
            <div>
                <strong id="newFechaTexto"></strong>
                <span>Ir a permisos para completar la solicitud</span>
            </div>
        </div>
        <div class="quick-actions">
            <a href="permisos.php" class="quick-btn primary">
                <i class="fa-solid fa-circle-check"></i>
                Ir a Autorizar Ambiente
            </a>
            <button class="quick-btn secondary" onclick="cerrarModales()">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- ══ JAVASCRIPT ══════════════════════════════════════════════════════ -->
<script>
const $ = id => document.getElementById(id);

function showLoading(v) {
    $('calLoading').classList.toggle('visible', v);
}

function fmtFecha(isoStr) {
    if (!isoStr) return '—';
    const d = new Date(isoStr);
    if (isNaN(d)) return isoStr;
    const meses = ['Ene','Feb','Mar','Abr','May','Jun',
                   'Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${String(d.getDate()).padStart(2,'0')} ${meses[d.getMonth()]} ${d.getFullYear()}`;
}

function actualizarStats(events) {
    $('statTotal').textContent     = events.length;
    $('statAprobado').textContent  = events.filter(e => e.extendedProps.estado === 'Aprobado').length;
    $('statPendiente').textContent = events.filter(e => e.extendedProps.estado === 'Pendiente').length;
    $('statRechazado').textContent = events.filter(e => e.extendedProps.estado === 'Rechazado').length;
}

function abrirModal(tipo) {
    $('modalOverlay').classList.add('visible');
    requestAnimationFrame(() =>
        $(tipo === 'event' ? 'eventModal' : 'newModal').classList.add('visible')
    );
}

function cerrarModales() {
    $('modalOverlay').classList.remove('visible');
    $('eventModal').classList.remove('visible');
    $('newModal').classList.remove('visible');
}

document.addEventListener('DOMContentLoaded', function () {

    /* ── Cargar dropdowns ── */
    fetch('filtro_calendario.php?type=ambientes')
        .then(r => r.json())
        .then(data => {
            const sel = $('filtroAmbiente');
            data.forEach(a => sel.appendChild(new Option(a.nombre_ambiente, a.id)));
        }).catch(e => console.warn('Ambientes:', e));

    fetch('filtro_calendario.php?type=instructores')
        .then(r => r.json())
        .then(data => {
            const sel = $('filtroInstructor');
            data.forEach(i => sel.appendChild(new Option(i.nombre, i.id)));
        }).catch(e => console.warn('Instructores:', e));

    /* ── FullCalendar ── */
    const calendar = new FullCalendar.Calendar($('calendar'), {
        locale:      'es',
        initialView: 'timeGridWeek',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay',
        },
        buttonText: { today:'Hoy', month:'Mes', week:'Semana', day:'Día' },
        height:       'auto',
        slotMinTime:  '05:00:00',
        slotMaxTime:  '22:00:00',
        slotDuration: '00:30:00',
        allDaySlot:   false,
        nowIndicator: true,
        dayMaxEvents: 4,

        events: function (info, ok, fail) {
            showLoading(true);

            const params = new URLSearchParams({
                ambiente:   $('filtroAmbiente').value,
                instructor: $('filtroInstructor').value,
                estado:     $('filtroEstado').value,
                start:      info.startStr,
                end:        info.endStr,
            });

            console.log("📡 Enviando params:", params.toString());

            fetch('eventos.php?' + params)
                .then(r => {
                    console.log("📥 Response status:", r.status);
                    if (!r.ok) throw new Error("Error HTTP " + r.status);
                    return r.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error("❌ Error en eventos.php:", data.error);
                        fail(data.error);
                    } else {
                        actualizarStats(data);
                        ok(data);
                    }
                })
                .catch(err => {
                    console.error('❌ ERROR eventos.php:', err);
                    fail(err);
                })
                .finally(() => showLoading(false));
        },

        /* ── Clic en evento ── */
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p = info.event.extendedProps;

            $('modalStripe').style.background = info.event.backgroundColor;
            $('mAmbiente').textContent         = p.ambiente;
            $('mInstructor').textContent       = p.instructor;
            $('mHorario').textContent          = `${p.hora_inicio} — ${p.hora_fin}`;

            const fi = fmtFecha(info.event.startStr);
            const ff = fmtFecha(info.event.endStr);
            $('mPeriodo').textContent = fi === ff ? fi : `${fi} → ${ff}`;

            const cls = { Aprobado:'badge-aprobado', Pendiente:'badge-pendiente', Rechazado:'badge-rechazado' };
            const badge = $('mEstadoBadge');
            badge.className   = 'modal-estado-badge ' + (cls[p.estado] || '');
            badge.textContent = p.estado;

            if (p.obs) {
                $('mObs').textContent      = p.obs;
                $('mObsRow').style.display = 'flex';
            } else {
                $('mObsRow').style.display = 'none';
            }

            $('mBtnEditar').href = `consultar.php?ambiente=${encodeURIComponent(p.ambiente)}`;
            abrirModal('event');
        },

        /* ── Clic en espacio vacío ── */
        dateClick: function (info) {
            const d   = new Date(info.dateStr);
            const txt = d.toLocaleDateString('es-CO', {
                weekday:'long', year:'numeric', month:'long', day:'numeric'
            });
            $('newFechaTexto').textContent  = txt.charAt(0).toUpperCase() + txt.slice(1);
            $('newFechaDisplay').textContent = 'Fecha seleccionada:';
            abrirModal('new');
        },

        eventMouseEnter: function (info) {
            const p = info.event.extendedProps;
            info.el.title = `${p.ambiente}\n${p.instructor}\n${p.hora_inicio} - ${p.hora_fin}`;
        },
    });

    calendar.render();

    /* ── Filtros disparan refetch con debounce ── */
    let timeout;

    function refetchSeguro() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            calendar.refetchEvents();
        }, 300);
    }

    ['filtroAmbiente', 'filtroInstructor', 'filtroEstado'].forEach(id =>
        $(id).addEventListener('change', refetchSeguro)
    );

    /* ── Limpiar filtros ── */
    $('btnReset').addEventListener('click', () => {
        ['filtroAmbiente','filtroInstructor','filtroEstado']
            .forEach(id => $(id).value = 'todos');
        calendar.refetchEvents();
    });

    /* ── Cerrar modales ── */
    $('modalOverlay').addEventListener('click', cerrarModales);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModales(); });
});
</script>

</body>
</html>