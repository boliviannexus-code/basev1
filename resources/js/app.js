import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import DataTable from 'datatables.net-bs5';
import TomSelect from 'tom-select';
import 'datatables.net-responsive-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';

window.Swal = Swal;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const ajaxModalElement = document.getElementById('ajaxModal');
const ajaxModal = ajaxModalElement ? new bootstrap.Modal(ajaxModalElement) : null;
const ajaxModalTitle = document.getElementById('ajaxModalTitle');
const ajaxModalBody = ajaxModalElement?.querySelector('[data-modal-body]');

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
});

function showInitialAlerts() {
    const success = document.querySelector('[data-swal-success]')?.dataset.swalSuccess;
    const error = document.querySelector('[data-swal-error]')?.dataset.swalError;

    if (success) {
        toast.fire({ icon: 'success', title: success });
    }

    if (error) {
        Swal.fire({ icon: 'error', title: 'Atencion', text: error });
    }
}

async function fetchHtml(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error('No se pudo cargar el contenido solicitado.');
    }

    return response.text();
}

function openAjaxModal(trigger) {
    if (!ajaxModal || !ajaxModalBody || !ajaxModalTitle) {
        window.location.href = trigger.href;

        return;
    }

    ajaxModalTitle.textContent = trigger.dataset.modalTitle ?? 'Detalle';
    ajaxModalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    ajaxModal.show();

    fetchHtml(trigger.dataset.modalUrl ?? trigger.href)
        .then((html) => {
            ajaxModalBody.innerHTML = html;
            disableBusinessFormAutocomplete(ajaxModalBody);
            initTomSelects(ajaxModalBody);
            syncPointSaleWarehouse(ajaxModalBody);
            initDefragmentForms(ajaxModalBody);
            initTransferForms(ajaxModalBody);
            initStockAdjustmentForms(ajaxModalBody);
        })
        .catch((error) => {
            ajaxModal.hide();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        });
}

function disableBusinessFormAutocomplete(scope = document) {
    scope.querySelectorAll('form[data-ajax-form], .form-panel form').forEach((form) => {
        form.setAttribute('autocomplete', 'off');
    });

    scope.querySelectorAll('form[data-ajax-form] input, form[data-ajax-form] textarea, .form-panel input, .form-panel textarea').forEach((field) => {
        if (['hidden', 'checkbox', 'radio', 'submit', 'button'].includes(field.type)) {
            return;
        }

        const shouldUsePasswordToken = field.name === 'name' || field.id.endsWith('-name');
        field.setAttribute('autocomplete', shouldUsePasswordToken ? 'new-password' : 'off');
        field.setAttribute('data-lpignore', 'true');
        field.setAttribute('data-1p-ignore', 'true');
    });
}

function clearFormErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    form.querySelectorAll('[data-error-for]').forEach((target) => {
        target.textContent = '';
    });
}

function showFormErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        const feedback = form.querySelector(`[data-error-for="${field}"]`);

        input?.classList.add('is-invalid');

        if (feedback) {
            feedback.textContent = messages[0] ?? 'Dato invalido.';
        }
    });
}

function setSubmitting(form, submitting) {
    const submit = form.querySelector('[type="submit"]');
    const spinner = form.querySelector('[data-submit-spinner]');

    if (submit) {
        submit.disabled = submitting;
    }

    spinner?.classList.toggle('d-none', !submitting);
}

async function refreshContainer(url) {
    const current = document.querySelector('[data-refresh-container]');

    if (!current) {
        return;
    }

    const html = await fetchHtml(url ?? window.location.href);
    const documentFragment = new DOMParser().parseFromString(html, 'text/html');
    const fresh = documentFragment.querySelector('[data-refresh-container]');

    if (fresh) {
        current.replaceWith(fresh);
        initTomSelects(fresh);
        initAdminDataTables();
        initCharacterCounters(fresh);
        initSpaceLocationMaps(fresh);
        initSharedRoomSort(fresh);
        initPhotoUploadPreviews(fresh);
    }
}

async function submitAjaxForm(form) {
    clearFormErrors(form);
    setSubmitting(form, true);

    try {
        const response = await fetch(form.action, {
            method: form.method.toUpperCase(),
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json();

        if (response.status === 422) {
            showFormErrors(form, payload.errors ?? payload.data ?? {});
            Swal.fire({ icon: 'error', title: 'Validacion', text: payload.message ?? 'Revisa los datos ingresados.' });

            return;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo completar la operacion.');
        }

        ajaxModal?.hide();
        await refreshContainer(payload.refresh_url ?? form.dataset.refreshUrl);
        toast.fire({ icon: 'success', title: payload.message ?? 'Operacion realizada correctamente.' });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
    } finally {
        setSubmitting(form, false);
    }
}

function confirmDelete(form) {
    Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmDelete ?? 'Confirmar eliminacion',
        text: 'Esta accion no se puede deshacer facilmente.',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
    }).then((result) => {
        if (result.isConfirmed) {
            if (form.matches('[data-ajax-form]')) {
                submitAjaxForm(form);

                return;
            }

            form.submit();
        }
    });
}

function initCharacterCounters(scope = document) {
    scope.querySelectorAll('[data-character-counter]').forEach((field) => {
        if (field.dataset.characterCounterInitialized === '1') {
            return;
        }

        const target = scope.querySelector(field.dataset.characterCounter) ?? document.querySelector(field.dataset.characterCounter);

        if (!target) {
            return;
        }

        const min = Number(field.getAttribute('minlength') || 0);
        const max = Number(field.getAttribute('maxlength') || 0);
        const update = () => {
            const length = field.value.length;
            const minimum = min ? ` / minimo ${min}` : '';
            const maximum = max ? ` / maximo ${max}` : '';
            target.textContent = `${length} caracteres${minimum}${maximum}`;
            target.classList.toggle('text-danger', (min && length < min) || (max && length > max));
            target.classList.toggle('text-success', (!min || length >= min) && (!max || length <= max));
        };

        field.addEventListener('input', update);
        field.dataset.characterCounterInitialized = '1';
        update();
    });
}

let googleMapsLoaderPromise = null;

