import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['preparation'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        preparationId: Number,
        selectedId: { type: Number, default: 0 },
    };

    async selectPreparation(event) {
        const clickedId = parseInt(event.currentTarget.dataset.preparationId);
        const isDeselecting = this.selectedIdValue === clickedId;

        this.preparationIdValue = clickedId;
        const parentElement = this.preparationTarget.parentElement;

        const url = new URL(this.spicyMatchHistoryUrlValue, window.location.origin);
        url.searchParams.set('spiceId', this.spiceIdValue);
        url.searchParams.set('preparationId', this.preparationIdValue);

        try {
            const response = await fetch(url, { method: 'GET' });
            if (!response.ok) { console.error('Preparation fetch failed', response.status); return; }
            parentElement.innerHTML = await response.json();
        } catch (e) { console.error('Preparation fetch error', e); return; }

        this.selectedIdValue = isDeselecting ? 0 : clickedId;

        this.element.dispatchEvent(new CustomEvent('preparation-updated', {
            bubbles: true,
            detail: { spiceId: this.spiceIdValue, selected: !isDeselecting },
        }));
    }
}
