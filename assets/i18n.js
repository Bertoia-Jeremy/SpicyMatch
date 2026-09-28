
let dict = null;
let cachedRaw = null;

function load() {
    const el = document.getElementById('js-i18n');
    const raw = el ? (el.textContent || '{}') : '{}';
    if (raw === cachedRaw && dict !== null) return dict;

    cachedRaw = raw;
    try {
        dict = JSON.parse(raw);
    } catch (e) {
        console.error('js-i18n: invalid JSON payload', e);
        dict = {};
    }
    return dict;
}

export function t(key, fallback) {
    const d = load();
    if (Object.prototype.hasOwnProperty.call(d, key)) return d[key];
    return fallback !== undefined ? fallback : key;
}
