import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['cooking'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        cookingId: Number,
    };

    async selectCooking(event) {
        this.cookingIdValue = event.currentTarget.dataset.cookingId;
        const parentElement = this.cookingTarget.parentElement;

        const url = new URL(this.spicyMatchHistoryUrlValue, window.location.origin);
        url.searchParams.set('spiceId', this.spiceIdValue);
        url.searchParams.set('cookingId', this.cookingIdValue);

        try {
            const response = await fetch(url, { method: 'GET' });
            if (!response.ok) { console.error('Cooking fetch failed', response.status); return; }
            parentElement.innerHTML = await response.json();
        } catch (e) { console.error('Cooking fetch error', e); return; }

        // Signal au bloc Alpine parent de se replier + mettre à jour le compteur
        this.element.dispatchEvent(new CustomEvent('cooking-confirmed', {
            bubbles: true,
            detail: { spiceId: this.spiceIdValue },
        }));
    }
}
