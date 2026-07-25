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
window.bootstrap = bootstrap;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const ajaxModalElement = document.getElementById('ajaxModal');
const ajaxModal = ajaxModalElement ? new bootstrap.Modal(ajaxModalElement) : null;
const ajaxModalTitle = document.getElementById('ajaxModalTitle');
const ajaxModalBody = ajaxModalElement?.querySelector('[data-modal-body]');
const digitalPersonaAuthEndpoint = document.querySelector('meta[name="digitalpersona-auth-endpoint"]')?.getAttribute('content') ?? '';
let digitalPersonaModules;
let fingerprintReader;

const FingerprintQuality = {
    Good: 0,
    NoImage: 1,
    TooLight: 2,
    TooDark: 3,
    TooNoisy: 4,
    LowContrast: 5,
    NotEnoughFeatures: 6,
    NotCentered: 7,
    NotAFinger: 8,
    TooHigh: 9,
    TooLow: 10,
    TooLeft: 11,
    TooRight: 12,
    TooFast: 14,
    TooSlow: 17,
    PressureTooHard: 19,
    PressureTooLight: 20,
    WetFinger: 21,
    FakeFinger: 22,
    TooSmall: 23,
    RotatedTooMuch: 24,
};

function resolveDigitalPersonaDevices(module) {
    const candidates = [
        module,
        module?.default,
        module?.devices,
        module?.default?.devices,
        window.dp?.devices,
    ];

    return candidates.find((candidate) => typeof candidate?.FingerprintReader === 'function') ?? null;
}

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
});

function showInitialAlerts() {
    const successElement = document.querySelector('[data-swal-success]');
    const success = successElement?.dataset.swalSuccess;
    const error = document.querySelector('[data-swal-error]')?.dataset.swalError;
    const reportUrl = successElement?.dataset.reportUrl;

    if (success) {
        toast.fire({ icon: 'success', title: success });
    }

    if (reportUrl) {
        window.open(reportUrl, '_blank', 'noopener');
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
            initPunishmentPlayerFilters(ajaxModalBody);
            initRegistrationCategorySelects(ajaxModalBody);
            initTournamentCategorySelects(ajaxModalBody);
            initTournamentNamePreviews(ajaxModalBody);
            initTournamentSteppers(ajaxModalBody);
            initFixtureSteppers(ajaxModalBody);
            initMatchdayDateSelectors(ajaxModalBody);
            initMatchdayFixtureFilters(ajaxModalBody);
            initMatchdayFiscalFilters(ajaxModalBody);
            initTeamNameMatches(ajaxModalBody);
            initAffiliatePlayerLookup(ajaxModalBody);
            initHabilitationPlayerSearch(ajaxModalBody);
            initMatchReportPlayerSearch(ajaxModalBody);
            initPlayerPhotoForms(ajaxModalBody);
            initLocalLocationAutocomplete(ajaxModalBody);
            syncPointSaleWarehouse(ajaxModalBody);
            initDefragmentForms(ajaxModalBody);
            initTransferForms(ajaxModalBody);
            initStockAdjustmentForms(ajaxModalBody);
            initPermissionManagers(ajaxModalBody);
            initMeetingAttendance(ajaxModalBody);
            initFingerprintForms(ajaxModalBody);
            initPlayerBiometricRegistration(ajaxModalBody);
        })
        .catch((error) => {
            ajaxModal.hide();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        });
}

function bytesToReadable(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 KB';
    }

    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function ensurePhotoPreviewImage(form, src) {
    const wrapper = form.closest('[data-player-photo-panel]') ?? form.closest('.col-md-4') ?? form.parentElement;
    let preview = wrapper?.querySelector('[data-player-photo-preview]');

    if (preview) {
        preview.src = src;

        return preview;
    }

    const placeholder = wrapper?.querySelector('[data-player-photo-placeholder]');

    if (!placeholder) {
        return null;
    }

    preview = document.createElement('img');
    preview.className = 'object-fit-cover w-100 h-100';
    preview.alt = 'Foto del jugador';
    preview.dataset.playerPhotoPreview = '';
    preview.src = src;
    placeholder.replaceWith(preview);

    return preview;
}

function updatePhotoPreviewAfterUpload(form, data) {
    const src = data.photo_data_uri ?? data.photo_url;

    if (!src) {
        return;
    }

    ensurePhotoPreviewImage(form, data.photo_data_uri ? src : `${src}?v=${Date.now()}`);
    form.reset();

    const meta = form.querySelector('[data-player-photo-meta]');

    if (meta) {
        meta.textContent = 'Foto optimizada guardada como WebP 600x600.';
    }
}

function fileFromCanvas(canvas, filename) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('No se pudo preparar el recorte de la foto.'));

                return;
            }

            resolve(new File([blob], filename, { type: blob.type || 'image/webp' }));
        }, 'image/webp', 0.9);
    });
}

function initPlayerPhotoForms(scope = document) {
    scope.querySelectorAll('[data-player-photo-form]').forEach((form) => {
        if (form.dataset.photoInitialized === '1') {
            return;
        }

        const input = form.querySelector('[data-player-photo-input]');
        const meta = form.querySelector('[data-player-photo-meta]');
        const cropper = form.querySelector('[data-player-photo-cropper]');
        const canvas = form.querySelector('[data-player-photo-canvas]');
        const zoom = form.querySelector('[data-player-photo-zoom]');
        const preview = form.querySelector('[data-player-photo-preview]');
        const placeholder = form.querySelector('[data-player-photo-placeholder]');
        const context = canvas?.getContext('2d');
        const image = new Image();
        const cropState = {
            imageLoaded: false,
            scale: 1,
            minScale: 1,
            offsetX: 0,
            offsetY: 0,
            dragging: false,
            pointerX: 0,
            pointerY: 0,
        };

        image.decoding = 'async';

        const constrainOffsets = () => {
            if (!canvas || !image.naturalWidth || !image.naturalHeight) {
                return;
            }

            const width = image.naturalWidth * cropState.scale;
            const height = image.naturalHeight * cropState.scale;
            const minX = Math.min(0, canvas.width - width);
            const minY = Math.min(0, canvas.height - height);

            cropState.offsetX = Math.min(0, Math.max(minX, cropState.offsetX));
            cropState.offsetY = Math.min(0, Math.max(minY, cropState.offsetY));
        };

        const drawCrop = () => {
            if (!context || !canvas || !cropState.imageLoaded) {
                return;
            }

            constrainOffsets();
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = '#f8f9fa';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(
                image,
                cropState.offsetX,
                cropState.offsetY,
                image.naturalWidth * cropState.scale,
                image.naturalHeight * cropState.scale,
            );
        };

        const pointerPosition = (event) => {
            const point = event.touches?.[0] ?? event;

            return {
                x: point.clientX,
                y: point.clientY,
            };
        };

        input?.addEventListener('change', () => {
            const file = input.files?.[0];

            if (!file) {
                if (meta) {
                    meta.textContent = 'Salida: 600x600 WebP, 40-120 KB aprox.';
                }

                return;
            }

            if (!file.type.startsWith('image/')) {
                cropState.imageLoaded = false;
                canvas?.classList.add('d-none');
                preview?.classList.remove('d-none');
                placeholder?.classList.remove('d-none');
                if (zoom) {
                    zoom.disabled = true;
                    zoom.value = '1';
                }

                return;
            }

            if (meta) {
                meta.textContent = `Original: ${bytesToReadable(file.size)} · salida: 600x600 WebP, 40-120 KB aprox.`;
            }

            const objectUrl = URL.createObjectURL(file);
            image.onload = () => {
                if (!canvas) {
                    URL.revokeObjectURL(objectUrl);

                    return;
                }

                cropState.imageLoaded = true;
                cropState.minScale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight);
                cropState.scale = cropState.minScale;
                cropState.offsetX = (canvas.width - image.naturalWidth * cropState.scale) / 2;
                cropState.offsetY = (canvas.height - image.naturalHeight * cropState.scale) / 2;

                if (zoom) {
                    zoom.disabled = false;
                    zoom.min = String(cropState.minScale);
                    zoom.max = String(cropState.minScale * 3);
                    zoom.step = String(cropState.minScale / 100);
                    zoom.value = String(cropState.scale);
                }

                preview?.classList.add('d-none');
                placeholder?.classList.add('d-none');
                canvas.classList.remove('d-none');
                drawCrop();
                URL.revokeObjectURL(objectUrl);
            };
            image.onerror = () => {
                cropState.imageLoaded = false;
                URL.revokeObjectURL(objectUrl);

                if (meta) {
                    meta.textContent = 'No se pudo leer la imagen seleccionada.';
                }
            };
            image.src = objectUrl;
        });

        zoom?.addEventListener('input', () => {
            if (!cropState.imageLoaded || !canvas) {
                return;
            }

            const previousScale = cropState.scale;
            const nextScale = Number.parseFloat(zoom.value);
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const imageCenterX = (centerX - cropState.offsetX) / previousScale;
            const imageCenterY = (centerY - cropState.offsetY) / previousScale;

            cropState.scale = nextScale;
            cropState.offsetX = centerX - imageCenterX * cropState.scale;
            cropState.offsetY = centerY - imageCenterY * cropState.scale;
            drawCrop();
        });

        cropper?.addEventListener('pointerdown', (event) => {
            if (!cropState.imageLoaded) {
                return;
            }

            const position = pointerPosition(event);
            cropState.dragging = true;
            cropState.pointerX = position.x;
            cropState.pointerY = position.y;
            cropper.setPointerCapture?.(event.pointerId);
        });

        cropper?.addEventListener('pointermove', (event) => {
            if (!cropState.dragging) {
                return;
            }

            const position = pointerPosition(event);
            const rect = cropper.getBoundingClientRect();
            const ratio = canvas.width / rect.width;

            cropState.offsetX += (position.x - cropState.pointerX) * ratio;
            cropState.offsetY += (position.y - cropState.pointerY) * ratio;
            cropState.pointerX = position.x;
            cropState.pointerY = position.y;
            drawCrop();
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
            cropper?.addEventListener(eventName, () => {
                cropState.dragging = false;
            });
        });

        form.addEventListener('submit', async (event) => {
            if (!cropState.imageLoaded || !canvas || !input?.files?.length) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            clearFormErrors(form);
            setSubmitting(form, true);

            try {
                const croppedFile = await fileFromCanvas(canvas, 'player-photo.webp');
                const formData = new FormData(form);
                formData.set('photo', croppedFile);
                await submitAjaxForm(form, formData);
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Foto', text: error.message });
                setSubmitting(form, false);
            }
        });

        form.dataset.photoInitialized = '1';
    });
}

function renderAffiliatePlayerSummary(container, payload) {
    container.innerHTML = '';

    if (!payload.found) {
        container.className = 'col-md-12';

        const alert = document.createElement('div');
        alert.className = 'alert alert-info py-2 mb-0';
        alert.textContent = 'Jugador no encontrado con ese dato. Completa los datos para crear y afiliar al jugador.';
        container.append(alert);

        return;
    }

    const player = payload.player ?? {};
    const currentTeam = payload.current_team;
    const habilitation = payload.habilitation;
    const status = payload.status ?? {};
    const actions = payload.actions ?? {};

    container.className = 'col-md-12';

    const wrapper = document.createElement('div');
    wrapper.className = 'border rounded bg-body-tertiary p-3';

    const header = document.createElement('div');
    header.className = 'd-flex flex-wrap justify-content-between align-items-start gap-2 mb-2';

    const title = document.createElement('div');
    const name = document.createElement('div');
    name.className = 'fw-semibold';
    name.textContent = player.full_name ?? 'Jugador registrado';
    const meta = document.createElement('div');
    meta.className = 'text-body-secondary small';
    meta.textContent = `CI ${player.ci ?? '-'} · ${player.internal_code ?? 'Sin codigo'}`;
    title.append(name, meta);

    const badge = document.createElement('span');
    badge.className = `badge text-bg-${status.tone ?? 'secondary'}`;
    badge.textContent = status.label ?? 'Registrado';
    header.append(title, badge);

    const grid = document.createElement('div');
    grid.className = 'row g-2 small';

    const items = [
        ['Equipo actual', currentTeam?.name ?? 'Sin equipo en esta division'],
        ['Division', currentTeam?.division ?? '-'],
        ['Afiliacion', currentTeam?.joined_at ?? '-'],
        ['Habilitacion', habilitation ? `${habilitation.team ?? '-'} · ${habilitation.enabled_at ?? '-'}` : 'Sin habilitacion activa'],
    ];

    items.forEach(([label, value]) => {
        const col = document.createElement('div');
        col.className = 'col-sm-6';
        const labelEl = document.createElement('div');
        labelEl.className = 'text-body-secondary';
        labelEl.textContent = label;
        const valueEl = document.createElement('div');
        valueEl.className = 'fw-semibold';
        valueEl.textContent = value;
        col.append(labelEl, valueEl);
        grid.append(col);
    });

    wrapper.append(header, grid);

    if (actions.is_other_team_roster) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning py-2 mt-2 mb-0';
        warning.textContent = actions.can_request_transfer
            ? 'El jugador pertenece a otro equipo en esta division. Puedes solicitar el pase para continuar.'
            : (actions.transfer_blocked_reason ?? 'El jugador ya figura afiliado a otro equipo en esta division.');
        wrapper.append(warning);
    }

    if (!habilitation && actions.age_blocked_reason) {
        const ageWarning = document.createElement('div');
        ageWarning.className = 'alert alert-danger py-2 mt-2 mb-0';
        ageWarning.textContent = actions.age_blocked_reason;
        wrapper.append(ageWarning);
    }

    if (!habilitation && actions.habilitation_blocked_reason) {
        const habilitationWarning = document.createElement('div');
        habilitationWarning.className = 'alert alert-warning py-2 mt-2 mb-0';
        habilitationWarning.textContent = actions.habilitation_blocked_reason;
        wrapper.append(habilitationWarning);
    }

    container.append(wrapper);
}

