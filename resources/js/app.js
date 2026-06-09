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
const ajaxModalDialog = ajaxModalElement?.querySelector('.modal-dialog');
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

    const modalSize = trigger.dataset.modalSize ?? 'lg';
    ajaxModalDialog?.classList.toggle('modal-xl', modalSize === 'xl');
    ajaxModalDialog?.classList.toggle('modal-lg', modalSize !== 'xl');
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
            initPackageIconSelectors(ajaxModalBody);
            initPackageServiceCarts(ajaxModalBody);
            initExtraChargeForms(ajaxModalBody);
            initStayPaymentForms(ajaxModalBody);
            initRoomChangeForms(ajaxModalBody);
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
        initPublicCompanyMaps(fresh);
        initSharedRoomSort(fresh);
        initPhotoUploadPreviews(fresh);
        initPackageIconSelectors(fresh);
        initPackageServiceCarts(fresh);
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

function confirmSubmit(form) {
    Swal.fire({
        icon: form.dataset.confirmIcon ?? 'question',
        title: form.dataset.confirmSubmit ?? 'Confirmar accion',
        text: form.dataset.confirmText ?? 'Confirma para continuar.',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton ?? 'Si, continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: form.dataset.confirmColor ?? '#066fd1',
    }).then((result) => {
        if (result.isConfirmed) {
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

function normalizeTablerIconClass(value) {
    const icon = String(value || '').trim();

    if (!icon) {
        return 'ti ti-icons';
    }

    if (icon.startsWith('ti ')) {
        return icon;
    }

    return icon.startsWith('ti-') ? `ti ${icon}` : `ti ti-${icon}`;
}

function updatePackageIconPreview(input) {
    const form = input.closest('form') ?? document;
    const preview = form.querySelector('[data-package-icon-preview] i');

    if (!preview) {
        return;
    }

    preview.className = normalizeTablerIconClass(input.value);
}

function initPackageIconSelectors(scope = document) {
    scope.querySelectorAll('[data-package-icon-input]').forEach((input) => {
        if (input.dataset.packageIconInitialized === '1') {
            return;
        }

        input.addEventListener('input', () => updatePackageIconPreview(input));
        updatePackageIconPreview(input);
        input.dataset.packageIconInitialized = '1';
    });
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');

    return element.innerHTML;
}

function reindexPackageServiceCart(cart) {
    const items = [...cart.querySelectorAll('[data-package-service-selected]')];
    const empty = cart.querySelector('[data-package-service-empty]');
    const count = cart.querySelector('[data-package-service-count]');

    items.forEach((item, index) => {
        item.querySelectorAll('[data-package-service-input]').forEach((input) => {
            const key = input.dataset.packageServiceInput;

            if (key) {
                input.name = `services[${index}][${key}]`;
            }
        });

        const order = item.querySelector('[data-package-service-input="sort_order"]');

        if (order) {
            order.value = index;
        }

        togglePackageServiceAdditionalPrice(item);
    });

    empty?.classList.toggle('d-none', items.length > 0);

    if (count) {
        count.textContent = String(items.length);
    }

    cart.querySelectorAll('[data-package-service-add]').forEach((button) => {
        const selected = cart.querySelector(`[data-package-service-selected="${button.dataset.serviceId}"]`);
        const card = cart.querySelector(`[data-package-service-card="${button.dataset.serviceId}"]`);

        button.disabled = Boolean(selected);
        card?.classList.toggle('is-selected', Boolean(selected));
    });
}

function togglePackageServiceAdditionalPrice(item) {
    const inclusion = item.querySelector('[data-package-service-input="inclusion_type"]');
    const additionalPrice = item.querySelector('[data-package-service-input="additional_price"]');
    const enabled = inclusion?.value === 'optional_paid';

    if (!additionalPrice) {
        return;
    }

    additionalPrice.disabled = !enabled;

    if (!enabled) {
        additionalPrice.value = '';
    }
}

function addPackageServiceToCart(button) {
    const cart = button.closest('[data-package-service-cart]');
    const list = cart?.querySelector('[data-package-service-selected-list]');
    const template = cart?.querySelector('[data-package-service-template]');

    if (!cart || !list || !template || cart.querySelector(`[data-package-service-selected="${button.dataset.serviceId}"]`)) {
        return;
    }

    const index = list.querySelectorAll('[data-package-service-selected]').length;
    const html = template.innerHTML
        .replaceAll('__INDEX__', String(index))
        .replaceAll('__SERVICE_ID__', escapeHtml(button.dataset.serviceId))
        .replaceAll('__ICON__', escapeHtml(button.dataset.serviceIcon || 'ti ti-circle-check'))
        .replaceAll('__NAME__', escapeHtml(button.dataset.serviceName))
        .replaceAll('__TYPE__', escapeHtml(button.dataset.serviceType));

    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    list.append(wrapper.firstElementChild);
    reindexPackageServiceCart(cart);
}

function removePackageServiceFromCart(button) {
    const cart = button.closest('[data-package-service-cart]');
    button.closest('[data-package-service-selected]')?.remove();

    if (cart) {
        reindexPackageServiceCart(cart);
    }
}

function movePackageServiceItem(event, list) {
    const dragged = list.querySelector('.is-dragging');
    const target = event.target.closest('[data-package-service-selected]');

    if (!dragged || !target || dragged === target) {
        return;
    }

    const targetRect = target.getBoundingClientRect();
    const placeAfter = event.clientY > targetRect.top + targetRect.height / 2;
    list.insertBefore(dragged, placeAfter ? target.nextSibling : target);
}

function initPackageServiceCarts(scope = document) {
    scope.querySelectorAll('[data-package-service-cart]').forEach((cart) => {
        if (cart.dataset.packageServiceCartInitialized === '1') {
            return;
        }

        const list = cart.querySelector('[data-package-service-selected-list]');

        list?.addEventListener('dragstart', (event) => {
            const item = event.target.closest('[data-package-service-selected]');

            if (!item) {
                return;
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.packageServiceSelected ?? '');
            item.classList.add('is-dragging');
        });

        list?.addEventListener('dragend', (event) => {
            const item = event.target.closest('[data-package-service-selected]');
            item?.classList.remove('is-dragging');
            reindexPackageServiceCart(cart);
        });

        list?.addEventListener('dragover', (event) => {
            event.preventDefault();
            movePackageServiceItem(event, list);
        });

        cart.addEventListener('change', (event) => {
            const inclusion = event.target.closest('[data-package-service-input="inclusion_type"]');

            if (!inclusion) {
                return;
            }

            togglePackageServiceAdditionalPrice(inclusion.closest('[data-package-service-selected]'));
        });

        reindexPackageServiceCart(cart);
        cart.dataset.packageServiceCartInitialized = '1';
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

function initPublicCompanyMaps(scope = document) {
    scope.querySelectorAll('[data-public-company-map]').forEach((root) => {
        if (root.dataset.publicCompanyMapInitialized === '1') {
            return;
        }

        const apiKey = root.dataset.googleMapsKey;
        const canvas = root.querySelector('[data-public-company-map-canvas]');
        const locations = parsePublicCompanyMapLocations(root);

        if (!apiKey || !canvas || locations.length === 0) {
            return;
        }

        root.dataset.publicCompanyMapInitialized = '1';
        loadGoogleMaps(apiKey)
            .then(() => mountPublicCompanyMap(root, canvas, locations))
            .catch((error) => {
                canvas.innerHTML = `<div class="company-public-map-placeholder"><i class="ti ti-map-2"></i><strong>Mapa no disponible</strong><span>${error.message}</span></div>`;
            });
    });
}

function parsePublicCompanyMapLocations(root) {
    try {
        const locations = JSON.parse(root.dataset.locations || '[]');

        return locations
            .map((location, index) => ({
                ...location,
                id: location.id || `location-${index}`,
                latitude: Number(location.latitude),
                longitude: Number(location.longitude),
            }))
            .filter((location) => Number.isFinite(location.latitude) && Number.isFinite(location.longitude));
    } catch {
        return [];
    }
}

function mountPublicCompanyMap(root, canvas, locations) {
    const first = locations[0];
    const map = new google.maps.Map(canvas, {
        center: { lat: first.latitude, lng: first.longitude },
        zoom: 13,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });
    const bounds = new google.maps.LatLngBounds();
    const infoWindow = new google.maps.InfoWindow();
    const markerIcons = publicCompanyMarkerIcons();
    const markersById = new Map();
    const locationRegion = root.closest('.company-public-location-grid') || document;
    const cardsById = new Map(
        [...locationRegion.querySelectorAll('[data-public-company-location][data-location-id]')]
            .map((card) => [card.dataset.locationId, card])
    );
    let selectedLocationId = null;

    locations.forEach((location) => {
        const position = { lat: location.latitude, lng: location.longitude };
        const marker = new google.maps.Marker({
            icon: markerIcons.default,
            map,
            position,
            title: location.name,
        });
        const details = [location.address, location.reference, [location.city, location.country].filter(Boolean).join(', ')]
            .filter(Boolean)
            .map((item) => `<div>${escapeHtml(item)}</div>`)
            .join('');
        const content = `<strong>${escapeHtml(location.name)}</strong>${details}`;

        marker.addListener('click', () => {
            selectLocation(location.id, 'marker');
        });

        markersById.set(location.id, { marker, position, content });
        bounds.extend(position);
    });

    cardsById.forEach((card, locationId) => {
        const selectFromCard = () => selectLocation(locationId, 'list');

        card.addEventListener('click', selectFromCard);
        card.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            selectFromCard();
        });
    });

    if (locations.length > 1) {
        map.fitBounds(bounds);
    }

    function selectLocation(locationId, source) {
        const selected = markersById.get(locationId);

        if (!selected) {
            return;
        }

        if (selectedLocationId !== null && markersById.has(selectedLocationId)) {
            markersById.get(selectedLocationId).marker.setIcon(markerIcons.default);
            markersById.get(selectedLocationId).marker.setZIndex(null);
        }

        if (selectedLocationId !== null && cardsById.has(selectedLocationId)) {
            cardsById.get(selectedLocationId).classList.remove('is-selected');
        }

        selectedLocationId = locationId;
        selected.marker.setIcon(markerIcons.selected);
        selected.marker.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
        infoWindow.setContent(selected.content);
        infoWindow.open({ anchor: selected.marker, map });
        map.panTo(selected.position);

        if (source === 'list' && map.getZoom() < 15) {
            map.setZoom(15);
        }

        const card = cardsById.get(locationId);

        if (!card) {
            return;
        }

        card.classList.add('is-selected');

        if (source === 'marker') {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function publicCompanyMarkerIcons() {
    return {
        default: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: '#1d4f91',
            fillOpacity: 1,
            scale: 8,
            strokeColor: '#ffffff',
            strokeWeight: 2,
        },
        selected: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: '#f4c35a',
            fillOpacity: 1,
            scale: 11,
            strokeColor: '#121826',
            strokeWeight: 2,
        },
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
    const errorTarget = input.closest('.col-md-6, .col-md-5, .col-12, form')?.querySelector('[data-photo-error]');
    const preview = document.querySelector(input.dataset.photoPreview ?? '');
    const clearButton = document.querySelector(input.dataset.photoClear ?? '');
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
        if (!input.dataset.initialPreview) {
            input.dataset.initialPreview = preview.innerHTML;
        }

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

            if (files.length === 0) {
                preview.innerHTML = input.dataset.initialPreview ?? '';
            }
        }
    }

    clearButton?.classList.toggle('d-none', files.length === 0);

    return errors.length === 0;
}

function initPhotoUploadPreviews(scope = document) {
    scope.querySelectorAll('[data-photo-input]').forEach((input) => {
        if (input.dataset.photoPreviewInitialized === '1') {
            return;
        }

        input.addEventListener('change', () => validatePhotoInput(input));
        document.querySelector(input.dataset.photoClear ?? '')?.addEventListener('click', () => {
            input.value = '';
            validatePhotoInput(input);
        });
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
    const actionsPopover = root.querySelector('[data-occupancy-actions-popover]');
    const actionModalElement = root.querySelector('[data-occupancy-action-modal]');
    const actionModal = actionModalElement ? bootstrap.Modal.getOrCreateInstance(actionModalElement) : null;
    const actionModalDialog = actionModalElement?.querySelector('.modal-dialog');
    const actionModalTitle = root.querySelector('[data-occupancy-action-modal-title]');
    const actionModalBody = root.querySelector('[data-occupancy-action-modal-body]');
    const form = root.querySelector('[data-occupancy-form]');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    const modalTitle = root.querySelector('[data-occupancy-modal-title]');
    const blockIdInput = root.querySelector('[data-occupancy-block-id]');
    const bedUnitIdInput = root.querySelector('[data-occupancy-bed-unit-id]');
    const spaceSelect = root.querySelector('[data-occupancy-space-select]');
    const roomSelect = root.querySelector('[data-occupancy-room-select]');
    const roomWrap = root.querySelector('[data-occupancy-room-wrap]');
    const deleteButton = root.querySelector('[data-occupancy-delete]');
    const spaces = JSON.parse(root.dataset.spaces ?? '[]');
    const canManage = root.dataset.canManage === '1';
    let weekData = JSON.parse(root.dataset.initialWeek ?? '{}');
    let weekStart = weekData.week_start;

    if (!gridTarget || !filtersForm || !weekPicker || !modal || !form || !actionsPopover || !actionModal || !actionModalTitle || !actionModalBody) {
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
    const cellParams = (spaceId, roomId, date, bedUnitId = '') => {
        const params = new URLSearchParams({
            space_id: spaceId,
            date,
        });

        if (roomId) {
            params.set('space_room_id', roomId);
        }

        if (bedUnitId) {
            params.set('room_bed_unit_id', bedUnitId);
        }

        return params;
    };
    const findSpace = (spaceId) => spaces.find((space) => String(space.id) === String(spaceId));
    const findRow = (spaceId, roomId, bedUnitId = '') => weekData.rows?.find((row) => String(row.space_id) === String(spaceId)
        && String(row.room_id ?? '') === String(roomId ?? '')
        && String(row.room_bed_unit_id ?? '') === String(bedUnitId ?? ''));
    const findCell = (spaceId, roomId, date, bedUnitId = '') => findRow(spaceId, roomId, bedUnitId)?.cells?.find((cell) => cell.date === date);
    const cellContent = (cell) => {
        if (cell.stay_id) {
            const holderName = cell.holder_guest_name ?? '';

            return `
                <span class="occupancy-cell-label occupancy-cell-stay" data-stay-id="${escapeHtml(cell.stay_id)}">
                    ${holderName ? '' : `<span class="occupancy-cell-state">${escapeHtml(cell.status_label ?? 'Ocupado')}</span>`}
                    <span class="occupancy-cell-guest">${escapeHtml(holderName || 'Sin titular')}</span>
                    <span class="occupancy-cell-code">${escapeHtml(cell.check_in_code ?? '')}</span>
                </span>
            `;
        }

        const label = cell.status === 'available' ? '' : (cell.label ?? '');

        return `
            <span class="occupancy-cell-label" data-block-id="${escapeHtml(cell.block_id ?? '')}">
                ${escapeHtml(label)}
            </span>
        `;
    };

    const cellAttributes = (row, cell) => `
        data-date="${escapeHtml(cell.date)}"
        data-space-id="${escapeHtml(row.space_id)}"
        data-room-id="${escapeHtml(row.room_id ?? '')}"
        data-room-bed-unit-id="${escapeHtml(row.room_bed_unit_id ?? cell.room_bed_unit_id ?? '')}"
        data-resource-type="${escapeHtml(row.type)}"
        data-status="${escapeHtml(cell.status)}"
        data-block-id="${escapeHtml(cell.block_id ?? '')}"
        data-stay-id="${escapeHtml(cell.stay_id ?? '')}"
        data-reservation-id="${escapeHtml(cell.reservation_id ?? '')}"
        data-check-in-group-id="${escapeHtml(cell.check_in_group_id ?? '')}"
        data-account-statement-id="${escapeHtml(cell.account_statement_id ?? '')}"
        data-closed-by-availability="${cell.closed_by_availability ? '1' : '0'}"
        data-blocked-by-availability="${cell.blocked_by_availability ? '1' : '0'}"
    `;

    const emptyOccupancyCell = () => '<td class="occupancy-cell-empty"></td>';

    const rowCells = (row) => {
        const cells = row.cells ?? [];
        const html = [];

        if (cells.length === 0) {
            return (weekData.dates ?? []).map(emptyOccupancyCell).join('');
        }

        for (let index = 0; index < cells.length; index += 1) {
            const cell = cells[index];
            let colspan = 1;

            if (cell.is_covered_by_full_room && !row.renders_full_room_coverage) {
                continue;
            }

            if (cell.is_empty_group_cell) {
                html.push(emptyOccupancyCell());

                continue;
            }

            if (cell.stay_id) {
                while (cells[index + colspan]?.stay_id && String(cells[index + colspan].stay_id) === String(cell.stay_id)) {
                    colspan += 1;
                }
            }

            const rowspan = cell.is_full_room_coverage && Number(row.full_room_rowspan || 1) > 1
                ? Number(row.full_room_rowspan)
                : 1;

            html.push(`
                <td
                    class="occupancy-cell occupancy-cell-${escapeHtml(cell.tone)} ${colspan > 1 ? 'occupancy-cell-span' : ''} ${rowspan > 1 ? 'occupancy-cell-vspan' : ''}"
                    ${colspan > 1 ? `colspan="${colspan}"` : ''}
                    ${rowspan > 1 ? `rowspan="${rowspan}"` : ''}
                    ${cellAttributes(row, cell)}
                >
                    ${cellContent(cell)}
                </td>
            `);

            index += colspan - 1;
        }

        return html.join('');
    };

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
        bedUnitIdInput.value = '';
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
                    <tr class="${row.type === 'shared_bed_unit' ? 'occupancy-bed-unit-row' : (row.type === 'shared_room' ? 'occupancy-room-row' : 'occupancy-private-row')}">
                        <td class="occupancy-resource-cell">
                            ${row.type === 'shared_bed_unit' ? '<i class="ti ti-bed me-1"></i>' : (row.type === 'shared_room' ? '<i class="ti ti-door me-1"></i>' : '<i class="ti ti-home me-1"></i>')}${escapeHtml(row.label)}
                        </td>
                        ${rowCells(row)}
                    </tr>
                `;
            }).join('')
            : `<tr><td class="text-center text-body-secondary py-4" colspan="${dates.length + 1}">No hay recursos para mostrar.</td></tr>`;

        gridTarget.innerHTML = `<table class="occupancy-grid">${header}<tbody>${body}</tbody></table>`;
        renderSummary();
    };

    const hideCellActions = () => {
        actionsPopover.classList.add('d-none');
        actionsPopover.innerHTML = '';
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
        hideCellActions();
    }

    const openCreateBlockModal = (spaceId = '', roomId = '', date = '', bedUnitId = '') => {
        if (!canManage) {
            return;
        }

        clearOccupancyForm();
        modalTitle.textContent = 'Nuevo bloqueo';
        spaceSelect.value = spaceId;
        fillRooms(spaceId, roomId);
        bedUnitIdInput.value = bedUnitId;
        form.querySelector('[name="start_date"]').value = date;
        form.querySelector('[name="end_date"]').value = date;
        const row = findRow(spaceId, roomId, bedUnitId);
        form.querySelector('[name="title"]').value = row ? `Bloqueo - ${row.label}` : '';
        modal.show();
    };

    const openEditBlockModal = (spaceId, roomId, date, bedUnitId = '') => {
        const cell = findCell(spaceId, roomId, date, bedUnitId);

        if (!cell?.block_id) {
            openCreateBlockModal(spaceId, roomId, date, bedUnitId);

            return;
        }

        clearOccupancyForm();
        modalTitle.textContent = canManage ? 'Editar bloqueo' : 'Detalle de bloqueo';
        blockIdInput.value = cell.block_id;
        deleteButton?.classList.toggle('d-none', !canManage);
        spaceSelect.value = spaceId;
        fillRooms(spaceId, roomId);
        bedUnitIdInput.value = bedUnitId;
        form.querySelector('[name="type"]').value = cell.status;
        form.querySelector('[name="title"]').value = cell.title || typeLabels[cell.status] || 'Bloqueo';
        form.querySelector('[name="description"]').value = cell.description || '';
        form.querySelector('[name="start_date"]').value = cell.start_date;
        form.querySelector('[name="end_date"]').value = cell.end_date;
        modal.show();
    };

    const showCellActions = async (cell) => {
        if (!canManage) {
            Swal.fire({
                icon: 'info',
                title: 'Solo lectura',
                text: 'No tienes permiso para gestionar ocupabilidad.',
            });

            return;
        }

        actionsPopover.classList.remove('d-none');
        actionsPopover.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';

        const cellRect = cell.getBoundingClientRect();
        const rootRect = root.getBoundingClientRect();
        actionsPopover.style.top = `${cellRect.bottom - rootRect.top + 6 + root.scrollTop}px`;
        actionsPopover.style.left = `${cellRect.left - rootRect.left + root.scrollLeft}px`;

        const response = await fetch(`${root.dataset.cellActionsUrl}?${cellParams(cell.dataset.spaceId, cell.dataset.roomId, cell.dataset.date, cell.dataset.roomBedUnitId).toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (response.status === 422) {
            throw new Error(payload.message ?? 'La celda seleccionada no es valida.');
        }

        if (!response.ok) {
            throw new Error(payload.message ?? 'No se pudieron cargar las acciones.');
        }

        actionsPopover.innerHTML = payload.html;
    };

    const openOccupancyActionModal = async (title, url, spaceId, roomId, date, bedUnitId = '', size = 'lg') => {
        actionModalTitle.textContent = title;
        actionModalDialog?.classList.toggle('modal-xl', size === 'xl');
        actionModalDialog?.classList.toggle('modal-lg', size !== 'xl');
        actionModalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        actionModal.show();

        const response = await fetch(`${url}?${cellParams(spaceId, roomId, date, bedUnitId).toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 422) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message ?? 'La accion no esta permitida para esta fecha.');
        }

        if (!response.ok) {
            throw new Error('No se pudo cargar el panel solicitado.');
        }

        actionModalBody.innerHTML = await response.text();
        initExtraChargeForms(actionModalBody);
        initOccupancyCheckOutForms(actionModalBody);
    };

    const openCheckInModal = (spaceId, date, roomId = '', bedUnitId = '') => {
        const params = new URLSearchParams({
            check_in_date: date,
            space_id: spaceId,
            resource_type: bedUnitId ? 'shared_bed_unit' : (roomId ? 'shared_room' : 'private_space'),
        });

        if (roomId) {
            params.set('space_room_id', roomId);
        }

        if (bedUnitId) {
            params.set('room_bed_unit_id', bedUnitId);
        }

        window.location.href = `${root.dataset.checkInCreateUrl}?${params.toString()}`;
    };
    const openCheckOutModal = (spaceId, date, roomId = '', bedUnitId = '') => openOccupancyActionModal('Check-out', root.dataset.checkOutModalUrl, spaceId, roomId, date, bedUnitId);
    const openCheckInSummaryModal = (spaceId, date, roomId = '', bedUnitId = '') => openOccupancyActionModal('Ver check-in', root.dataset.checkInSummaryModalUrl, spaceId, roomId, date, bedUnitId, 'xl');
    const openReservationModal = (spaceId, date, roomId = '', bedUnitId = '') => openOccupancyActionModal('Reserva', root.dataset.reservationModalUrl, spaceId, roomId, date, bedUnitId);
    const openExtraChargeModal = (spaceId, date, roomId = '', bedUnitId = '') => openOccupancyActionModal('Cargo extra', root.dataset.extraChargeModalUrl, spaceId, roomId, date, bedUnitId);
    const openStayPaymentModal = async (stayId) => {
        if (!stayId || !root.dataset.stayPaymentCreateUrlTemplate) {
            throw new Error('No se encontro la estancia para cobrar.');
        }

        actionModalTitle.textContent = 'Cobrar estancia';
        actionModalDialog?.classList.toggle('modal-xl', false);
        actionModalDialog?.classList.toggle('modal-lg', true);
        actionModalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        actionModal.show();

        const response = await fetch(routeFor(root.dataset.stayPaymentCreateUrlTemplate, stayId), {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el formulario de cobro.');
        }

        actionModalBody.innerHTML = await response.text();
    };
    const openBlockModal = (spaceId, date, roomId = '', bedUnitId = '') => openEditBlockModal(spaceId, roomId, date, bedUnitId);

    const openActionFromMenu = (button) => {
        const { occupancyAction, spaceId, roomId, roomBedUnitId, date, stayId } = button.dataset;
        const handlers = {
            view_check_in: () => openCheckInSummaryModal(spaceId, date, roomId, roomBedUnitId),
            collect_stay_payment: () => openStayPaymentModal(stayId),
            check_in: () => openCheckInModal(spaceId, date, roomId, roomBedUnitId),
            check_out: () => openCheckOutModal(spaceId, date, roomId, roomBedUnitId),
            reservation: () => openReservationModal(spaceId, date, roomId, roomBedUnitId),
            extra_charge: () => openExtraChargeModal(spaceId, date, roomId, roomBedUnitId),
            block: () => openBlockModal(spaceId, date, roomId, roomBedUnitId),
        };

        hideCellActions();

        return handlers[occupancyAction]?.();
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

    const submitCheckOut = async (formElement) => {
        clearFormErrors(formElement);
        setSubmitting(formElement, true);

        try {
            const response = await fetch(formElement.action, {
                method: 'POST',
                body: new FormData(formElement),
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();

            if (response.status === 422) {
                showFormErrors(formElement, payload.errors ?? {});
                Swal.fire({ icon: 'error', title: 'Check-out bloqueado', text: payload.message ?? 'El check-in tiene deuda pendiente.' });

                return;
            }

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? 'No se pudo realizar el check-out.');
            }

            actionModal.hide();
            await loadWeekData();
            toast.fire({ icon: 'success', title: payload.message ?? 'Check-out realizado.' });
        } finally {
            setSubmitting(formElement, false);
        }
    };

    const initOccupancyCheckOutForms = (scope = document) => {
        scope.querySelectorAll('[data-check-out-form]').forEach((formElement) => {
            if (formElement.dataset.checkOutInitialized === '1') {
                return;
            }

            formElement.addEventListener('submit', (event) => {
                event.preventDefault();
                submitCheckOut(formElement).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
            });
            formElement.dataset.checkOutInitialized = '1';
        });
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

        if (cell.dataset.blockedByAvailability === '1') {
            Swal.fire({
                icon: 'info',
                title: 'Gestionada desde Disponibilidad',
                text: 'Esta fecha esta cerrada, reservada u ocupada desde Disponibilidad. Ajustala ahi primero para operar en Ocupabilidad.',
            });

            return;
        }

        showCellActions(cell).catch((error) => {
            hideCellActions();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        });
    });

    actionsPopover.addEventListener('click', (event) => {
        const actionButton = event.target.closest('[data-occupancy-action]');

        if (!actionButton) {
            return;
        }

        openActionFromMenu(actionButton)?.catch((error) => {
            actionModal.hide();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('.occupancy-cell') || event.target.closest('[data-occupancy-actions-popover]')) {
            return;
        }

        hideCellActions();
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
    const statusModalElement = root.querySelector('[data-availability-status-modal]');
    const statusForm = root.querySelector('[data-availability-status-form]');
    const statusModal = statusModalElement ? bootstrap.Modal.getOrCreateInstance(statusModalElement) : null;
    const previousButton = root.querySelector('[data-availability-prev]');
    const spaces = JSON.parse(root.dataset.spaces ?? '[]');
    const canManage = root.dataset.canManage === '1';
    let gridData = JSON.parse(root.dataset.initialGrid ?? '{}');
    let weekStart = gridData.week_start ?? gridData.start_date;

    if (!gridTarget || !filtersForm || !datePicker || !statusModal || !statusForm) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const statusIcons = {
        available: 'ti ti-check',
        closed: 'ti ti-lock',
        reserved: 'ti ti-calendar-time',
        occupied: 'ti ti-user-check',
    };
    const findSpace = (spaceId) => spaces.find((space) => String(space.id) === String(spaceId));
    const findRoom = (space, roomId) => (space?.rooms ?? []).find((room) => String(room.id) === String(roomId));
    const statusField = (name) => statusForm.querySelector(`[data-availability-status-field="${name}"]`);
    const statusLabel = (name) => statusForm.querySelector(`[data-availability-status-label="${name}"]`);

    const renderSummary = () => {
        if (!summaryTarget) {
            return;
        }

        const summary = gridData.summary ?? {};
        const items = [
            ['Recursos', summary.resources ?? 0],
            ['Disponible', summary.available ?? 0],
            ['Cerrado', summary.closed ?? 0],
            ['Reservado', summary.reserved ?? 0],
            ['Ocupado', summary.occupied ?? 0],
        ];

        summaryTarget.innerHTML = items.map(([label, value]) => `
            <div class="col-sm-6 col-xl">
                <div class="availability-summary-item">
                    <span>${escapeHtml(label)}</span>
                    <strong>${escapeHtml(value)}</strong>
                </div>
            </div>
        `).join('');
    };

    const syncNavigation = () => {
        if (previousButton) {
            previousButton.disabled = gridData.can_go_previous === false;
        }

        if (gridData.current_period) {
            datePicker.min = gridData.current_period;
        }
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
                    <tr class="${row.type === 'shared_bed_unit' ? 'availability-bed-unit-row' : (row.type === 'shared_room' ? 'availability-room-row' : 'availability-private-row')}">
                        <td class="availability-resource-cell">
                            ${row.type === 'shared_bed_unit' ? '<i class="ti ti-bed me-1"></i>' : (row.type === 'shared_room' ? '<i class="ti ti-door me-1"></i>' : '<i class="ti ti-home me-1"></i>')}${escapeHtml(row.label)}
                        </td>
                        ${row.is_group_header || (row.cells ?? []).length === 0 ? dates.map(() => '<td class="availability-cell-empty"></td>').join('') : row.cells.map((cell) => `
                            <td
                                class="availability-cell availability-cell-${escapeHtml(cell.tone)} ${cell.is_past ? 'availability-cell-past' : ''}"
                                data-date="${escapeHtml(cell.date)}"
                                data-space-id="${escapeHtml(row.space_id)}"
                                data-room-id="${escapeHtml(row.room_id ?? '')}"
                                data-room-bed-unit-id="${escapeHtml(row.room_bed_unit_id ?? cell.room_bed_unit_id ?? '')}"
                                data-resource-type="${escapeHtml(row.type)}"
                                data-status="${escapeHtml(cell.status)}"
                                data-source="${escapeHtml(cell.source ?? '')}"
                                data-availability-status-id="${escapeHtml(cell.availability_status_id ?? '')}"
                                data-notes="${escapeHtml(cell.notes ?? '')}"
                                data-space-label="${escapeHtml(row.space_label ?? row.label)}"
                                data-room-label="${escapeHtml(row.type === 'shared_room' ? row.label : (row.room_label ?? ''))}"
                                data-bed-unit-label="${escapeHtml(row.type === 'shared_bed_unit' ? row.label : '')}"
                            >
                                <button
                                    class="availability-cell-content"
                                    type="button"
                                    data-availability-cell-action
                                    ${canManage && !cell.is_past && (cell.actions ?? []).includes('change_status') ? '' : 'disabled'}
                                >
                                    <i class="${escapeHtml(statusIcons[cell.status] ?? 'ti ti-circle')}"></i>
                                    <span
                                        class="availability-status availability-status-action"
                                    >${escapeHtml(cell.guest_name ?? cell.short_label ?? cell.label)}</span>
                                    ${cell.source ? `<small>${escapeHtml(cell.source)}</small>` : ''}
                                </button>
                            </td>
                        `).join('')}
                    </tr>
                `;
            }).join('')
            : `<tr><td class="text-center text-body-secondary py-4" colspan="${dates.length + 1}">No hay recursos para mostrar.</td></tr>`;

        gridTarget.innerHTML = `<table class="availability-grid">${header}<tbody>${body}</tbody></table>`;
        renderSummary();
        syncNavigation();
    };

    async function loadGridData(targetDate = weekStart) {
        const params = new URLSearchParams(new FormData(filtersForm));
        params.set('week_start', targetDate);

        const response = await fetch(`${root.dataset.weekDataUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar la disponibilidad.');
        }

        gridData = await response.json();
        weekStart = gridData.week_start;
        datePicker.value = weekStart;
        renderGrid();
    }

    const openStatusModal = (cell) => {
        if (!canManage || !cell) {
            return;
        }

        clearFormErrors(statusForm);
        statusForm.reset();
        statusField('space_id').value = cell.dataset.spaceId ?? '';
        statusField('space_room_id').value = cell.dataset.roomId ?? '';
        statusField('room_bed_unit_id').value = cell.dataset.roomBedUnitId ?? '';
        statusField('date').value = cell.dataset.date ?? '';
        statusField('availability_status_id').value = cell.dataset.availabilityStatusId ?? '';
        statusField('status').value = cell.dataset.status ?? 'available';
        statusField('notes').value = cell.dataset.notes ?? '';
        statusLabel('space').textContent = cell.dataset.spaceLabel ?? '-';
        statusLabel('room').textContent = cell.dataset.roomLabel || '-';
        statusLabel('bed_unit').textContent = cell.dataset.bedUnitLabel || '-';
        statusLabel('date').textContent = cell.dataset.date ?? '-';
        statusModal.show();
    };

    const saveStatus = async () => {
        const body = new FormData();
        const statusId = statusField('availability_status_id').value;
        const url = statusId
            ? root.dataset.updateStatusUrlTemplate.replace('__ID__', statusId)
            : root.dataset.storeStatusUrl;

        body.append('space_id', statusField('space_id').value);
        body.append('space_room_id', statusField('space_room_id').value);
        body.append('room_bed_unit_id', statusField('room_bed_unit_id').value);
        body.append('date', statusField('date').value);
        body.append('status', statusField('status').value);
        body.append('notes', statusField('notes').value);

        if (statusId) {
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
            showFormErrors(statusForm, payload.errors ?? {});
            Swal.fire({ icon: 'error', title: 'Validacion', text: payload.message ?? 'Revisa los datos ingresados.' });

            return;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo guardar el estado.');
        }

        statusModal.hide();
        await loadGridData();
        toast.fire({ icon: 'success', title: payload.message ?? 'Estado guardado.' });
    };

    gridTarget.addEventListener('click', (event) => {
        const action = event.target.closest('[data-availability-cell-action]');

        if (!action) {
            return;
        }

        openStatusModal(action.closest('.availability-cell'));
    });

    previousButton?.addEventListener('click', () => loadGridData(gridData.previous_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-availability-today]')?.addEventListener('click', () => loadGridData(gridData.current_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
    root.querySelector('[data-availability-next]')?.addEventListener('click', () => loadGridData(gridData.next_period).catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message })));
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
    statusForm.addEventListener('submit', (event) => {
        event.preventDefault();
        saveStatus().catch((error) => Swal.fire({ icon: 'error', title: 'Error', text: error.message }));
    });

    renderGrid();
    root.dataset.availabilityInitialized = '1';
}

function initCountryAutocompleteSelect(select) {
    if (!select || select.tomselect) {
        return;
    }

    const url = select.dataset.countriesUrl || select.closest('form')?.dataset.countriesUrl;

    if (!url) {
        return;
    }

    new TomSelect(select, {
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        maxItems: 1,
        create: false,
        dropdownParent: 'body',
        placeholder: select.dataset.placeholder ?? 'Buscar pais',
        plugins: ['clear_button'],
        load(query, callback) {
            const params = new URLSearchParams({ q: query ?? '' });
            fetch(`${url}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((payload) => callback(payload.results ?? []))
                .catch(() => callback());
        },
        render: {
            no_results() {
                return '<div class="no-results">Sin resultados</div>';
            },
        },
    });
}

function setCountryAutocompleteValue(select, country) {
    if (!select || !country?.value) {
        return;
    }

    initCountryAutocompleteSelect(select);

    if (select.tomselect) {
        select.tomselect.addOption(country);
        select.tomselect.setValue(String(country.value), true);

        return;
    }

    select.innerHTML = `<option value="${escapeHtml(country.value)}" selected>${escapeHtml(country.text)}</option>`;
    select.value = String(country.value);
}

function localTodayString() {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 10);
}

function bindStayGuestLookup(row) {
    if (!row || row.dataset.stayGuestLookupInitialized === '1') {
        return;
    }

    const form = row.closest('form');
    const lookupUrl = form?.dataset.guestLookupUrl;
    const idField = row.querySelector('[data-stay-guest-id]');
    const documentType = row.querySelector('[data-stay-guest-document-type]');
    const documentNumber = row.querySelector('[data-stay-guest-document-number]');
    const firstName = row.querySelector('[data-stay-guest-first-name]');
    const lastName = row.querySelector('[data-stay-guest-last-name]');
    const birthDate = row.querySelector('[data-stay-guest-birth-date]');
    const country = row.querySelector('[data-stay-guest-country]');
    const message = row.querySelector('[data-stay-guest-lookup-message]');
    let timeout;
    let controller;

    const showMessage = (text = '', tone = 'success') => {
        if (!message) {
            return;
        }

        message.textContent = text;
        message.className = `form-hint ${tone === 'success' ? 'text-success' : 'text-body-secondary'} ${text ? '' : 'd-none'}`;
    };

    const fillGuest = (guest) => {
        if (idField) idField.value = guest.id ?? '';
        if (documentType) documentType.value = guest.document_type ?? documentType.value;
        if (documentNumber) documentNumber.value = guest.document_number ?? documentNumber.value;
        if (firstName) firstName.value = guest.first_name ?? '';
        if (lastName) lastName.value = guest.last_name ?? '';
        if (birthDate) birthDate.value = guest.birth_date ?? birthDate.value;
        setCountryAutocompleteValue(country, guest.birth_country);
        showMessage('Datos encontrados', 'success');
    };

    const clearFoundGuest = () => {
        if (idField) {
            idField.value = '';
        }

        showMessage('');
    };

    const lookup = async () => {
        const number = documentNumber?.value?.trim() ?? '';

        if (!lookupUrl || number.length < 3) {
            return;
        }

        controller?.abort();
        controller = new AbortController();

        const params = new URLSearchParams({ document_number: number });
        const response = await fetch(`${lookupUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (payload.found && payload.guest) {
            fillGuest(payload.guest);
        }
    };

    const scheduleLookup = () => {
        clearFoundGuest();
        window.clearTimeout(timeout);
        timeout = window.setTimeout(() => {
            lookup().catch((error) => {
                if (error.name !== 'AbortError') {
                    showMessage('');
                }
            });
        }, 350);
    };

    documentNumber?.addEventListener('input', scheduleLookup);
    documentNumber?.addEventListener('blur', scheduleLookup);
    documentType?.addEventListener('change', scheduleLookup);
    row.dataset.stayGuestLookupInitialized = '1';
}

function initCheckInForm(scope = document) {
    const form = scope.querySelector('[data-check-in-form]');

    if (!form || form.dataset.checkInInitialized === '1') {
        return;
    }

    let resources = JSON.parse(form.dataset.resources ?? '[]');
    const staysList = form.querySelector('[data-check-in-stays]');
    const stayTemplate = form.querySelector('[data-check-in-stay-template]');
    const addStayButton = form.querySelector('[data-check-in-add-stay]');
    const checkInDate = form.querySelector('[data-check-in-date]');
    const checkOutDate = form.querySelector('[data-check-out-date]');
    const totalPeople = form.querySelector('[data-check-in-total-people]');
    const peopleSum = form.querySelector('[data-check-in-people-sum]');
    const peopleTotal = form.querySelector('[data-check-in-people-total]');
    const reservedWarning = form.querySelector('[data-check-in-reserved-warning]');
    const countrySelect = form.querySelector('[data-country-autocomplete]');
    const documentType = form.querySelector('[data-guest-document-type]');
    const documentNumber = form.querySelector('[data-guest-document-number]');
    const firstName = form.querySelector('[data-main-guest-name]');
    const lastName = form.querySelector('[data-main-guest-last-name]');
    const birthDate = form.querySelector('[data-main-guest-birth-date]');
    const guestAge = form.querySelector('[data-main-guest-age]');
    const guestLookupMessage = form.querySelector('[data-guest-lookup-message]');
    const statusLabels = {
        available: 'Disponible',
        reserved: 'Reservado',
        closed: 'Cerrado',
        occupied: 'Ocupado',
    };

    const selectedResource = (row) => resources.find((resource) => resource.key === row.querySelector('[data-stay-resource-select]')?.value);
    const stayRows = () => [...form.querySelectorAll('[data-check-in-stay-row]')];
    const numberValue = (field) => Number(field?.value || 0);

    if (countrySelect && !countrySelect.tomselect) {
        new TomSelect(countrySelect, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxItems: 1,
            create: false,
            dropdownParent: 'body',
            placeholder: countrySelect.dataset.placeholder ?? 'Buscar pais',
            plugins: ['clear_button'],
            load(query, callback) {
                const params = new URLSearchParams({ q: query ?? '' });
                fetch(`${form.dataset.countriesUrl}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.json())
                    .then((payload) => callback(payload.results ?? []))
                    .catch(() => callback());
            },
            render: {
                no_results() {
                    return '<div class="no-results">Sin resultados</div>';
                },
            },
        });
    }

    let guestLookupTimeout;
    let guestLookupController;

    const showGuestLookupMessage = (message = '', tone = 'success') => {
        if (!guestLookupMessage) {
            return;
        }

        guestLookupMessage.textContent = message;
        guestLookupMessage.className = `form-hint ${tone === 'success' ? 'text-success' : 'text-body-secondary'} ${message ? '' : 'd-none'}`;
    };

    const setCountryValue = (country) => {
        if (!countrySelect || !country) {
            return;
        }

        if (countrySelect.tomselect) {
            countrySelect.tomselect.addOption(country);
            countrySelect.tomselect.setValue(String(country.value), true);

            return;
        }

        countrySelect.innerHTML = `<option value="${escapeHtml(country.value)}" selected>${escapeHtml(country.text)}</option>`;
        countrySelect.value = String(country.value);
    };

    const currentMainBirthCountry = () => {
        if (!countrySelect) {
            return null;
        }

        if (countrySelect.tomselect) {
            const value = countrySelect.tomselect.getValue();
            const option = countrySelect.tomselect.options[value];

            return value && option ? { value, text: option.text } : null;
        }

        const option = countrySelect.selectedOptions?.[0];

        return option?.value ? { value: option.value, text: option.textContent } : null;
    };

    const clearGuestLookupState = () => {
        if (documentNumber && documentNumber.readOnly) {
            documentNumber.readOnly = false;
            documentNumber.classList.remove('bg-body-secondary');
        }

        showGuestLookupMessage('');
    };

    const calculateAge = (value) => {
        if (!value) {
            return '';
        }

        const birth = new Date(`${value}T00:00:00`);
        const today = new Date();

        if (Number.isNaN(birth.getTime()) || birth > today) {
            return '';
        }

        let age = today.getFullYear() - birth.getFullYear();
        const birthdayThisYear = new Date(today.getFullYear(), birth.getMonth(), birth.getDate());

        if (today < birthdayThisYear) {
            age -= 1;
        }

        return age >= 0 ? String(age) : '';
    };

    const syncGuestAge = () => {
        if (guestAge) {
            guestAge.value = calculateAge(birthDate?.value ?? '');
        }
    };

    const fillGuest = (guest) => {
        if (firstName) firstName.value = guest.first_name ?? '';
        if (lastName) lastName.value = guest.last_name ?? '';
        if (birthDate) birthDate.value = guest.birth_date ?? '';
        syncGuestAge();
        setCountryValue(guest.birth_country);

        if (documentNumber) {
            documentNumber.value = guest.document_number ?? documentNumber.value;
            documentNumber.readOnly = true;
            documentNumber.classList.add('bg-body-secondary');
        }

        showGuestLookupMessage('Datos encontrados', 'success');
    };

    const lookupGuestByDocument = async () => {
        const number = documentNumber?.value?.trim() ?? '';

        if (!form.dataset.guestLookupUrl || number.length < 3 || documentNumber?.readOnly) {
            return;
        }

        guestLookupController?.abort();
        guestLookupController = new AbortController();

        const params = new URLSearchParams({ document_number: number });

        const response = await fetch(`${form.dataset.guestLookupUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: guestLookupController.signal,
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (payload.found && payload.guest) {
            fillGuest(payload.guest);
        }
    };

    const scheduleGuestLookup = () => {
        if (!documentNumber || documentNumber.readOnly) {
            return;
        }

        window.clearTimeout(guestLookupTimeout);
        guestLookupTimeout = window.setTimeout(() => {
            lookupGuestByDocument().catch((error) => {
                if (error.name !== 'AbortError') {
                    showGuestLookupMessage('');
                }
            });
        }, 350);
    };

    const optionHtml = (resource, selectedKey = '') => `
        <option
            value="${escapeHtml(resource.key)}"
            data-resource-type="${escapeHtml(resource.resource_type)}"
            data-space-id="${escapeHtml(resource.space_id)}"
            data-room-id="${escapeHtml(resource.space_room_id ?? '')}"
            data-bed-unit-id="${escapeHtml(resource.room_bed_unit_id ?? '')}"
            data-capacity="${escapeHtml(resource.capacity)}"
            data-status="${escapeHtml(resource.status)}"
            data-sale-mode="${escapeHtml(resource.sale_mode ?? '')}"
            data-full-room-key="${escapeHtml(resource.full_room_key ?? '')}"
            data-full-room-available="${resource.full_room_available ? '1' : '0'}"
            data-full-room-capacity="${escapeHtml(resource.full_room_capacity ?? '')}"
            data-full-room-status="${escapeHtml(resource.full_room_status ?? '')}"
            ${resource.disabled ? 'disabled' : ''}
            ${selectedKey === resource.key ? 'selected' : ''}
        >${escapeHtml(resource.label)} · Cap. ${escapeHtml(resource.capacity)} · ${escapeHtml(statusLabels[resource.status] ?? resource.status)}</option>
    `;

    const refreshResourceSelect = (select) => {
        const selected = select.value;
        select.innerHTML = `<option value="">Seleccionar</option>${resources.map((resource) => optionHtml(resource, selected)).join('')}`;

        if (selected && !resources.some((resource) => resource.key === selected && !resource.disabled)) {
            select.value = '';
        }
    };

    const disableNativeAutocomplete = (target = form) => {
        target.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => {
            field.setAttribute('autocomplete', 'off');
            field.setAttribute('autocorrect', 'off');
            field.setAttribute('autocapitalize', 'off');
            field.setAttribute('spellcheck', 'false');
        });
    };

    const syncRowResource = (row) => {
        const select = row.querySelector('[data-stay-resource-select]');
        const resource = selectedResource(row);
        const hint = row.querySelector('[data-stay-resource-hint]');
        const people = row.querySelector('[data-stay-people]');
        const fullRoomWrap = row.querySelector('[data-stay-full-room-wrap]');
        const fullRoomSwitch = row.querySelector('[data-stay-full-room-switch]');
        const fullRoomHint = row.querySelector('[data-stay-full-room-hint]');
        const canShowFullRoomSwitch = resource?.resource_type === 'shared_bed_unit' && resource?.sale_mode === 'flexible';
        const canSellFullRoom = canShowFullRoomSwitch && resource?.full_room_available === true;

        if (fullRoomSwitch && (!canShowFullRoomSwitch || !canSellFullRoom)) {
            fullRoomSwitch.checked = false;
        }

        const sellFullRoom = fullRoomSwitch?.checked
            && canSellFullRoom;
        const effectiveCapacity = sellFullRoom ? resource?.full_room_capacity : resource?.capacity;

        row.querySelector('[data-stay-resource-type]').value = sellFullRoom ? 'shared_room' : (resource?.resource_type ?? '');
        row.querySelector('[data-stay-space-id]').value = resource?.space_id ?? '';
        row.querySelector('[data-stay-room-id]').value = resource?.space_room_id ?? '';
        row.querySelector('[data-stay-bed-unit-id]').value = sellFullRoom ? '' : (resource?.room_bed_unit_id ?? '');

        if (fullRoomWrap && fullRoomSwitch) {
            fullRoomWrap.classList.toggle('d-none', !canShowFullRoomSwitch);
            fullRoomSwitch.disabled = !canSellFullRoom;

            if (fullRoomHint) {
                fullRoomHint.textContent = canShowFullRoomSwitch
                    ? (resource?.full_room_available ? `Capacidad habitacion completa ${resource.full_room_capacity}` : 'Habitacion completa no disponible en esas fechas.')
                    : '';
                fullRoomHint.className = `form-hint ${canShowFullRoomSwitch && !resource?.full_room_available ? 'text-warning' : ''}`;
            }
        }

        if (people && effectiveCapacity) {
            people.max = effectiveCapacity;

            if (numberValue(people) > Number(effectiveCapacity || 0)) {
                people.value = effectiveCapacity;
            }
        }

        if (hint) {
            if (!resource) {
                hint.textContent = '';
                hint.className = 'form-hint';
            } else {
                hint.textContent = `Capacidad ${effectiveCapacity} · ${statusLabels[resource.status] ?? resource.status}`;
                hint.className = `form-hint ${resource.status === 'reserved' ? 'text-warning' : ''}`;
            }
        }

        select?.classList.toggle('is-invalid', resource?.disabled === true);
    };

    const reindexRows = () => {
        stayRows().forEach((row, index) => {
            row.querySelector('[data-stay-number]').textContent = String(index + 1);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/stays\[[^\]]+]/, `stays[${index}]`);
            });
            reindexStayGuests(row);
        });
    };

    const guestRows = (row) => [...row.querySelectorAll('[data-stay-guest-row]')];

    const reindexStayGuests = (row) => {
        const stayIndex = stayRows().indexOf(row);

        guestRows(row).forEach((guestRow, guestIndex) => {
            guestRow.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name
                    .replace(/stays\[[^\]]+]/, `stays[${stayIndex}]`)
                    .replace(/\[guests]\[[^\]]+]/, `[guests][${guestIndex}]`);
            });
        });
    };

    const syncStayGuestCount = (row) => {
        const countTarget = row.querySelector('[data-stay-guest-count]');
        const people = Math.max(0, numberValue(row.querySelector('[data-stay-people]')));
        const maxAdditional = Math.max(people - 1, 0);
        const count = guestRows(row).length;

        if (countTarget) {
            countTarget.textContent = `${count}/${maxAdditional} acompanante(s).`;
            countTarget.classList.toggle('text-danger', count > maxAdditional);
        }
    };

    const addStayGuestRow = (row) => {
        const template = row.querySelector('[data-stay-guest-template]');
        const list = row.querySelector('[data-stay-guests]');

        if (!template || !list) {
            return;
        }

        const index = guestRows(row).length;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__GUEST_INDEX__', String(index)).trim();
        list.append(wrapper.firstElementChild);
        const guestRow = guestRows(row).at(-1);
        bindStayGuestRow(row, guestRow);
        setStayGuestDefaults(guestRow);
        reindexStayGuests(row);
        syncStayGuestCount(row);
    };

    const setStayGuestDefaults = (guestRow) => {
        const documentTypeField = guestRow.querySelector('[name$="[document_type]"]');
        const birthDateField = guestRow.querySelector('[name$="[birth_date]"]');
        const countryField = guestRow.querySelector('[data-stay-guest-country]');

        if (documentTypeField && !documentTypeField.value) {
            documentTypeField.value = 'passport';
        }

        if (birthDateField && !birthDateField.value) {
            birthDateField.value = localTodayString();
        }

        setCountryAutocompleteValue(countryField, currentMainBirthCountry());
    };

    function bindStayGuestRow(row, guestRow) {
        initCountryAutocompleteSelect(guestRow.querySelector('[data-stay-guest-country]'));
        bindStayGuestLookup(guestRow);
        guestRow.querySelector('[data-stay-remove-guest]')?.addEventListener('click', () => {
            guestRow.remove();
            reindexStayGuests(row);
            syncStayGuestCount(row);
        });
    }

    const updateMode = () => {
        const type = form.querySelector('[name="check_in_type"]:checked')?.value ?? 'individual';
        const rows = stayRows();

        if (type === 'individual' && rows.length > 1) {
            rows.slice(1).forEach((row) => row.remove());
            reindexRows();
        }

        addStayButton?.classList.toggle('d-none', type !== 'multiple');
        stayRows().forEach((row) => {
            row.querySelector('[data-check-in-remove-stay]')?.classList.toggle('d-none', type === 'individual' || stayRows().length === 1);
        });
        updateTotals();
    };

    const updateTotals = () => {
        const sum = stayRows().reduce((carry, row) => carry + Math.max(0, numberValue(row.querySelector('[data-stay-people]'))), 0);
        const total = Math.max(0, numberValue(totalPeople));
        const hasReserved = stayRows().some((row) => selectedResource(row)?.status === 'reserved');

        if (peopleSum) peopleSum.textContent = String(sum);
        if (peopleTotal) peopleTotal.textContent = String(total);
        reservedWarning?.classList.toggle('d-none', !hasReserved);
        peopleSum?.classList.toggle('text-danger', total > 0 && sum > total);
    };

    const syncPrices = (row, source) => {
        const rate = numberValue(row.querySelector('[data-stay-exchange-rate]'));
        const bob = row.querySelector('[data-stay-price-bob]');
        const usd = row.querySelector('[data-stay-price-usd]');

        if (!rate || rate <= 0) {
            return;
        }

        if (source === 'bob' && bob.value !== '') {
            usd.value = (numberValue(bob) / rate).toFixed(2);
        }

        if (source === 'usd' && usd.value !== '') {
            bob.value = (numberValue(usd) * rate).toFixed(2);
        }

        if (source === 'rate') {
            syncPrices(row, 'bob');
        }
    };

    const validateFrontend = () => {
        clearFormErrors(form);
        const errors = [];
        const inDate = checkInDate.value ? new Date(`${checkInDate.value}T00:00:00`) : null;
        const outDate = checkOutDate.value ? new Date(`${checkOutDate.value}T00:00:00`) : null;
        const total = Math.max(0, numberValue(totalPeople));
        const sum = stayRows().reduce((carry, row) => carry + Math.max(0, numberValue(row.querySelector('[data-stay-people]'))), 0);

        if (!inDate || !outDate || outDate <= inDate) {
            errors.push('La fecha de salida debe ser posterior a la fecha de ingreso.');
            checkOutDate.classList.add('is-invalid');
        }

        if (sum > total) {
            errors.push('La suma de personas por estancia no puede superar la cantidad total.');
            totalPeople.classList.add('is-invalid');
        }

        stayRows().forEach((row) => {
            const resource = selectedResource(row);
            const select = row.querySelector('[data-stay-resource-select]');
            const people = row.querySelector('[data-stay-people]');

            if (!resource) {
                errors.push('Selecciona recurso para cada estancia.');
                select.classList.add('is-invalid');
            } else if (resource.disabled || ['closed', 'occupied'].includes(resource.status)) {
                errors.push('No se puede seleccionar un recurso cerrado u ocupado.');
                select.classList.add('is-invalid');
            }

            if (resource) {
                const fullRoomSwitch = row.querySelector('[data-stay-full-room-switch]');
                const sellFullRoom = fullRoomSwitch?.checked
                    && resource.resource_type === 'shared_bed_unit'
                    && resource.sale_mode === 'flexible';
                const capacity = sellFullRoom ? Number(resource.full_room_capacity || 0) : Number(resource.capacity || 0);

                if (numberValue(people) > capacity) {
                    errors.push(`La estancia supera la capacidad de ${resource.label}.`);
                    people.classList.add('is-invalid');
                }
            }

            const fullRoomSwitch = row.querySelector('[data-stay-full-room-switch]');

            if (resource?.resource_type === 'shared_bed_unit' && fullRoomSwitch?.checked && resource.full_room_available !== true) {
                errors.push('La habitacion completa no esta libre en esas fechas.');
                select.classList.add('is-invalid');
            }

            const maxAdditionalGuests = Math.max(numberValue(people) - 1, 0);

            if (guestRows(row).length > maxAdditionalGuests) {
                errors.push('La cantidad de acompanantes no puede superar las personas de la estancia. El titular ya esta incluido.');
                row.querySelector('[data-stay-guest-count]')?.classList.add('text-danger');
            }
        });

        if (errors.length > 0) {
            Swal.fire({ icon: 'error', title: 'Validacion', html: [...new Set(errors)].join('<br>') });

            return false;
        }

        return true;
    };

    const addStayRow = () => {
        const index = stayRows().length;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = stayTemplate.innerHTML
            .replaceAll('__INDEX__', String(index))
            .replaceAll('__NUMBER__', String(index + 1))
            .trim();
        staysList.append(wrapper.firstElementChild);
        bindRow(stayRows().at(-1));
        disableNativeAutocomplete(stayRows().at(-1));
        updateMode();
    };

    const loadResources = async () => {
        if (!checkInDate.value || !checkOutDate.value) {
            return;
        }

        const params = new URLSearchParams({
            check_in_date: checkInDate.value,
            check_out_date: checkOutDate.value,
        });
        const response = await fetch(`${form.dataset.availableResourcesUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        resources = payload.resources ?? resources;
        stayRows().forEach((row) => {
            refreshResourceSelect(row.querySelector('[data-stay-resource-select]'));
            syncRowResource(row);
        });
        updateTotals();
    };

    function bindRow(row) {
        const select = row.querySelector('[data-stay-resource-select]');
        refreshResourceSelect(select);
        syncRowResource(row);

        select.addEventListener('change', () => {
            syncRowResource(row);
            updateTotals();
        });
        row.querySelector('[data-stay-people]')?.addEventListener('input', updateTotals);
        row.querySelector('[data-stay-people]')?.addEventListener('input', () => syncStayGuestCount(row));
        row.querySelector('[data-stay-price-bob]')?.addEventListener('input', () => syncPrices(row, 'bob'));
        row.querySelector('[data-stay-price-usd]')?.addEventListener('input', () => syncPrices(row, 'usd'));
        row.querySelector('[data-stay-exchange-rate]')?.addEventListener('input', () => syncPrices(row, 'rate'));
        row.querySelector('[data-stay-full-room-switch]')?.addEventListener('change', () => {
            syncRowResource(row);
            updateTotals();
        });
        row.querySelector('[data-check-in-remove-stay]')?.addEventListener('click', () => {
            row.remove();
            reindexRows();
            updateMode();
        });
        row.querySelector('[data-stay-add-guest]')?.addEventListener('click', () => addStayGuestRow(row));
        guestRows(row).forEach((guestRow) => bindStayGuestRow(row, guestRow));
        syncStayGuestCount(row);
    }

    disableNativeAutocomplete();
    stayRows().forEach(bindRow);
    form.querySelectorAll('[name="check_in_type"]').forEach((field) => field.addEventListener('change', updateMode));
    addStayButton?.addEventListener('click', addStayRow);
    totalPeople?.addEventListener('input', updateTotals);
    documentNumber?.addEventListener('input', () => {
        clearGuestLookupState();
        scheduleGuestLookup();
    });
    documentNumber?.addEventListener('blur', scheduleGuestLookup);
    documentType?.addEventListener('change', () => {
        clearGuestLookupState();
        scheduleGuestLookup();
    });
    birthDate?.addEventListener('input', syncGuestAge);
    birthDate?.addEventListener('change', syncGuestAge);
    checkInDate?.addEventListener('change', loadResources);
    checkOutDate?.addEventListener('change', loadResources);
    form.addEventListener('submit', (event) => {
        if (!validateFrontend()) {
            event.preventDefault();
        }
    });
    updateMode();
    syncGuestAge();
    form.dataset.checkInInitialized = '1';
}

function initCheckInPriceReferences(scope = document) {
    scope.querySelectorAll('[data-check-in-price-reference-row]').forEach((row) => {
        if (row.dataset.priceReferenceInitialized === '1') {
            return;
        }

        const bob = row.querySelector('[data-reference-price-bob]');
        const usd = row.querySelector('[data-reference-price-usd]');
        const exchangeRate = row.querySelector('[data-reference-exchange-rate]');
        const numberValue = (field) => Number(field?.value || 0);
        const syncReference = () => {
            const rate = numberValue(exchangeRate);

            if (!bob || !usd || !rate || rate <= 0 || bob.value === '') {
                return;
            }

            usd.value = (numberValue(bob) / rate).toFixed(2);
        };
        const syncBob = () => {
            const rate = numberValue(exchangeRate);

            if (!bob || !usd || !rate || rate <= 0 || usd.value === '') {
                return;
            }

            bob.value = (numberValue(usd) * rate).toFixed(2);
        };

        bob?.addEventListener('input', syncReference);
        usd?.addEventListener('input', syncBob);
        row.dataset.priceReferenceInitialized = '1';
    });
}

function initStayGuestEditors(scope = document) {
    scope.querySelectorAll('[data-check-in-price-reference-row] [data-stay-guests-section]').forEach((section) => {
        if (section.dataset.stayGuestsInitialized === '1') {
            return;
        }

        const form = section.closest('form');
        const list = section.querySelector('[data-stay-guests]');
        const template = section.querySelector('[data-stay-guest-template]');
        const addButton = section.querySelector('[data-stay-add-guest]');
        const people = form?.querySelector('[name="people_count"]');
        const countTarget = section.querySelector('[data-stay-guest-count]');
        const tableWrap = section.querySelector('[data-stay-guests-table-wrap]');
        const tableBody = section.querySelector('[data-stay-guests-table]');
        const emptyRow = section.querySelector('[data-stay-guests-empty]');
        const modalElement = section.querySelector('[data-stay-guest-modal]');
        const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
        const modalTitle = section.querySelector('[data-stay-guest-modal-title]');
        const modalSave = section.querySelector('[data-stay-guest-modal-save]');
        const modalRow = section.querySelector('[data-stay-guest-modal] [data-stay-guest-row]');
        const rows = () => [...section.querySelectorAll('[data-stay-guest-row]')];
        const storedRows = () => [...list?.querySelectorAll('[data-stay-guest-row]') ?? []];
        const numberValue = (field) => Number(field?.value || 0);
        const field = (row, selector) => row.querySelector(selector);
        const fieldValue = (row, selector) => field(row, selector)?.value ?? '';
        const selectedCountry = (row) => {
            const select = field(row, '[data-stay-guest-country]');

            if (!select) {
                return { value: '', text: '' };
            }

            if (select.tomselect) {
                const value = select.tomselect.getValue();
                const option = select.tomselect.options[value];

                return { value: value || '', text: option?.text ?? '' };
            }

            const option = select.selectedOptions?.[0];

            return { value: select.value || '', text: option?.textContent ?? '' };
        };
        const rowData = (row) => ({
            id: fieldValue(row, '[data-stay-guest-id]'),
            document_type: fieldValue(row, '[data-stay-guest-document-type]') || 'passport',
            document_number: fieldValue(row, '[data-stay-guest-document-number]'),
            first_name: fieldValue(row, '[data-stay-guest-first-name]'),
            last_name: fieldValue(row, '[data-stay-guest-last-name]'),
            birth_date: fieldValue(row, '[data-stay-guest-birth-date]') || localTodayString(),
            birth_country_id: selectedCountry(row).value,
            birth_country_text: selectedCountry(row).text,
        });
        const setSelectValue = (select, value, text = '') => {
            if (!select) {
                return;
            }

            if (select.tomselect) {
                if (value) {
                    select.tomselect.addOption({ value, text: text || value });
                    select.tomselect.setValue(String(value), true);
                } else {
                    select.tomselect.clear(true);
                }

                return;
            }

            select.innerHTML = value ? `<option value="${escapeHtml(value)}" selected>${escapeHtml(text || value)}</option>` : '';
            select.value = value ? String(value) : '';
        };
        const setRowData = (row, data) => {
            const set = (selector, value) => {
                const input = field(row, selector);

                if (input) {
                    input.value = value ?? '';
                }
            };

            set('[data-stay-guest-id]', data.id ?? '');
            set('[data-stay-guest-document-type]', data.document_type || 'passport');
            set('[data-stay-guest-document-number]', data.document_number ?? '');
            set('[data-stay-guest-first-name]', data.first_name ?? '');
            set('[data-stay-guest-last-name]', data.last_name ?? '');
            set('[data-stay-guest-birth-date]', data.birth_date || localTodayString());
            setSelectValue(field(row, '[data-stay-guest-country]'), data.birth_country_id ?? '', data.birth_country_text ?? '');
        };
        const reindex = () => {
            storedRows().forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/guests\[[^\]]+]/, `guests[${index}]`);
                });
            });
        };
        const syncCount = () => {
            const maxAdditional = Math.max(numberValue(people) - 1, 0);
            const count = storedRows().length;

            if (countTarget) {
                countTarget.textContent = `${count}/${maxAdditional} acompanante(s).`;
                countTarget.classList.toggle('text-danger', count > maxAdditional);
            }
        };
        const renderTable = () => {
            if (!tableBody) {
                return;
            }

            tableBody.querySelectorAll('[data-stay-guest-display-row]').forEach((row) => row.remove());
            const dataRows = storedRows();
            tableWrap?.classList.toggle('d-none', dataRows.length === 0);
            emptyRow?.classList.toggle('d-none', dataRows.length > 0);

            dataRows.forEach((row, index) => {
                const data = rowData(row);
                const tr = document.createElement('tr');
                tr.dataset.stayGuestDisplayRow = String(index);
                tr.innerHTML = `
                    <td>${escapeHtml(`${data.first_name} ${data.last_name}`.trim() || '-')}</td>
                    <td>${escapeHtml((data.document_type || '').toUpperCase())} ${escapeHtml(data.document_number || '-')}</td>
                    <td>${escapeHtml(data.birth_date || '-')}</td>
                    <td>${escapeHtml(data.birth_country_text || '-')}</td>
                    <td class="text-end">
                        <div class="btn-list justify-content-end flex-nowrap">
                            <button class="btn btn-outline-primary btn-sm" type="button" data-stay-guest-edit="${index}">
                                <i class="ti ti-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-stay-guest-delete="${index}">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.append(tr);
            });
        };
        const clearModal = () => {
            if (!modalRow) {
                return;
            }

            setRowData(modalRow, {
                id: '',
                document_type: 'passport',
                document_number: '',
                first_name: '',
                last_name: '',
                birth_date: localTodayString(),
                birth_country_id: '',
                birth_country_text: '',
            });
            field(modalRow, '[data-stay-guest-edit-index]').value = '';
        };
        const openModal = (index = null) => {
            if (!modal || !modalRow) {
                return;
            }

            clearModal();
            modalTitle.textContent = index === null ? 'Nuevo acompanante' : 'Editar acompanante';

            if (index !== null) {
                const stored = storedRows()[index];

                if (stored) {
                    setRowData(modalRow, rowData(stored));
                    field(modalRow, '[data-stay-guest-edit-index]').value = String(index);
                }
            }

            modal.show();
        };
        const saveModal = () => {
            if (!list || !template || !modalRow) {
                return;
            }

            const data = rowData(modalRow);
            const editIndex = fieldValue(modalRow, '[data-stay-guest-edit-index]');
            const requiredMissing = !data.document_type || !data.first_name || !data.last_name || !data.birth_date || !data.birth_country_id;

            if (requiredMissing) {
                Swal.fire({ icon: 'error', title: 'Validacion', text: 'Completa los datos requeridos del acompanante.' });

                return;
            }

            let target = editIndex !== '' ? storedRows()[Number(editIndex)] : null;

            if (!target) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = template.innerHTML.replaceAll('__GUEST_INDEX__', String(storedRows().length)).trim();
                list.append(wrapper.firstElementChild);
                target = storedRows().at(-1);
            }

            setRowData(target, data);
            reindex();
            renderTable();
            syncCount();
            modal.hide();
        };

        initCountryAutocompleteSelect(modalRow?.querySelector('[data-stay-guest-country]'));
        bindStayGuestLookup(modalRow);

        addButton?.addEventListener('click', () => openModal());
        modalSave?.addEventListener('click', saveModal);
        tableBody?.addEventListener('click', (event) => {
            const edit = event.target.closest('[data-stay-guest-edit]');
            const remove = event.target.closest('[data-stay-guest-delete]');

            if (edit) {
                openModal(Number(edit.dataset.stayGuestEdit));

                return;
            }

            if (remove) {
                const row = storedRows()[Number(remove.dataset.stayGuestDelete)];
                row.remove();
                reindex();
                renderTable();
                syncCount();
                return;
            }
        });
        people?.addEventListener('input', syncCount);
        form?.addEventListener('submit', (event) => {
            const maxAdditional = Math.max(numberValue(people) - 1, 0);

            if (storedRows().length > maxAdditional) {
                event.preventDefault();
                countTarget?.classList.add('text-danger');
                Swal.fire({
                    icon: 'error',
                    title: 'Validacion',
                    text: 'La cantidad de acompanantes no puede superar las personas de la estancia. El titular ya esta incluido.',
                });
            }
        });

        reindex();
        renderTable();
        syncCount();
        section.dataset.stayGuestsInitialized = '1';
    });
}

function updateExtraChargeForm(form) {
    const category = form.querySelector('[data-extra-charge-category]');
    const unit = form.querySelector('[data-extra-charge-unit]');
    const quantity = form.querySelector('[data-extra-charge-quantity]');
    const total = form.querySelector('[data-extra-charge-total]');

    if (!category || !unit || !quantity || !total) {
        return;
    }

    if (document.activeElement !== unit && unit.dataset.manualValue !== '1') {
        unit.value = Number(category.selectedOptions[0]?.dataset.unitPrice || 0).toFixed(2);
    }

    const amount = Math.max(0, Number(unit.value || 0)) * Math.max(0, Number(quantity.value || 0));
    total.textContent = amount.toFixed(2);
}

function initExtraChargeForms(scope = document) {
    scope.querySelectorAll('[data-extra-charge-form]').forEach((form) => {
        if (form.dataset.extraChargeInitialized === '1') {
            return;
        }

        const unit = form.querySelector('[data-extra-charge-unit]');
        const category = form.querySelector('[data-extra-charge-category]');

        unit?.addEventListener('input', () => {
            unit.dataset.manualValue = '1';
            updateExtraChargeForm(form);
        });

        form.addEventListener('input', () => updateExtraChargeForm(form));
        category?.addEventListener('change', () => {
            if (unit) {
                unit.dataset.manualValue = '0';
            }
            updateExtraChargeForm(form);
        });

        updateExtraChargeForm(form);
        form.dataset.extraChargeInitialized = '1';
    });
}

function initStayPaymentForms(scope = document) {
    scope.querySelectorAll('[data-stay-payment-form]').forEach((form) => {
        if (form.dataset.stayPaymentInitialized === '1') {
            return;
        }

        const scopeSelect = form.querySelector('[name="scope"]');
        const amount = form.querySelector('[data-stay-payment-amount]');
        const balanceLabel = form.querySelector('[data-stay-payment-balance-label]');
        const checkoutButton = form.querySelector('[data-stay-payment-checkout-button]');
        const canCheckOutToday = form.dataset.canCheckOutToday === '1';
        const canSubmitPayment = form.dataset.canSubmitPayment === '1';
        const currency = balanceLabel?.textContent.trim().split(' ').pop() || 'BOB';

        const selectedBalance = () => Number(scopeSelect?.selectedOptions[0]?.dataset.balance || 0);

        const sync = (resetAmount = false) => {
            const balance = selectedBalance();

            if (amount) {
                amount.max = balance.toFixed(2);

                if (resetAmount) {
                    amount.value = balance.toFixed(2);
                }
            }

            if (balanceLabel) {
                balanceLabel.textContent = `${balance.toFixed(2)} ${currency}`;
            }

            if (checkoutButton) {
                const amountValue = Number(amount?.value || 0);
                checkoutButton.disabled = !canSubmitPayment || !canCheckOutToday || balance <= 0 || amountValue < balance;
            }
        };

        scopeSelect?.addEventListener('change', () => sync(true));
        amount?.addEventListener('input', () => sync());
        sync();

        form.dataset.stayPaymentInitialized = '1';
    });
}

function initRoomChangeForms(scope = document) {
    scope.querySelectorAll('[data-room-change-form]').forEach((form) => {
        if (form.dataset.roomChangeInitialized === '1') {
            return;
        }

        const select = form.querySelector('[data-room-change-resource]');
        const hint = form.querySelector('[data-room-change-hint]');
        const resourceType = form.querySelector('[data-room-change-resource-type]');
        const spaceId = form.querySelector('[data-room-change-space-id]');
        const roomId = form.querySelector('[data-room-change-room-id]');
        const bedUnitId = form.querySelector('[data-room-change-bed-unit-id]');

        const sync = () => {
            const option = select?.selectedOptions[0];
            const hasResource = Boolean(option?.value);

            if (resourceType) resourceType.value = hasResource ? option.dataset.resourceType || '' : '';
            if (spaceId) spaceId.value = hasResource ? option.dataset.spaceId || '' : '';
            if (roomId) roomId.value = hasResource ? option.dataset.roomId || '' : '';
            if (bedUnitId) bedUnitId.value = hasResource ? option.dataset.bedUnitId || '' : '';

            if (hint) {
                hint.textContent = hasResource ? `Capacidad ${option.dataset.capacity || '-'}` : '';
            }
        };

        select?.addEventListener('change', sync);
        sync();
        form.dataset.roomChangeInitialized = '1';
    });
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
initPublicCompanyMaps();
initSharedRoomSort();
initPhotoUploadPreviews();
initPackageIconSelectors();
initPackageServiceCarts();
initOccupancyWeekGrid();
initAvailabilityGrid();
initCheckInForm();
initCheckInPriceReferences();
initStayGuestEditors();
initExtraChargeForms();
initStayPaymentForms();
initRoomChangeForms();

document.addEventListener('click', (event) => {
    const modalTrigger = event.target.closest('[data-modal-url]');
    const roomServicesCopyAll = event.target.closest('[data-room-services-copy-all]');
    const sharedRoomEditToggle = event.target.closest('[data-shared-room-edit-toggle]');
    const reservationChannelEditToggle = event.target.closest('[data-reservation-channel-edit-toggle]');
    const extraChargeCategoryEditToggle = event.target.closest('[data-extra-charge-category-edit-toggle]');
    const packageIconOption = event.target.closest('[data-package-icon-option]');
    const packageServiceAdd = event.target.closest('[data-package-service-add]');
    const packageServiceRemove = event.target.closest('[data-package-service-remove]');

    if (modalTrigger) {
        event.preventDefault();
        openAjaxModal(modalTrigger);

        return;
    }

    if (sharedRoomEditToggle) {
        event.preventDefault();
        const roomId = sharedRoomEditToggle.dataset.sharedRoomEditToggle;
        const panel = document.querySelector(`[data-shared-room-edit-panel="${roomId}"]`);

        if (panel) {
            panel.classList.toggle('d-none');
        }

        return;
    }

    if (reservationChannelEditToggle) {
        event.preventDefault();
        const channelId = reservationChannelEditToggle.dataset.reservationChannelEditToggle;
        const panel = document.querySelector(`[data-reservation-channel-edit-panel="${channelId}"]`);

        if (panel) {
            panel.classList.toggle('d-none');
        }

        return;
    }

    if (extraChargeCategoryEditToggle) {
        event.preventDefault();
        const categoryId = extraChargeCategoryEditToggle.dataset.extraChargeCategoryEditToggle;
        const panel = document.querySelector(`[data-extra-charge-category-edit-panel="${categoryId}"]`);

        if (panel) {
            panel.classList.toggle('d-none');
        }

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

    if (packageIconOption) {
        event.preventDefault();
        const input = document.querySelector('[data-package-icon-input]');

        if (!input) {
            return;
        }

        input.value = packageIconOption.dataset.packageIconOption ?? '';
        updatePackageIconPreview(input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (packageServiceAdd) {
        event.preventDefault();
        addPackageServiceToCart(packageServiceAdd);

        return;
    }

    if (packageServiceRemove) {
        event.preventDefault();
        removePackageServiceFromCart(packageServiceRemove);
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
    const confirmForm = event.target.closest('[data-confirm-submit]');
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

    if (confirmForm) {
        event.preventDefault();
        confirmSubmit(confirmForm);

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
