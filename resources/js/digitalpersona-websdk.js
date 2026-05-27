const WebSdk = window.WebSdk;

if (!WebSdk) {
    console.warn('DigitalPersona WebSDK no esta cargado. Verifica el script vendor antes de app.js.');
}

export default WebSdk;