function loadGoogleMaps(apiKey) {
    if (window.google?.maps?.places) {
        return Promise.resolve(window.google);
    }

    if (googleMapsLoaderPromise) {
        return googleMapsLoaderPromise;
    }

    googleMapsLoaderPromise = new Promise((resolve, reject) => {
        const callback = `initGoogleMaps${Date.now()}`;
        window[callback] = () => {
            delete window[callback];
            resolve(window.google);
        };

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey.trim())}&libraries=places&v=weekly&auth_referrer_policy=origin&callback=${callback}`;
        script.async = true;
        script.defer = true;
        script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));
        document.head.appendChild(script);
    });

    return googleMapsLoaderPromise;
}

function initSpaceLocationMaps(scope = document) {
    scope.querySelectorAll('[data-space-location-map]').forEach((root) => {
        if (root.dataset.locationMapInitialized === '1') {
            return;
        }

        const apiKey = root.dataset.googleMapsKey;
        const canvas = root.querySelector('[data-location-canvas]');
        const form = root.closest('form');

        if (!apiKey || !canvas || !form) {
            syncSpaceLocationAliasFields(form);
            return;
        }

        root.dataset.locationMapInitialized = '1';
        loadGoogleMaps(apiKey)
            .then(() => mountSpaceLocationMap(root, form, canvas))
            .catch((error) => {
                canvas.innerHTML = `<div class="space-location-map-fallback">${error.message}</div>`;
            });
    });
}

function mountSpaceLocationMap(root, form, canvas) {
    const fields = {
        search: root.querySelector('[data-location-search]'),
        country: form.querySelector('[data-location-field="country"]'),
        state: form.querySelector('[data-location-field="state_or_region"]'),
        city: form.querySelector('[data-location-field="city"]'),
        zone: form.querySelector('[data-location-field="zone_or_neighborhood"]'),
        address: form.querySelector('[data-location-field="address"]'),
        addressText: form.querySelector('[data-location-field="address_text"]'),
        reference: form.querySelector('[data-location-field="reference"]'),
        referenceText: form.querySelector('[data-location-field="reference_text"]'),
        latitude: form.querySelector('[data-location-field="latitude"]'),
        longitude: form.querySelector('[data-location-field="longitude"]'),
        placeId: form.querySelector('[data-location-field="google_place_id"]'),
    };
    const center = {
        lat: Number(root.dataset.lat || -16.2902),
        lng: Number(root.dataset.lng || -63.5887),
    };
    const map = new google.maps.Map(canvas, {
        center,
        zoom: root.dataset.hasLocation === '1' ? 16 : 6,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });
    const marker = new google.maps.Marker({
        map,
        position: center,
        draggable: true,
    });
    const geocoder = new google.maps.Geocoder();

    const setPosition = (latLng, shouldReverseGeocode = false) => {
        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
        marker.setPosition({ lat, lng });
        map.panTo({ lat, lng });
        fields.latitude.value = lat.toFixed(7);
        fields.longitude.value = lng.toFixed(7);

        if (shouldReverseGeocode) {
            fields.placeId.value = '';
            reverseGeocode(geocoder, { lat, lng }, fields);
        }
    };

    if (fields.search) {
        const autocomplete = new google.maps.places.Autocomplete(fields.search, {
            componentRestrictions: { country: 'bo' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id'],
        });
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();

            if (!place.geometry?.location) {
                return;
            }

            fillLocationFieldsFromPlace(place, fields);
            setPosition(place.geometry.location, false);
            map.setZoom(17);
        });
    }

    map.addListener('click', (event) => setPosition(event.latLng, true));
    marker.addListener('dragend', (event) => setPosition(event.latLng, true));

    fields.address?.addEventListener('input', () => syncSpaceLocationAliasFields(form));
    fields.reference?.addEventListener('input', () => syncSpaceLocationAliasFields(form));
    syncSpaceLocationAliasFields(form);
}

function reverseGeocode(geocoder, location, fields) {
    geocoder.geocode({ location }, (results, status) => {
        if (status !== 'OK' || !results?.[0]) {
            return;
        }

        fillLocationFieldsFromPlace(results[0], fields);
    });
}

function fillLocationFieldsFromPlace(place, fields) {
    const components = place.address_components ?? [];
    const byType = (type) => components.find((component) => component.types.includes(type))?.long_name ?? '';
    const city = byType('locality') || byType('administrative_area_level_2') || byType('administrative_area_level_1');
    const route = byType('route');
    const streetNumber = byType('street_number');
    const neighborhood = byType('sublocality') || byType('neighborhood');
    const address = place.formatted_address || [route, streetNumber].filter(Boolean).join(' ') || place.name || '';

    if (fields.country && byType('country')) fields.country.value = byType('country');
    if (fields.state && byType('administrative_area_level_1')) fields.state.value = byType('administrative_area_level_1');
    if (fields.city && city) fields.city.value = city;
    if (fields.zone && neighborhood) fields.zone.value = neighborhood;
    if (fields.address && address) fields.address.value = address;
    if (fields.addressText && address) fields.addressText.value = address;
    if (fields.placeId) fields.placeId.value = place.place_id ?? '';
}

function syncSpaceLocationAliasFields(form) {
    const address = form.querySelector('[data-location-field="address"]');
    const addressText = form.querySelector('[data-location-field="address_text"]');
    const reference = form.querySelector('[data-location-field="reference"]');
    const referenceText = form.querySelector('[data-location-field="reference_text"]');

    if (address && addressText) {
        addressText.value = address.value;
    }

    if (reference && referenceText) {
        referenceText.value = reference.value;
    }
}

function initPublicAccommodationSearch(scope = document) {
    scope.querySelectorAll('[data-public-accommodation-search]').forEach((form) => {
        if (form.dataset.publicSearchInitialized === '1') {
            return;
        }

        const apiKey = form.dataset.googleMapsKey;
        const input = form.querySelector('[data-public-destination-search]');

        if (!apiKey || !input) {
            return;
        }

        form.dataset.publicSearchInitialized = '1';
        loadGoogleMaps(apiKey)
            .then(() => mountPublicDestinationSearch(form, input))
            .catch(() => {
                clearPublicDestinationFields(form);
            });
    });
}

function mountPublicDestinationSearch(form, input) {
    const autocomplete = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: 'bo' },
        fields: ['address_components', 'formatted_address', 'geometry', 'name'],
        types: ['(regions)'],
    });

    input.addEventListener('input', () => clearPublicDestinationFields(form));

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();

        if (!place.geometry?.location) {
            clearPublicDestinationFields(form);

            return;
        }

        fillPublicDestinationFields(form, place);
    });
}

function fillPublicDestinationFields(form, place) {
    const components = place.address_components ?? [];
    const byType = (type) => components.find((component) => component.types.includes(type))?.long_name ?? '';
    const city = byType('locality') || byType('administrative_area_level_2');
    const state = byType('administrative_area_level_1');
    const country = byType('country');
    const location = place.geometry.location;
    const fields = publicDestinationFields(form);

    if (place.formatted_address || place.name) {
        const input = form.querySelector('[data-public-destination-search]');
        input.value = place.formatted_address || place.name;
    }

    if (fields.latitude) fields.latitude.value = location.lat().toFixed(7);
    if (fields.longitude) fields.longitude.value = location.lng().toFixed(7);
    if (fields.city) fields.city.value = city;
    if (fields.state) fields.state.value = state;
    if (fields.country) fields.country.value = country;
}

function clearPublicDestinationFields(form) {
    Object.values(publicDestinationFields(form)).forEach((field) => {
        if (field) {
            field.value = '';
        }
    });
}

function publicDestinationFields(form) {
    return {
        latitude: form.querySelector('[data-public-destination-field="latitude"]'),
        longitude: form.querySelector('[data-public-destination-field="longitude"]'),
        city: form.querySelector('[data-public-destination-field="city"]'),
        state: form.querySelector('[data-public-destination-field="state"]'),
        country: form.querySelector('[data-public-destination-field="country"]'),
    };
}

function initSharedRoomSort(scope = document) {
    scope.querySelectorAll('[data-room-sort-list]').forEach((list) => {
        if (list.dataset.roomSortInitialized === '1') {
            return;
        }

        let draggedRow = null;

        const saveOrder = async () => {
            const roomIds = [...list.querySelectorAll('[data-room-id]')].map((row) => row.dataset.roomId);
            const response = await fetch(list.dataset.sortUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ room_ids: roomIds }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? 'No se pudo actualizar el orden.');
            }

            toast.fire({ icon: 'success', title: payload.message ?? 'Orden actualizado.' });
        };

        list.querySelectorAll('[data-room-id]').forEach((row) => {
            row.addEventListener('dragstart', (event) => {
                draggedRow = row;
                row.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.roomId);
            });

            row.addEventListener('dragend', () => {
                row.classList.remove('is-dragging');
                draggedRow = null;
                saveOrder().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
            });

            row.addEventListener('dragover', (event) => {
                event.preventDefault();

                if (!draggedRow || draggedRow === row) {
                    return;
                }

                const rect = row.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2;
                list.insertBefore(draggedRow, after ? row.nextSibling : row);
            });
        });

        list.dataset.roomSortInitialized = '1';
    });
}

function formatFileSize(bytes) {
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}

function validatePhotoInput(input) {
    const files = [...input.files];
    const maxSizeKb = Number(input.dataset.photoMaxSize ?? 4096);
    const maxFiles = Number(input.dataset.photoMaxFiles ?? 1);
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const errorTarget = input.closest('.col-md-6, .col-12, form')?.querySelector('[data-photo-error]');
    const preview = document.querySelector(input.dataset.photoPreview ?? '');
    const errors = [];

    if (files.length > maxFiles) {
        errors.push(`Selecciona maximo ${maxFiles} archivo${maxFiles === 1 ? '' : 's'}.`);
    }

    files.forEach((file) => {
        if (!allowedTypes.includes(file.type)) {
            errors.push(`${file.name}: formato no permitido.`);
        }

        if (file.size > maxSizeKb * 1024) {
            errors.push(`${file.name}: pesa ${formatFileSize(file.size)} y el maximo es ${(maxSizeKb / 1024).toFixed(0)} MB.`);
        }
    });

    input.classList.toggle('is-invalid', errors.length > 0);
    input.dataset.photoInvalid = errors.length > 0 ? '1' : '0';

    if (errorTarget) {
        errorTarget.textContent = errors[0] ?? '';
    }

    if (preview) {
        preview.innerHTML = '';

        if (errors.length > 0) {
            preview.innerHTML = `<div class="photo-upload-error">${errors.join('<br>')}</div>`;
        } else {
            files.forEach((file) => {
                const item = document.createElement('div');
                item.className = 'photo-upload-preview-item';
                item.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="${file.name}"><span>${file.name}</span>`;
                preview.append(item);
            });
        }
    }

    return errors.length === 0;
}

function initPhotoUploadPreviews(scope = document) {
    scope.querySelectorAll('[data-photo-input]').forEach((input) => {
        if (input.dataset.photoPreviewInitialized === '1') {
            return;
        }

        input.addEventListener('change', () => validatePhotoInput(input));
        input.dataset.photoPreviewInitialized = '1';
    });
}

function validatePhotoUploadForm(form) {
    const inputs = [...form.querySelectorAll('[data-photo-input]')];

    return inputs.every((input) => validatePhotoInput(input));
}

function confirmVoidPurchase(form) {
    Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmVoidPurchase ?? 'Anular compra',
        text: 'Se revertira el stock ingresado por esta compra.',
        input: 'textarea',
        inputLabel: 'Motivo de anulacion',
        inputPlaceholder: 'Describe el motivo',
        inputAttributes: {
            maxlength: 500,
        },
        showCancelButton: true,
        confirmButtonText: 'Si, anular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        preConfirm: (value) => {
            if (!value || value.trim().length < 3) {
                Swal.showValidationMessage('Ingresa un motivo de al menos 3 caracteres.');
                return false;
            }

            return value.trim();
        },
    }).then(async (result) => {
        if (!result.isConfirmed) {
            return;
        }

        const reason = document.createElement('input');
        reason.type = 'hidden';
        reason.name = 'void_reason';
        reason.value = result.value;
        form.append(reason);

        if (form.matches('[data-ajax-form]')) {
            await submitAjaxForm(form);
            reason.remove();
            return;
        }

        form.submit();
    });
}

function confirmVoidSale(form) {
    Swal.fire({
        icon: 'warning',
        title: form.dataset.confirmVoidSale ?? 'Anular venta',
        text: 'Se devolvera el stock de esta venta y dejara de contar en la caja.',
        input: 'textarea',
        inputLabel: 'Motivo de anulacion',
        inputPlaceholder: 'Describe el motivo',
        inputAttributes: {
            maxlength: 500,
        },
        showCancelButton: true,
        confirmButtonText: 'Si, anular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        preConfirm: (value) => {
            if (!value || value.trim().length < 3) {
                Swal.showValidationMessage('Ingresa un motivo de al menos 3 caracteres.');
                return false;
            }

            return value.trim();
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const reason = document.createElement('input');
        reason.type = 'hidden';
        reason.name = 'void_reason';
        reason.value = result.value;
        form.append(reason);
        form.submit();
    });
}

function initAdminDataTables() {
    document.querySelectorAll('[data-datatable]').forEach((table) => {
        if (table.dataset.datatableInitialized === '1') {
            return;
        }

        const columnsElement = document.getElementById(table.dataset.columnsId ?? '');
        const columns = JSON.parse(columnsElement?.textContent ?? table.dataset.columns ?? '[]');
        const filtersForm = table.dataset.filtersForm ? document.querySelector(table.dataset.filtersForm) : null;

        const dataTable = new DataTable(table, {
            ajax: {
                url: table.dataset.url,
                data(data) {
                    if (!filtersForm) {
                        return;
                    }

                    new FormData(filtersForm).forEach((value, key) => {
                        data[key] = value;
                    });
                },
            },
            columns,
            processing: true,
            serverSide: true,
            responsive: true,
            pageLength: Number(table.dataset.pageLength ?? 10),
            order: JSON.parse(table.dataset.order ?? '[[0,"desc"]]'),
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Sin registros',
                infoFiltered: '(filtrado de _MAX_ registros)',
                loadingRecords: 'Cargando...',
                processing: 'Procesando...',
                zeroRecords: 'No se encontraron registros',
                emptyTable: 'No hay datos disponibles',
                paginate: {
                    first: 'Primero',
                    previous: 'Anterior',
                    next: 'Siguiente',
                    last: 'Ultimo',
                },
            },
        });

        if (filtersForm) {
            let reloadTimeout;
            const reloadTable = () => {
                window.clearTimeout(reloadTimeout);
                reloadTimeout = window.setTimeout(() => dataTable.ajax.reload(), 180);
            };

            filtersForm.addEventListener('change', reloadTable);
            filtersForm.addEventListener('reset', () => {
                window.setTimeout(() => {
                    filtersForm.querySelectorAll('select[data-tom-select]').forEach((select) => {
                        select.tomselect?.clear(true);
                    });
                    dataTable.ajax.reload();
                }, 0);
            });
        }

        dataTable.on('xhr.dt', (_event, _settings, json, xhr) => {
            if (xhr.status === 401 || xhr.status === 403 || xhr.responseURL?.includes('/login')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin acceso',
                    text: 'No tienes permisos para cargar los datos de esta tabla o tu sesion expiro.',
                });
            }
        });
        table.dataset.datatableInitialized = '1';
    });
}

