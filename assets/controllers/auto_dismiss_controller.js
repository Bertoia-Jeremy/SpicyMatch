import { Controller } from '@hotwired/stimulus';

/**
 * Auto-hide a toast-like element after a delay. Hover pauses the timer.
 * Replaces the inline `x-data="{ show: true, timer: null }" x-init="setTimeout(...)"`
 * pattern used in gamification notification toasts.
 *
 * Usage:
 *   <div data-controller="auto-dismiss"
 *        data-auto-dismiss-delay-value="5000"
 *        data-auto-dismiss-pause-delay-value="2500"
 *        data-action="mouseenter->auto-dismiss#pause mouseleave->auto-dismiss#resume">
 *     ...toast content...
 *     <button data-action="auto-dismiss#dismiss">×</button>
 *   </div>
 */
export default class extends Controller {
    static values = {
        delay: { type: Number, default: 5000 },
        pauseDelay: { type: Number, default: 2500 },
    };

    connect() {
        this.scheduleDismiss(this.delayValue);
    }

    disconnect() {
        this.clearTimer();
    }

    pause() {
        this.clearTimer();
    }

    resume() {
        this.scheduleDismiss(this.pauseDelayValue);
    }

    dismiss() {
        this.clearTimer();
        this.element.remove();
    }

    scheduleDismiss(delay) {
        this.clearTimer();
        this.timer = window.setTimeout(() => {
            this.element.classList.add('opacity-0', 'transition-opacity', 'duration-200');
            window.setTimeout(() => this.element.remove(), 200);
        }, delay);
    }

    clearTimer() {
        if (this.timer) {
            window.clearTimeout(this.timer);
            this.timer = null;
        }
    }
}