function setAffiliateExistingPlayerState(form, existing) {
    form.querySelectorAll('[data-affiliate-player-field]').forEach((field) => {
        field.readOnly = existing;
    });
}

function setAffiliateSubmitText(form, text) {
    const submit = form.querySelector('[type="submit"]');
    const spinner = submit?.querySelector('[data-submit-spinner]');

    if (!submit) {
        return;
    }

    submit.textContent = '';

    if (spinner) {
        submit.append(spinner);
    }

    submit.append(document.createTextNode(text));
}

function setAffiliateActionState(form, payload = null) {
    const submit = form.querySelector('[type="submit"]');
    const transferButton = form.querySelector('[data-transfer-request-button]');
    const actions = payload?.actions ?? {};

    if (!payload || !payload.found) {
        if (submit) {
            submit.disabled = false;
        }

        setAffiliateSubmitText(form, 'Registrar y habilitar');
        transferButton?.classList.add('d-none');
        transferButton?.setAttribute('disabled', 'disabled');

        return;
    }

    if (submit) {
        submit.disabled = actions.can_affiliate === false;
    }

    setAffiliateSubmitText(form, actions.is_selected_team_roster ? 'Habilitar jugador' : 'Afiliar y habilitar');

    if (!transferButton) {
        return;
    }

    transferButton.classList.toggle('d-none', !actions.is_other_team_roster);
    transferButton.disabled = actions.can_request_transfer !== true;
    transferButton.dataset.transferBlockedReason = actions.transfer_blocked_reason ?? '';
    transferButton.dataset.modalUrl = actions.transfer_request_url ?? '';
    transferButton.dataset.modalTitle = 'Solicitar pase';
}

async function updateAffiliateAge(form) {
    const birthDate = form.querySelector('[name="birth_date"]')?.value ?? '';
    const age = form.querySelector('[data-affiliate-age]');

    if (!age) {
        return;
    }

    if (!birthDate || !form.dataset.playerAgeUrl) {
        age.value = '-';

        return;
    }

    const url = new URL(form.dataset.playerAgeUrl, window.location.origin);
    url.searchParams.set('birth_date', birthDate);

    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        age.value = '-';

        return;
    }

    const payload = await response.json();
    age.value = payload.label ?? '-';
}

function initAffiliatePlayerLookup(scope = document) {
    scope.querySelectorAll('[data-affiliate-player-form]').forEach((form) => {
        if (form.dataset.playerLookupInitialized === '1') {
            return;
        }

        const ci = form.querySelector('[data-affiliate-ci]');
        const summary = form.querySelector('[data-affiliate-player-summary]');
        const internalCode = form.querySelector('[data-affiliate-internal-code]');
        const birthDate = form.querySelector('[name="birth_date"]');
        let timer;

        const resetExistingState = () => {
            setAffiliateExistingPlayerState(form, false);
            setAffiliateActionState(form);
            updateAffiliateAge(form);
            if (internalCode) {
                internalCode.value = 'Se generara automaticamente';
            }
        };

        const lookup = async () => {
            const value = ci?.value.trim() ?? '';

            if (value.length < 2 || !summary || !form.dataset.playerLookupUrl) {
                summary?.classList.add('d-none');
                resetExistingState();

                return;
            }

            const url = new URL(form.dataset.playerLookupUrl, window.location.origin);
            url.searchParams.set('ci', value);
            url.searchParams.set('tournament_id', form.querySelector('[name="tournament_id"]')?.value ?? '');
            url.searchParams.set('team_id', form.querySelector('[name="team_id"]')?.value ?? '');

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            summary.classList.remove('d-none');
            renderAffiliatePlayerSummary(summary, payload);
            setAffiliateActionState(form, payload);

            if (!payload.found) {
                resetExistingState();
                form.querySelector('[name="first_name"]').value = '';
                form.querySelector('[name="last_name"]').value = '';
                form.querySelector('[name="maternal_name"]').value = '';
                form.querySelector('[name="birth_date"]').value = '';
                updateAffiliateAge(form);

                return;
            }

            form.querySelector('[name="first_name"]').value = payload.player?.first_name ?? '';
            form.querySelector('[name="last_name"]').value = payload.player?.last_name ?? '';
            form.querySelector('[name="maternal_name"]').value = payload.player?.maternal_name ?? '';
            form.querySelector('[name="birth_date"]').value = payload.player?.birth_date ?? '';
            updateAffiliateAge(form);

            if (internalCode) {
                internalCode.value = payload.player?.internal_code ?? 'Sin codigo';
            }

            setAffiliateExistingPlayerState(form, true);
        };

        form.querySelector('[data-transfer-request-button]')?.addEventListener('click', (event) => {
            const reason = event.currentTarget.dataset.transferBlockedReason;
            const modalUrl = event.currentTarget.dataset.modalUrl;

            if (!reason && modalUrl) {
                openAjaxModal(event.currentTarget);

                return;
            }

            Swal.fire({
                icon: reason ? 'warning' : 'info',
                title: 'Solicitud de pase',
                text: reason || 'La opcion quedo marcada para el modulo de pases. Aun no se implementa el registro de la solicitud.',
            });
        });

        ci?.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(lookup, 350);
        });
        ci?.addEventListener('blur', lookup);
        birthDate?.addEventListener('change', () => updateAffiliateAge(form));

        if ((ci?.value.trim() ?? '').length >= 2) {
            lookup();
        }

        form.dataset.playerLookupInitialized = '1';
    });
}

function initHabilitationPlayerSearch(scope = document) {
    scope.querySelectorAll('[data-habilitation-player-search]').forEach((form) => {
        if (form.dataset.habilitationSearchInitialized === '1') {
            return;
        }

        const input = form.querySelector('[data-habilitation-search-input]');
        const summary = form.querySelector('[data-habilitation-player-summary]');
        const actionsContainer = form.querySelector('[data-habilitation-search-actions]');
        const registerButton = form.querySelector('[data-register-player-button]');
        const transferButton = form.querySelector('[data-transfer-request-button]');
        const enableButton = form.querySelector('[data-enable-found-player-button]');
        const enableForm = document.getElementById(enableButton?.getAttribute('form') ?? '');
        const enablePlayerId = enableForm?.querySelector('[data-enable-found-player-id]');
        const enableQuery = enableForm?.querySelector('[data-enable-found-player-query]');
        let timer;

        const affiliateUrl = (ciValue) => {
            const url = new URL(form.dataset.affiliateFormUrl, window.location.origin);
            url.searchParams.set('ci', ciValue);

            return url.toString();
        };

        const clearSearchRefreshUrl = () => new URL(form.action, window.location.origin).toString();

        const resetActions = () => {
            actionsContainer?.classList.add('d-none');
            registerButton?.classList.add('d-none');
            transferButton?.classList.add('d-none');
            enableButton?.classList.add('d-none');
            transferButton?.setAttribute('disabled', 'disabled');
            if (enablePlayerId) {
                enablePlayerId.value = '';
            }
            if (enableQuery) {
                enableQuery.value = '';
            }
            if (enableForm) {
                enableForm.dataset.refreshUrl = clearSearchRefreshUrl();
            }
        };

        const setRegisterButton = (ciValue, label) => {
            if (!registerButton) {
                return;
            }

            const url = affiliateUrl(ciValue);
            registerButton.textContent = label;
            registerButton.href = url;
            registerButton.dataset.modalUrl = url;
            registerButton.classList.remove('d-none');
        };

        const lookup = async () => {
            const value = input?.value.trim() ?? '';

            if (value.length < 2 || !summary || !form.dataset.playerLookupUrl) {
                summary?.classList.add('d-none');
                resetActions();

                return;
            }

            const url = new URL(form.dataset.playerLookupUrl, window.location.origin);
            url.searchParams.set('ci', value);
            url.searchParams.set('tournament_id', form.dataset.tournamentId ?? '');
            url.searchParams.set('team_id', form.dataset.teamId ?? '');

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const actions = payload.actions ?? {};

            summary.classList.remove('d-none');
            renderAffiliatePlayerSummary(summary, payload);
            resetActions();

            if (!actionsContainer) {
                return;
            }

            if (!payload.found) {
                actionsContainer.classList.remove('d-none');
                setRegisterButton(value, 'Registrar nuevo');

                return;
            }

            if (actions.is_other_team_roster) {
                actionsContainer.classList.remove('d-none');
                transferButton?.classList.remove('d-none');
                if (transferButton) {
                    transferButton.disabled = actions.can_request_transfer !== true;
                    transferButton.dataset.transferBlockedReason = actions.transfer_blocked_reason ?? '';
                    transferButton.dataset.modalUrl = actions.transfer_request_url ?? '';
                    transferButton.dataset.modalTitle = 'Solicitar pase';
                }

                return;
            }

            if (actions.can_enable && payload.current_team?.team_player_id && enableButton && enablePlayerId) {
                actionsContainer.classList.remove('d-none');
                enablePlayerId.value = payload.current_team.team_player_id;
                if (enableQuery) {
                    enableQuery.value = '';
                }
                enableForm.dataset.refreshUrl = clearSearchRefreshUrl();
                enableButton.classList.remove('d-none');

                return;
            }

            if (actions.can_affiliate && !actions.is_selected_team_roster) {
                actionsContainer.classList.remove('d-none');
                setRegisterButton(value, 'Afiliar y habilitar');
            }
        };

        transferButton?.addEventListener('click', (event) => {
            const reason = event.currentTarget.dataset.transferBlockedReason;
            const modalUrl = event.currentTarget.dataset.modalUrl;

            if (!reason && modalUrl) {
                openAjaxModal(event.currentTarget);

                return;
            }

            Swal.fire({
                icon: reason ? 'warning' : 'info',
                title: 'Solicitud de pase',
                text: reason || 'La opcion quedo marcada para el modulo de pases. Aun no se implementa el registro de la solicitud.',
            });
        });

        input?.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(lookup, 350);
        });
        input?.addEventListener('blur', lookup);

        if ((input?.value.trim() ?? '').length >= 2) {
            lookup();
        }

        form.dataset.habilitationSearchInitialized = '1';
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
        const parts = field.split('.');
        const bracketField = parts.length > 1 ? `${parts.shift()}[${parts.join('][')}]` : field;
        const input = form.querySelector(`[name="${field}"], [name="${bracketField}"]`);
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
        initPunishmentPlayerFilters(fresh);
        initTeamNameMatches(fresh);
        initLocalLocationAutocomplete(fresh);
        initAdminDataTables();
        initHabilitationPlayerSearch(fresh);
        initRegistrationTeamNumberForms(fresh);
        initMatchReportPlayerSearch(fresh);
    }
}

function setRegistrationTeamNumberState(form, state, message = '') {
    const input = form.querySelector('[name="team_number"]');
    const feedback = form.querySelector('[data-error-for="team_number"]');

    input?.classList.toggle('is-invalid', state === 'error');
    input?.classList.toggle('is-valid', state === 'success');

    if (feedback) {
        feedback.textContent = state === 'error' ? message : '';
    }
}

async function saveRegistrationTeamNumber(form) {
    const input = form.querySelector('[name="team_number"]');

    if (!input || input.value === input.dataset.originalValue) {
        return;
    }

    setRegistrationTeamNumberState(form, 'idle');
    input.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (response.status === 422) {
            const message = payload.data?.team_number?.[0] ?? payload.message ?? 'Numero invalido.';
            setRegistrationTeamNumberState(form, 'error', message);
            Swal.fire({ icon: 'error', title: 'Validacion', text: message });

            return;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message ?? 'No se pudo actualizar el numero de equipo.');
        }

        input.value = payload.data?.team_number ?? input.value;
        input.dataset.originalValue = input.value;
        setRegistrationTeamNumberState(form, 'success');
        toast.fire({ icon: 'success', title: payload.message ?? 'Numero de equipo actualizado.' });
    } catch (error) {
        setRegistrationTeamNumberState(form, 'error', error.message);
        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
    } finally {
        input.disabled = false;
    }
}

function initRegistrationTeamNumberForms(scope = document) {
    scope.querySelectorAll('[data-registration-team-number-form]').forEach((form) => {
        if (form.dataset.registrationTeamNumberInitialized === '1') {
            return;
        }

        const input = form.querySelector('[name="team_number"]');

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            saveRegistrationTeamNumber(form);
        });

        input?.addEventListener('change', () => saveRegistrationTeamNumber(form));
        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveRegistrationTeamNumber(form);
                input.blur();
            }
        });

        form.dataset.registrationTeamNumberInitialized = '1';
    });
}