function initTomSelects(scope = document) {
    scope.querySelectorAll('select[data-tom-select]').forEach((select) => {
        if (select.tomselect) {
            return;
        }

        new TomSelect(select, {
            allowEmptyOption: true,
            create: false,
            dropdownParent: 'body',
            maxItems: select.multiple ? null : 1,
            placeholder: select.dataset.placeholder ?? 'Seleccionar',
            plugins: ['clear_button'],
            render: {
                no_results() {
                    return '<div class="no-results">Sin resultados</div>';
                },
            },
        });
    });
}

function selectedOption(select) {
    return select?.selectedOptions?.[0] ?? null;
}

function rowNumberValue(row, selector) {
    return Number(row.querySelector(selector)?.value || 0);
}

function updatePurchaseRow(row) {
    const product = row.querySelector('[data-purchase-product]');
    const presentation = row.querySelector('[data-purchase-presentation]');
    const unitPrice = row.querySelector('[data-unit-price]');
    const quantity = Math.max(0, rowNumberValue(row, '[data-package-quantity]'));
    let price = Math.max(0, rowNumberValue(row, '[data-unit-price]'));
    const productOption = selectedOption(product);
    const presentationOption = selectedOption(presentation);
    const unitsPerPackage = Number(presentationOption?.dataset.units || 0);
    const unitLabel = productOption?.dataset.unit || 'u.';
    const totalUnits = quantity * unitsPerPackage;
    const basePrice = Number(productOption?.dataset.price || 0);
    const shouldAutoPrice = unitPrice && (unitPrice.dataset.autoPrice === '1' || !unitPrice.value);

    if (unitPrice && shouldAutoPrice && basePrice >= 0 && unitsPerPackage > 0) {
        unitPrice.value = (basePrice * unitsPerPackage).toFixed(2);
        unitPrice.dataset.autoPrice = '1';
        price = Math.max(0, rowNumberValue(row, '[data-unit-price]'));
    }

    row.querySelector('[data-unit-calculation]').textContent = unitsPerPackage > 0
        ? `${quantity} x ${unitsPerPackage} = ${totalUnits} ${unitLabel}`
        : `0 ${unitLabel}`;
    row.querySelector('[data-line-subtotal]').textContent = (quantity * price).toFixed(2);
}

function updatePurchaseTotals(form) {
    let subtotal = 0;

    form.querySelectorAll('[data-purchase-item-row]').forEach((row) => {
        updatePurchaseRow(row);
        subtotal += Math.max(0, rowNumberValue(row, '[data-package-quantity]')) * Math.max(0, rowNumberValue(row, '[data-unit-price]'));
    });

    form.querySelector('[data-purchase-subtotal]').textContent = subtotal.toFixed(2);
    form.querySelector('[data-purchase-total]').textContent = subtotal.toFixed(2);
}

function refreshPurchaseReference(form) {
    const warehouse = form.querySelector('[name="warehouse_id"]');
    const preview = form.querySelector('[data-reference-preview]');
    const previews = JSON.parse(form.dataset.referencePreviews || '{}');

    if (!preview) {
        return;
    }

    preview.value = previews[warehouse?.value] || 'Se generara al seleccionar almacen';
}

function clearPurchaseRow(row) {
    row.querySelectorAll('select').forEach((select) => select.tomselect?.clear());
    row.querySelectorAll('input').forEach((input) => {
        input.value = input.matches('[data-package-quantity]') ? '1' : '';
    });
}

function initPurchaseForm() {
    document.querySelectorAll('[data-purchase-form]').forEach((form) => {
        if (form.dataset.purchaseInitialized === '1') {
            return;
        }

        const items = form.querySelector('[data-purchase-items]');
        const template = form.querySelector('[data-purchase-item-template]') ?? document.querySelector('[data-purchase-item-template]');
        form.dataset.purchaseItemIndex = String(items?.querySelectorAll('[data-purchase-item-row]').length || 0);

        form.addEventListener('change', (event) => {
            if (event.target.closest('[name="warehouse_id"]')) {
                refreshPurchaseReference(form);
            }

            if (event.target.closest('[data-purchase-product], [data-purchase-presentation]')) {
                const row = event.target.closest('[data-purchase-item-row]');
                const unitPrice = row?.querySelector('[data-unit-price]');

                if (unitPrice) {
                    unitPrice.dataset.autoPrice = '1';
                }
            }

            if (event.target.closest('[data-purchase-product], [data-purchase-presentation], [data-package-quantity], [data-unit-price]')) {
                updatePurchaseTotals(form);
            }
        });

        form.addEventListener('input', (event) => {
            const unitPrice = event.target.closest('[data-unit-price]');

            if (unitPrice) {
                unitPrice.dataset.autoPrice = '0';
            }

            if (event.target.closest('[data-package-quantity], [data-unit-price]')) {
                updatePurchaseTotals(form);
            }
        });

        form.querySelector('[data-add-purchase-item]')?.addEventListener('click', () => {
            if (!items || !template) {
                return;
            }

            const index = Number(form.dataset.purchaseItemIndex || 0);
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const row = wrapper.firstElementChild;

            items.append(row);
            form.dataset.purchaseItemIndex = String(index + 1);
            initTomSelects(row);
            updatePurchaseTotals(form);
        });

        form.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-purchase-item]');

            if (!remove) {
                return;
            }

            const row = remove.closest('[data-purchase-item-row]');
            const rows = items?.querySelectorAll('[data-purchase-item-row]') ?? [];

            if (rows.length <= 1) {
                clearPurchaseRow(row);
            } else {
                row.remove();
            }

            updatePurchaseTotals(form);
        });

        refreshPurchaseReference(form);
        updatePurchaseTotals(form);
        form.dataset.purchaseInitialized = '1';
    });
}

function syncPointSaleWarehouse(scope = document) {
    scope.querySelectorAll('[data-point-sale-branch]').forEach((branchSelect) => {
        const form = branchSelect.closest('form') ?? branchSelect.closest('.card') ?? document;
        const warehouseSelect = form.querySelector('[data-point-sale-warehouse]');

        if (!warehouseSelect) {
            return;
        }

        const branchId = branchSelect.value;
        let selectedStillVisible = true;

        warehouseSelect.querySelectorAll('option[data-branch-id]').forEach((option) => {
            const visible = !branchId || option.dataset.branchId === branchId;
            option.hidden = !visible;
            option.disabled = !visible;

            if (option.selected && !visible) {
                selectedStillVisible = false;
            }
        });

        if (!selectedStillVisible) {
            warehouseSelect.value = '';
        }
    });
}

