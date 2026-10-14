if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
function connectionState() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}
window.addEventListener('online', connectionState);
window.addEventListener('offline', connectionState);
connectionState();
