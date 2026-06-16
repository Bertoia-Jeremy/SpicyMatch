import { Controller } from '@hotwired/stimulus';
import { t } from '../i18n.js';

/**
 * Wall-clock countdown timer for Live Component games.
 * Replaces the inline `x-data` timer in ChronoGame and HangmanGame templates,
 * eliminating one of the main obstacles to a CSP without `script-src 'unsafe-inline'`.
 *
 * Usage:
 *   <div data-controller="game-timer"
 *        data-game-timer-expires-at-value="{{ this.expiresAt }}"
 *        data-game-timer-total-seconds-value="{{ this.timeLimit }}"
 *        data-game-timer-timeout-action-value="timeout">
 *     <span data-game-timer-target="label"></span>
 *     <div data-game-timer-target="bar"></div>
 *     <button data-game-timer-target="timeoutButton" class="hidden"
 *             data-action="live#action"
 *             data-live-action-param="timeout"></button>
 *   </div>
 *
 * The controller never mutates the DOM beyond its targets — safe under morphdom
 * re-renders as long as the containing element has `data-live-ignore`.
 */
export default class extends Controller {
    static targets = ['label', 'bar', 'timeoutButton', 'announcer'];

    static values = {
        // Unix timestamp (seconds) at which the game expires.
        expiresAt: Number,
        // Total countdown duration (seconds) — used to compute the progress bar %.
        totalSeconds: Number,
        // CSS class thresholds (optional overrides).
        dangerThreshold: { type: Number, default: 5 },
        warningThreshold: { type: Number, default: 15 },
    };

    connect() {
        const nowMs = Date.now();
        this.deadlineMs = this.totalSecondsValue > 0
            ? nowMs + this.totalSecondsValue * 1000
            : this.expiresAtValue * 1000;
        this.render();
        this.interval = window.setInterval(() => this.render(), 250);
    }

    disconnect() {
        if (this.interval) {
            window.clearInterval(this.interval);
            this.interval = null;
        }
    }

    render() {
        const remainingMs = Math.max(0, this.deadlineMs - Date.now());
        const remaining = Math.ceil(remainingMs / 1000);

        if (this.hasLabelTarget) {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            this.labelTarget.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
            this.labelTarget.classList.toggle('text-paprika-600', remaining <= this.dangerThresholdValue);
            this.labelTarget.classList.toggle('text-stone-700', remaining > this.dangerThresholdValue);
        }

        // Annonce SR toutes les 10 s (par décade : insensible aux ticks sautés)
        const decade = Math.floor(remaining / 10);
        if (this.hasAnnouncerTarget && remaining > 0 && decade !== this.lastAnnounced) {
            this.lastAnnounced = decade;
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            const time = minutes > 0 ? `${minutes} min ${seconds} s` : `${seconds} s`;
            this.announcerTarget.textContent = t('timer.remaining', '%time% restantes').replace('%time%', time);
        }

        if (this.hasBarTarget && this.totalSecondsValue > 0) {
            const pct = Math.min(100, Math.max(0, (remainingMs / (this.totalSecondsValue * 1000)) * 100));
            this.barTarget.style.width = `${pct}%`;
            this.barTarget.classList.remove('bg-saffron-500', 'bg-turmeric-500', 'bg-paprika-700');
            if (remaining <= this.dangerThresholdValue) {
                this.barTarget.style.backgroundColor = 'var(--color-paprika-700)';
            } else if (remaining <= this.warningThresholdValue) {
                this.barTarget.style.backgroundColor = 'var(--color-turmeric-500)';
            } else {
                this.barTarget.style.backgroundColor = 'var(--color-saffron-500)';
            }
        }

        if (remainingMs <= 0) {
            if (this.interval) {
                window.clearInterval(this.interval);
                this.interval = null;
            }
            if (this.hasTimeoutButtonTarget) {
                this.timeoutButtonTarget.click();
            }
        }
    }
}