function initPosSaleForm() {
    document.querySelectorAll('[data-pos-sale-form]').forEach((form) => {
        if (form.dataset.posInitialized === '1') {
            return;
        }

        const productPicker = form.querySelector('[data-pos-product-picker]');
        const presentationPicker = form.querySelector('[data-pos-presentation-picker]');
        const quantityPicker = form.querySelector('[data-pos-quantity-picker]');
        const items = form.querySelector('[data-pos-items]');
        const template = form.querySelector('[data-pos-line-template]');
        const empty = form.querySelector('[data-pos-empty]');
        const submit = form.querySelector('[data-pos-submit]');
        const stockAvailability = JSON.parse(form.dataset.posStock || '{}');
        const customers = JSON.parse(form.dataset.posCustomers || '[]');
        const payments = form.querySelector('[data-pos-payments]');
        const paymentTemplate = form.querySelector('[data-pos-payment-template]');
        const paymentMode = form.querySelector('[data-pos-payment-mode]');
        const cashPanel = form.querySelector('[data-pos-cash-panel]');
        const mixedPanel = form.querySelector('[data-pos-mixed-panel]');
        const cashReceived = form.querySelector('[data-pos-cash-received]');
        const cashChange = form.querySelector('[data-pos-cash-change]');
        const useCash = form.querySelector('[data-pos-use-cash]');
        const useMixed = form.querySelector('[data-pos-use-mixed]');
        const modeToggles = form.querySelectorAll('[data-pos-mode-toggle]');
        const modePanels = form.querySelectorAll('[data-pos-mode-panel]');

        const focusTomSelect = (select) => {
            select?.tomselect?.focus();
            select?.tomselect?.open();
        };

        const tomOption = (select) => {
            const value = select?.value;

            return value && select?.tomselect ? select.tomselect.options[value] : null;
        };

        const selectedPackagesInCart = (productId, presentationId) => Array.from(items.querySelectorAll('[data-pos-row]'))
            .filter((row) => row.dataset.productId === String(productId) && row.dataset.presentationId === String(presentationId))
            .reduce((total, row) => total + Math.max(1, Number(row.querySelector('[data-pos-line-quantity]').value || 1)), 0);

        const refreshPresentationOptions = () => {
            const product = selectedOption(productPicker);
            const tom = presentationPicker?.tomselect;
            const availability = stockAvailability[product?.value]?.presentations || [];

            if (!tom) {
                return;
            }

            tom.clear(true);
            tom.clearOptions();

            if (availability.length === 0) {
                tom.addOption({ value: '', text: product?.value ? 'Sin presentaciones con stock' : 'Selecciona producto' });
                tom.refreshOptions(false);
                return;
            }

            availability.forEach((presentation) => {
                tom.addOption({
                    value: String(presentation.id),
                    text: `${presentation.name} - ${presentation.packages} disp. (${presentation.units} u.)`,
                    units: presentation.units_per_package,
                    packages: presentation.packages,
                    unitsAvailable: presentation.units,
                    baseName: presentation.name,
                });
            });
            tom.refreshOptions(false);
        };

        const focusPresentation = () => {
            window.setTimeout(() => focusTomSelect(presentationPicker), 60);
        };

        const focusQuantity = () => {
            window.setTimeout(() => {
                quantityPicker?.focus();
                quantityPicker?.select();
            }, 60);
        };

        const updateNames = () => {
            items.querySelectorAll('[data-pos-row]').forEach((row, index) => {
                row.querySelector('[data-pos-product-input]').name = `items[${index}][product_id]`;
                row.querySelector('[data-pos-presentation-input]').name = `items[${index}][presentation_id]`;
                row.querySelector('[data-pos-line-quantity]').name = `items[${index}][package_quantity]`;
                row.querySelector('[data-pos-line-price]').name = `items[${index}][unit_price]`;
                row.querySelector('[data-pos-line-discount]').name = `items[${index}][discount]`;
            });
        };

        const updatePaymentNames = () => {
            if (paymentMode?.value !== 'mixed') {
                payments?.querySelectorAll('[data-pos-payment-row]').forEach((row) => {
                    row.querySelector('[data-pos-payment-method]').removeAttribute('name');
                    row.querySelector('[data-pos-payment-amount]').removeAttribute('name');
                    row.querySelector('[data-pos-payment-reference]').removeAttribute('name');
                });

                return;
            }

            payments?.querySelectorAll('[data-pos-payment-row]').forEach((row, index) => {
                row.querySelector('[data-pos-payment-method]').name = `payments[${index}][payment_method_id]`;
                row.querySelector('[data-pos-payment-amount]').name = `payments[${index}][amount]`;
                row.querySelector('[data-pos-payment-reference]').name = `payments[${index}][reference]`;
            });
        };

        const updateCashPayment = (total) => {
            if (cashReceived && (cashReceived.dataset.autoAmount === '1' || !cashReceived.value)) {
                cashReceived.value = total > 0 ? total.toFixed(2) : '';
                cashReceived.dataset.autoAmount = '1';
            }

            const received = Math.max(0, Number(cashReceived?.value || 0));
            const change = Math.max(0, received - total);
            const complete = total > 0 && received >= total;

            if (cashChange) {
                cashChange.textContent = change.toFixed(2);
                cashChange.classList.toggle('text-success', complete);
                cashChange.classList.toggle('text-danger', total > 0 && !complete);
            }

            return complete;
        };

        const updatePayments = (total) => {
            const rows = payments?.querySelectorAll('[data-pos-payment-row]') ?? [];
            const paidTarget = form.querySelector('[data-pos-paid]');
            const dueTarget = form.querySelector('[data-pos-due]');

            if (rows.length === 1) {
                const amount = rows[0].querySelector('[data-pos-payment-amount]');
                if (amount && (amount.dataset.autoAmount === '1' || !amount.value)) {
                    amount.value = total > 0 ? total.toFixed(2) : '';
                    amount.dataset.autoAmount = '1';
                }
            }

            let paid = 0;
            rows.forEach((row) => {
                paid += Math.max(0, Number(row.querySelector('[data-pos-payment-amount]').value || 0));
            });

            const due = Math.max(0, total - paid);
            if (paidTarget) {
                paidTarget.textContent = paid.toFixed(2);
            }
            if (dueTarget) {
                dueTarget.textContent = due.toFixed(2);
                dueTarget.classList.toggle('text-danger', Math.abs(total - paid) >= 0.01);
                dueTarget.classList.toggle('text-success', total > 0 && Math.abs(total - paid) < 0.01);
            }

            updatePaymentNames();

            return Math.abs(total - paid) < 0.01 && rows.length > 0;
        };

        const updateRow = (row) => {
            const quantity = Math.max(1, Number(row.querySelector('[data-pos-line-quantity]').value || 1));
            const price = Math.max(0, Number(row.querySelector('[data-pos-line-price]').value || 0));
            const discount = Math.max(0, Number(row.querySelector('[data-pos-line-discount]').value || 0));
            const units = Number(row.dataset.units || 1);
            const unitLabel = row.dataset.unit || 'u';
            const subtotal = Math.max(0, (quantity * price) - discount);
            const available = Number(row.dataset.availablePackages || 0);

            row.querySelector('[data-pos-calculation]').textContent = `${quantity} x ${units} = ${quantity * units} ${unitLabel}`;
            row.querySelector('[data-pos-line-subtotal]').textContent = subtotal.toFixed(2);
            row.classList.toggle('table-warning', available > 0 && quantity > available);
        };

        const updateTotals = () => {
            let subtotal = 0;
            let discount = 0;
            const rows = items.querySelectorAll('[data-pos-row]');

            rows.forEach((row) => {
                updateRow(row);
                subtotal += Math.max(1, Number(row.querySelector('[data-pos-line-quantity]').value || 1)) * Math.max(0, Number(row.querySelector('[data-pos-line-price]').value || 0));
                discount += Math.max(0, Number(row.querySelector('[data-pos-line-discount]').value || 0));
            });

            form.querySelector('[data-pos-subtotal]').textContent = subtotal.toFixed(2);
            form.querySelector('[data-pos-discount]').textContent = discount.toFixed(2);
            const total = Math.max(0, subtotal - discount);
            form.querySelector('[data-pos-total]').textContent = total.toFixed(2);
            empty?.classList.toggle('d-none', rows.length > 0);
            const paymentsComplete = paymentMode?.value === 'mixed'
                ? updatePayments(total)
                : updateCashPayment(total);
            if (submit) {
                submit.disabled = rows.length === 0 || !paymentsComplete;
            }
            if (paymentMode?.value !== 'mixed') {
                updatePaymentNames();
            }
            updateNames();
        };

        const appendLine = ({
            productId,
            presentationId,
            productName,
            presentationName,
            quantity,
            unitPrice,
            units,
            unitLabel,
            availablePackages,
        }) => {
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.trim();
            const row = wrapper.firstElementChild;

            row.dataset.productId = String(productId);
            row.dataset.presentationId = String(presentationId);
            row.dataset.units = String(units);
            row.dataset.unit = unitLabel || 'u';
            row.dataset.availablePackages = String(availablePackages);
            row.querySelector('[data-pos-product-input]').value = productId;
            row.querySelector('[data-pos-presentation-input]').value = presentationId;
            row.querySelector('[data-pos-product-name]').textContent = productName;
            row.querySelector('[data-pos-presentation-name]').textContent = presentationName;
            row.querySelector('[data-pos-line-quantity]').value = String(quantity);
            row.querySelector('[data-pos-line-quantity]').max = String(availablePackages);
            row.querySelector('[data-pos-line-price]').value = Number(unitPrice).toFixed(2);

            items.append(row);
            updateTotals();

            return row;
        };

        const ensureCanAdd = (productId, presentationId, quantity, availablePackages) => {
            const alreadySelected = selectedPackagesInCart(productId, presentationId);

            if (quantity + alreadySelected > availablePackages) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    text: `Disponible: ${availablePackages} presentaciones. Ya agregaste ${alreadySelected}.`,
                });

                return false;
            }

            return true;
        };

        const addLine = () => {
            const product = selectedOption(productPicker);
            const presentation = selectedOption(presentationPicker);
            const presentationData = tomOption(presentationPicker);
            const quantity = Math.max(1, Number(quantityPicker?.value || 1));

            if (!product?.value || !presentation?.value || !template) {
                Swal.fire({ icon: 'warning', title: 'Falta informacion', text: 'Selecciona producto y presentacion.' });
                return;
            }

            const availablePackages = Number(presentationData?.packages ?? presentation.dataset.packages ?? 0);
            const units = Number(presentationData?.units ?? presentation.dataset.units ?? 1);
            const basePrice = Number(product.dataset.price || 0);

            if (!ensureCanAdd(product.value, presentation.value, quantity, availablePackages)) {
                return;
            }

            appendLine({
                productId: product.value,
                presentationId: presentation.value,
                productName: product.dataset.name || product.textContent.trim(),
                presentationName: presentationData?.baseName || presentation.dataset.baseName || presentation.textContent.trim(),
                quantity,
                unitPrice: basePrice * units,
                units,
                unitLabel: product.dataset.unit || 'u',
                availablePackages,
            });
            productPicker.tomselect?.clear();
            presentationPicker.tomselect?.clear();
            presentationPicker.tomselect?.clearOptions();
            if (quantityPicker) {
                quantityPicker.value = '1';
            }
        };

        const addQuickLine = (tile) => {
            if (!template || tile.disabled || tile.classList.contains('is-disabled')) {
                return;
            }

            const productId = tile.dataset.productId;
            const presentationId = tile.dataset.presentationId;
            const availablePackages = Number(tile.dataset.stock || 0);
            const existing = Array.from(items.querySelectorAll('[data-pos-row]'))
                .find((row) => row.dataset.productId === String(productId) && row.dataset.presentationId === String(presentationId));

            if (existing) {
                const quantityInput = existing.querySelector('[data-pos-line-quantity]');
                const currentQuantity = Math.max(1, Number(quantityInput.value || 1));

                if (currentQuantity + 1 > availablePackages) {
                    Swal.fire({ icon: 'warning', title: 'Stock maximo', text: `Disponible: ${availablePackages} unidades.` });
                    return;
                }

                quantityInput.value = String(currentQuantity + 1);
                updateTotals();
                return;
            }

            if (!ensureCanAdd(productId, presentationId, 1, availablePackages)) {
                return;
            }

            appendLine({
                productId,
                presentationId,
                productName: tile.dataset.productName || tile.textContent.trim(),
                presentationName: tile.dataset.presentationName || 'Unidad',
                quantity: 1,
                unitPrice: Number(tile.dataset.price || 0),
                units: 1,
                unitLabel: tile.dataset.unit || 'u',
                availablePackages,
            });
        };

        const syncCustomer = () => {
            const documentInput = form.querySelector('[data-pos-customer-document]');
            const nameInput = form.querySelector('[data-pos-customer-name]');
            const customerIdInput = form.querySelector('[data-pos-customer-id]');
            const status = form.querySelector('[data-pos-customer-status]');
            const documentNumber = documentInput?.value.trim() || '';
            const customerName = nameInput?.value.trim() || '';
            const customer = customers.find((item) => String(item.document_number || '').trim() === documentNumber);
            const autoFilledName = nameInput?.dataset.autoFilledName || '';

            const clearAutoFilledName = () => {
                if (nameInput && autoFilledName && nameInput.value.trim() === autoFilledName) {
                    nameInput.value = '';
                    nameInput.dataset.autoFilledName = '';
                }
            };

            if (!documentNumber) {
                if (customerIdInput) {
                    customerIdInput.value = '';
                }
                clearAutoFilledName();
                if (status) {
                    status.textContent = nameInput?.value.trim()
                        ? 'Se guardara el nombre solo en esta venta, sin registrar cliente.'
                        : 'Sin cliente asociado.';
                }
                return;
            }

            if (customer) {
                if (customerIdInput) {
                    customerIdInput.value = String(customer.id);
                }
                if (nameInput && (!nameInput.value.trim() || nameInput.value.trim() === autoFilledName)) {
                    nameInput.value = customer.name || '';
                    nameInput.dataset.autoFilledName = customer.name || '';
                }
                if (status) {
                    const sales = Number(customer.sales_count || 0);
                    status.textContent = `Cliente encontrado: ${customer.name}. Historial: ${sales} venta(s).`;
                }
                return;
            }

            if (customerIdInput) {
                customerIdInput.value = '';
            }
            clearAutoFilledName();
            if (status) {
                status.textContent = 'Documento nuevo. Ingresa el nombre para registrar el cliente.';
            }
        };

        form.querySelectorAll('[data-add-pos-item]').forEach((button) => {
            button.addEventListener('click', addLine);
        });

        form.addEventListener('input', (event) => {
            if (event.target.closest('[data-pos-line-quantity], [data-pos-line-price], [data-pos-line-discount]')) {
                const quantityInput = event.target.closest('[data-pos-line-quantity]');
                if (quantityInput) {
                    const row = quantityInput.closest('[data-pos-row]');
                    const available = Number(row?.dataset.availablePackages || 0);
                    const value = Math.max(1, Number(quantityInput.value || 1));
                    if (available > 0 && value > available) {
                        quantityInput.value = String(available);
                        Swal.fire({ icon: 'warning', title: 'Stock maximo', text: `Disponible: ${available} presentaciones.` });
                    }
                }
                updateTotals();
            }

            const paymentAmount = event.target.closest('[data-pos-payment-amount]');
            if (paymentAmount) {
                paymentAmount.dataset.autoAmount = '0';
                updateTotals();
            }

            if (event.target.closest('[data-pos-cash-received]')) {
                event.target.dataset.autoAmount = '0';
                updateTotals();
            }
        });

        productPicker?.addEventListener('change', () => {
            refreshPresentationOptions();

            if (productPicker.value) {
                focusPresentation();
            }
        });
        presentationPicker?.addEventListener('change', () => {
            if (presentationPicker.value) {
                focusQuantity();
            }
        });
        quantityPicker?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addLine();
                window.setTimeout(() => focusTomSelect(productPicker), 80);
            }
        });
        form.querySelector('[data-pos-customer-document]')?.addEventListener('input', syncCustomer);
        form.querySelector('[data-pos-customer-document]')?.addEventListener('change', syncCustomer);
        form.querySelector('[data-pos-customer-name]')?.addEventListener('input', syncCustomer);

        const setPaymentMode = (mode) => {
            if (paymentMode) {
                paymentMode.value = mode;
            }
            cashPanel?.classList.toggle('d-none', mode !== 'cash');
            mixedPanel?.classList.toggle('d-none', mode !== 'mixed');
            useCash?.classList.toggle('btn-primary', mode === 'cash');
            useCash?.classList.toggle('btn-outline-primary', mode !== 'cash');
            useMixed?.classList.toggle('btn-primary', mode === 'mixed');
            useMixed?.classList.toggle('btn-outline-primary', mode !== 'mixed');
            updateTotals();
        };

        useCash?.addEventListener('click', () => setPaymentMode('cash'));
        useMixed?.addEventListener('click', () => setPaymentMode('mixed'));

        const posModeKey = 'inventario-pos-sale-mode';

        const setPosMode = (mode, persist = true) => {
            const selectedMode = mode === 'quick' ? 'quick' : 'normal';

            modePanels.forEach((panel) => {
                panel.classList.toggle('d-none', panel.dataset.posModePanel !== selectedMode);
            });
            modeToggles.forEach((button) => {
                const active = button.dataset.posModeToggle === selectedMode;
                button.classList.toggle('btn-primary', active);
                button.classList.toggle('btn-outline-primary', !active);
            });

            if (persist) {
                window.localStorage?.setItem(posModeKey, selectedMode);
            }
        };

        modeToggles.forEach((button) => {
            button.addEventListener('click', () => setPosMode(button.dataset.posModeToggle || 'normal'));
        });

        if (document.body.dataset.posQuickAddBound !== '1') {
            document.addEventListener('keydown', (event) => {
                if (event.key.toLowerCase() !== 'n' || !event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
                    return;
                }

                const activeForm = document.querySelector('[data-pos-sale-form]');
                const activeProductPicker = activeForm?.querySelector('[data-pos-product-picker]');

                if (!activeProductPicker) {
                    return;
                }

                event.preventDefault();
                focusTomSelect(activeProductPicker);
            });

            document.body.dataset.posQuickAddBound = '1';
        }

        form.addEventListener('click', (event) => {
            const addPayment = event.target.closest('[data-add-pos-payment]');
            if (addPayment && payments && paymentTemplate) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = paymentTemplate.innerHTML.trim();
                const row = wrapper.firstElementChild;
                const amount = row.querySelector('[data-pos-payment-amount]');

                if (amount) {
                    amount.dataset.autoAmount = '0';
                }

                payments.append(row);
                updateTotals();
                amount?.focus();

                return;
            }

            const quickProduct = event.target.closest('[data-pos-quick-product]');
            if (quickProduct) {
                addQuickLine(quickProduct);

                return;
            }

            const remove = event.target.closest('[data-pos-remove]');
            if (remove) {
                remove.closest('[data-pos-row]')?.remove();
                updateTotals();

                return;
            }

            const removePayment = event.target.closest('[data-remove-pos-payment]');
            if (removePayment) {
                const rows = payments?.querySelectorAll('[data-pos-payment-row]') ?? [];
                const row = removePayment.closest('[data-pos-payment-row]');

                if (rows.length <= 1) {
                    row?.querySelectorAll('input').forEach((input) => {
                        input.value = '';
                        input.dataset.autoAmount = input.matches('[data-pos-payment-amount]') ? '1' : '0';
                    });
                } else {
                    row?.remove();
                }

                updateTotals();
            }
        });

        updateTotals();
        if (cashReceived && !cashReceived.value) {
            cashReceived.dataset.autoAmount = '1';
        }
        setPaymentMode(paymentMode?.value || 'cash');
        setPosMode(window.localStorage?.getItem(posModeKey) || 'normal', false);
        syncCustomer();
        form.dataset.posInitialized = '1';
    });
}