function initMatchReportPlayerSearch(scope = document) {
    scope.querySelectorAll('[data-match-player-search]').forEach((input) => {
        if (input.dataset.matchPlayerSearchInitialized === '1') {
            return;
        }

        const select = scope.querySelector(input.dataset.target) ?? document.querySelector(input.dataset.target);

        if (!select) {
            return;
        }

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();

            Array.from(select.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;

                    return;
                }

                const searchableText = option.dataset.search ?? option.textContent.toLowerCase();
                option.hidden = query !== '' && !searchableText.includes(query);
            });

            if (select.selectedOptions[0]?.hidden) {
                select.value = '';
            }
        });

        input.dataset.matchPlayerSearchInitialized = '1';
    });
}

async function submitAjaxForm(form, body = null) {
    clearFormErrors(form);
    setSubmitting(form, true);

    try {
        const response = await fetch(form.action, {
            method: form.method.toUpperCase(),
            body: body ?? new FormData(form),
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

        if (form.dataset.showUrl && ajaxModalBody && ajaxModalElement?.classList.contains('show')) {
            try {
                ajaxModalTitle.textContent = 'Detalle de jugador';
                ajaxModalBody.innerHTML = await fetchHtml(form.dataset.showUrl);
                initPlayerPhotoForms(ajaxModalBody);
                initPlayerBiometricRegistration(ajaxModalBody);
            } catch (_error) {
                ajaxModal?.hide();
            }

            toast.fire({ icon: 'success', title: payload.message ?? 'Operacion realizada correctamente.' });

            return;
        }

        if (form.dataset.keepModal === 'true') {
            updatePhotoPreviewAfterUpload(form, payload.data ?? {});
            toast.fire({ icon: 'success', title: payload.message ?? 'Operacion realizada correctamente.' });

            return;
        }

        const localModal = form.closest('.modal');

        if (localModal && localModal !== ajaxModalElement && window.bootstrap) {
            window.bootstrap.Modal.getInstance(localModal)?.hide();
        }

        ajaxModal?.hide();
        await refreshContainer(form.dataset.refreshUrl);
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
        text: form.dataset.confirmText ?? 'Esta accion no se puede deshacer facilmente.',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButtonText ?? 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: form.dataset.confirmColor ?? '#dc3545',
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
        const usesAjax = Boolean(table.dataset.url);

        const options = {
            processing: usesAjax,
            serverSide: usesAjax,
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
        };

        if (usesAjax) {
            options.ajax = {
                url: table.dataset.url,
                data(data) {
                    if (!filtersForm) {
                        return;
                    }

                    new FormData(filtersForm).forEach((value, key) => {
                        data[key] = value;
                    });
                },
            };
            options.columns = columns;
        }

        const dataTable = new DataTable(table, options);

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
                    dataTable.ajax?.reload();
                }, 0);
            });
        }

        if (usesAjax) {
            dataTable.on('xhr.dt', (_event, _settings, json, xhr) => {
                if (xhr.status === 401 || xhr.status === 403 || xhr.responseURL?.includes('/login')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin acceso',
                        text: 'No tienes permisos para cargar los datos de esta tabla o tu sesion expiro.',
                    });
                }
            });
        }
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
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxItems: select.multiple ? null : 1,
            placeholder: select.dataset.placeholder ?? 'Seleccionar',
            plugins: ['clear_button'],
            load(query, callback) {
                if (!select.matches('[data-remote-team-select]')) {
                    callback();

                    return;
                }

                if (query.length < 2) {
                    callback();

                    return;
                }

                const form = select.closest('form') ?? document;
                const tournament = form.querySelector('[data-registration-tournament]');
                const url = new URL(select.dataset.url, window.location.origin);

                url.searchParams.set('q', query);

                if (tournament?.value) {
                    url.searchParams.set('tournament_id', tournament.value);
                }

                fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.json())
                    .then((payload) => callback(payload.data ?? []))
                    .catch(() => callback());
            },
            render: {
                no_results() {
                    return '<div class="no-results">Sin resultados</div>';
                },
            },
        });

        if (select.matches('[data-remote-team-select]')) {
            const form = select.closest('form') ?? document;
            const tournament = form.querySelector('[data-registration-tournament]');

            tournament?.addEventListener('change', () => {
                select.tomselect?.clear(true);
                select.tomselect?.clearOptions();
            });
        }
    });
}

function initLocalLocationAutocomplete(scope = document) {
    scope.querySelectorAll('input[data-location-country-picker]').forEach((input) => {
        if (input.tomselect) {
            return;
        }

        const form = input.closest('form') ?? document;
        const countryValueInput = form.querySelector(input.dataset.locationCountryTarget ?? '[data-location-country-value]');
        const searchUrl = input.dataset.locationSearchUrl;

        if (!searchUrl) {
            return;
        }

        const countrySelect = new TomSelect(input, {
            create: false,
            dropdownParent: 'body',
            maxItems: 1,
            maxOptions: 20,
            persist: false,
            preload: 'focus',
            valueField: 'value',
            labelField: 'label',
            searchField: ['label', 'country', 'country_code'],
            loadThrottle: 240,
            load(query, callback) {
                const params = new URLSearchParams({
                    q: query ?? '',
                    type: 'country',
                    limit: '20',
                });

                fetch(`${searchUrl}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('No se pudo buscar el pais.')))
                    .then((payload) => callback(payload.data ?? []))
                    .catch(() => callback());
            },
            onChange(value) {
                const option = this.options[value];

                if (countryValueInput) {
                    countryValueInput.value = option?.country ?? value;
                    countryValueInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            },
            render: {
                option(data, escape) {
                    const meta = data.country_code ? `<div class="text-body-secondary small">${escape(data.country_code)}</div>` : '';

                    return `<div><div>${escape(data.label ?? data.value)}</div>${meta}</div>`;
                },
                item(data, escape) {
                    return `<div>${escape(data.country ?? data.label)}</div>`;
                },
                no_results() {
                    return '<div class="no-results">Sin resultados</div>';
                },
            },
        });

        if (countryValueInput?.value || input.value) {
            const value = countryValueInput?.value || input.value;
            const initialValue = `current:${value}`;
            countrySelect.addOption({
                value: initialValue,
                label: value,
                country: value,
                type: 'country',
            });
            countrySelect.setValue(initialValue, true);
        }
    });

    scope.querySelectorAll('input[data-location-city]').forEach((input) => {
        if (input.tomselect) {
            return;
        }

        const form = input.closest('form') ?? document;
        const countryInput = form.querySelector('[data-location-country]');
        const cityValueInput = form.querySelector('[data-location-city-value]');
        const searchUrl = input.dataset.locationSearchUrl;

        if (!searchUrl) {
            return;
        }

        const citySelect = new TomSelect(input, {
            create: false,
            dropdownParent: 'body',
            maxItems: 1,
            maxOptions: 20,
            persist: false,
            preload: false,
            valueField: 'value',
            labelField: 'label',
            searchField: ['label', 'country', 'city', 'region'],
            loadThrottle: 240,
            load(query, callback) {
                const params = new URLSearchParams({
                    q: query ?? '',
                    type: 'city',
                    limit: '20',
                });

                fetch(`${searchUrl}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('No se pudo buscar la ubicacion.')))
                    .then((payload) => callback(payload.data ?? []))
                    .catch(() => callback());
            },
            onChange(value) {
                const option = this.options[value];

                if (option?.city && cityValueInput) {
                    cityValueInput.value = option.city;
                } else if (cityValueInput) {
                    cityValueInput.value = value;
                }

                if (option?.country && countryInput) {
                    countryInput.value = option.country;
                    countryInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            },
            onItemAdd(value, item) {
                const option = this.options[value];

                item.dataset.locationType = option?.type ?? '';
            },
            render: {
                option(data, escape) {
                    const meta = data.type === 'city'
                        ? [data.region, data.country].filter(Boolean).join(' - ')
                        : data.country_code;

                    return `<div><div>${escape(data.label ?? data.value)}</div>${meta ? `<div class="text-body-secondary small">${escape(meta)}</div>` : ''}</div>`;
                },
                item(data, escape) {
                    return `<div>${escape(data.city ?? data.label)}</div>`;
                },
                no_results() {
                    return '<div class="no-results">Sin resultados</div>';
                },
            },
        });

        if (input.value) {
            const initialValue = `current:${input.value}`;
            citySelect.addOption({
                value: initialValue,
                label: input.value,
                city: input.value,
                country: countryInput?.value ?? '',
                type: 'city',
            });
            citySelect.setValue(initialValue, true);
        }
    });
}

function initPublicPopup() {
    const popup = document.querySelector('[data-public-popup]');

    if (!popup || sessionStorage.getItem('public-popup-closed') === '1') {
        return;
    }

    window.setTimeout(() => {
        popup.hidden = false;
    }, 650);

    popup.querySelectorAll('[data-public-popup-close]').forEach((button) => {
        button.addEventListener('click', () => {
            popup.hidden = true;
            sessionStorage.setItem('public-popup-closed', '1');
        });
    });

    popup.addEventListener('click', (event) => {
        if (event.target === popup) {
            popup.hidden = true;
            sessionStorage.setItem('public-popup-closed', '1');
        }
    });
}

function initPunishmentPlayerFilters(scope = document) {
    scope.querySelectorAll('[data-punishment-team]').forEach((teamSelect) => {
        if (teamSelect.dataset.punishmentFilterReady === '1') {
            return;
        }

        teamSelect.dataset.punishmentFilterReady = '1';

        const form = teamSelect.closest('form') ?? scope;
        const playerSelect = form.querySelector('[data-punishment-player]');

        if (!playerSelect) {
            return;
        }

        const resetPlayers = () => {
            playerSelect.tomselect?.clear(true);
            playerSelect.tomselect?.clearOptions();
            playerSelect.disabled = true;
            playerSelect.tomselect?.disable();
        };

        const loadPlayers = () => {
            resetPlayers();

            if (!teamSelect.value || !playerSelect.dataset.urlTemplate) {
                return;
            }

            const url = playerSelect.dataset.urlTemplate.replace('__TEAM__', teamSelect.value);

            fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((payload) => {
                    (payload.data ?? []).forEach((option) => {
                        playerSelect.tomselect?.addOption(option);
                    });

                    playerSelect.disabled = false;
                    playerSelect.tomselect?.enable();
                    playerSelect.tomselect?.refreshOptions(false);

                    if (playerSelect.dataset.selectedValue) {
                        playerSelect.tomselect?.setValue(playerSelect.dataset.selectedValue, true);
                        playerSelect.dataset.selectedValue = '';
                    }
                })
                .catch(() => resetPlayers());
        };

        resetPlayers();
        teamSelect.addEventListener('change', loadPlayers);
        teamSelect.tomselect?.on('change', loadPlayers);

        if (teamSelect.value) {
            loadPlayers();
        }
    });
}

function initTournamentCategorySelects(scope = document) {
    scope.querySelectorAll('[data-tournament-category]').forEach((categorySelect) => {
        const form = categorySelect.closest('form') ?? scope;
        const divisionSelect = form.querySelector('[data-tournament-division]');

        if (!divisionSelect) {
            return;
        }

        if (!categorySelect.dataset.allCategoryOptions) {
            categorySelect.dataset.allCategoryOptions = JSON.stringify(Array.from(categorySelect.querySelectorAll('option'))
                .filter((option) => option.value)
                .map((option) => ({
                    value: option.value,
                    text: option.textContent.trim(),
                    divisionId: option.dataset.divisionId || '',
                })));
        }

        refreshTournamentCategorySelect(categorySelect, divisionSelect, false);
    });
}

function optionLabel(select) {
    const option = select?.selectedOptions?.[0];

    return option?.dataset.label || option?.textContent?.trim() || '';
}

function initTournamentNamePreviews(scope = document) {
    scope.querySelectorAll('[data-tournament-name-preview]').forEach((preview) => {
        refreshTournamentNamePreview(preview.closest('form') ?? scope);
    });
}

function initTeamNameMatches(scope = document) {
    scope.querySelectorAll('[data-team-name]').forEach((input) => {
        if (input.dataset.teamMatchesInitialized === '1') {
            return;
        }

        const form = input.closest('form') ?? scope;
        const target = form.querySelector('[data-team-name-matches]');
        const company = form.querySelector('[data-team-company]');
        let timeout;

        const renderMatches = (matches) => {
            if (!target) {
                return;
            }

            if (!matches.length) {
                target.classList.add('d-none');
                target.innerHTML = '';

                return;
            }

            target.classList.remove('d-none');
            target.innerHTML = [
                '<div class="fw-semibold mb-1">Coincidencias encontradas</div>',
                ...matches.map((match) => `<div class="d-flex justify-content-between gap-2"><span>${escapeHtml(match.name)}</span><span class="text-body-secondary">${escapeHtml(match.founded_at ?? '')}</span></div>`),
            ].join('');
        };

        const search = async () => {
            const name = input.value.trim();

            if (name.length < 2 || !input.dataset.teamMatchesUrl) {
                renderMatches([]);

                return;
            }

            const url = new URL(input.dataset.teamMatchesUrl, window.location.origin);
            url.searchParams.set('name', name);

            if (company?.value) {
                url.searchParams.set('company_id', company.value);
            }

            if (input.dataset.teamIgnoreId) {
                url.searchParams.set('ignore_id', input.dataset.teamIgnoreId);
            }

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                renderMatches([]);

                return;
            }

            const payload = await response.json();
            renderMatches(payload.data ?? []);
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(search, 220);
        });

        input.addEventListener('focus', search);
        company?.addEventListener('change', search);
        input.dataset.teamMatchesInitialized = '1';
    });
}

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value;

    return element.innerHTML;
}

