import { Controller } from '@hotwired/stimulus';

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
