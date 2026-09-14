import { Controller } from '@hotwired/stimulus';
import { t } from '../i18n.js';

export default class extends Controller {
    static targets = ['label', 'bar', 'timeoutButton', 'announcer'];

    static values = {
        expiresAt: Number,
        totalSeconds: Number,
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