function refreshTournamentNamePreview(scope = document) {
    const preview = scope.querySelector('[data-tournament-name-preview]');

    if (!preview) {
        return;
    }

    const division = optionLabel(scope.querySelector('[data-tournament-division]'));
    const category = optionLabel(scope.querySelector('[data-tournament-category]'));
    const season = optionLabel(scope.querySelector('[name="season_id"]'));
    const parts = [division, category, season].filter(Boolean);

    preview.value = parts.length === 3 ? parts.join(' - ') : '';
}

function refreshTournamentCategorySelect(categorySelect, divisionSelect, clearInvalid = false) {
    const selectedDivisionId = divisionSelect.value;
    const categories = JSON.parse(categorySelect.dataset.allCategoryOptions || '[]');
    const visibleCategories = categories.filter((category) => category.divisionId === selectedDivisionId);
    const currentValue = categorySelect.value;
    const currentIsVisible = visibleCategories.some((category) => category.value === currentValue);

    if (categorySelect.tomselect) {
        categorySelect.tomselect.clearOptions();
        visibleCategories.forEach((category) => {
            categorySelect.tomselect.addOption({ value: category.value, text: category.text });
        });

        if (currentValue && currentIsVisible) {
            categorySelect.tomselect.setValue(currentValue, true);
        } else if (clearInvalid) {
            categorySelect.tomselect.clear(true);
        }

        categorySelect.tomselect.refreshOptions(false);

        return;
    }

    categorySelect.querySelectorAll('option[data-division-id]').forEach((option) => {
        const visible = option.dataset.divisionId === selectedDivisionId;
        option.hidden = !visible;
        option.disabled = !visible;
    });

    if (clearInvalid && currentValue && !currentIsVisible) {
        categorySelect.value = '';
    }
}

function initTournamentSteppers(scope = document) {
    scope.querySelectorAll('[data-tournament-stepper]').forEach((stepper) => {
        if (stepper.dataset.tournamentStepperInitialized === '1') {
            return;
        }

        const form = stepper.closest('form') ?? document;
        const divisionSelect = form.querySelector('[data-tournament-division]');
        const nextButton = stepper.querySelector('[data-tournament-next-step]');
        const prevButton = stepper.querySelector('[data-tournament-prev-step]');
        const submitButton = form.querySelector('button[type="submit"]');
        let currentStep = 1;

        const refreshCategories = () => {
            const selectedDivisionId = divisionSelect?.value || '';
            const options = Array.from(stepper.querySelectorAll('[data-tournament-category-option]'));
            let visibleCount = 0;

            options.forEach((option) => {
                const visible = option.dataset.divisionId === selectedDivisionId;
                const checkbox = option.querySelector('input[type="checkbox"]');
                option.classList.toggle('d-none', !visible);
                if (checkbox) {
                    checkbox.disabled = !visible;
                    if (!visible) {
                        checkbox.checked = false;
                    }
                }
                visibleCount += visible ? 1 : 0;
            });

            stepper.querySelector('[data-tournament-empty-categories]')?.classList.toggle('d-none', visibleCount > 0);
        };

        const showStep = (step) => {
            currentStep = step;
            stepper.querySelectorAll('[data-tournament-step]').forEach((section) => {
                section.classList.toggle('d-none', Number(section.dataset.tournamentStep) !== currentStep);
            });
            stepper.querySelectorAll('[data-tournament-step-indicator]').forEach((indicator) => {
                const active = Number(indicator.dataset.tournamentStepIndicator) === currentStep;
                indicator.classList.toggle('text-bg-primary', active);
                indicator.classList.toggle('text-bg-secondary', !active);
            });
            prevButton?.classList.toggle('d-none', currentStep === 1);
            nextButton?.classList.toggle('d-none', currentStep === 2);
            submitButton?.classList.toggle('d-none', currentStep !== 2);

            if (currentStep === 2) {
                refreshCategories();
            }
        };

        nextButton?.addEventListener('click', () => showStep(2));
        prevButton?.addEventListener('click', () => showStep(1));
        divisionSelect?.addEventListener('change', refreshCategories);

        refreshCategories();
        showStep(1);
        stepper.dataset.tournamentStepperInitialized = '1';
    });
}

function initFixtureSteppers(scope = document) {
    scope.querySelectorAll('[data-fixture-stepper]').forEach((stepper) => {
        if (stepper.dataset.fixtureStepperInitialized === '1') {
            return;
        }

        let currentStep = 1;
        const nextButton = stepper.querySelector('[data-fixture-next-step]');
        const prevButton = stepper.querySelector('[data-fixture-prev-step]');
        const submitButton = stepper.querySelector('[data-fixture-submit]');
        const firstPhaseInputs = Array.from(stepper.querySelectorAll('[name="first_phase_rounds"]'));
        const secondPhaseInputs = Array.from(stepper.querySelectorAll('[name="second_phase_mode"]'));
        const qualifiersInput = stepper.querySelector('[data-fixture-qualifiers-input]');
        const fillRule = stepper.querySelector('[data-fixture-fill-rule]');
        const leagueChampionRule = stepper.querySelector('[name="league_champion_rule"]');
        const seriesCount = Number(stepper.dataset.seriesCount || 1);
        const qualifiedCount = stepper.querySelector('[data-fixture-qualified-count]');
        const qualifiedHelp = stepper.querySelector('[data-fixture-qualified-help]');
        const bracketAdvice = stepper.querySelector('[data-fixture-bracket-advice]');
        const fillOptions = stepper.querySelector('[data-fixture-fill-options]');
        const fillHelp = stepper.querySelector('[data-fixture-fill-help]');
        const knockoutPanel = stepper.querySelector('[data-fixture-knockout-panel]');
        const leaguePanel = stepper.querySelector('[data-fixture-league-panel]');
        const accumulativePanel = stepper.querySelector('[data-fixture-accumulative-panel]');
        const knockoutRound = stepper.querySelector('[data-fixture-knockout-round]');
        const stageList = stepper.querySelector('[data-fixture-stage-list]');
        const thirdPlacePanel = stepper.querySelector('[data-fixture-third-place-panel]');
        const summaryFirstPhase = stepper.querySelector('[data-fixture-summary="first-phase"]');
        const summaryQualifiers = stepper.querySelector('[data-fixture-summary="qualifiers"]');
        const summarySecondPhase = stepper.querySelector('[data-fixture-summary="second-phase"]');
        const summaryChampion = stepper.querySelector('[data-fixture-summary="champion"]');

        const selectedFirstPhase = () => firstPhaseInputs.find((input) => input.checked) ?? firstPhaseInputs[0] ?? null;
        const selectedSecondPhase = () => secondPhaseInputs.find((input) => input.checked) ?? secondPhaseInputs[0] ?? null;
        const optionTitle = (input) => input?.closest('.fixture-option')?.querySelector('.fixture-option-title')?.textContent?.trim() ?? input?.value ?? '-';

        const stagesFor = (target) => {
            const stages = [
                { key: 'round_of_32', size: 32, label: '16avos de final', defaultLegMode: 'double' },
                { key: 'round_of_16', size: 16, label: 'Octavos de final', defaultLegMode: 'double' },
                { key: 'quarterfinal', size: 8, label: 'Cuartos de final', defaultLegMode: 'double' },
                { key: 'semifinal', size: 4, label: 'Semifinal', defaultLegMode: 'single' },
                { key: 'final', size: 2, label: 'Final', defaultLegMode: 'single' },
            ];

            return stages.filter((stage) => target >= stage.size);
        };

        const stageMeta = (stage) => {
            const matches = Math.max(1, stage.size / 2);
            const winners = Math.max(1, stage.size / 2);

            return {
                matches,
                winners,
                teamsLabel: `${stage.size} equipos`,
                matchesLabel: `${matches} partido${matches === 1 ? '' : 's'}`,
                winnersLabel: winners === 1 ? 'Define campeon' : `Avanzan ${winners}`,
            };
        };

        const bracketFor = (teams) => {
            if (teams <= 0) {
                return { target: 0, round: 'Sin clasificados', missing: 0, exact: false };
            }

            if (teams <= 2) {
                return { target: 2, round: 'Final directa', missing: Math.max(0, 2 - teams), exact: teams === 2 };
            }

            if (teams <= 4) {
                return { target: 4, round: 'Semifinal', missing: Math.max(0, 4 - teams), exact: teams === 4 };
            }

            if (teams <= 8) {
                return { target: 8, round: 'Cuartos de final', missing: Math.max(0, 8 - teams), exact: teams === 8 };
            }

            if (teams <= 16) {
                return { target: 16, round: 'Octavos de final', missing: Math.max(0, 16 - teams), exact: teams === 16 };
            }

            if (teams <= 32) {
                return { target: 32, round: '16avos de final', missing: Math.max(0, 32 - teams), exact: teams === 32 };
            }

            return { target: teams, round: 'Liguilla recomendada', missing: 0, exact: false };
        };

        const renderStages = (bracket) => {
            if (!stageList) {
                return;
            }

            stageList.innerHTML = '';
            stagesFor(bracket.target).forEach((stage) => {
                const meta = stageMeta(stage);
                const card = document.createElement('div');
                card.className = 'fixture-bracket-stage';
                card.innerHTML = `
                    <div class="fixture-bracket-stage-title">${stage.label}</div>
                    <div class="fixture-bracket-stage-meta">
                        <span>${meta.teamsLabel}</span>
                        <span>${meta.matchesLabel}</span>
                        <span>${meta.winnersLabel}</span>
                    </div>
                    <label class="form-label mt-2" for="fixture-stage-${stage.key}">Modalidad de esta llave</label>
                    <select class="form-select form-select-sm" id="fixture-stage-${stage.key}" name="stage_leg_modes[${stage.key}]">
                        <option value="single" ${stage.defaultLegMode === 'single' ? 'selected' : ''}>Partido unico</option>
                        <option value="double" ${stage.defaultLegMode === 'double' ? 'selected' : ''}>Ida y vuelta</option>
                    </select>
                `;
                stageList.append(card);
            });

            if (!stageList.children.length) {
                const card = document.createElement('div');
                card.className = 'fixture-bracket-stage';
                card.innerHTML = `
                    <div class="fixture-bracket-stage-title">${bracket.round}</div>
                    <div class="fixture-bracket-stage-meta">
                        <span>Sin cruces definidos</span>
                    </div>
                `;
                stageList.append(card);
            }
        };

        const updateConditionalOptions = () => {
            const qualifiersPerSeries = Math.max(0, Number(qualifiersInput?.value || 0));
            const totalQualified = qualifiersPerSeries * Math.max(1, seriesCount);
            const bracket = bracketFor(totalQualified);
            const secondPhase = selectedSecondPhase()?.value ?? 'knockout';

            if (qualifiedCount) {
                qualifiedCount.textContent = seriesCount > 1
                    ? `${totalQualified} equipos (${qualifiersPerSeries} por serie)`
                    : `${totalQualified} equipos`;
            }

            if (qualifiedHelp) {
                qualifiedHelp.textContent = seriesCount > 1
                    ? 'El total se calcula multiplicando clasificados por la cantidad de series.'
                    : 'En serie unica el numero seleccionado pasa directo a la segunda fase.';
            }

            if (bracketAdvice) {
                if (totalQualified > 32) {
                    bracketAdvice.className = 'alert alert-warning mb-0';
                    bracketAdvice.textContent = 'Con mas de 32 clasificados conviene usar liguilla o ajustar la clasificacion antes de generar llaves.';
                } else if (bracket.exact) {
                    bracketAdvice.className = 'alert alert-success mb-0';
                    bracketAdvice.textContent = `${totalQualified} clasificados encajan exacto para iniciar en ${bracket.round}.`;
                } else if (bracket.missing > 0) {
                    bracketAdvice.className = 'alert alert-warning mb-0';
                    bracketAdvice.textContent = `${totalQualified} clasificados no completan ${bracket.round}. Faltan ${bracket.missing} equipo(s) para completar ${bracket.target}.`;
                } else {
                    bracketAdvice.className = 'alert alert-light border mb-0';
                    bracketAdvice.textContent = 'Selecciona cuantos equipos clasifican para calcular la fase recomendada.';
                }
            }

            const needsFill = secondPhase === 'knockout' && bracket.missing > 0 && totalQualified <= 32;
            fillOptions?.classList.toggle('d-none', !needsFill);
            if (fillHelp) {
                fillHelp.textContent = needsFill
                    ? `Se agregaran ${bracket.missing} equipo(s) adicional(es) para completar ${bracket.round}.`
                    : '';
            }

            knockoutPanel?.classList.toggle('d-none', secondPhase !== 'knockout');
            leaguePanel?.classList.toggle('d-none', secondPhase !== 'league');
            accumulativePanel?.classList.toggle('d-none', secondPhase !== 'accumulative');

            if (knockoutRound) {
                knockoutRound.textContent = bracket.round;
            }
            renderStages(bracket);
            thirdPlacePanel?.classList.toggle('d-none', secondPhase !== 'knockout' || bracket.target < 4);

            if (summaryFirstPhase) {
                summaryFirstPhase.textContent = optionTitle(selectedFirstPhase());
            }

            if (summaryQualifiers && qualifiersInput) {
                summaryQualifiers.textContent = seriesCount > 1
                    ? `${totalQualified} total, ${qualifiersPerSeries} por serie`
                    : `${totalQualified} total`;
            }

            if (summarySecondPhase) {
                if (secondPhase === 'knockout') {
                    summarySecondPhase.textContent = `Llaves desde ${bracket.round}`;
                } else {
                    summarySecondPhase.textContent = optionTitle(selectedSecondPhase());
                }
            }

            if (summaryChampion) {
                if (secondPhase === 'knockout') {
                    summaryChampion.textContent = needsFill
                        ? `Ganador de la final, completando llave con ${fillRule?.selectedOptions?.[0]?.textContent?.trim() ?? 'mejores terceros'}`
                        : 'Ganador de la final';
                } else if (secondPhase === 'league') {
                    const championLabel = leagueChampionRule?.selectedOptions?.[0]?.textContent?.trim() ?? 'Tabla acumulada de la liguilla';
                    summaryChampion.textContent = championLabel;
                } else {
                    summaryChampion.textContent = 'Primer lugar por puntos acumulados';
                }
            }
        };

        const render = () => {
            stepper.querySelectorAll('[data-fixture-step]').forEach((section) => {
                section.classList.toggle('d-none', section.dataset.fixtureStep !== String(currentStep));
            });
            stepper.querySelectorAll('[data-fixture-step-indicator]').forEach((indicator) => {
                const step = Number(indicator.dataset.fixtureStepIndicator);
                indicator.classList.toggle('active', step === currentStep);
                indicator.classList.toggle('completed', step < currentStep);
            });

            if (prevButton) {
                prevButton.disabled = currentStep === 1;
            }

            if (nextButton) {
                nextButton.classList.toggle('d-none', currentStep === 4);
                nextButton.innerHTML = 'Siguiente <i class="ti ti-arrow-right ms-1"></i>';
                nextButton.disabled = false;
            }

            if (submitButton) {
                submitButton.classList.toggle('d-none', currentStep !== 4);
            }

            updateConditionalOptions();
        };

        nextButton?.addEventListener('click', () => {
            currentStep = Math.min(4, currentStep + 1);
            render();
        });

        prevButton?.addEventListener('click', () => {
            currentStep = Math.max(1, currentStep - 1);
            render();
        });

        stepper.querySelectorAll('[data-fixture-step-indicator]').forEach((indicator) => {
            indicator.addEventListener('click', () => {
                currentStep = Number(indicator.dataset.fixtureStepIndicator);
                render();
            });
        });

        firstPhaseInputs.forEach((input) => input.addEventListener('change', updateConditionalOptions));
        secondPhaseInputs.forEach((input) => input.addEventListener('change', updateConditionalOptions));
        qualifiersInput?.addEventListener('input', updateConditionalOptions);
        fillRule?.addEventListener('change', updateConditionalOptions);
        leagueChampionRule?.addEventListener('change', updateConditionalOptions);
        render();
        stepper.dataset.fixtureStepperInitialized = '1';
    });
}

