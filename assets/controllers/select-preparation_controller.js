import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['preparation'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        preparationId: Number,
    };

    async selectPreparation(event) {
        this.preparationIdValue = event.currentTarget.dataset.preparationId;
        const parentElement = this.preparationTarget.parentElement;

        const url = new URL(this.spicyMatchHistoryUrlValue, window.location.origin);
        url.searchParams.set('spiceId', this.spiceIdValue);
        url.searchParams.set('preparationId', this.preparationIdValue);

        const response = await fetch(url, { method: 'GET' });
        parentElement.innerHTML = await response.json();
    }
}
