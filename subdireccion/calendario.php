<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'subdireccion') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Ambientes — SENA</title>

    <!-- FullCalendar v6 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- CSS del calendario -->
    <link rel="stylesheet" href="../css/calendario.css">
</head>
<body>

<!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
<div class="header">
    <div class="header-left">
        <img src="../css/img/senab.png" alt="Logo SENA" class="logo-sena">
        <div class="header-title">
            <h1>Calendario de Ambientes</h1>
        </div>
    </div>
    <div class="header-right">
        <a href="index.php" class="btn-volver">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver al Panel</span>
        </a>
        <div class="header-user">
            <i class="fa-solid fa-user"></i> Subdireccion
        </div>
        
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
            <div class="legend-title">
                <i class="fa-solid fa-circle-info"></i> Leyenda
            </div>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="legend-dot" style="background:#2e7d32;color:#2e7d32;"></span> Aprobado
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#f9a825;color:#f9a825;"></span> Pendiente
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#c62828;color:#c62828;"></span> Rechazado
                </div>
            </div>
        </div>

    </div>

    <!-- STATS -->
    <div class="stats-strip">
        <div class="stat-chip total">
            <span class="stat-chip-bar"></span>
            <span class="label">Total:</span>
            <span class="count" id="statTotal">0</span>
        </div>
        <div class="stat-chip aprobado">
            <span class="stat-chip-bar"></span>
            <span class="label">Aprobados:</span>
            <span class="count" id="statAprobado">0</span>
        </div>
        <div class="stat-chip pendiente">
            <span class="stat-chip-bar"></span>
            <span class="label">Pendientes:</span>
            <span class="count" id="statPendiente">0</span>
        </div>
        <div class="stat-chip rechazado">
            <span class="stat-chip-bar"></span>
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

</div><!-- /.cal-wrapper -->

<!-- ══ OVERLAY ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- ══ MODAL DETALLE EVENTO ════════════════════════════════════════════ -->
<div class="event-modal" id="eventModal" role="dialog" aria-modal="true" aria-labelledby="mAmbiente">

    <div class="modal-stripe" id="modalStripe"></div>

    <div class="modal-head">
        <div class="modal-ambiente" id="mAmbiente"></div>
        <span class="modal-estado-badge" id="mEstadoBadge"></span>
    </div>

    <div class="modal-body">

        <!-- Instructor -->
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="modal-row-label">Instructor</div>
                <div class="modal-row-value" id="mInstructor"></div>
            </div>
        </div>

        <!-- Período -->
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-regular fa-calendar"></i></div>
            <div>
                <div class="modal-row-label">Período</div>
                <div class="modal-row-value" id="mPeriodo"></div>
            </div>
        </div>

        <!-- Horario -->
        <div class="modal-row">
            <div class="modal-row-icon"><i class="fa-regular fa-clock"></i></div>
            <div>
                <div class="modal-row-label">Horario</div>
                <div class="modal-row-value" id="mHorario"></div>
            </div>
        </div>

        <!-- Ficha (visible solo si existe) -->
        <div class="modal-row modal-ficha-block" id="mFichaRow" style="display:none;">
            <div class="modal-row-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <div class="modal-row-label">Ficha</div>
                <div class="modal-ficha-numero" id="mFichaNumero"></div>
                <div class="modal-ficha-programa" id="mFichaPrograma"></div>
            </div>
        </div>

        <!-- Observaciones (visible solo si existe) -->
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
<div class="new-modal" id="newModal" role="dialog" aria-modal="true">

    <div class="new-modal-head">
        <div>
            <h3><i class="fa-solid fa-calendar-plus"></i> Nueva Reserva</h3>
            <p id="newFechaDisplay">Fecha seleccionada</p>
        </div>
        <button class="modal-x" onclick="cerrarModales()" aria-label="Cerrar">
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
/* ── Helpers ── */
const $ = id => document.getElementById(id);

function showLoading(v) {
    $('calLoading').classList.toggle('visible', v);
}

