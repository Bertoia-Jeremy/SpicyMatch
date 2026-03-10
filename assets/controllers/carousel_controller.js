import { Controller } from '@hotwired/stimulus';
import EmblaCarousel from 'embla-carousel';

export default class extends Controller {
    static targets = ['viewport', 'prev', 'next'];
    static values = {
        options: { type: Object, default: { align: 'start', loop: false, skipSnaps: false, dragFree: true } }
    }

    connect() {
        if (!this.hasViewportTarget) return;

        // Initialisation de l'instance Embla
        this.embla = EmblaCarousel(this.viewportTarget, this.optionsValue);

        // Ecouter les changements pour mettre à jour l'état des boutons
        const onSelect = () => this.updateButtons();

        this.embla.on('select', onSelect);
        this.embla.on('init', onSelect);
        this.embla.on('reInit', onSelect);

        // Mise à jour initiale
        this.updateButtons();

        // ReInit après le prochain frame de rendu pour gérer le cas où Alpine
        // aurait masqué/démasqué l'élément avant la mesure initiale d'Embla
        requestAnimationFrame(() => {
            this.embla?.reInit();
            this.updateButtons();
        });

        // Écoute l'événement dispatché par Alpine quand le slider redevient visible
        this._reinitHandler = () => {
            requestAnimationFrame(() => {
                this.embla?.reInit();
                this.updateButtons();
            });
        };
        window.addEventListener('carousel:reinit', this._reinitHandler);
    }

    disconnect() {
        if (this._reinitHandler) {
            window.removeEventListener('carousel:reinit', this._reinitHandler);
        }
        if (this.embla) {
            this.embla.destroy();
        }
    }

    // Actions appelées par les boutons
    prev(event) {
        if (event) event.preventDefault();
        if (this.embla) this.embla.scrollPrev();
    }

    next(event) {
        if (event) event.preventDefault();
        if (this.embla) this.embla.scrollNext();
    }

    updateButtons() {
        if (!this.embla) return;

        const canPrev = this.embla.canScrollPrev();
        const canNext = this.embla.canScrollNext();

        if (this.hasPrevTarget) {
            this.prevTarget.disabled = !canPrev;
            // On ajoute une classe visuelle pour le feedback
            this.prevTarget.classList.toggle('opacity-30', !canPrev);
            this.prevTarget.classList.toggle('cursor-not-allowed', !canPrev);
        }

        if (this.hasNextTarget) {
            this.nextTarget.disabled = !canNext;
            this.nextTarget.classList.toggle('opacity-30', !canNext);
            this.nextTarget.classList.toggle('cursor-not-allowed', !canNext);
        }
    }
}
