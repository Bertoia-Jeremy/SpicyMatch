import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['preparation'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        preparationId: Number,
    };

    #abortController = null;

    async selectPreparation(event) {
        const card = event.currentTarget;
        const clickedId = parseInt(card.dataset.preparationId);
        const isDeselecting = this.preparationIdValue === clickedId;

        if (this.#abortController) {
            this.#abortController.abort();
        }
        this.#abortController = new AbortController();

        const csrfToken = this.element.querySelector('[name="_token"]')?.value || '';
        const url = new URL(this.spicyMatchHistoryUrlValue, window.location.origin);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: new URLSearchParams({
                    spiceId: this.spiceIdValue,
                    preparationId: clickedId,
                }),
                signal: this.#abortController.signal,
            });
            if (!response.ok) { console.error('Preparation fetch failed', response.status); return; }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error('Preparation fetch error', e);
            return;
        }

        if (isDeselecting) {
            // Deselect: show all cards again
            this.preparationIdValue = 0;
            this.preparationTargets.forEach(t => {
                t.classList.remove('hidden');
                const inner = t.querySelector(':scope > div');
                if (inner) {
                    inner.classList.remove('border-saffron-500', 'bg-saffron-50/50', 'shadow-sm', 'ring-1', 'ring-saffron-400/30');
                    inner.classList.add('border-stone-200');
                }
            });
        } else {
            // Select: hide others, highlight selected
            this.preparationIdValue = clickedId;
            this.preparationTargets.forEach(t => {
                const inner = t.querySelector(':scope > div');
                if (t === card) {
                    inner?.classList.remove('border-stone-200');
                    inner?.classList.add('border-saffron-500', 'bg-saffron-50/50', 'shadow-sm', 'ring-1', 'ring-saffron-400/30');
                } else {
                    t.classList.add('hidden');
                }
            });
        }

        window.dispatchEvent(new CustomEvent('preparation-updated', {
            detail: { spiceId: this.spiceIdValue, selected: !isDeselecting },
        }));
    }

    disconnect() {
        if (this.#abortController) {
            this.#abortController.abort();
        }
    }
}