function initRegistrationCategorySelects(scope = document) {
    scope.querySelectorAll('[data-registration-category]').forEach((categorySelect) => {
        if (categorySelect.dataset.registrationCategoryInitialized === '1') {
            return;
        }

        const form = categorySelect.closest('form') ?? scope;
        const tournamentSelect = form.querySelector('[data-registration-tournament]');
        const teamInput = form.querySelector('[data-registration-team-id]');
        const help = form.querySelector('[data-registration-category-help]');

        if (!tournamentSelect) {
            return;
        }

        if (!categorySelect.dataset.allCategoryOptions) {
            categorySelect.dataset.allCategoryOptions = JSON.stringify(Array.from(categorySelect.querySelectorAll('option'))
                .filter((option) => option.value)
                .map((option) => ({
                    value: option.value,
                    text: option.textContent.trim(),
                    tournamentId: option.dataset.tournamentId || '',
                })));
        }

        const setOptions = (categories, currentValue, clear = false) => {
            const currentIsVisible = categories.some((category) => category.value === currentValue);

            if (categorySelect.tomselect) {
                categorySelect.tomselect.clearOptions();
                categories.forEach((category) => categorySelect.tomselect.addOption({ value: category.value, text: category.text }));

                if (currentValue && currentIsVisible) {
                    categorySelect.tomselect.setValue(currentValue, true);
                } else if (clear) {
                    categorySelect.tomselect.clear(true);
                }

                categorySelect.tomselect.refreshOptions(false);

                return;
            }

            categorySelect.innerHTML = '<option value="">Seleccionar categoria</option>';
            categories.forEach((category) => {
                const option = document.createElement('option');
                option.value = category.value;
                option.textContent = category.text;
                categorySelect.append(option);
            });

            if (currentValue && currentIsVisible) {
                categorySelect.value = currentValue;
            } else if (clear) {
                categorySelect.value = '';
            }
        };

        const setDisabled = (disabled) => {
            categorySelect.disabled = disabled;
            if (categorySelect.tomselect) {
                disabled ? categorySelect.tomselect.disable() : categorySelect.tomselect.enable();
            }
        };

        const refresh = async (clear = false) => {
            const selectedTournamentId = tournamentSelect.value;
            const currentValue = categorySelect.value;
            const urlTemplate = categorySelect.dataset.registrationCategoryUrlTemplate;

            if (!selectedTournamentId) {
                setOptions([], currentValue, true);
                setDisabled(true);
                if (help) {
                    help.textContent = 'Selecciona un torneo para cargar sus categorias habilitadas.';
                }

                return;
            }

            if (urlTemplate) {
                setDisabled(true);
                if (help) {
                    help.textContent = 'Cargando categorias...';
                }

                const url = new URL(urlTemplate.replace('__TOURNAMENT__', selectedTournamentId), window.location.origin);

                if (teamInput?.value) {
                    url.searchParams.set('team_id', teamInput.value);
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const payload = await response.json();
                    const categories = payload.data ?? [];

                    setOptions(categories, currentValue, clear);
                    setDisabled(categories.length === 0);
                    if (help) {
                        help.textContent = payload.already_registered
                            ? 'Este equipo ya esta inscrito en el torneo seleccionado.'
                            : (categories.length ? 'Selecciona una categoria habilitada para este torneo.' : 'Este torneo no tiene categorias habilitadas.');
                    }
                } catch (_error) {
                    setOptions([], currentValue, true);
                    setDisabled(true);
                    if (help) {
                        help.textContent = 'No se pudieron cargar las categorias del torneo.';
                    }
                }

                return;
            }

            const categories = JSON.parse(categorySelect.dataset.allCategoryOptions || '[]')
                .filter((category) => category.tournamentId === selectedTournamentId);

            setOptions(categories, currentValue, clear);
            setDisabled(categories.length === 0);
        };

        tournamentSelect.addEventListener('change', () => refresh(true));
        refresh(false);
        categorySelect.dataset.registrationCategoryInitialized = '1';
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
    const closeButton = document.querySelector('[data-sidebar-close]');

    if (!toggle || toggle.dataset.sidebarToggleInitialized === '1') {
        return;
    }

    const icon = toggle.querySelector('i');
    const isMobile = () => window.matchMedia('(max-width: 991.98px)').matches;

    document.querySelectorAll('.app-sidebar .nav-link, .app-sidebar .app-menu-toggle').forEach((item) => {
        const label = item.querySelector('.nav-link-title')?.textContent?.trim();

        if (label && !item.getAttribute('title')) {
            item.setAttribute('title', label);
        }
    });

    const syncState = () => {
        if (isMobile()) {
            const open = document.body.classList.contains('app-sidebar-mobile-open');
            toggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
            toggle.setAttribute('title', open ? 'Cerrar menu' : 'Abrir menu');

            if (icon) {
                icon.className = open ? 'ti ti-x' : 'ti ti-menu-2';
            }

            return;
        }

        const collapsed = document.body.classList.contains('app-sidebar-collapsed');
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Replegar menu');
        toggle.setAttribute('title', collapsed ? 'Expandir menu' : 'Replegar menu');

        if (icon) {
            icon.className = collapsed ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse';
        }
    };

    const closeMobileSidebar = () => {
        document.body.classList.remove('app-sidebar-mobile-open');
        syncState();
    };

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            document.body.classList.toggle('app-sidebar-mobile-open');
            document.body.classList.remove('app-sidebar-peek');
            syncState();

            return;
        }

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

    const expandSidebar = () => {
        document.body.classList.remove('app-sidebar-collapsed', 'app-sidebar-peek');
        localStorage.setItem('app-sidebar-collapsed', '0');
        syncState();
    };

    sidebar?.addEventListener('click', (event) => {
        if (isMobile()) {
            const link = event.target.closest('a.nav-link');

            if (link) {
                closeMobileSidebar();
            }

            return;
        }

        if (!document.body.classList.contains('app-sidebar-collapsed')) {
            return;
        }

        const link = event.target.closest('a.nav-link');
        const toggleButton = event.target.closest('.app-menu-toggle');

        if (toggleButton) {
            event.preventDefault();
            event.stopPropagation();
            expandSidebar();

            const target = document.querySelector(toggleButton.dataset.bsTarget);

            if (target) {
                bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).show();
                toggleButton.classList.remove('collapsed');
                toggleButton.setAttribute('aria-expanded', 'true');
            }

            return;
        }

        openPeek();

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

    closeButton?.addEventListener('click', closeMobileSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePeek();
            closeMobileSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (!isMobile()) {
            document.body.classList.remove('app-sidebar-mobile-open');
        }

        syncState();
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

function normalizeDigitalPersonaError(error) {
    const message = error?.message ?? String(error ?? '');

    if (message.includes('Communication failure')) {
        return 'No se pudo conectar con DigitalPersona Agent. Verifica que el agente este instalado y ejecutandose.';
    }

    return message || 'No se pudo comunicar con el lector biometrico.';
}

async function loadDigitalPersona() {
    if (!digitalPersonaModules) {
        const core = window.dp?.core;
        const services = window.dp?.services;
        const devices = resolveDigitalPersonaDevices(window.dp?.devices);

        if (!core || !services || !devices) {
            console.debug('DigitalPersona global incompleto:', window.dp);
            throw new Error('DigitalPersona Devices no expone FingerprintReader.');
        }

        digitalPersonaModules = { core, devices, services };
        window.DigitalPersonaDevices = {
            FingerprintReader: devices.FingerprintReader,
            QualityCode: devices.QualityCode,
            SampleFormat: devices.SampleFormat,
        };
    }

    return digitalPersonaModules;
}

async function getFingerprintReader() {
    if (!fingerprintReader) {
        const { devices } = await loadDigitalPersona();
        fingerprintReader = new devices.FingerprintReader();
    }

    return fingerprintReader;
}

async function detectFingerprintDevices() {
    try {
        return await (await getFingerprintReader()).enumerateDevices();
    } catch (error) {
        throw new Error(normalizeDigitalPersonaError(error));
    }
}

function serializeFingerprintSample(sample) {
    if (typeof sample === 'string') {
        return sample;
    }

    if (sample?.Data && typeof sample.Data === 'string') {
        return JSON.stringify({
            Version: sample.Version ?? 1,
            Header: sample.Header ?? null,
            Data: sample.Data,
        });
    }

    return JSON.stringify(sample ?? {});
}

function parseFingerprintSample(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }

    try {
        return JSON.parse(value);
    } catch (_error) {
        return { Data: value };
    }
}

function fingerprintSamplesFromValue(value) {
    const sample = parseFingerprintSample(value);

    if (!sample?.Data) {
        return [];
    }

    return Array.isArray(sample) ? sample : [sample];
}

function fingerprintSampleIsValid(value) {
    const sample = fingerprintSamplesFromValue(value)[0];
    const data = sample?.Data;

    return typeof data === 'string' && data.length >= 20;
}

function fingerprintIdentity(form) {
    const identityField = form.querySelector('[data-fingerprint-user-identity]');

    if (identityField?.matches('select')) {
        return identityField.selectedOptions?.[0]?.dataset.fingerprintIdentity ?? '';
    }

    return identityField?.value?.split(' - ').pop()?.trim() ?? '';
}

async function authenticateFingerprint(identity, sampleValue) {
    if (!digitalPersonaAuthEndpoint) {
        throw new Error('Configura DIGITALPERSONA_AUTH_ENDPOINT para comparar biometricamente la huella.');
    }

    if (!identity) {
        throw new Error('Selecciona un usuario con identidad DigitalPersona valida.');
    }

    const samples = fingerprintSamplesFromValue(sampleValue);

    if (!samples.length) {
        throw new Error('La captura no genero una muestra biometrica valida.');
    }

    const { core, services } = await loadDigitalPersona();
    const authService = new services.AuthService(digitalPersonaAuthEndpoint);
    const credential = new core.Credential(core.Credential.Fingerprints, samples);

    return authService.Authenticate(new core.User(identity), credential);
}

function fingerprintQualityMessage(quality) {
    const messages = {
        [FingerprintQuality.Good]: 'Huella reconocida correctamente.',
        [FingerprintQuality.NoImage]: 'No se reconoce la huella. Coloca el dedo sobre el lector.',
        [FingerprintQuality.TooLight]: 'Presiona un poco mas el dedo.',
        [FingerprintQuality.TooDark]: 'Reduce la presion sobre el lector.',
        [FingerprintQuality.TooNoisy]: 'No se reconoce bien la huella. Limpia el lector o intenta otra vez.',
        [FingerprintQuality.LowContrast]: 'No se reconoce bien la huella. Intenta colocar el dedo de nuevo.',
        [FingerprintQuality.NotEnoughFeatures]: 'Huella no reconocida. Mantén el dedo completo sobre el lector.',
        [FingerprintQuality.NotCentered]: 'Centra el dedo en el lector.',
        [FingerprintQuality.NotAFinger]: 'No se reconoce un dedo sobre el lector.',
        [FingerprintQuality.TooHigh]: 'Baja un poco el dedo.',
        [FingerprintQuality.TooLow]: 'Sube un poco el dedo.',
        [FingerprintQuality.TooLeft]: 'Mueve el dedo hacia la derecha.',
        [FingerprintQuality.TooRight]: 'Mueve el dedo hacia la izquierda.',
        [FingerprintQuality.TooFast]: 'Mueve el dedo mas lento.',
        [FingerprintQuality.TooSlow]: 'Mueve el dedo un poco mas rapido.',
        [FingerprintQuality.PressureTooHard]: 'Estas presionando demasiado.',
        [FingerprintQuality.PressureTooLight]: 'Presiona un poco mas.',
        [FingerprintQuality.WetFinger]: 'Dedo humedo. Seca el dedo e intenta nuevamente.',
        [FingerprintQuality.FakeFinger]: 'Huella no reconocida por el lector.',
        [FingerprintQuality.TooSmall]: 'Apoya mas superficie del dedo.',
        [FingerprintQuality.RotatedTooMuch]: 'Endereza el dedo sobre el lector.',
    };

    return messages[quality] ?? 'No se reconoce bien la huella. Intenta nuevamente.';
}

async function captureFingerprintSample(deviceUid, onStatus = () => {}) {
    return new Promise(async (resolve, reject) => {
        let settled = false;
        let timeout;
        let reader;

        try {
            reader = await getFingerprintReader();
        } catch (error) {
            reject(new Error(normalizeDigitalPersonaError(error)));

            return;
        }

        const cleanup = async () => {
            window.clearTimeout(timeout);
            reader.off('AcquisitionStarted', onAcquisitionStarted);
            reader.off('AcquisitionStopped', onAcquisitionStopped);
            reader.off('CommunicationFailed', onCommunicationFailed);
            reader.off('DeviceConnected', onDeviceConnected);
            reader.off('DeviceDisconnected', onDeviceDisconnected);
            reader.off('QualityReported', onQualityReported);
            reader.off('SamplesAcquired', onSamplesAcquired);
            reader.off('ErrorOccurred', onErrorOccurred);

            try {
                await reader.stopAcquisition(deviceUid);
            } catch (_error) {
                // The agent may already have stopped after a successful scan.
            }
        };

        const finish = async (callback, value) => {
            if (settled) {
                return;
            }

            settled = true;
            await cleanup();
            callback(value);
        };

        const onAcquisitionStarted = () => {
            onStatus('Lector conectado. Coloca el dedo en el sensor.');
        };

        const onAcquisitionStopped = () => {
            onStatus('El lector dejo de capturar.');
        };

        const onCommunicationFailed = () => {
            finish(reject, new Error('No se pudo conectar con DigitalPersona Agent. Verifica que este ejecutandose.'));
        };

        const onDeviceConnected = (event) => {
            onStatus(`Lector conectado: ${event.deviceId}.`);
        };

        const onDeviceDisconnected = () => {
            finish(reject, new Error('El lector biometrico se desconecto.'));
        };

        const onQualityReported = (event) => {
            onStatus(fingerprintQualityMessage(event.quality));
        };

        const onSamplesAcquired = (event) => {
            const sample = serializeFingerprintSample(event.samples?.[0]);
            finish(resolve, sample);
        };

        const onErrorOccurred = (event) => {
            finish(reject, new Error(`Error del lector biometrico: ${event.error ?? 'captura fallida'}.`));
        };

        timeout = window.setTimeout(() => {
            finish(reject, new Error('Tiempo agotado esperando la huella.'));
        }, 30000);

        reader.on('AcquisitionStarted', onAcquisitionStarted);
        reader.on('AcquisitionStopped', onAcquisitionStopped);
        reader.on('CommunicationFailed', onCommunicationFailed);
        reader.on('DeviceConnected', onDeviceConnected);
        reader.on('DeviceDisconnected', onDeviceDisconnected);
        reader.on('QualityReported', onQualityReported);
        reader.on('SamplesAcquired', onSamplesAcquired);
        reader.on('ErrorOccurred', onErrorOccurred);

        try {
            const { devices } = await loadDigitalPersona();
            await reader.startAcquisition(devices.SampleFormat.Intermediate, deviceUid);
        } catch (error) {
            await cleanup();
            reject(new Error(normalizeDigitalPersonaError(error)));
        }
    });
}

function base64UrlToBase64(value) {
    if (!value || typeof value !== 'string') {
        return '';
    }

    if (value.startsWith('data:image/png;base64,')) {
        return value.replace('data:image/png;base64,', '');
    }

    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padding = base64.length % 4;

    return padding ? base64 + '='.repeat(4 - padding) : base64;
}

function extractFingerprintPng(samples) {
    const sample = Array.isArray(samples) ? samples[0] : samples;

    if (!sample) {
        return '';
    }

    if (typeof sample === 'string') {
        return base64UrlToBase64(sample);
    }

    for (const key of ['Data', 'data', 'ImageData', 'imageData']) {
        if (typeof sample[key] === 'string') {
            return base64UrlToBase64(sample[key]);
        }
    }

    return '';
}

async function captureFingerprintPng(deviceUid, onStatus = () => {}, onQuality = () => {}) {
    return new Promise(async (resolve, reject) => {
        let settled = false;
        let timeout;
        let reader;

        try {
            reader = await getFingerprintReader();
        } catch (error) {
            reject(new Error(normalizeDigitalPersonaError(error)));

            return;
        }

        const cleanup = async () => {
            window.clearTimeout(timeout);
            reader.off('AcquisitionStarted', onAcquisitionStarted);
            reader.off('CommunicationFailed', onCommunicationFailed);
            reader.off('DeviceDisconnected', onDeviceDisconnected);
            reader.off('QualityReported', onQualityReported);
            reader.off('SamplesAcquired', onSamplesAcquired);
            reader.off('ErrorOccurred', onErrorOccurred);

            try {
                await reader.stopAcquisition(deviceUid);
            } catch (_error) {
                // The agent may already have stopped after a successful scan.
            }
        };

        const finish = async (callback, value) => {
            if (settled) {
                return;
            }

            settled = true;
            await cleanup();
            callback(value);
        };

        const onAcquisitionStarted = () => onStatus('Lector conectado. Coloca el indice derecho en el sensor.');
        const onCommunicationFailed = () => finish(reject, new Error('No se pudo conectar con DigitalPersona Agent. Verifica que este ejecutandose.'));
        const onDeviceDisconnected = () => finish(reject, new Error('El lector biometrico se desconecto.'));
        const onQualityReported = (event) => {
            onQuality(event.quality);
            onStatus(fingerprintQualityMessage(event.quality));
        };
        const onSamplesAcquired = (event) => {
            const image = extractFingerprintPng(event.samples);

            if (!image) {
                finish(reject, new Error('No se recibio una imagen PNG valida.'));

                return;
            }

            finish(resolve, image);
        };
        const onErrorOccurred = (event) => finish(reject, new Error(`Error del lector biometrico: ${event.error ?? 'captura fallida'}.`));

        timeout = window.setTimeout(() => {
            finish(reject, new Error('Tiempo agotado esperando la huella.'));
        }, 30000);

        reader.on('AcquisitionStarted', onAcquisitionStarted);
        reader.on('CommunicationFailed', onCommunicationFailed);
        reader.on('DeviceDisconnected', onDeviceDisconnected);
        reader.on('QualityReported', onQualityReported);
        reader.on('SamplesAcquired', onSamplesAcquired);
        reader.on('ErrorOccurred', onErrorOccurred);

        try {
            const { devices } = await loadDigitalPersona();
            await reader.startAcquisition(devices.SampleFormat.PngImage, deviceUid);
        } catch (error) {
            await cleanup();
            reject(new Error(normalizeDigitalPersonaError(error)));
        }
    });
}

async function detectarLector() {
    let devices = [];

    try {
        devices = await detectFingerprintDevices();
    } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'DigitalPersona Agent', text: error.message });

        return [];
    }

    console.log('Lectores detectados:', devices);

    if (!devices?.length) {
        Swal.fire({ icon: 'warning', title: 'Sin lector', text: 'No se detecto ningun lector biometrico.' });

        return [];
    }

    Swal.fire({
        icon: 'success',
        title: 'Lector detectado',
        text: devices.join(', '),
    });

    return devices;
}

