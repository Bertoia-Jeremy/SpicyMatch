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

        const response = await fetch(url, { method: 'GET' });
        parentElement.innerHTML = await response.json();

        // Signal au bloc Alpine parent de se replier
        this.element.dispatchEvent(new CustomEvent('cooking-confirmed', { bubbles: true }));
    }
}
