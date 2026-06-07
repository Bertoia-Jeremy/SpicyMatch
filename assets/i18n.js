/* Minimal JS i18n bridge (CSP-safe).
 *
 * Server-side Twig renders a JSON dictionary into <script type="application/json"
 * id="js-i18n"> in <head> (see base.html.twig), filled via |trans from the `js`
 * translation domain. JSON scripts are inert (not executed) so they need no CSP
 * nonce, and they survive Turbo navigations because <head> is merged, not replaced.
 *
 * Usage in JS:  import { t } from './i18n.js';  t('favorites.added')
 * The optional 2nd arg is a fallback used if the key is missing (dev safety).
 */

let dict = null;
let cachedRaw = null;

// Cache indexé sur le contenu (pas le nœud) : re-lit si #js-i18n change après
// un rendu partiel, sans dépendre de la stratégie de merge <head> de Turbo.
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

/**
 * Translate a flat dotted key (e.g. 'favorites.added').
 * Falls back to the provided default, then to the key itself.
 */
export function t(key, fallback) {
    const d = load();
    if (Object.prototype.hasOwnProperty.call(d, key)) return d[key];
    return fallback !== undefined ? fallback : key;
}