window.detectarLector = detectarLector;

function initFingerprintForms(scope = document) {
    scope.querySelectorAll('[data-fingerprint-form]').forEach((form) => {
        if (form.dataset.fingerprintInitialized === '1') {
            return;
        }

        const template = form.querySelector('[data-fingerprint-template]');
        const format = form.querySelector('[name="format"]');
        const file = form.querySelector('[data-fingerprint-file]');
        const detect = form.querySelector('[data-fingerprint-detect]');
        const capture = form.querySelector('[data-fingerprint-capture]');
        const verify = form.querySelector('[data-fingerprint-verify]');
        const status = form.querySelector('[data-fingerprint-status]');

        const setStatus = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        const refreshConnectionStatus = async () => {
            setStatus('Verificando lector biometrico...');

            try {
                const devices = await detectFingerprintDevices();
                setStatus(devices.length ? `Lector conectado: ${devices.join(', ')}.` : 'No se reconoce ningun lector biometrico.');
            } catch (error) {
                setStatus(error.message);
            }
        };

        file?.addEventListener('change', async () => {
            const selected = file.files?.[0];

            if (!selected || !template) {
                return;
            }

            template.value = await selected.text();
            setStatus(`Plantilla cargada desde ${selected.name}.`);
        });

        detect?.addEventListener('click', async () => {
            detect.disabled = true;
            setStatus('Buscando lectores biometricos...');

            try {
                const devices = await detectarLector();
                setStatus(devices.length ? `Lector conectado: ${devices.join(', ')}.` : 'No se reconoce ningun lector biometrico.');
            } finally {
                detect.disabled = false;
            }
        });

        capture?.addEventListener('click', async () => {
            if (!template) {
                return;
            }

            capture.disabled = true;
            setStatus('Buscando lector biometrico...');

            try {
                const devices = await detectFingerprintDevices();

                if (!devices.length) {
                    setStatus('No se detecto un lector conectado. Carga un archivo o pega la plantilla.');
                    template.focus();

                    return;
                }

                setStatus('Coloca el dedo en el lector...');

                const sample = await captureFingerprintSample(devices[0], setStatus);
                template.value = sample;
                if (format) {
                    format.value = format.value || 'DigitalPersona Intermediate';
                }
                setStatus('Plantilla capturada correctamente.');
            } catch (error) {
                setStatus(error.message ?? 'No se pudo capturar la plantilla.');
            } finally {
                capture.disabled = false;
            }
        });

        verify?.addEventListener('click', async () => {
            if (!template) {
                return;
            }

            if (!fingerprintSampleIsValid(template.value.trim())) {
                setStatus('Primero captura o carga una plantilla valida para verificar.');
                template.focus();

                return;
            }

            verify.disabled = true;
            setStatus('Verificando lectura de huella...');

            try {
                const devices = await detectFingerprintDevices();

                if (!devices.length) {
                    setStatus('No se detecto un lector conectado para verificar.');

                    return;
                }

                setStatus('Coloca nuevamente el dedo en el lector...');

                const verificationSample = await captureFingerprintSample(devices[0], setStatus);

                if (!fingerprintSampleIsValid(verificationSample)) {
                    setStatus('El lector capturo datos, pero la huella no se reconoce como plantilla valida.');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Verificacion incompleta',
                        text: 'La lectura no genero una muestra biometrica valida. Intenta nuevamente.',
                    });

                    return;
                }

                await authenticateFingerprint(fingerprintIdentity(form), verificationSample);
                setStatus('Verificacion correcta: la huella coincide con el usuario.');
                Swal.fire({
                    icon: 'success',
                    title: 'Huella verificada',
                    text: 'DigitalPersona autentico la huella contra el usuario seleccionado.',
                });
            } catch (error) {
                setStatus(error.message ?? 'No se pudo verificar la huella.');
                Swal.fire({
                    icon: 'error',
                    title: 'Huella no verificada',
                    text: error.message ?? 'DigitalPersona no pudo autenticar la huella.',
                });
            } finally {
                verify.disabled = false;
            }
        });

        refreshConnectionStatus();
        form.dataset.fingerprintInitialized = '1';
    });
}

