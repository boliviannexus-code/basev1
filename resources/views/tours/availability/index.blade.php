@extends('layouts.admin')

@section('title', 'Disponibilidad de tours | '.config('app.name', 'Base Admin'))
@section('page-title', 'Disponibilidad')
@section('page-subtitle', 'Calendario operativo de disponibilidad, cupos y precios')

@push('styles')
    <style>
        .availability-control-card { margin-bottom: .5rem; }
        .availability-control-card .card-body { padding: .5rem .75rem; }
        .availability-toolbar { display: grid; grid-template-columns: minmax(220px, 320px) 160px auto 1fr; gap: .5rem; align-items: end; }
        .availability-toolbar .form-label,
        .availability-bulk-panel .form-label { font-size: .72rem; margin-bottom: .15rem; }
        .availability-calendar { border: 1px solid var(--tblr-border-color); border-radius: 8px; overflow: hidden; background: var(--tblr-bg-surface); }
        .availability-scroll { overflow: auto; max-height: calc(100vh - 200px); position: relative; }
        .availability-grid { display: grid; width: max-content; min-width: 100%; }
        .availability-row { display: grid; grid-template-columns: 280px repeat(var(--availability-days), 116px); min-height: 42px; }
        .availability-left,
        .availability-cell,
        .availability-date { border-right: 1px solid var(--tblr-border-color); border-bottom: 1px solid var(--tblr-border-color); }
        .availability-left { position: sticky; left: 0; z-index: 3; background: var(--tblr-bg-surface); padding: .55rem .75rem; }
        .availability-date { position: sticky; top: 0; z-index: 2; background: var(--tblr-bg-surface); padding: .35rem .4rem; text-align: center; }
        .availability-row:first-child .availability-left { position: sticky; top: 0; left: 0; z-index: 5; }
        .availability-cell { padding: .28rem; background: var(--tblr-bg-surface); }
        .availability-tour { min-height: 48px; background: var(--tblr-bg-surface-secondary); }
        .availability-row-label { font-size: .75rem; color: var(--tblr-secondary); text-transform: uppercase; letter-spacing: 0; }
        .availability-status { width: 100%; min-height: 32px; border: 1px solid transparent; border-radius: 6px; font-size: .75rem; font-weight: 700; }
        .availability-status.available { background: #e8f7ef; color: #087f3f; border-color: #9bd8b6; }
        .availability-status.closed,
        .availability-status.cancelled { background: #fdecec; color: #b42318; border-color: #f3a8a1; }
        .availability-status.sold_out { background: #fff4d6; color: #946200; border-color: #f1c969; }
        .availability-input { min-width: 96px; height: 32px; font-size: .75rem; }
        .availability-bookings { display: grid; place-items: center; min-height: 32px; border-radius: 6px; background: #eef6ff; color: #175cd3; font-weight: 700; font-size: .75rem; }
        .availability-special-price { background: #eaf4ff; border-color: #8ec5ff; }
        .availability-tour-disabled,
        .availability-cell-disabled { opacity: .55; }
        .availability-cell-disabled input,
        .availability-cell-disabled button { cursor: not-allowed; }
        .availability-week-start { box-shadow: inset 2px 0 0 var(--tblr-primary); }
        .availability-loading { min-height: 320px; display: grid; place-items: center; color: var(--tblr-secondary); }
        .availability-popover { position: fixed; width: min(360px, calc(100vw - 2rem)); z-index: 1080; background: var(--tblr-bg-surface); border: 1px solid var(--tblr-border-color); border-radius: 8px; box-shadow: 0 .75rem 2rem rgba(15, 23, 42, .18); padding: .75rem; }
        @media (max-width: 991px) { .availability-toolbar { grid-template-columns: 1fr; } .availability-scroll { max-height: none; } }
    </style>
@endpush

@section('content')
    <div class="card availability-control-card">
        <div class="card-body">
            <div class="availability-toolbar" data-availability-toolbar>
                <div>
                    <label class="form-label" for="availability-tour-filter">Tour</label>
                    <select class="form-select form-select-sm" id="availability-tour-filter" data-tour-filter>
                        <option value="">Todos los tours</option>
                        @foreach ($tours as $tour)
                            <option value="{{ $tour->id }}">{{ $tour->display_title ?: $tour->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="availability-start-date">Desde</label>
                    <input class="form-control form-control-sm" id="availability-start-date" type="date" value="{{ now()->toDateString() }}" data-start-date>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Navegacion calendario">
                    <button class="btn btn-outline-secondary" type="button" data-shift-days="-30"><i class="ti ti-chevrons-left"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-shift-days="-7"><i class="ti ti-chevron-left"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-shift-days="7"><i class="ti ti-chevron-right"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-shift-days="30"><i class="ti ti-chevrons-right"></i></button>
                </div>
                <div class="text-end">
                    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#availability-bulk-panel">
                        <i class="ti ti-edit"></i> Edicion masiva
                    </button>
                </div>
            </div>

            <div class="collapse mt-2 availability-bulk-panel" id="availability-bulk-panel">
                <form class="row g-1 align-items-end" data-bulk-form>
                    <div class="col-md-2"><label class="form-label">Desde</label><input class="form-control form-control-sm" name="start_date" type="date" required></div>
                    <div class="col-md-2"><label class="form-label">Hasta</label><input class="form-control form-control-sm" name="end_date" type="date" required></div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Sin cambiar</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Cupo maximo</label><input class="form-control form-control-sm" name="capacity" type="number" min="0" placeholder="Maximo del tour"></div>
                    <div class="col-md-2"><label class="form-label">Precio USD</label><input class="form-control form-control-sm" name="price_usd" type="number" min="0" step="0.01" placeholder="Todos los rangos"></div>
                    <div class="col-md-2"><button class="btn btn-success btn-sm w-100" type="submit">Aplicar</button></div>
                    <div class="col-12">
                        <div class="form-selectgroup">
                            @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 0 => 'Dom'] as $value => $label)
                                <label class="form-selectgroup-item">
                                    <input class="form-selectgroup-input" type="checkbox" name="weekdays[]" value="{{ $value }}" checked>
                                    <span class="form-selectgroup-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="availability-calendar" data-grid-shell>
        <div class="availability-loading">Cargando calendario...</div>
    </div>

    <div class="availability-popover d-none" data-day-popover>
        <form data-day-form>
            <input type="hidden" name="tour_id">
            <input type="hidden" name="date">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <div class="fw-semibold" data-popover-title>Editar dia</div>
                    <div class="text-body-secondary small" data-popover-subtitle></div>
                </div>
                <button class="btn-close" type="button" data-close-popover aria-label="Cerrar"></button>
            </div>
            <div class="mb-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="status">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Cupo maximo</label>
                <input class="form-control" name="capacity" type="number" min="0">
            </div>
            <div class="mb-3" data-popover-prices></div>
            <button class="btn btn-primary w-100" type="submit">Guardar cambios</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const shell = document.querySelector('[data-grid-shell]');
            const tourFilter = document.querySelector('[data-tour-filter]');
            const startInput = document.querySelector('[data-start-date]');
            const popover = document.querySelector('[data-day-popover]');
            const dayForm = document.querySelector('[data-day-form]');
            const bulkForm = document.querySelector('[data-bulk-form]');
            let gridState = { dates: [], tours: [], statuses: @json($statuses), range: {} };

            const statusClass = (status) => status || 'closed';
            const money = (value) => Number(value || 0).toFixed(2);
            const statusLabel = (status) => gridState.statuses[status] || status;
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
            const priceLabel = (price) => price.title || `Precio ${price.min_people}-${price.max_people || '+'}`;

            const requestJson = async (url, options = {}) => {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
                    ...options,
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'No se pudo completar la operacion.');
                return payload;
            };

            const loadGrid = async () => {
                closePopover();
                shell.innerHTML = '<div class="availability-loading">Cargando calendario...</div>';
                const params = new URLSearchParams({ start_date: startInput.value, days: '90' });
                if (tourFilter.value) params.set('tour_id', tourFilter.value);
                gridState = await requestJson(`{{ route('tours.availability.grid') }}?${params.toString()}`);
                renderGrid();
            };

            const renderGrid = () => {
                shell.style.setProperty('--availability-days', gridState.dates.length);
                const header = `
                    <div class="availability-row">
                        <div class="availability-left"><div class="fw-semibold">Tours</div><div class="text-body-secondary small">${gridState.range.start_date} / ${gridState.range.end_date}</div></div>
                        ${gridState.dates.map((date) => `<div class="availability-date ${date.is_week_start ? 'availability-week-start' : ''}"><div class="small text-body-secondary">${date.month}</div><div class="fw-semibold">${date.weekday}</div><div>${date.day}</div></div>`).join('')}
                    </div>`;
                const rows = gridState.tours.map((tour) => tourRows(tour)).join('');
                shell.innerHTML = `<div class="availability-scroll"><div class="availability-grid">${header}${rows || emptyRow()}</div></div>`;
            };

            const emptyRow = () => `<div class="availability-row"><div class="availability-left">Sin tours disponibles</div><div class="availability-cell" style="grid-column: span ${gridState.dates.length};"></div></div>`;

            const tourRows = (tour) => {
                const disabled = !tour.is_active;
                const disabledAttr = disabled ? 'disabled aria-disabled="true" title="Tour inactivo"' : '';
                const disabledClass = disabled ? ' availability-cell-disabled' : '';
                const tourHeader = `
                    <div class="availability-row">
                        <div class="availability-left availability-tour ${disabled ? 'availability-tour-disabled' : ''}">
                            <div class="fw-semibold text-truncate">${escapeHtml(tour.title)}</div>
                        </div>
                        ${gridState.dates.map((date) => `<div class="availability-cell availability-tour ${disabledClass} ${date.is_week_start ? 'availability-week-start' : ''}"></div>`).join('')}
                    </div>`;
                const statusRow = row(tour, 'Estado', (day, date) => `<button class="availability-status ${statusClass(day.status)}" type="button" data-edit-day data-tour-id="${tour.id}" data-date="${date.date}" ${disabledAttr}>${statusLabel(day.status)}</button>`);
                const bookingsRow = row(tour, 'Reservas', (day) => `<div class="availability-bookings">${day.booked_count || 0}</div>`);
                const capacityRow = row(tour, 'Cupo maximo', (day, date) => `<input class="form-control availability-input" type="number" min="0" value="${day.capacity ?? ''}" data-quick-capacity data-tour-id="${tour.id}" data-date="${date.date}" placeholder="Max." ${disabledAttr}>`);
                const priceRows = (tour.prices.length ? tour.prices : [{ tour_price_id: '', title: '', min_people: 1, max_people: null, price_usd: 0 }]).map((price) => row(tour, priceLabel(price), (day, date) => {
                    const dayPrice = (day.prices || []).find((item) => String(item.tour_price_id || '') === String(price.tour_price_id || '')) || price;
                    const special = Number(dayPrice.price_usd) !== Number(price.price_usd);
                    return `<input class="form-control availability-input ${special ? 'availability-special-price' : ''}" type="number" min="0" step="0.01" value="${money(dayPrice.price_usd)}" data-quick-price data-tour-id="${tour.id}" data-date="${date.date}" data-tour-price-id="${price.tour_price_id || ''}" data-min-people="${price.min_people}" data-max-people="${price.max_people || ''}" ${disabledAttr}>`;
                })).join('');

                return tourHeader + statusRow + bookingsRow + capacityRow + priceRows;
            };

            const row = (tour, label, cell) => `
                <div class="availability-row">
                    <div class="availability-left"><span class="availability-row-label">${escapeHtml(label)}</span></div>
                    ${gridState.dates.map((date) => {
                        const day = tour.days[date.date];
                        return `<div class="availability-cell ${!tour.is_active ? 'availability-cell-disabled' : ''} ${date.is_week_start ? 'availability-week-start' : ''}">${cell(day, date)}</div>`;
                    }).join('')}
                </div>`;

            const findTourDay = (tourId, date) => {
                const tour = gridState.tours.find((item) => String(item.id) === String(tourId));
                return { tour, day: tour?.days?.[date] };
            };

            const openPopover = (button) => {
                const { tour, day } = findTourDay(button.dataset.tourId, button.dataset.date);
                if (!tour || !day || !tour.is_active) return;
                dayForm.tour_id.value = tour.id;
                dayForm.date.value = day.date;
                dayForm.status.value = day.status;
                dayForm.capacity.value = day.capacity ?? '';
                popover.querySelector('[data-popover-title]').textContent = tour.title;
                popover.querySelector('[data-popover-subtitle]').textContent = day.date;
                popover.querySelector('[data-popover-prices]').innerHTML = (day.prices || []).map((price, index) => `
                    <div class="mb-2">
                        <label class="form-label">${escapeHtml(priceLabel(price))}</label>
                        <input type="hidden" name="prices[${index}][tour_price_id]" value="${price.tour_price_id || ''}">
                        <input type="hidden" name="prices[${index}][title]" value="${escapeHtml(price.title || '')}">
                        <input type="hidden" name="prices[${index}][min_people]" value="${price.min_people}">
                        <input type="hidden" name="prices[${index}][max_people]" value="${price.max_people || ''}">
                        <input class="form-control" name="prices[${index}][price_usd]" type="number" min="0" step="0.01" value="${money(price.price_usd)}">
                    </div>`).join('');
                const rect = button.getBoundingClientRect();
                popover.style.top = `${Math.min(rect.bottom + 8, window.innerHeight - 420)}px`;
                popover.style.left = `${Math.min(rect.left, window.innerWidth - 380)}px`;
                popover.classList.remove('d-none');
            };

            const closePopover = () => popover?.classList.add('d-none');

            const formPayload = (form) => {
                const payload = {};
                new FormData(form).forEach((value, key) => {
                    const match = key.match(/^prices\[(\d+)]\[(.+)]$/);
                    if (match) {
                        payload.prices = payload.prices || [];
                        payload.prices[Number(match[1])] = payload.prices[Number(match[1])] || {};
                        payload.prices[Number(match[1])][match[2]] = value;
                    } else if (key === 'weekdays[]') {
                        payload.weekdays = payload.weekdays || [];
                        payload.weekdays.push(Number(value));
                    } else {
                        payload[key] = value;
                    }
                });
                return payload;
            };

            shell.addEventListener('click', (event) => {
                const button = event.target.closest('[data-edit-day]');
                if (button) openPopover(button);
            });
            shell.addEventListener('change', async (event) => {
                const capacity = event.target.closest('[data-quick-capacity]');
                const price = event.target.closest('[data-quick-price]');
                if (!capacity && !price) return;
                const input = capacity || price;
                const { day } = findTourDay(input.dataset.tourId, input.dataset.date);
                if (input.disabled) return;
                const payload = { tour_id: input.dataset.tourId, date: input.dataset.date, status: day.status, capacity: day.capacity, prices: day.prices || [] };
                if (capacity) payload.capacity = capacity.value;
                if (price) {
                    const item = payload.prices.find((row) => String(row.tour_price_id || '') === String(price.dataset.tourPriceId || ''));
                    if (item) item.price_usd = price.value;
                }
                await requestJson('{{ route('tours.availability.day.update') }}', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                await loadGrid();
            });
            dayForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                await requestJson('{{ route('tours.availability.day.update') }}', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formPayload(dayForm)) });
                await loadGrid();
            });
            bulkForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = formPayload(bulkForm);
                if (tourFilter.value) payload.tour_id = tourFilter.value;
                if (payload.price_usd !== undefined && payload.price_usd !== '') {
                    payload.bulk_price_usd = payload.price_usd;
                }
                delete payload.price_usd;
                await requestJson('{{ route('tours.availability.bulk') }}', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                await loadGrid();
            });
            document.querySelector('[data-close-popover]')?.addEventListener('click', closePopover);
            tourFilter.addEventListener('change', loadGrid);
            startInput.addEventListener('change', loadGrid);
            document.querySelectorAll('[data-shift-days]').forEach((button) => button.addEventListener('click', () => {
                const date = new Date(`${startInput.value}T00:00:00`);
                date.setDate(date.getDate() + Number(button.dataset.shiftDays));
                startInput.value = date.toISOString().slice(0, 10);
                loadGrid();
            }));
            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-day-popover], [data-edit-day]')) closePopover();
            });
            loadGrid();
        });
    </script>
@endpush