function initDefragmentForms(scope = document) {
    scope.querySelectorAll('[data-defragment-form]').forEach((form) => {
        if (form.dataset.defragmentInitialized === '1') {
            return;
        }

        const presentation = form.querySelector('[data-defragment-presentation]');
        const quantity = form.querySelector('[data-defragment-quantity]');
        const preview = form.querySelector('[data-defragment-preview]');

        const syncLimits = () => {
            const option = selectedOption(presentation);
            const max = Math.max(1, Number(option?.dataset.max || 1));
            const units = Math.max(1, Number(option?.dataset.units || 1));
            const value = Math.min(max, Math.max(1, Number(quantity?.value || 1)));

            if (quantity) {
                quantity.max = String(max);
                quantity.value = String(value);
            }

            if (preview) {
                preview.textContent = `Se convertiran ${value} empaque(s) en ${value * units} unidad(es). Disponible: ${max}.`;
            }
        };

        presentation?.addEventListener('change', syncLimits);
        quantity?.addEventListener('input', syncLimits);
        syncLimits();
        form.dataset.defragmentInitialized = '1';
    });
}

function initTransferForms(scope = document) {
    scope.querySelectorAll('[data-transfer-form]').forEach((form) => {
        if (form.dataset.transferInitialized === '1') {
            return;
        }

        const source = form.querySelector('[data-transfer-source]');
        const target = form.querySelector('[data-transfer-target]');
        const product = form.querySelector('[data-transfer-product]');
        const presentation = form.querySelector('[data-transfer-presentation]');
        const units = form.querySelector('[data-transfer-units]');
        const packages = form.querySelector('[data-transfer-packages]');
        const summary = form.querySelector('[data-transfer-summary]');
        const submit = form.querySelector('[data-transfer-submit]');
        const unitsHelp = form.querySelector('[data-transfer-units-help]');
        const packagesHelp = form.querySelector('[data-transfer-packages-help]');
        const stockAvailability = JSON.parse(form.dataset.transferStock || '{}');
        const productOptions = Array.from(product?.querySelectorAll('option[value]') ?? [])
            .filter((option) => option.value)
            .map((option) => ({
                value: option.value,
                text: option.dataset.name || option.textContent.trim(),
            }));

        const tomOption = (select) => {
            const value = select?.value;

            return value && select?.tomselect ? select.tomselect.options[value] : null;
        };

        const setFieldError = (field, errorKey, message = '') => {
            if (!field) {
                return;
            }

            field.setCustomValidity(message);
            field.classList.toggle('is-invalid', message !== '');
            field.tomselect?.wrapper?.classList.toggle('is-invalid', message !== '');

            const feedback = form.querySelector(`[data-error-for="${errorKey}"]`);
            if (feedback) {
                feedback.textContent = message;
            }
        };

        const selectedAvailability = () => {
            const warehouseStock = stockAvailability[source?.value] || {};

            return warehouseStock[product?.value] || { stock: 0, presentations: [] };
        };

        const selectedPresentation = () => {
            const value = presentation?.value;

            if (!value) {
                return null;
            }

            return selectedAvailability().presentations.find((item) => String(item.id) === String(value)) || tomOption(presentation);
        };

        const refreshProductOptions = () => {
            if (!product?.tomselect) {
                return;
            }

            const selected = product.value;

            product.tomselect.clear(true);
            product.tomselect.clearOptions();

            productOptions.forEach((option) => {
                const stock = Number(stockAvailability[source?.value]?.[option.value]?.stock || 0);
                const showStock = Boolean(source?.value);

                product.tomselect.addOption({
                    value: option.value,
                    text: showStock ? `${option.text} - ${stock} u.` : option.text,
                    baseName: option.text,
                    stock,
                    disabled: showStock && stock <= 0,
                });
            });

            product.tomselect.refreshOptions(false);

            if (selected && (!source?.value || Number(stockAvailability[source.value]?.[selected]?.stock || 0) > 0)) {
                product.tomselect.setValue(selected, true);
            }
        };

        const refreshPresentationOptions = () => {
            if (!presentation?.tomselect) {
                return;
            }

            const previous = presentation.value;
            const availability = selectedAvailability();
            const hasSelection = Boolean(source?.value && product?.value);

            presentation.tomselect.clear(true);
            presentation.tomselect.clearOptions();
            presentation.tomselect.addOption({
                value: '',
                text: hasSelection ? `Unidad base - ${Number(availability.base_units || 0)} u.` : 'Selecciona almacen y producto',
                baseName: 'Unidad base',
                packages: Number(availability.base_units || 0),
                unitsAvailable: Number(availability.base_units || 0),
                units: 1,
            });

            (availability.presentations || []).forEach((item) => {
                presentation.tomselect.addOption({
                    value: String(item.id),
                    text: `${item.name} - ${item.packages} disp. (${item.units} u.)`,
                    baseName: item.name,
                    packages: Number(item.packages || 0),
                    unitsAvailable: Number(item.units || 0),
                    units: Number(item.units_per_package || 1),
                    disabled: Number(item.packages || 0) <= 0,
                });
            });

            presentation.tomselect.refreshOptions(false);
            presentation.tomselect.setValue(
                (availability.presentations || []).some((item) => String(item.id) === String(previous)) ? previous : '',
                true
            );

            if (hasSelection) {
                presentation.tomselect.enable();
            } else {
                presentation.tomselect.disable();
            }
        };

        const clampNumber = (input, min, max) => {
            if (!input) {
                return 0;
            }

            const raw = Number(input.value || min);
            const safeMax = Math.max(min, Number(max || 0));
            const value = Math.min(safeMax, Math.max(min, Number.isFinite(raw) ? raw : min));

            input.value = String(value);

            return value;
        };

        const syncTransfer = () => {
            let valid = true;
            const sameWarehouse = Boolean(source?.value && target?.value && source.value === target.value);
            const availability = selectedAvailability();
            const selectedStock = Number(availability.base_units || 0);
            const presentationData = selectedPresentation();

            setFieldError(target, 'target_warehouse_id', sameWarehouse ? 'El almacen destino debe ser diferente al origen.' : '');

            if (sameWarehouse) {
                valid = false;
            }

            if (!source?.value || !target?.value || !product?.value) {
                valid = false;
            }

            if (presentationData) {
                const maxPackages = Number(presentationData.packages || 0);
                const unitsPerPackage = Number(presentationData.units || presentationData.units_per_package || 1);
                const packageValue = clampNumber(packages, 1, maxPackages);
                const totalUnits = packageValue * unitsPerPackage;

                if (packages) {
                    packages.disabled = false;
                    packages.required = true;
                    packages.max = String(Math.max(1, maxPackages));
                }

                if (units) {
                    units.readOnly = true;
                    units.required = false;
                    units.value = String(totalUnits);
                    units.max = String(Math.max(1, Number(presentationData.unitsAvailable || totalUnits)));
                }
                setFieldError(units, 'items.0.quantity', '');

                if (unitsHelp) {
                    unitsHelp.textContent = 'Las unidades se calculan automaticamente desde la presentacion.';
                }

                if (packagesHelp) {
                    packagesHelp.textContent = `Disponible: ${maxPackages} presentacion(es).`;
                }

                setFieldError(packages, 'items.0.package_quantity', maxPackages <= 0 || packageValue > maxPackages
                    ? `Disponible: ${maxPackages} presentacion(es).`
                    : '');

                if (maxPackages <= 0 || packageValue > maxPackages) {
                    valid = false;
                }

                if (summary) {
                    summary.textContent = `${packageValue} presentacion(es) x ${unitsPerPackage} unidad(es) = ${totalUnits} unidad(es). Stock disponible: ${maxPackages} presentacion(es), ${Number(presentationData.unitsAvailable || 0)} unidad(es).`;
                }
            } else {
                const unitValue = clampNumber(units, 1, selectedStock);

                if (packages) {
                    packages.disabled = true;
                    packages.required = false;
                    packages.value = '';
                    packages.removeAttribute('max');
                    setFieldError(packages, 'items.0.package_quantity', '');
                }

                if (units) {
                    units.readOnly = false;
                    units.required = true;
                    units.max = String(Math.max(1, selectedStock));
                }

                if (unitsHelp) {
                    unitsHelp.textContent = `Disponible: ${selectedStock} unidad(es) suelta(s).`;
                }

                if (packagesHelp) {
                    packagesHelp.textContent = 'Se habilita cuando selecciones caja, paquete u otra presentacion.';
                }

                setFieldError(units, 'items.0.quantity', selectedStock <= 0 || unitValue > selectedStock
                    ? `Disponible: ${selectedStock} unidad(es).`
                    : '');

                if (selectedStock <= 0 || unitValue > selectedStock) {
                    valid = false;
                }

                if (summary) {
                    summary.textContent = product?.value
                        ? `${unitValue} unidad(es) suelta(s) seleccionada(s). Stock base disponible: ${selectedStock} unidad(es).`
                        : 'Selecciona almacen origen y producto para ver existencias disponibles.';
                }
            }

            if (summary) {
                summary.classList.toggle('alert-info', valid);
                summary.classList.toggle('alert-warning', !valid);
            }

            if (submit) {
                submit.disabled = !valid;
            }

            return valid;
        };

        source?.addEventListener('change', () => {
            refreshProductOptions();
            refreshPresentationOptions();
            syncTransfer();
        });
        target?.addEventListener('change', syncTransfer);
        product?.addEventListener('change', () => {
            refreshPresentationOptions();
            syncTransfer();
        });
        presentation?.addEventListener('change', syncTransfer);
        units?.addEventListener('input', syncTransfer);
        packages?.addEventListener('input', syncTransfer);

        form.addEventListener('submit', (event) => {
            if (syncTransfer()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            Swal.fire({
                icon: 'warning',
                title: 'Revisa la transferencia',
                text: 'Selecciona almacenes diferentes y una cantidad disponible para continuar.',
            });
        });

        refreshProductOptions();
        refreshPresentationOptions();
        syncTransfer();
        form.dataset.transferInitialized = '1';
    });
}

function initStockAdjustmentForms(scope = document) {
    scope.querySelectorAll('[data-stock-adjustment-form]').forEach((form) => {
        if (form.dataset.stockAdjustmentInitialized === '1') {
            return;
        }

        const presentation = form.querySelector('[data-stock-adjustment-presentation]');
        const current = form.querySelector('[data-stock-adjustment-current]');
        const counted = form.querySelector('[data-stock-adjustment-counted]');
        const preview = form.querySelector('[data-stock-adjustment-preview]');

        const syncPreview = () => {
            const option = selectedOption(presentation);
            const currentQuantity = Number(option?.dataset.current || 0);
            const unitsPerPackage = Number(option?.dataset.units || 1);
            const label = option?.dataset.label || 'Unidad base';
            const countedQuantity = Math.max(0, Number(counted?.value || 0));
            const difference = countedQuantity - currentQuantity;
            const unitDifference = Math.abs(difference) * unitsPerPackage;

            if (current) {
                current.value = String(currentQuantity);
            }

            if (counted && Number(counted.value || 0) < 0) {
                counted.value = '0';
            }

            if (!preview) {
                return;
            }

            preview.classList.toggle('alert-info', difference === 0);
            preview.classList.toggle('alert-success', difference > 0);
            preview.classList.toggle('alert-warning', difference < 0);

            if (difference === 0) {
                preview.textContent = `Sin diferencia para ${label}: no se generara movimiento.`;
                return;
            }

            const action = difference > 0 ? 'ingreso' : 'salida';
            const packageLabel = presentation?.value ? 'presentacion(es)' : 'unidad(es) suelta(s)';
            preview.textContent = `Se generara un ${action} por ${Math.abs(difference)} ${packageLabel}, equivalente a ${unitDifference} unidad(es).`;
        };

        presentation?.addEventListener('change', () => {
            const option = selectedOption(presentation);
            if (counted) {
                counted.value = option?.dataset.current || '0';
            }
            syncPreview();
        });
        counted?.addEventListener('input', syncPreview);

        syncPreview();
        form.dataset.stockAdjustmentInitialized = '1';
    });
}

function initUserDropdowns() {
    document.querySelectorAll('[data-user-dropdown-toggle]').forEach((toggle) => {
        if (toggle.dataset.dropdownInitialized === '1') {
            return;
        }

        const dropdown = bootstrap.Dropdown.getOrCreateInstance(toggle, {
            autoClose: true,
            popperConfig: {
                strategy: 'fixed',
            },
        });

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            dropdown.toggle();
        });

        toggle.dataset.dropdownInitialized = '1';
    });
}