function initPlayerBiometricRegistration(scope = document) {
    scope.querySelectorAll('[data-player-biometric-registration]').forEach((form) => {
        if (form.dataset.playerBiometricInitialized === '1') {
            return;
        }

        const detect = form.querySelector('[data-player-biometric-detect]');
        const capture = form.querySelector('[data-player-biometric-capture]');
        const save = form.querySelector('[data-player-biometric-save]');
        const status = form.querySelector('[data-player-biometric-status]');
        const preview = form.querySelector('[data-player-biometric-preview]');
        const empty = form.querySelector('[data-player-biometric-empty]');
        const sampleError = form.querySelector('[data-error-for="sample_image"]');
        let capturedImage = '';
        let qualityScore = null;

        const setStatus = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        const setBusy = (busy) => {
            if (detect) {
                detect.disabled = busy;
            }

            if (capture) {
                capture.disabled = busy;
            }

            if (save) {
                save.disabled = busy || !capturedImage;
            }
        };

        detect?.addEventListener('click', async () => {
            setBusy(true);
            setStatus('Buscando lector biometrico...');

            try {
                const devices = await detectarLector();
                setStatus(devices.length ? `Lector conectado: ${devices.join(', ')}.` : 'No se reconoce ningun lector biometrico.');
            } finally {
                setBusy(false);
            }
        });

        capture?.addEventListener('click', async () => {
            setBusy(true);
            setStatus('Buscando lector biometrico...');
            if (sampleError) {
                sampleError.textContent = '';
            }

            try {
                const devices = await detectFingerprintDevices();

                if (!devices.length) {
                    setStatus('No se detecto ningun lector.');

                    return;
                }

                capturedImage = await captureFingerprintPng(devices[0], setStatus, (quality) => {
                    qualityScore = quality === FingerprintQuality.Good ? 100 : null;
                });

                if (preview) {
                    preview.src = `data:image/png;base64,${capturedImage}`;
                    preview.classList.remove('d-none');
                }

                empty?.classList.add('d-none');
                setStatus('Huella capturada. Puedes guardar el registro.');
            } catch (error) {
                capturedImage = '';
                setStatus(error.message ?? 'No se pudo capturar la huella.');
            } finally {
                setBusy(false);
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!capturedImage) {
                setStatus('Primero captura la huella del indice derecho.');

                return;
            }

            setBusy(true);
            setStatus('Guardando huella...');
            if (sampleError) {
                sampleError.textContent = '';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        sample_image: capturedImage,
                        quality_score: qualityScore,
                    }),
                });
                const payload = await response.json();

                if (response.status === 422) {
                    if (sampleError) {
                        sampleError.textContent = payload.errors?.sample_image?.[0] ?? payload.message ?? 'Revisa la huella capturada.';
                    }

                    throw new Error(payload.message ?? 'Revisa la huella capturada.');
                }

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message ?? 'No se pudo guardar la huella.');
                }

                toast.fire({ icon: 'success', title: payload.message ?? 'Huella guardada correctamente.' });
                setStatus('Huella guardada correctamente.');

                if (ajaxModalBody && form.dataset.showUrl) {
                    try {
                        ajaxModalTitle.textContent = 'Detalle de jugador';
                        ajaxModalBody.innerHTML = await fetchHtml(form.dataset.showUrl);
                        initPlayerPhotoForms(ajaxModalBody);
                        initPlayerBiometricRegistration(ajaxModalBody);
                    } catch (_error) {
                        ajaxModal?.hide();
                    }
                }
            } catch (error) {
                setStatus(error.message ?? 'No se pudo guardar la huella.');
                Swal.fire({ icon: 'error', title: 'Registro biometrico', text: error.message ?? 'No se pudo guardar la huella.' });
            } finally {
                setBusy(false);
            }
        });

        form.dataset.playerBiometricInitialized = '1';
    });
}

function initMatchdayDateSelectors(scope = document) {
    scope.querySelectorAll('[data-matchday-date-selector]').forEach((form) => {
        if (form.dataset.matchdayDatesInitialized === '1') {
            return;
        }

        const input = form.querySelector('[data-matchday-date-input]');
        const addButton = form.querySelector('[data-matchday-date-add]');
        const list = form.querySelector('[data-matchday-date-list]');
        const empty = form.querySelector('[data-matchday-date-empty]');

        if (!input || !addButton || !list) {
            return;
        }

        const selectedDates = new Set([...list.querySelectorAll('[data-matchday-date-chip]')].map((chip) => chip.dataset.date));
        const syncEmptyState = () => {
            if (empty) {
                empty.classList.toggle('d-none', selectedDates.size > 0);
            }
        };
        const formatDate = (date) => {
            const [year, month, day] = date.split('-');

            return `${day}/${month}/${year}`;
        };
        const addDate = (date) => {
            if (!date || selectedDates.has(date)) {
                input.value = '';
                syncEmptyState();

                return;
            }

            selectedDates.add(date);

            const chip = document.createElement('span');
            chip.className = 'badge text-bg-primary d-inline-flex align-items-center gap-1 me-1 mb-1';
            chip.dataset.matchdayDateChip = '';
            chip.dataset.date = date;
            chip.innerHTML = `
                ${formatDate(date)}
                <button class="btn-close btn-close-white ms-1" type="button" aria-label="Quitar fecha" data-matchday-date-remove></button>
                <input type="hidden" name="dates[]" value="${date}">
            `;
            list.appendChild(chip);
            input.value = '';
            syncEmptyState();
        };

        addButton.addEventListener('click', () => addDate(input.value));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addDate(input.value);
            }
        });
        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-matchday-date-remove]');

            if (!removeButton) {
                return;
            }

            const chip = removeButton.closest('[data-matchday-date-chip]');

            if (!chip) {
                return;
            }

            selectedDates.delete(chip.dataset.date);
            chip.remove();
            syncEmptyState();
        });
        syncEmptyState();
        form.dataset.matchdayDatesInitialized = '1';
    });
}

function initMatchdayFixtureFilters(scope = document) {
    scope.querySelectorAll('[data-matchday-fixture-filter]').forEach((form) => {
        if (form.dataset.fixtureFilterInitialized === '1') {
            return;
        }

        const tournamentSelect = form.querySelector('[data-fixture-tournament-select]');
        const groupSelect = form.querySelector('[data-fixture-group-select]');
        const container = form.parentElement?.querySelector('[data-fixture-matches-container]');
        const url = form.dataset.optionsUrl;

        if (!tournamentSelect || !groupSelect || !container || !url) {
            return;
        }

        const setOptions = (select, options, placeholder, selected = '') => {
            select.innerHTML = '';
            select.append(new Option(placeholder, ''));

            options.forEach((option) => {
                const item = new Option(option.label, option.value);
                item.selected = String(option.value) === String(selected);
                select.append(item);
            });
        };

        const currentParams = () => {
            const params = new URLSearchParams(window.location.search);

            params.delete('tournament_id');
            params.delete('fixture_group');
            params.delete('category_id');
            params.delete('series');
            params.delete('fixture_page');

            if (tournamentSelect.value) {
                params.set('tournament_id', tournamentSelect.value);
            }

            if (groupSelect.value) {
                const [categoryId = '', series = ''] = groupSelect.value.split('|');

                params.set('fixture_group', groupSelect.value);
                params.set('category_id', categoryId);
                params.set('series', series);
            }

            return params;
        };

        const updateAddress = (params) => {
            const query = params.toString();
            const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;

            window.history.replaceState({}, '', nextUrl);
        };

        const loadMatches = async ({ resetGroup = false, paginationUrl = null } = {}) => {
            const params = paginationUrl
                ? new URLSearchParams(new URL(paginationUrl, window.location.origin).search)
                : currentParams();

            if (resetGroup) {
                params.delete('fixture_group');
                params.delete('category_id');
                params.delete('series');
                params.delete('fixture_page');
            }

            groupSelect.disabled = true;
            container.classList.add('opacity-50');

            try {
                const response = await fetch(`${url}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudieron cargar los partidos del fixture.');
                }

                const payload = await response.json();
                const selectedGroup = resetGroup ? '' : (payload.selected_fixture_group ?? groupSelect.value);

                setOptions(groupSelect, payload.fixture_groups ?? [], 'Seleccionar', selectedGroup);
                groupSelect.disabled = !tournamentSelect.value;
                tournamentSelect.value = payload.selected_tournament_id ?? tournamentSelect.value;
                container.innerHTML = payload.html ?? '';
                updateAddress(params);
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Fixture', text: error.message });
                groupSelect.disabled = !tournamentSelect.value;
            } finally {
                container.classList.remove('opacity-50');
            }
        };

        tournamentSelect.addEventListener('change', () => {
            groupSelect.value = '';
            loadMatches({ resetGroup: true });
        });

        groupSelect.addEventListener('change', () => {
            loadMatches();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            loadMatches();
        });

        container.addEventListener('click', (event) => {
            const link = event.target.closest('[data-fixture-pagination] a');

            if (!link) {
                return;
            }

            event.preventDefault();
            loadMatches({ paginationUrl: link.href });
        });

        form.dataset.fixtureFilterInitialized = '1';
    });
}

function initMatchdayFiscalFilters(scope = document) {
    scope.querySelectorAll('[data-matchday-fiscal-filter]').forEach((container) => {
        if (container.dataset.fiscalFilterInitialized === '1') {
            return;
        }

        const tournamentSelect = container.querySelector('[data-fiscal-tournament-select]');
        const groupSelect = container.querySelector('[data-fiscal-group-select]');
        const modal = container.closest('.modal');
        const teamSelect = modal?.querySelector('[data-fiscal-team-select]');
        const assignmentForm = modal?.querySelector('[data-fiscal-assignment-form]');
        const emptyAlert = modal?.querySelector('[data-fiscal-filter-empty]');
        const url = container.dataset.optionsUrl;

        if (!tournamentSelect || !groupSelect || !teamSelect || !assignmentForm || !url) {
            return;
        }

        const hidden = {
            tournament: assignmentForm.querySelector('input[name="fiscal_tournament_id"]'),
            group: assignmentForm.querySelector('input[name="fiscal_fixture_group"]'),
            category: assignmentForm.querySelector('input[name="fiscal_category_id"]'),
            series: assignmentForm.querySelector('input[name="fiscal_series"]'),
        };

        const setOptions = (select, options, placeholder, selected = '') => {
            select.innerHTML = '';
            select.append(new Option(placeholder, ''));

            options.forEach((option) => {
                const item = new Option(option.label, option.value);
                item.selected = String(option.value) === String(selected);
                select.append(item);
            });
        };

        const setCandidates = (candidates) => {
            teamSelect.innerHTML = '';
            teamSelect.append(new Option('Seleccionar fiscal', ''));

            candidates.forEach((candidate) => {
                teamSelect.append(new Option(
                    `${candidate.team_name} (${candidate.fiscal_count} fiscalia(s) en la gestion)`,
                    candidate.team_id,
                ));
            });
        };

        const setFormEnabled = (enabled) => {
            assignmentForm.querySelectorAll('input[name="start_time"], input[name="end_time"], select[name="team_id"], button[type="submit"]').forEach((field) => {
                field.disabled = !enabled;
            });
            emptyAlert?.classList.toggle('d-none', enabled);
        };

        const syncHiddenValues = () => {
            const [categoryId = '', series = ''] = groupSelect.value.split('|');

            if (hidden.tournament) hidden.tournament.value = tournamentSelect.value;
            if (hidden.group) hidden.group.value = groupSelect.value;
            if (hidden.category) hidden.category.value = categoryId;
            if (hidden.series) hidden.series.value = series;
        };

        const loadOptions = async ({ resetGroup = false } = {}) => {
            const params = new URLSearchParams();

            if (tournamentSelect.value) {
                params.set('fiscal_tournament_id', tournamentSelect.value);
            }

            if (!resetGroup && groupSelect.value) {
                params.set('fiscal_fixture_group', groupSelect.value);
            }

            groupSelect.disabled = true;
            teamSelect.disabled = true;

            try {
                const response = await fetch(`${url}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('No se pudieron cargar los fiscales.');
                }

                const payload = await response.json();
                const selectedGroup = resetGroup ? '' : groupSelect.value;

                setOptions(groupSelect, payload.fixture_groups ?? [], 'Seleccionar', selectedGroup);
                groupSelect.disabled = !tournamentSelect.value;
                setCandidates(payload.candidates ?? []);
                syncHiddenValues();
                setFormEnabled(Boolean(groupSelect.value));
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Fiscalias', text: error.message });
                groupSelect.disabled = !tournamentSelect.value;
                setFormEnabled(false);
            }
        };

        tournamentSelect.addEventListener('change', () => {
            groupSelect.value = '';
            setCandidates([]);
            syncHiddenValues();
            setFormEnabled(false);
            loadOptions({ resetGroup: true });
        });

        groupSelect.addEventListener('change', () => {
            setCandidates([]);
            syncHiddenValues();
            setFormEnabled(Boolean(groupSelect.value));
            loadOptions();
        });

        syncHiddenValues();
        setFormEnabled(Boolean(groupSelect.value));
        container.dataset.fiscalFilterInitialized = '1';
    });
}

function resetScheduleTimeButton(button) {
    button?.classList.remove('btn-danger');
    button?.classList.add('btn-outline-primary');
}

function markScheduleTimeChanged(input) {
    const form = input.closest('form');
    const button = form?.querySelector('[data-schedule-time-save]');

    button?.classList.remove('btn-outline-primary');
    button?.classList.add('btn-danger');
}

function sortScheduledRows(tbody) {
    const rows = [...tbody.querySelectorAll('[data-scheduled-match-row]')];

    rows
        .sort((left, right) => {
            const leftTime = left.querySelector('[data-schedule-time-input]')?.value ?? '';
            const rightTime = right.querySelector('[data-schedule-time-input]')?.value ?? '';

            return leftTime.localeCompare(rightTime);
        })
        .forEach((row) => tbody.appendChild(row));
}

