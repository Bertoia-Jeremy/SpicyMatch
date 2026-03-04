/**
 * Haptic feedback pour les interactions de gamification.
 * Utilise web-haptics (https://haptics.lochie.me/) via la Web Vibration API.
 * Silencieux sur desktop et iOS (non supporté) — aucune gestion d'erreur requise.
 *
 * Usage dans les templates Twig :
 *   <button data-haptic="nudge">...</button>
 *   <button data-haptic="success">...</button>
 *
 * Presets disponibles : success | nudge | error | buzz | light | medium | heavy
 */
import { WebHaptics } from 'web-haptics';

const haptics = new WebHaptics();

/**
 * Event delegation sur document — compatible Turbo Frames (pas besoin de
 * réattacher les listeners après chaque navigation partielle).
 */
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-haptic]');
    if (!el) return;
    haptics.trigger(el.dataset.haptic || 'medium');
});
