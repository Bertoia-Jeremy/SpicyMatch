import { Controller } from '@hotwired/stimulus';
import $ from 'jquery';

export default class extends Controller 
{
    static targets = ['preparation'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        preparationId: Number
    };
    
    async selectPreparation(event) {
        this.preparationIdValue = event.currentTarget.dataset.preparationId;
        const parentElement = this.preparationTarget.parentElement;

        const response = await $.ajax({
            url: this.spicyMatchHistoryUrlValue,
            method: 'GET',
            data: {
                spiceId: this.spiceIdValue,
                preparationId: this.preparationIdValue,
            },
        });
        
        parentElement.innerHTML = response;
    }
}
