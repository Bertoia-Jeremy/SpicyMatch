import { Controller } from '@hotwired/stimulus';
import $ from 'jquery';

export default class extends Controller 
{
    static targets = ['cooking'];
    static values = {
        spicyMatchHistoryUrl: String,
        spiceId: Number,
        cookingId: Number
    };
    
    async selectCooking(event) {
        this.cookingIdValue = event.currentTarget.dataset.cookingId;
        const parentElement = this.cookingTarget.parentElement;

        const response = await $.ajax({
            url: this.spicyMatchHistoryUrlValue,
            method: 'GET',
            data: {
                spiceId: this.spiceIdValue,
                cookingId: this.cookingIdValue,
            },
        });
        
        parentElement.innerHTML = response;
    }
}
