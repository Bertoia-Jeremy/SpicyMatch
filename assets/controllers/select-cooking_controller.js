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

    connect() 
    {
    }
    
    /*selectCooking(event) 
    {
        
        
        
        
        this.showOrHide(clickedCooking);
        
    }*/
    
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
        console.log(response);
        parentElement.innerHTML = response;
    }

    showOrHide(clickedCooking)
    {
        if (clickedCooking == this.cookingIdValue) {
            this.cookingIdValue = null;

            this.cookingTargets.forEach((element) => {
                    element.classList.remove('card-hide');
                    element.classList.add('card-show');
                });
            } else {
                this.cookingIdValue = clickedCooking;
                
                this.cookingTargets.forEach((element) => {
                    if (element.dataset.cookingId != this.cookingIdValue) {
                    element.classList.remove('card-show');
                    element.classList.add('card-hide');
                }
            });
        }
    }

    // Récupérer l'id du cooking, lui ajouter une classe selected, fondre les autres en arriere
    // Via un appelle ajax, ajouter le cooking au spicyMatchHistoryEdit
    // Faire les vérif dessus 
        // si c'est un int
        // vérifier que l'historyMatch appartient bien au user
        // si l'épice appartient bien à l'historyMatch
        // si le cooking appartient bien à l'épice
    // Enregistrer en BDD, mais il faut avoir un history créé dès le début 
    // Si on re-clique sur le selectionner, le déselectionner, faire apparaitre les autres, et l'enlever du historyMatch, refaire les vérifs
}
