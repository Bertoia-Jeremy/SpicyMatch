import { Controller } from '@hotwired/stimulus';

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
    static targets = ['label', 'bar', 'timeoutButton'];

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
        this.render();
        this.interval = window.setInterval(() => this.render(), 1000);
    }

    disconnect() {
        if (this.interval) {
            window.clearInterval(this.interval);
            this.interval = null;
        }
    }

    render() {
        const nowSec = Math.floor(Date.now() / 1000);
        const remaining = Math.max(0, this.expiresAtValue - nowSec);

        if (this.hasLabelTarget) {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            this.labelTarget.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
            this.labelTarget.classList.toggle('text-paprika-600', remaining <= this.dangerThresholdValue);
            this.labelTarget.classList.toggle('text-stone-700', remaining > this.dangerThresholdValue);
        }

        if (this.hasBarTarget && this.totalSecondsValue > 0) {
            const pct = Math.max(0, (remaining / this.totalSecondsValue) * 100);
            this.barTarget.style.width = `${pct}%`;
            // Toggle colors by threshold.
            this.barTarget.classList.remove('bg-saffron-500', 'bg-turmeric-500', 'bg-paprika-500');
            if (remaining <= this.dangerThresholdValue) {
                this.barTarget.classList.add('bg-paprika-500');
            } else if (remaining <= this.warningThresholdValue) {
                this.barTarget.classList.add('bg-turmeric-500');
            } else {
                this.barTarget.classList.add('bg-saffron-500');
            }
        }

        if (remaining <= 0) {
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