function initSidebarToggle() {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('.app-sidebar');

    if (!toggle || toggle.dataset.sidebarToggleInitialized === '1') {
        return;
    }

    const icon = toggle.querySelector('i');
    document.querySelectorAll('.app-sidebar .nav-link, .app-sidebar .app-menu-toggle').forEach((item) => {
        const label = item.querySelector('.nav-link-title')?.textContent?.trim();

        if (label && !item.getAttribute('title')) {
            item.setAttribute('title', label);
        }
    });

    const syncState = () => {
        const collapsed = document.body.classList.contains('app-sidebar-collapsed');
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Replegar menu');
        toggle.setAttribute('title', collapsed ? 'Expandir menu' : 'Replegar menu');

        if (icon) {
            icon.className = collapsed ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse';
        }
    };

    toggle.addEventListener('click', () => {
        document.body.classList.toggle('app-sidebar-collapsed');
        document.body.classList.remove('app-sidebar-peek');
        localStorage.setItem('app-sidebar-collapsed', document.body.classList.contains('app-sidebar-collapsed') ? '1' : '0');
        syncState();
    });

    const openPeek = () => {
        if (document.body.classList.contains('app-sidebar-collapsed')) {
            document.body.classList.add('app-sidebar-peek');
        }
    };

    const closePeek = () => {
        document.body.classList.remove('app-sidebar-peek');
    };

    sidebar?.addEventListener('click', (event) => {
        if (!document.body.classList.contains('app-sidebar-collapsed')) {
            return;
        }

        openPeek();

        const link = event.target.closest('a.nav-link');
        const toggleButton = event.target.closest('.app-menu-toggle');

        if (link && !toggleButton) {
            closePeek();
        }
    }, true);

    document.addEventListener('click', (event) => {
        if (!document.body.classList.contains('app-sidebar-peek')) {
            return;
        }

        if (event.target.closest('.app-sidebar') || event.target.closest('[data-sidebar-toggle]')) {
            return;
        }

        closePeek();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePeek();
        }
    });

    syncState();
    toggle.dataset.sidebarToggleInitialized = '1';
}

function initCashExpenseModal() {
    const modal = document.querySelector('[data-show-cash-expense-modal]');

    if (!modal) {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modal).show();
}

function initCashCloseModal() {
    const modal = document.querySelector('[data-show-cash-close-modal]');

    if (!modal) {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modal).show();
}