function fmtFecha(isoStr) {
    if (!isoStr) return '—';
    // Parseamos solo la parte de fecha para evitar desfase por zona horaria
    const parte = isoStr.substring(0, 10);
    const [y, m, d] = parte.split('-').map(Number);
    const meses = ['Ene','Feb','Mar','Abr','May','Jun',
                   'Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${String(d).padStart(2,'0')} ${meses[m - 1]} ${y}`;
}

function actualizarStats(events) {
    $('statTotal').textContent     = events.length;
    $('statAprobado').textContent  = events.filter(e => e.extendedProps?.estado === 'Aprobado').length;
    $('statPendiente').textContent = events.filter(e => e.extendedProps?.estado === 'Pendiente').length;
    $('statRechazado').textContent = events.filter(e => e.extendedProps?.estado === 'Rechazado').length;
}

function abrirModal(tipo) {
    $('modalOverlay').classList.add('visible');
    // Pequeño delay para que la animación de escala se vea suave
    requestAnimationFrame(() => {
        $(tipo === 'event' ? 'eventModal' : 'newModal').classList.add('visible');
    });
}

function cerrarModales() {
    $('modalOverlay').classList.remove('visible');
    $('eventModal').classList.remove('visible');
    $('newModal').classList.remove('visible');
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function () {

    /* ── Cargar dropdown de ambientes ── */
    fetch('filtro_calendario.php?type=ambientes')
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            const sel = $('filtroAmbiente');
            data.forEach(a => {
                const opt = new Option(a.nombre_ambiente, a.id);
                sel.appendChild(opt);
            });
        })
        .catch(e => console.warn('No se pudieron cargar ambientes:', e));

    /* ── Cargar dropdown de instructores ── */
    fetch('filtro_calendario.php?type=instructores')
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            const sel = $('filtroInstructor');
            data.forEach(i => {
                const opt = new Option(i.nombre, i.id);
                sel.appendChild(opt);
            });
        })
        .catch(e => console.warn('No se pudieron cargar instructores:', e));

    /* ── FullCalendar ── */
    const calEl    = $('calendar');
    const calendar = new FullCalendar.Calendar(calEl, {
        locale:        'es',
        initialView:   'timeGridWeek',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay',
        },
        buttonText: {
            today:  'Hoy',
            month:  'Mes',
            week:   'Semana',
            day:    'Día',
        },
        height:        'auto',
        slotMinTime:   '05:00:00',
        slotMaxTime:   '22:00:00',
        slotDuration:  '00:30:00',
        allDaySlot:    false,
        nowIndicator:  true,
        dayMaxEvents:  4,

        /* ── Fuente de eventos ── */
        events: function (info, successCallback, failureCallback) {
            showLoading(true);

            const params = new URLSearchParams({
                ambiente:   $('filtroAmbiente').value,
                instructor: $('filtroInstructor').value,
                estado:     $('filtroEstado').value,
                start:      info.startStr,
                end:        info.endStr,
            });

            fetch('eventos.php?' + params)
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Error en eventos.php:', data.error);
                        failureCallback(data.error);
                    } else {
                        actualizarStats(data);
                        successCallback(data);
                    }
                })
                .catch(err => {
                    console.error('Fetch eventos.php falló:', err);
                    failureCallback(err);
                })
                .finally(() => showLoading(false));
        },

        /* ── Clic en evento: modal detalle ── */
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p = info.event.extendedProps;

            /* Franja de color */
            $('modalStripe').style.background = info.event.backgroundColor || '#1a2744';

            /* Datos básicos */
            $('mAmbiente').textContent   = p.ambiente   || '—';
            $('mInstructor').textContent = p.instructor || '—';
            $('mHorario').textContent    = (p.hora_inicio && p.hora_fin)
                                            ? `${p.hora_inicio} — ${p.hora_fin}`
                                            : '—';

            /* Período */
            const fi = fmtFecha(info.event.startStr);
            const ff = fmtFecha(info.event.endStr);
            $('mPeriodo').textContent = (fi && ff && fi !== ff) ? `${fi} → ${ff}` : fi || '—';

            /* Badge de estado */
            const clsMap = {
                Aprobado:  'badge-aprobado',
                Pendiente: 'badge-pendiente',
                Rechazado: 'badge-rechazado',
            };
            const badge   = $('mEstadoBadge');
            badge.className   = 'modal-estado-badge ' + (clsMap[p.estado] || '');
            badge.textContent = p.estado || '';

            /* Ficha */
            if (p.numero_ficha) {
                $('mFichaNumero').textContent   = p.numero_ficha;
                $('mFichaPrograma').textContent = p.programa || '';
                $('mFichaPrograma').style.display = p.programa ? 'block' : 'none';
                $('mFichaRow').style.display    = 'flex';
            } else {
                $('mFichaRow').style.display = 'none';
            }

            /* Observaciones */
            if (p.obs) {
                $('mObs').textContent      = p.obs;
                $('mObsRow').style.display = 'flex';
            } else {
                $('mObsRow').style.display = 'none';
            }

            /* Botón Ver ambiente */
            $('mBtnEditar').href = p.ambiente
                ? `consultar.php?ambiente=${encodeURIComponent(p.ambiente)}`
                : '#';

            abrirModal('event');
        },

        /* ── Clic en espacio vacío → modal nueva reserva ── */
        dateClick: function (info) {
            const d   = new Date(info.dateStr + (info.dateStr.length === 10 ? 'T00:00:00' : ''));
            const txt = d.toLocaleDateString('es-CO', {
                weekday: 'long',
                year:    'numeric',
                month:   'long',
                day:     'numeric',
            });
            $('newFechaTexto').textContent   = txt.charAt(0).toUpperCase() + txt.slice(1);
            $('newFechaDisplay').textContent = 'Fecha seleccionada:';
            abrirModal('new');
        },

        /* ── Tooltip en hover ── */
        eventMouseEnter: function (info) {
            const p     = info.event.extendedProps;
            const ficha = p.numero_ficha ? ` | Ficha: ${p.numero_ficha}` : '';
            info.el.title = [
                p.ambiente,
                p.instructor,
                ficha,
                `${p.hora_inicio} - ${p.hora_fin}`,
            ].filter(Boolean).join('\n');
        },
    });

    calendar.render();

    /* ── Filtros con debounce ── */
    let debounceTimer;
    function refetchDebounced() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => calendar.refetchEvents(), 300);
    }

    ['filtroAmbiente', 'filtroInstructor', 'filtroEstado'].forEach(id =>
        $(id).addEventListener('change', refetchDebounced)
    );

    /* ── Limpiar filtros ── */
    $('btnReset').addEventListener('click', () => {
        ['filtroAmbiente', 'filtroInstructor', 'filtroEstado']
            .forEach(id => $(id).value = 'todos');
        calendar.refetchEvents();
    });

    /* ── Cerrar modales ── */
    $('modalOverlay').addEventListener('click', cerrarModales);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') cerrarModales();
    });

});
</script>

</body>
</html>