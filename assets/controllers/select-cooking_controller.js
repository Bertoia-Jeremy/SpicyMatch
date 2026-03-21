import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['cooking'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        cookingId: Number,
    };

    #abortController = null;

    async selectCooking(event) {
        const card = event.currentTarget;
        const clickedId = parseInt(card.dataset.cookingId);
        const isDeselecting = this.cookingIdValue === clickedId;

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
                    cookingId: clickedId,
                }),
                signal: this.#abortController.signal,
            });
            if (!response.ok) { console.error('Cooking fetch failed', response.status); return; }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error('Cooking fetch error', e);
            return;
        }

        if (isDeselecting) {
            // Deselect: show all cards again
            this.cookingIdValue = 0;
            this.cookingTargets.forEach(t => {
                t.classList.remove('hidden');
                const inner = t.querySelector(':scope > div');
                if (inner) {
                    inner.classList.remove('border-saffron-500', 'bg-saffron-50/50', 'shadow-sm', 'ring-1', 'ring-saffron-400/30');
                    inner.classList.add('border-stone-200');
                }
            });
        } else {
            // Select: hide others, highlight selected
            this.cookingIdValue = clickedId;
            this.cookingTargets.forEach(t => {
                const inner = t.querySelector(':scope > div');
                if (t === card) {
                    inner?.classList.remove('border-stone-200');
                    inner?.classList.add('border-saffron-500', 'bg-saffron-50/50', 'shadow-sm', 'ring-1', 'ring-saffron-400/30');
                } else {
                    t.classList.add('hidden');
                }
            });
        }

        window.dispatchEvent(new CustomEvent('cooking-confirmed', {
            detail: { spiceId: this.spiceIdValue, selected: !isDeselecting },
        }));
    }

    disconnect() {
        if (this.#abortController) {
            this.#abortController.abort();
        }
    }
}