async function submitScheduleTimeForm(form) {
    const input = form.querySelector('[data-schedule-time-input]');
    const button = form.querySelector('[data-schedule-time-save]');

    if (!input || input.value === input.dataset.originalValue) {
        resetScheduleTimeButton(button);

        return;
    }

    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            const message = payload.message ?? 'No se pudo actualizar el horario.';

            throw new Error(message);
        }

        input.value = payload.data?.scheduled_time_label ?? input.value;
        input.dataset.originalValue = input.value;
        resetScheduleTimeButton(button);
        sortScheduledRows(form.closest('tbody'));
        toast.fire({ icon: 'success', title: payload.message ?? 'Horario actualizado.' });
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Horario', text: error.message });
    } finally {
        button.disabled = false;
    }
}

function refreshPermissionManager(manager) {
    const boxes = [...manager.querySelectorAll('[data-permission-checkbox]')];
    const checked = boxes.filter((box) => box.checked).length;
    const selectedCount = manager.querySelector('[data-permission-selected-count]');

    if (selectedCount) {
        selectedCount.textContent = checked;
    }

    manager.querySelectorAll('[data-permission-group]').forEach((group) => {
        const groupBoxes = [...group.querySelectorAll('[data-permission-checkbox]')];
        const groupChecked = groupBoxes.filter((box) => box.checked).length;
        const counter = group.querySelector('[data-permission-group-count]');

        if (counter) {
            counter.textContent = `${groupChecked}/${groupBoxes.length}`;
        }
    });

    boxes.forEach((box) => {
        const option = box.closest('.permission-option');
        option?.classList.toggle('bg-primary-lt', box.checked);
        option?.classList.toggle('border-primary', box.checked);
        option?.classList.toggle('bg-white', !box.checked);
    });
}

function initPermissionManagers(scope = document) {
    scope.querySelectorAll('[data-permission-manager]').forEach((manager) => {
        if (manager.dataset.permissionManagerInitialized === '1') {
            return;
        }

        manager.dataset.permissionManagerInitialized = '1';

        manager.addEventListener('change', (event) => {
            if (event.target.matches('[data-permission-checkbox]')) {
                refreshPermissionManager(manager);
            }
        });

        manager.querySelector('[data-permission-search]')?.addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();

            manager.querySelectorAll('[data-permission-item]').forEach((item) => {
                item.classList.toggle('d-none', term !== '' && !item.dataset.permissionLabel.includes(term));
            });

            manager.querySelectorAll('[data-permission-group]').forEach((group) => {
                const hasVisible = [...group.querySelectorAll('[data-permission-item]')]
                    .some((item) => !item.classList.contains('d-none'));
                group.classList.toggle('d-none', !hasVisible);
            });
        });

        manager.querySelector('[data-permission-clear]')?.addEventListener('click', () => {
            manager.querySelectorAll('[data-permission-checkbox]').forEach((box) => {
                box.checked = false;
            });
            refreshPermissionManager(manager);
        });

        manager.querySelectorAll('[data-permission-group-check]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('[data-permission-group]')?.querySelectorAll('[data-permission-checkbox]').forEach((box) => {
                    box.checked = true;
                });
                refreshPermissionManager(manager);
            });
        });

        manager.querySelectorAll('[data-permission-group-uncheck]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('[data-permission-group]')?.querySelectorAll('[data-permission-checkbox]').forEach((box) => {
                    box.checked = false;
                });
                refreshPermissionManager(manager);
            });
        });

        refreshPermissionManager(manager);
    });
}

function initMeetingAttendance(scope = document) {
    const search = scope.querySelector('[data-meeting-attendance-search]');

    if (search && search.dataset.meetingSearchInitialized !== '1') {
        search.dataset.meetingSearchInitialized = '1';
        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();
            const container = search.closest('.card') ?? document;

            container.querySelectorAll('[data-meeting-attendance-row]').forEach((row) => {
                row.classList.toggle('d-none', term !== '' && !row.dataset.teamName.includes(term));
            });
        });
    }

    scope.querySelectorAll('[data-meeting-attendance-toggle]').forEach((input) => {
        if (input.dataset.meetingToggleInitialized === '1') {
            return;
        }

        input.dataset.meetingToggleInitialized = '1';
        input.addEventListener('change', async () => {
            const original = !input.checked;
            const row = input.closest('[data-meeting-attendance-row]');
            const label = row?.querySelector('[data-meeting-attendance-label]');
            const status = row?.querySelector('[data-meeting-attendance-status]');
            const permissionForm = row?.querySelector('[data-meeting-permission-form]');

            input.disabled = true;

            try {
                const response = await fetch(input.dataset.url, {
                    method: 'POST',
                    body: new URLSearchParams({
                        _method: 'PATCH',
                        present: input.checked ? '1' : '0',
                    }),
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json();

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message ?? 'No se pudo registrar la asistencia.');
                }

                const present = Boolean(payload.data?.present);
                input.checked = present;

                if (label) {
                    label.textContent = present ? 'Presente' : 'Marcar asistencia';
                    label.classList.toggle('text-success', present);
                    label.classList.toggle('text-body-secondary', !present);
                }

                if (status) {
                    status.innerHTML = present
                        ? `<span class="badge text-bg-success">Presente</span><div class="text-body-secondary small">${payload.data?.attended_at ?? ''}</div>`
                        : '<span class="badge text-bg-secondary">Ausente</span>';
                }

                if (permissionForm) {
                    permissionForm.querySelector('input[name="permission_reason"]')?.toggleAttribute('disabled', present);
                    permissionForm.querySelector('button')?.toggleAttribute('disabled', present);
                }

                document.querySelector('[data-meeting-present-count]')?.replaceChildren(String(payload.data?.present_count ?? 0));
                document.querySelector('[data-meeting-permission-count]')?.replaceChildren(String(payload.data?.permission_count ?? 0));
                document.querySelector('[data-meeting-absent-count]')?.replaceChildren(String(payload.data?.absent_count ?? 0));
                document.querySelector('[data-meeting-total-count]')?.replaceChildren(String(payload.data?.total_count ?? 0));
                toast.fire({ icon: 'success', title: payload.message ?? 'Asistencia actualizada.' });
            } catch (error) {
                input.checked = original;
                Swal.fire({ icon: 'error', title: 'Asistencia', text: error.message });
            } finally {
                input.disabled = false;
            }
        });
    });

    scope.querySelectorAll('[data-meeting-permission-form]').forEach((form) => {
        if (form.dataset.meetingPermissionInitialized === '1') {
            return;
        }

        form.dataset.meetingPermissionInitialized = '1';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const row = form.closest('[data-meeting-attendance-row]');
            const toggle = row?.querySelector('[data-meeting-attendance-toggle]');
            const label = row?.querySelector('[data-meeting-attendance-label]');
            const status = row?.querySelector('[data-meeting-attendance-status]');
            const button = form.querySelector('button');
            const reason = form.querySelector('input[name="permission_reason"]');

            button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)),
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json();

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message ?? 'No se pudo solicitar el permiso.');
                }

                if (toggle) {
                    toggle.checked = false;
                }

                if (label) {
                    label.textContent = 'Marcar asistencia';
                    label.classList.remove('text-success');
                    label.classList.add('text-body-secondary');
                }

                if (status) {
                    status.innerHTML = `<span class="badge text-bg-warning">Permiso</span><div class="text-body-secondary small">${payload.data?.permission_requested_at ?? ''}</div>`;
                }

                if (reason) {
                    reason.disabled = true;
                }

                document.querySelector('[data-meeting-present-count]')?.replaceChildren(String(payload.data?.present_count ?? 0));
                document.querySelector('[data-meeting-permission-count]')?.replaceChildren(String(payload.data?.permission_count ?? 0));
                document.querySelector('[data-meeting-absent-count]')?.replaceChildren(String(payload.data?.absent_count ?? 0));
                document.querySelector('[data-meeting-total-count]')?.replaceChildren(String(payload.data?.total_count ?? 0));
                toast.fire({ icon: 'success', title: payload.message ?? 'Permiso solicitado.' });
            } catch (error) {
                button.disabled = false;
                Swal.fire({ icon: 'error', title: 'Permiso', text: error.message });
            }
        });
    });
}

showInitialAlerts();
disableBusinessFormAutocomplete();
initTomSelects();
initPunishmentPlayerFilters();
initRegistrationCategorySelects();
initTournamentCategorySelects();
initTournamentNamePreviews();
initTournamentSteppers();
initFixtureSteppers();
initMatchdayDateSelectors();
initMatchdayFixtureFilters();
initMatchdayFiscalFilters();
initTeamNameMatches();
initAffiliatePlayerLookup();
initHabilitationPlayerSearch();
initPlayerPhotoForms();
initLocalLocationAutocomplete();
initPublicPopup();
initPurchaseForm();
syncPointSaleWarehouse();
initPosSaleForm();
initDefragmentForms();
initTransferForms();
initStockAdjustmentForms();
initPermissionManagers();
initMeetingAttendance();
initUserDropdowns();
initSidebarToggle();
initCashExpenseModal();
initCashCloseModal();
initFingerprintForms();
initPlayerBiometricRegistration();
initAdminDataTables();
initRegistrationTeamNumberForms();
initMatchReportPlayerSearch();

document.addEventListener('click', (event) => {
    const modalTrigger = event.target.closest('[data-modal-url]');

    if (modalTrigger) {
        event.preventDefault();
        openAjaxModal(modalTrigger);

        return;
    }
});

document.addEventListener('change', (event) => {
    const scheduleTimeInput = event.target.closest('[data-schedule-time-input]');

    if (scheduleTimeInput) {
        markScheduleTimeChanged(scheduleTimeInput);
    }

    if (event.target.closest('[data-point-sale-branch]')) {
        syncPointSaleWarehouse(event.target.closest('form') ?? document);
    }

    const tournamentDivision = event.target.closest('[data-tournament-division]');

    if (tournamentDivision) {
        const form = tournamentDivision.closest('form') ?? document;
        const categorySelect = form.querySelector('[data-tournament-category]');

        if (categorySelect) {
            refreshTournamentCategorySelect(categorySelect, tournamentDivision, true);
        }

        refreshTournamentNamePreview(form);
    }

    if (event.target.closest('[data-tournament-category], [name="season_id"]')) {
        refreshTournamentNamePreview(event.target.closest('form') ?? document);
    }

    if (event.target.closest('[data-habilitation-autosubmit]')) {
        event.target.closest('form')?.submit();
    }
});

document.addEventListener('submit', (event) => {
    const scheduleTimeForm = event.target.closest('[data-schedule-time-form]');
    const ajaxForm = event.target.closest('[data-ajax-form]');
    const deleteForm = event.target.closest('[data-confirm-delete]');
    const matchFinishForm = event.target.closest('[data-confirm-match-finish]');
    const reopenMatchForm = event.target.closest('[data-confirm-reopen-match]');
    const resolveSeedsForm = event.target.closest('[data-confirm-resolve-seeds]');
    const meetingFinishForm = event.target.closest('[data-confirm-meeting-finish]');
    const voidPurchaseForm = event.target.closest('[data-confirm-void-purchase]');
    const voidSaleForm = event.target.closest('[data-confirm-void-sale]');

    if (reopenMatchForm && reopenMatchForm.dataset.confirmed !== '1') {
        event.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Editar partido finalizado',
            text: 'Se habilitara el partido para correcciones y debera finalizarse nuevamente.',
            showCancelButton: true,
            confirmButtonText: 'Si, editar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        }).then((result) => {
            if (result.isConfirmed) {
                reopenMatchForm.dataset.confirmed = '1';
                reopenMatchForm.submit();
            }
        });

        return;
    }

    if (resolveSeedsForm && resolveSeedsForm.dataset.confirmed !== '1') {
        event.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Resolver semillas',
            text: 'Se asignaran los clasificados segun la tabla final de posiciones.',
            showCancelButton: true,
            confirmButtonText: 'Si, resolver',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
        }).then((result) => {
            if (result.isConfirmed) {
                resolveSeedsForm.dataset.confirmed = '1';
                resolveSeedsForm.submit();
            }
        });

        return;
    }

    if (matchFinishForm && matchFinishForm.dataset.confirmed !== '1') {
        event.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Finalizar partido',
            text: 'Se cerrara el registro y se generara la planilla del partido.',
            showCancelButton: true,
            confirmButtonText: 'Si, finalizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        }).then((result) => {
            if (result.isConfirmed) {
                matchFinishForm.dataset.confirmed = '1';
                matchFinishForm.submit();
            }
        });

        return;
    }

    if (meetingFinishForm && meetingFinishForm.dataset.confirmed !== '1') {
        event.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Finalizar reunion',
            text: 'Se cerrara la asistencia y se abrira el reporte para imprimir.',
            showCancelButton: true,
            confirmButtonText: 'Si, finalizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        }).then((result) => {
            if (result.isConfirmed) {
                meetingFinishForm.dataset.confirmed = '1';
                meetingFinishForm.submit();
            }
        });

        return;
    }

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
        submitAjaxForm(ajaxForm);

        return;
    }

    if (scheduleTimeForm) {
        event.preventDefault();
        submitScheduleTimeForm(scheduleTimeForm);

        return;
    }
});