function initOccupancyWeekGrid() {
    const root = document.querySelector('[data-occupancy-week]');

    if (!root || root.dataset.occupancyInitialized === '1') {
        return;
    }

    const gridTarget = root.querySelector('[data-occupancy-grid]');
    const summaryTarget = root.querySelector('[data-occupancy-summary]');
    const filtersForm = root.querySelector('[data-occupancy-filters]');
    const weekPicker = root.querySelector('[data-occupancy-week-picker]');
    const modalElement = root.querySelector('[data-occupancy-modal]');
    const form = root.querySelector('[data-occupancy-form]');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    const modalTitle = root.querySelector('[data-occupancy-modal-title]');
    const blockIdInput = root.querySelector('[data-occupancy-block-id]');
    const spaceSelect = root.querySelector('[data-occupancy-space-select]');
    const roomSelect = root.querySelector('[data-occupancy-room-select]');
    const roomWrap = root.querySelector('[data-occupancy-room-wrap]');
    const deleteButton = root.querySelector('[data-occupancy-delete]');
    const spaces = JSON.parse(root.dataset.spaces ?? '[]');
    const canManage = root.dataset.canManage === '1';
    let weekData = JSON.parse(root.dataset.initialWeek ?? '{}');
    let weekStart = weekData.week_start;

    if (!gridTarget || !filtersForm || !weekPicker || !modal || !form) {
        return;
    }

    const typeLabels = {
        manual_block: 'Bloqueo manual',
        maintenance: 'Mantenimiento',
        owner_use: 'Uso propietario',
        unavailable: 'No disponible',
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const routeFor = (template, id) => template.replace('__ID__', id);
    const findSpace = (spaceId) => spaces.find((space) => String(space.id) === String(spaceId));
    const findRow = (spaceId, roomId) => weekData.rows?.find((row) => String(row.space_id) === String(spaceId) && String(row.room_id ?? '') === String(roomId ?? ''));
    const findCell = (spaceId, roomId, date) => findRow(spaceId, roomId)?.cells?.find((cell) => cell.date === date);

    const fillRooms = (spaceId, selectedRoomId = '') => {
        const space = findSpace(spaceId);
        const rooms = space?.rooms ?? [];
        roomSelect.innerHTML = '<option value="">Seleccionar</option>';

        rooms.forEach((room) => {
            const option = document.createElement('option');
            option.value = room.id;
            option.textContent = room.name;
            roomSelect.append(option);
        });

        roomWrap.classList.toggle('d-none', space?.mode !== 'compartido');
        roomSelect.required = space?.mode === 'compartido';
        roomSelect.value = selectedRoomId && rooms.some((room) => String(room.id) === String(selectedRoomId)) ? selectedRoomId : '';
    };

    const setFormDisabled = (disabled) => {
        form.querySelectorAll('input, select, textarea, button[type="submit"]').forEach((field) => {
            field.disabled = disabled;
        });
    };

    const clearOccupancyForm = () => {
        form.reset();
        clearFormErrors(form);
        setFormDisabled(!canManage);
        blockIdInput.disabled = false;
        blockIdInput.value = '';
        modalTitle.textContent = 'Nuevo bloqueo';
        deleteButton?.classList.add('d-none');
        fillRooms('');
    };

    const renderSummary = () => {
        if (!summaryTarget) {
            return;
        }

        const summary = weekData.summary ?? {};
        summaryTarget.innerHTML = [
            ['Espacios privados', summary.private_spaces ?? 0],
            ['Habitaciones', summary.shared_rooms ?? 0],
            ['Bloqueos del periodo', summary.blocks_this_week ?? 0],
        ].map(([label, value]) => `
            <div class="occupancy-summary-item">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </div>
        `).join('');
    };

    const renderGrid = () => {
        const dates = weekData.dates ?? [];
        const rows = weekData.rows ?? [];

        const header = `
            <thead>
                <tr>
                    <th>Espacio / Habitacion</th>
                    ${dates.map((date) => `<th class="${date.is_today ? 'text-primary' : ''}">${escapeHtml(date.label)}</th>`).join('')}
                </tr>
            </thead>
        `;

        const body = rows.length
            ? rows.map((row) => {
                if (row.type === 'shared_space_group') {
                    return `
                        <tr class="occupancy-space-group">
                            <td class="occupancy-resource-cell">
                                <i class="ti ti-building me-1"></i>${escapeHtml(row.label)}
                            </td>
                            ${dates.map(() => '<td></td>').join('')}
                        </tr>
                    `;
                }

                return `
                    <tr class="${row.type === 'shared_room' ? 'occupancy-room-row' : 'occupancy-private-row'}">
                        <td class="occupancy-resource-cell">
                            ${row.type === 'shared_room' ? '<i class="ti ti-door me-1"></i>' : '<i class="ti ti-home me-1"></i>'}${escapeHtml(row.label)}
                        </td>
                        ${row.cells.map((cell) => `
                            <td
                                class="occupancy-cell occupancy-cell-${escapeHtml(cell.tone)}"
                                data-date="${escapeHtml(cell.date)}"
                                data-space-id="${escapeHtml(row.space_id)}"
                                data-room-id="${escapeHtml(row.room_id ?? '')}"
                                data-resource-type="${escapeHtml(row.type)}"
                                data-status="${escapeHtml(cell.status)}"
                                data-block-id="${escapeHtml(cell.block_id ?? '')}"
                                data-closed-by-availability="${cell.closed_by_availability ? '1' : '0'}"
                            >
                                <span class="occupancy-cell-label" data-block-id="${escapeHtml(cell.block_id ?? '')}">
                                    ${escapeHtml(cell.label)}
                                </span>
                            </td>
                        `).join('')}
                    </tr>
                `;
            }).join('')
            : `<tr><td class="text-center text-body-secondary py-4" colspan="${dates.length + 1}">No hay recursos para mostrar.</td></tr>`;

        gridTarget.innerHTML = `<table class="occupancy-grid">${header}<tbody>${body}</tbody></table>`;
        renderSummary();
    };

    async function loadWeekData(targetWeek = weekStart) {
        const params = new URLSearchParams(new FormData(filtersForm));
        params.set('week_start', targetWeek);

        const response = await fetch(`${root.dataset.weekDataUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el periodo.');
        }

        weekData = await response.json();
        weekStart = weekData.week_start;
        weekPicker.value = weekStart;
        renderGrid();
    }

    const openCreateBlockModal = (spaceId = '', roomId = '', date = '') => {
        if (!canManage) {
            return;
        }

        clearOccupancyForm();
        modalTitle.textContent = 'Nuevo bloqueo';
        spaceSelect.value = spaceId;
        fillRooms(spaceId, roomId);
        form.querySelector('[name="start_date"]').value = date;
        form.querySelector('[name="end_date"]').value = date;
        const row = findRow(spaceId, roomId);
        form.querySelector('[name="title"]').value = row ? `Bloqueo - ${row.label}` : '';
        modal.show();
    };

    const openEditBlockModal = (spaceId, roomId, date) => {
        const cell = findCell(spaceId, roomId, date);

        if (!cell?.block_id) {
            openCreateBlockModal(spaceId, roomId, date);

            return;
        }

        clearOccupancyForm();
        modalTitle.textContent = canManage ? 'Editar bloqueo' : 'Detalle de bloqueo';
        blockIdInput.value = cell.block_id;
        deleteButton?.classList.toggle('d-none', !canManage);
        spaceSelect.value = spaceId;
        fillRooms(spaceId, roomId);
        form.querySelector('[name="type"]').value = cell.status;
        form.querySelector('[name="title"]').value = cell.title || typeLabels[cell.status] || 'Bloqueo';
        form.querySelector('[name="description"]').value = cell.description || '';
        form.querySelector('[name="start_date"]').value = cell.start_date;
        form.querySelector('[name="end_date"]').value = cell.end_date;
        modal.show();
    };

    const saveBlock = async () => {
        clearFormErrors(form);
        const blockId = blockIdInput.value;
        const url = blockId ? routeFor(root.dataset.updateUrlTemplate, blockId) : root.dataset.storeUrl;
        const body = new FormData(form);

        if (blockId) {
            body.append('_method', 'PATCH');
        }

        const response = await fetch(url, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (response.status === 422) {
            showFormErrors(form, payload.errors ?? {});
            Swal.fire({ icon: 'error', title: 'Validacion', text: payload.message ?? 'Revisa los datos ingresados.' });

            return;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo guardar el bloqueo.');
        }

        modal.hide();
        await loadWeekData();
        toast.fire({ icon: 'success', title: payload.message ?? 'Bloqueo guardado.' });
    };

    const deleteBlock = async () => {
        const blockId = blockIdInput.value;

        if (!blockId || !canManage) {
            return;
        }

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Eliminar bloqueo',
            text: 'El bloqueo dejara de aparecer en la grilla.',
            showCancelButton: true,
            confirmButtonText: 'Si, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });

        if (!result.isConfirmed) {
            return;
        }

        const response = await fetch(routeFor(root.dataset.destroyUrlTemplate, blockId), {
            method: 'POST',
            body: new URLSearchParams({ _method: 'DELETE' }),
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo eliminar el bloqueo.');
        }

        modal.hide();
        await loadWeekData();
        toast.fire({ icon: 'success', title: payload.message ?? 'Bloqueo eliminado.' });
    };

    // Punto de extension para drag/drop y seleccion extendida por fila en fases futuras.
    gridTarget.addEventListener('click', (event) => {
        const cell = event.target.closest('.occupancy-cell');

        if (!cell) {
            return;
        }

        if (cell.dataset.closedByAvailability === '1') {
            Swal.fire({
                icon: 'info',
                title: 'Fecha cerrada',
                text: 'Esta fecha esta cerrada desde Disponibilidad. Habilitala primero para operar en Ocupabilidad.',
            });

            return;
        }

        openEditBlockModal(cell.dataset.spaceId, cell.dataset.roomId, cell.dataset.date);
    });

    root.querySelector('[data-occupancy-week-prev]')?.addEventListener('click', () => loadWeekData(weekData.previous_week).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-occupancy-week-today]')?.addEventListener('click', () => loadWeekData(weekData.current_week).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-occupancy-week-next]')?.addEventListener('click', () => loadWeekData(weekData.next_week).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-occupancy-new-block]')?.addEventListener('click', () => openCreateBlockModal('', '', weekStart));
    weekPicker.addEventListener('change', () => loadWeekData(weekPicker.value).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    filtersForm.addEventListener('change', () => loadWeekData().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    filtersForm.addEventListener('reset', () => window.setTimeout(() => loadWeekData().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })), 0));
    spaceSelect.addEventListener('change', () => fillRooms(spaceSelect.value));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        saveBlock().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });
    deleteButton?.addEventListener('click', () => deleteBlock().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));

    renderGrid();
    root.dataset.occupancyInitialized = '1';
}

function initAvailabilityGrid() {
    const root = document.querySelector('[data-availability]');

    if (!root || root.dataset.availabilityInitialized === '1') {
        return;
    }

    const gridTarget = root.querySelector('[data-availability-grid]');
    const summaryTarget = root.querySelector('[data-availability-summary]');
    const filtersForm = root.querySelector('[data-availability-filters]');
    const datePicker = root.querySelector('[data-availability-picker]');
    const bulkModalElement = root.querySelector('[data-availability-bulk-modal]');
    const bulkForm = root.querySelector('[data-availability-bulk-form]');
    const bulkModal = bulkModalElement ? bootstrap.Modal.getOrCreateInstance(bulkModalElement) : null;
    const spaces = JSON.parse(root.dataset.spaces ?? '[]');
    const canManage = root.dataset.canManage === '1';
    let gridData = JSON.parse(root.dataset.initialGrid ?? '{}');
    let startDate = gridData.start_date;

    if (!gridTarget || !filtersForm || !datePicker || !bulkModal || !bulkForm) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const findSpace = (spaceId) => spaces.find((space) => String(space.id) === String(spaceId));

    const renderSummary = () => {
        if (!summaryTarget) {
            return;
        }

        const summary = gridData.summary ?? {};
        summaryTarget.innerHTML = [
            ['Recursos', summary.resources ?? 0],
            ['Disponible', summary.available ?? 0],
            ['Cerrado', summary.closed ?? 0],
            ['Agotado', summary.sold_out ?? 0],
            ['Sin precio', summary.without_price ?? 0],
        ].map(([label, value]) => `
            <div class="availability-summary-item">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </div>
        `).join('');
    };

    const renderGrid = () => {
        const dates = gridData.dates ?? [];
        const rows = gridData.rows ?? [];
        const header = `
            <thead>
                <tr>
                    <th>Espacio / Habitacion</th>
                    ${dates.map((date) => `<th class="${date.is_today ? 'text-primary' : ''}">${escapeHtml(date.label)}</th>`).join('')}
                </tr>
            </thead>
        `;
        const body = rows.length
            ? rows.map((row) => {
                if (row.type === 'shared_space_group') {
                    return `
                        <tr class="availability-space-group">
                            <td class="availability-resource-cell">
                                <i class="ti ti-building me-1"></i>${escapeHtml(row.label)}
                            </td>
                            ${dates.map(() => '<td></td>').join('')}
                        </tr>
                    `;
                }

                return `
                    <tr class="${row.type === 'shared_room' ? 'availability-room-row' : 'availability-private-row'}">
                        <td class="availability-resource-cell">
                            ${row.type === 'shared_room' ? '<i class="ti ti-door me-1"></i>' : '<i class="ti ti-home me-1"></i>'}${escapeHtml(row.label)}
                        </td>
                        ${row.cells.map((cell) => `
                            <td
                                class="availability-cell availability-cell-${escapeHtml(cell.tone)}"
                                data-date="${escapeHtml(cell.date)}"
                                data-space-id="${escapeHtml(row.space_id)}"
                                data-room-id="${escapeHtml(row.room_id ?? '')}"
                                data-status="${escapeHtml(cell.status)}"
                                data-stored-status="${escapeHtml(cell.stored_status)}"
                                data-price="${escapeHtml(cell.price ?? '')}"
                            >
                                <div class="availability-cell-content">
                                    <input
                                        class="availability-price-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="${escapeHtml(cell.price ?? '')}"
                                        placeholder="--"
                                        data-availability-price-input
                                        ${canManage ? '' : 'disabled'}
                                    >
                                    <button
                                        class="availability-status availability-status-action"
                                        type="button"
                                        title="${cell.stored_status === 'closed' ? 'Cambiar a disponible' : 'Cambiar a cerrado'}"
                                        data-availability-toggle
                                        ${canManage ? '' : 'disabled'}
                                    >${escapeHtml(cell.label)}</button>
                                </div>
                            </td>
                        `).join('')}
                    </tr>
                `;
            }).join('')
            : `<tr><td class="text-center text-body-secondary py-4" colspan="${dates.length + 1}">No hay recursos para mostrar.</td></tr>`;

        gridTarget.innerHTML = `<table class="availability-grid">${header}<tbody>${body}</tbody></table>`;
        renderSummary();
    };

    async function loadGridData(targetDate = startDate) {
        const params = new URLSearchParams(new FormData(filtersForm));
        params.set('start_date', targetDate);

        const response = await fetch(`${root.dataset.gridDataUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar la disponibilidad.');
        }

        gridData = await response.json();
        startDate = gridData.start_date;
        datePicker.value = startDate;
        renderGrid();
    }

    const fillBulkRooms = () => {
        const spaceSelect = bulkForm.querySelector('[data-availability-bulk-space]');
        const roomSelect = bulkForm.querySelector('[data-availability-bulk-room]');
        const roomWrap = bulkForm.querySelector('[data-availability-bulk-room-wrap]');
        const space = findSpace(spaceSelect.value);
        const rooms = space?.rooms ?? [];

        roomSelect.innerHTML = '<option value="">Todas</option>';
        rooms.forEach((room) => {
            const option = document.createElement('option');
            option.value = room.id;
            option.textContent = room.name;
            roomSelect.append(option);
        });

        roomWrap.classList.toggle('d-none', space?.mode !== 'compartido');
        roomSelect.value = '';
    };

    const saveInlineDay = async (cell, values = {}) => {
        if (!canManage || !cell) {
            return;
        }

        const input = cell.querySelector('[data-availability-price-input]');
        const price = values.price ?? input?.value ?? '';
        const status = price === '' ? 'closed' : (values.status ?? cell.dataset.storedStatus ?? 'closed');
        const body = new FormData();

        body.append('_method', 'PATCH');
        body.append('space_id', cell.dataset.spaceId ?? '');
        body.append('space_room_id', cell.dataset.roomId ?? '');
        body.append('date', cell.dataset.date ?? '');
        body.append('price', price);
        body.append('status', status);
        cell.classList.add('availability-cell-saving');

        const response = await fetch(root.dataset.storeDayUrl, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (response.status === 422) {
            cell.classList.remove('availability-cell-saving');
            Swal.fire({ icon: 'error', title: 'Validacion', text: payload.message ?? 'Revisa los datos ingresados.' });

            return;
        }

        if (!response.ok || payload.success === false) {
            cell.classList.remove('availability-cell-saving');
            throw new Error(payload.message ?? 'No se pudo guardar la disponibilidad.');
        }

        await loadGridData();
        toast.fire({ icon: 'success', title: 'Disponibilidad guardada.' });
    };

    const openBulkModal = () => {
        clearFormErrors(bulkForm);
        bulkForm.reset();
        bulkForm.querySelector('[data-availability-bulk-start]').value = gridData.start_date;
        bulkForm.querySelector('[data-availability-bulk-end]').value = gridData.end_date;
        bulkForm.querySelector('[data-availability-bulk-apply-status]').checked = true;
        bulkForm.querySelector('[data-availability-bulk-status]').value = 'available';
        fillBulkRooms();
        bulkModal.show();
    };

    const saveBulk = async () => {
        clearFormErrors(bulkForm);
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Aplicar cambios',
            text: 'Se actualizaran todas las celdas que coincidan con el rango y filtros seleccionados.',
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
        });

        if (!result.isConfirmed) {
            return;
        }

        const response = await fetch(root.dataset.bulkUrl, {
            method: 'POST',
            body: new FormData(bulkForm),
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (response.status === 422) {
            showFormErrors(bulkForm, payload.errors ?? {});
            Swal.fire({ icon: 'error', title: 'Validacion', text: payload.message ?? 'Revisa los datos ingresados.' });

            return;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo aplicar la accion en bloque.');
        }

        bulkModal.hide();
        await loadGridData();
        toast.fire({ icon: 'success', title: payload.message ?? 'Disponibilidad actualizada.' });
    };

    gridTarget.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-availability-toggle]');

        if (!toggle) {
            return;
        }

        const cell = toggle.closest('.availability-cell');
        const nextStatus = cell?.dataset.storedStatus === 'closed' ? 'available' : 'closed';
        const price = cell?.querySelector('[data-availability-price-input]')?.value ?? '';

        if (nextStatus === 'available' && price === '') {
            Swal.fire({
                icon: 'info',
                title: 'Precio requerido',
                text: 'Asigna un precio antes de habilitar esta fecha.',
            });

            return;
        }

        saveInlineDay(cell, { status: nextStatus }).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });

    gridTarget.addEventListener('focusin', (event) => {
        const input = event.target.closest('[data-availability-price-input]');

        if (input) {
            input.dataset.previousValue = input.value;
        }
    });

    gridTarget.addEventListener('keydown', (event) => {
        const input = event.target.closest('[data-availability-price-input]');

        if (input && event.key === 'Enter') {
            event.preventDefault();
            input.blur();
        }
    });

    gridTarget.addEventListener('focusout', (event) => {
        const input = event.target.closest('[data-availability-price-input]');

        if (!input || input.value === (input.dataset.previousValue ?? '')) {
            return;
        }

        saveInlineDay(input.closest('.availability-cell'), { price: input.value }).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });

    root.querySelector('[data-availability-prev]')?.addEventListener('click', () => loadGridData(gridData.previous_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-availability-today]')?.addEventListener('click', () => loadGridData(gridData.current_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-availability-next]')?.addEventListener('click', () => loadGridData(gridData.next_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-availability-bulk-open]')?.addEventListener('click', openBulkModal);
    datePicker.addEventListener('change', (event) => {
        event.preventDefault();
        loadGridData(datePicker.value).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });
    filtersForm.addEventListener('change', () => loadGridData().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    filtersForm.addEventListener('submit', (event) => {
        event.preventDefault();
        loadGridData().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });
    filtersForm.addEventListener('reset', () => window.setTimeout(() => loadGridData().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })), 0));
    bulkForm.querySelector('[data-availability-bulk-space]')?.addEventListener('change', fillBulkRooms);
    bulkForm.addEventListener('submit', (event) => {
        event.preventDefault();
        saveBulk().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });

    renderGrid();
    root.dataset.availabilityInitialized = '1';
}

