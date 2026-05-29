const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
let devicesModule;
let reader;

const FingerprintQuality = {
    Good: 0,
    NoImage: 1,
    TooLight: 2,
    TooDark: 3,
    TooNoisy: 4,
    NotCentered: 7,
    NotAFinger: 8,
    PressureTooHard: 19,
    PressureTooLight: 20,
    WetFinger: 21,
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

function normalizeDigitalPersonaError(error) {
    const message = error?.message ?? String(error ?? '');

    if (message.includes('Communication failure')) {
        return 'No se pudo conectar con DigitalPersona Agent. Verifica que el agente este instalado y ejecutandose en esta computadora.';
    }

    if (message.includes('DigitalPersona Devices no expone FingerprintReader')) {
        return 'DigitalPersona WebSDK no cargo correctamente. Recarga la pagina con Ctrl + F5.';
    }

    return message || 'No se pudo comunicar con el lector biometrico.';
}

function isCommunicationFailure(error) {
    const message = error?.message ?? String(error ?? '');

    return message.includes('Communication failure')
        || message.includes('No se pudo conectar con DigitalPersona Agent');
}

function resetDigitalPersonaSession() {
    try {
        window.sessionStorage?.removeItem('websdk.sessionId');
    } catch (_error) {
        // sessionStorage can be unavailable in restricted browser modes.
    }

    reader = null;
}

async function getDevicesModule() {
    if (!devicesModule) {
        devicesModule = resolveDigitalPersonaDevices(window.dp?.devices);

        if (!devicesModule) {
            console.debug('DigitalPersona Devices global no disponible:', window.dp);
            throw new Error('DigitalPersona Devices no expone FingerprintReader.');
        }
    }

    return devicesModule;
}

async function getReader({ fresh = false } = {}) {
    if (fresh) {
        resetDigitalPersonaSession();
    }

    if (!reader) {
        const { FingerprintReader } = await getDevicesModule();
        reader = new FingerprintReader();
    }

    return reader;
}

async function withDigitalPersonaRetry(callback) {
    try {
        return await callback(false);
    } catch (error) {
        if (!isCommunicationFailure(error)) {
            throw error;
        }

        resetDigitalPersonaSession();

        return callback(true);
    }
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

function extractPngImage(samples) {
    const sample = Array.isArray(samples) ? samples[0] : samples;

    if (!sample) {
        return '';
    }

    if (typeof sample === 'string') {
        return base64UrlToBase64(sample);
    }

    if (typeof sample.Data === 'string') {
        return base64UrlToBase64(sample.Data);
    }

    if (typeof sample.data === 'string') {
        return base64UrlToBase64(sample.data);
    }

    if (typeof sample.ImageData === 'string') {
        return base64UrlToBase64(sample.ImageData);
    }

    if (typeof sample.imageData === 'string') {
        return base64UrlToBase64(sample.imageData);
    }

    console.debug('Muestra de huella sin imagen PNG reconocible:', sample);

    return '';
}

function qualityMessage(quality) {
    const messages = {
        [FingerprintQuality.Good]: 'Huella capturada con buena calidad.',
        [FingerprintQuality.NoImage]: 'No se reconoce una huella. Coloca el dedo sobre el lector.',
        [FingerprintQuality.TooLight]: 'Presiona un poco mas.',
        [FingerprintQuality.TooDark]: 'Reduce un poco la presion.',
        [FingerprintQuality.TooNoisy]: 'Limpia el lector o intenta nuevamente.',
        [FingerprintQuality.NotCentered]: 'Centra el dedo en el lector.',
        [FingerprintQuality.NotAFinger]: 'No se reconoce un dedo sobre el lector.',
        [FingerprintQuality.PressureTooHard]: 'Estas presionando demasiado.',
        [FingerprintQuality.PressureTooLight]: 'Presiona un poco mas.',
        [FingerprintQuality.WetFinger]: 'Seca el dedo e intenta nuevamente.',
    };

    return messages[quality] ?? 'Leyendo huella...';
}

function fingerPositionLabel(position) {
    const labels = {
        right_thumb: 'Pulgar derecho',
        right_index: 'Indice derecho',
        right_middle: 'Medio derecho',
        right_ring: 'Anular derecho',
        right_little: 'Menique derecho',
        left_thumb: 'Pulgar izquierdo',
        left_index: 'Indice izquierdo',
        left_middle: 'Medio izquierdo',
        left_ring: 'Anular izquierdo',
        left_little: 'Menique izquierdo',
    };

    return labels[position] ?? position ?? 'No especificado';
}

async function capturePng(deviceUid, onStatus, onQuality) {
    return new Promise(async (resolve, reject) => {
        let settled = false;
        let timeout;
        let activeReader;

        try {
            activeReader = await getReader();
        } catch (error) {
            reject(new Error(error?.message ?? 'No se pudo cargar DigitalPersona WebSDK.'));

            return;
        }

        const cleanup = async () => {
            window.clearTimeout(timeout);
            activeReader.off('AcquisitionStarted', onStarted);
            activeReader.off('QualityReported', onQualityReported);
            activeReader.off('SamplesAcquired', onSamplesAcquired);
            activeReader.off('ErrorOccurred', onErrorOccurred);
            activeReader.off('CommunicationFailed', onCommunicationFailed);
            activeReader.off('DeviceDisconnected', onDisconnected);

            try {
                await activeReader.stopAcquisition(deviceUid);
            } catch (_error) {
                // The reader can already be stopped after a completed scan.
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

        const onStarted = () => onStatus('Esperando dedo...');
        const onCommunicationFailed = () => finish(reject, new Error('Communication failure.'));
        const onDisconnected = () => finish(reject, new Error('El lector se desconecto.'));
        const onErrorOccurred = (event) => finish(reject, new Error(`Error del lector: ${event.error ?? 'captura fallida'}.`));
        const onQualityReported = (event) => {
            onQuality(event.quality);
            onStatus(qualityMessage(event.quality));
        };
        const onSamplesAcquired = (event) => {
            const image = extractPngImage(event.samples);

            if (!image) {
                finish(reject, new Error('No se recibio una imagen PNG valida.'));

                return;
            }

            finish(resolve, image);
        };

        timeout = window.setTimeout(() => {
            finish(reject, new Error('Tiempo agotado esperando la huella.'));
        }, 30000);

        activeReader.on('AcquisitionStarted', onStarted);
        activeReader.on('QualityReported', onQualityReported);
        activeReader.on('SamplesAcquired', onSamplesAcquired);
        activeReader.on('ErrorOccurred', onErrorOccurred);
        activeReader.on('CommunicationFailed', onCommunicationFailed);
        activeReader.on('DeviceDisconnected', onDisconnected);

        try {
            const { SampleFormat } = await getDevicesModule();
            await activeReader.startAcquisition(SampleFormat.PngImage, deviceUid);
        } catch (error) {
            await cleanup();
            reject(new Error(error?.message ?? 'No se pudo iniciar la captura.'));
        }
    });
}

document.querySelectorAll('[data-biometric-test]').forEach((module) => {
    const status = module.querySelector('[data-biometric-status]');
    const detectButton = module.querySelector('[data-detect-reader]');
    const captureButton = module.querySelector('[data-capture-fingerprint]');
    const saveButton = module.querySelector('[data-save-fingerprint]');
    const verifyButton = module.querySelector('[data-verify-fingerprint]');
    const identifyButton = module.querySelector('[data-identify-fingerprint]');
    const fingerPosition = module.querySelector('[data-finger-position]');
    const preview = module.querySelector('[data-fingerprint-preview]');
    const empty = module.querySelector('[data-fingerprint-empty]');
    let capturedImage = '';
    let qualityScore = null;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const setBusy = (busy) => {
        if (detectButton) {
            detectButton.disabled = busy;
        }

        if (captureButton) {
            captureButton.disabled = busy;
        }

        if (saveButton) {
            saveButton.disabled = busy || !capturedImage;
        }

        if (verifyButton) {
            verifyButton.disabled = busy;
        }

        if (identifyButton) {
            identifyButton.disabled = busy;
        }
    };

    const captureFreshImage = async (initialStatus, retryStatus) => {
        setStatus(initialStatus);

        const devices = await withDigitalPersonaRetry(async (retrying) => {
            if (retrying) {
                setStatus('Reiniciando conexion con DigitalPersona Agent...');
            }

            const activeReader = await getReader({ fresh: retrying });

            return activeReader.enumerateDevices();
        });

        if (!devices.length) {
            throw new Error('No se detecto ningun lector.');
        }

        return withDigitalPersonaRetry(async (retrying) => {
            if (retrying) {
                setStatus(retryStatus);
            }

            return capturePng(devices[0], setStatus, () => {});
        });
    };

    detectButton?.addEventListener('click', async () => {
        setBusy(true);
        setStatus('Buscando lector...');

        try {
            const devices = await withDigitalPersonaRetry(async (retrying) => {
                if (retrying) {
                    setStatus('Reiniciando conexion con DigitalPersona Agent...');
                }

                const activeReader = await getReader({ fresh: retrying });

                return activeReader.enumerateDevices();
            });

            setStatus(devices.length ? `Lector detectado: ${devices.join(', ')}.` : 'No se detecto ningun lector.');
        } catch (error) {
            setStatus(normalizeDigitalPersonaError(error));
        } finally {
            setBusy(false);
        }
    });

    captureButton?.addEventListener('click', async () => {
        setBusy(true);
        setStatus('Buscando lector...');

        try {
            const devices = await withDigitalPersonaRetry(async (retrying) => {
                if (retrying) {
                    setStatus('Reiniciando conexion con DigitalPersona Agent...');
                }

                const activeReader = await getReader({ fresh: retrying });

                return activeReader.enumerateDevices();
            });

            if (!devices.length) {
                setStatus('No se detecto ningun lector.');

                return;
            }

            capturedImage = await withDigitalPersonaRetry(async (retrying) => {
                if (retrying) {
                    setStatus('Reintentando captura con una sesion nueva...');
                }

                return capturePng(devices[0], setStatus, (quality) => {
                    qualityScore = quality === FingerprintQuality.Good ? 100 : null;
                });
            });

            preview.src = `data:image/png;base64,${capturedImage}`;
            preview.classList.remove('d-none');
            empty.classList.add('d-none');
            setStatus('Huella capturada.');
        } catch (error) {
            capturedImage = '';
            setStatus(normalizeDigitalPersonaError(error));
        } finally {
            setBusy(false);
        }
    });

    saveButton?.addEventListener('click', async () => {
        if (!capturedImage) {
            return;
        }

        setBusy(true);
        setStatus('Guardando huella...');

        try {
            const response = await fetch(module.dataset.enrollUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    sample_image: capturedImage,
                    finger_position: fingerPosition?.value || null,
                    quality_score: qualityScore,
                }),
            });

            const payload = await response.json();

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? 'No se pudo guardar la huella.');
            }

            setStatus('Huella guardada.');
            window.Swal?.fire({ icon: 'success', title: 'Huella guardada', text: payload.message });
        } catch (error) {
            setStatus(error?.message ?? 'No se pudo guardar la huella.');
        } finally {
            setBusy(false);
        }
    });

    verifyButton?.addEventListener('click', async () => {
        setBusy(true);

        try {
            const candidateImage = await captureFreshImage(
                'Buscando lector para verificar...',
                'Reintentando verificacion con una sesion nueva...',
            );

            const response = await fetch(module.dataset.verifyUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    sample_image: candidateImage,
                    threshold: 40,
                }),
            });

            const payload = await response.json();

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? 'No se pudo verificar la huella.');
            }

            const result = payload.data ?? {};
            const match = result.match === true;
            const score = result.score ?? '-';
            const threshold = result.threshold ?? 40;

            preview.src = `data:image/png;base64,${candidateImage}`;
            preview.classList.remove('d-none');
            empty.classList.add('d-none');
            setStatus(`${payload.message} Score: ${score}. Umbral: ${threshold}.`);
            window.Swal?.fire({
                icon: match ? 'success' : 'warning',
                title: match ? 'Huella verificada' : 'Huella no coincide',
                text: `Score: ${score}. Umbral: ${threshold}.`,
            });
        } catch (error) {
            setStatus(error?.message ?? 'No se pudo verificar la huella.');
        } finally {
            setBusy(false);
        }
    });

    identifyButton?.addEventListener('click', async () => {
        setBusy(true);

        try {
            const candidateImage = await captureFreshImage(
                'Coloca el dedo que quieres buscar...',
                'Reintentando busqueda con una sesion nueva...',
            );

            const response = await fetch(module.dataset.identifyUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    sample_image: candidateImage,
                    threshold: 40,
                }),
            });

            const payload = await response.json();

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? 'No se pudo buscar la huella.');
            }

            const result = payload.data ?? {};
            const match = result.match === true;
            const score = result.score ?? '-';
            const threshold = result.threshold ?? 40;
            const finger = fingerPositionLabel(result.finger_position);

            preview.src = `data:image/png;base64,${candidateImage}`;
            preview.classList.remove('d-none');
            empty.classList.add('d-none');

            setStatus(match
                ? `Dedo identificado: ${finger}. Score: ${score}. Umbral: ${threshold}.`
                : `Sin coincidencia confiable. Mejor candidato: ${finger}. Score: ${score}. Umbral: ${threshold}.`);

            window.Swal?.fire({
                icon: match ? 'success' : 'warning',
                title: match ? `Dedo: ${finger}` : 'Sin coincidencia confiable',
                text: `Score: ${score}. Umbral: ${threshold}.`,
            });
        } catch (error) {
            setStatus(error?.message ?? 'No se pudo buscar la huella.');
        } finally {
            setBusy(false);
        }
    });
});