showInitialAlerts();
disableBusinessFormAutocomplete();
initTomSelects();
initPurchaseForm();
syncPointSaleWarehouse();
initPosSaleForm();
initDefragmentForms();
initTransferForms();
initStockAdjustmentForms();
initUserDropdowns();
initSidebarToggle();
initCashExpenseModal();
initCashCloseModal();
initAdminDataTables();
initCharacterCounters();
initSpaceLocationMaps();
initPublicAccommodationSearch();
initSharedRoomSort();
initPhotoUploadPreviews();
initOccupancyWeekGrid();
initAvailabilityGrid();

document.addEventListener('click', (event) => {
    const modalTrigger = event.target.closest('[data-modal-url]');
    const roomServicesCopyAll = event.target.closest('[data-room-services-copy-all]');

    if (modalTrigger) {
        event.preventDefault();
        openAjaxModal(modalTrigger);

        return;
    }

    if (roomServicesCopyAll) {
        event.preventDefault();
        const form = roomServicesCopyAll.closest('form');
        const select = form?.querySelector('select[name="target_room_ids[]"]');
        const values = [...(select?.options ?? [])].map((option) => option.value);

        if (select?.tomselect) {
            select.tomselect.setValue(values);
        } else if (select) {
            [...select.options].forEach((option) => {
                option.selected = true;
            });
        }
    }
});

document.addEventListener('change', (event) => {
    if (event.target.closest('[data-point-sale-branch]')) {
        syncPointSaleWarehouse(event.target.closest('form') ?? document);
    }

    const autoSubmitField = event.target.closest('[data-auto-submit-form]');

    if (autoSubmitField) {
        autoSubmitField.closest('form')?.requestSubmit();
    }
});

document.addEventListener('submit', (event) => {
    const ajaxForm = event.target.closest('[data-ajax-form]');
    const deleteForm = event.target.closest('[data-confirm-delete]');
    const voidPurchaseForm = event.target.closest('[data-confirm-void-purchase]');
    const voidSaleForm = event.target.closest('[data-confirm-void-sale]');

    if (voidPurchaseForm) {
        event.preventDefault();
        confirmVoidPurchase(voidPurchaseForm);

        return;
    }

    if (voidSaleForm) {
        event.preventDefault();
        confirmVoidSale(voidSaleForm);

        return;
    }

    if (deleteForm) {
        event.preventDefault();
        confirmDelete(deleteForm);

        return;
    }

    if (ajaxForm) {
        event.preventDefault();

        if (ajaxForm.matches('[data-photo-upload-form]') && !validatePhotoUploadForm(ajaxForm)) {
            Swal.fire({ icon: 'error', title: 'Fotografias', text: 'Revisa el formato, cantidad o peso de las fotografias antes de guardar.' });

            return;
        }

        submitAjaxForm(ajaxForm);
    }
});
