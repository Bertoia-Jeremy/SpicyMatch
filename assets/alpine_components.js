/* Alpine.data() registry — all components used across the SpicyMatch UI.
 *
 * Required for CSP Phase 2 (ADR-007): with the `@alpinejs/csp` build:
 *   - x-data MUST reference a registered Alpine.data() name (no inline literals)
 *   - Directive expressions allow ONLY: identifiers, member access, method calls
 *     (with literal args). NO ternary, NO arrow functions, NO `document.*`,
 *     NO `$watch` callbacks inline.
 *
 * Therefore every conditional class / text uses a helper METHOD on the
 * component, e.g. `:class="chevronClass()"` instead of `:class="open ? 'a' : 'b'"`.
 */

const apiFetch = async (url, options = {}) => {
    const res = await fetch(url, {
        ...options,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res;
};

const toast = (message, icon) => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, icon } }));
};

export default function registerAlpineComponents(Alpine) {
    /* ─── Generic toggles / modals / accordions ──────────────────────── */
    Alpine.data('toggle', (initial = false) => ({
        open: initial,
        show: initial,
        toggle() { this.open = !this.open; this.show = this.open; },
        openIt() { this.open = true; this.show = true; },
        close() { this.open = false; this.show = false; },
        chevronClass() { return this.open ? '' : '-rotate-180'; },
    }));

    /* Navbar mobile drawer — toggles `inert` on <main> and focuses first link. */
    Alpine.data('mobileMenu', () => ({
        open: false,
        init() {
            this.$watch('open', (val) => {
                const main = document.getElementById('main-content');
                if (main) main.inert = val;
                if (val) {
                    this.$nextTick(() => {
                        const first = this.$el.querySelector('.drawer-body a, .drawer-body button');
                        if (first) first.focus();
                    });
                }
            });
        },
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
        barTopClass() { return this.open ? 'rotate-45 translate-y-1.75' : ''; },
        barMiddleClass() { return this.open ? 'opacity-0 scale-x-0' : ''; },
        barBottomClass() { return this.open ? '-rotate-45 -translate-y-1.75' : ''; },
    }));

    Alpine.data('dropdown', () => ({
        dropdownOpen: false,
        toggle() { this.dropdownOpen = !this.dropdownOpen; },
        close() { this.dropdownOpen = false; },
        chevronClass() { return this.dropdownOpen ? 'rotate-180' : ''; },
    }));

    Alpine.data('passwordVisibility', () => ({
        show: false,
        toggle() { this.show = !this.show; },
        label() { return this.show ? 'Masquer' : 'Afficher'; },
        inputType() { return this.show ? 'text' : 'password'; },
    }));

    Alpine.data('equipButton', () => ({
        equipping: false,
        async equip(evt) {
            if (this.equipping) return;
            this.equipping = true;
            const btn = evt.currentTarget;
            const url = btn.dataset.equipUrl;
            const token = btn.dataset.equipToken;
            const redirect = btn.dataset.equipRedirect;
            try {
                await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_token=' + encodeURIComponent(token),
                });
                if (window.Turbo) {
                    window.Turbo.visit(redirect, { action: 'replace' });
                } else {
                    window.location.href = redirect;
                }
            } catch (e) {
                console.error('Equip error', e);
                this.equipping = false;
            }
        },
    }));

    Alpine.data('submitOnceGrid', () => ({
        selected: null,
        pick(id) {
            this.selected = id;
        },
        cardClass(id) {
            return this.selected === id ? 'ring-2 ring-saffron-500 border-saffron-400' : '';
        },
    }));

    Alpine.data('hangmanKeyboard', () => ({
        submitting: false,
        guess() {
            if (this.submitting) return false;
            this.submitting = true;
            return true;
        },
    }));

    /* ─── Layout / global ─────────────────────────────────────────────── */
    Alpine.data('scrollTop', () => ({
        visible: false,
        init() {
            const onScroll = () => { this.visible = window.scrollY > 400; };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        },
        scrollUp() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    }));

    Alpine.data('notificationHost', () => ({
        toasts: [],
        onToast(evt) {
            const t = { id: Date.now(), message: evt.detail.message, icon: evt.detail.icon || 'fa-solid fa-check' };
            this.toasts.push(t);
            setTimeout(() => {
                this.toasts = this.toasts.filter(x => x.id !== t.id);
            }, 2000);
        },
    }));

    /* ─── Spice catalog widgets ───────────────────────────────────────── */
    Alpine.data('spicesLimit', (total = 9999) => ({
        gridMode: false,
        limit: 8,
        total,
        setGrid() {
            this.gridMode = false;
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('carousel:reinit')));
        },
        setList() { this.gridMode = true; },
        hasMore() { return this.limit < this.total; },
        showMore() { this.limit += 8; },
        sliderToggleClass() { return this.gridMode ? 'text-stone-400' : 'bg-white shadow-sm text-saffron-600'; },
        gridToggleClass() { return this.gridMode ? 'bg-white shadow-sm text-saffron-600' : 'text-stone-400'; },
        isVisible(index) { return index <= this.limit; },
    }));

    Alpine.data('quickSpiceView', () => ({
        modalOpen: false,
        selectedUrl: null,
        fullPageUrl: null,
        openSpice(evt) {
            this.selectedUrl = evt.detail?.url ?? null;
            this.fullPageUrl = evt.detail?.fullUrl ?? null;
            this.modalOpen = true;
        },
        close() {
            this.modalOpen = false;
            this.selectedUrl = null;
            this.fullPageUrl = null;
        },
        modalSrc() { return this.modalOpen ? this.selectedUrl : null; },
    }));

    Alpine.data('counter', (initial = 0) => ({
        count: initial,
        bump(delta = 1) { this.count += delta; },
        set(v) { this.count = v; },
    }));

    Alpine.data('favoriteCount', (initial = 0) => ({
        count: initial,
        onToast(evt) {
            const msg = evt.detail?.message;
            if (msg === 'Ajouté aux favoris') this.count++;
            else if (msg === 'Supprimé des favoris') this.count--;
        },
    }));

    Alpine.data('spiceFilters', (initialCount = 0) => ({
        activeCount: initialCount,
        submitForm() {
            this.$refs.filterForm.requestSubmit();
        },
        onRadioChange() {
            this.activeCount = this.$refs.filterForm.querySelectorAll('input[type=radio]:checked').length;
            this.submitForm();
        },
        onSearchInput(evt) {
            const textCount = evt.target.value ? 1 : 0;
            const radioCount = this.$refs.filterForm.querySelectorAll('input[type=radio]:checked').length;
            this.activeCount = textCount + radioCount;
            this.submitForm();
        },
        resetForm() {
            this.$refs.filterForm.querySelectorAll('input[type=radio], input[type=checkbox]').forEach(i => { i.checked = false; });
            this.$refs.filterForm.querySelectorAll('select').forEach(s => { s.value = ''; });
            this.activeCount = 0;
            this.$refs.filterForm.requestSubmit();
        },
    }));

    /* ─── Education ───────────────────────────────────────────────────── */
    Alpine.data('modeSelector', (defaultMode = '', defaultDifficulty = 'easy') => ({
        selectedMode: defaultMode,
        selectedDifficulty: defaultDifficulty,
        pickMode(mode) { this.selectedMode = mode; },
        pickDifficulty(diff) { this.selectedDifficulty = diff; },
        modeCardClass(mode) {
            return this.selectedMode === mode
                ? 'ring-2 ring-saffron-500 border-saffron-400 bg-spice-surface'
                : 'border-stone-200 bg-white hover:bg-cream';
        },
        difficultyButtonClass(diff) {
            return this.selectedDifficulty === diff
                ? 'bg-saffron-600 text-white'
                : 'bg-stone-100 text-stone-700 hover:bg-stone-200';
        },
    }));

    Alpine.data('qcmForm', () => ({
        selected: null,
        submitted: false,
        startTime: Date.now(),
        elapsed() { return Date.now() - this.startTime; },
        pick(name) { this.selected = name; },
        submit() { this.submitted = true; },
        canSubmit() { return !this.selected || this.submitted; },
        optionSelectedClass(name) {
            return this.selected === name
                ? 'border-saffron-500 bg-saffron-50 ring-2 ring-saffron-500/30'
                : 'border-stone-200 hover:border-saffron-300 hover:bg-cream';
        },
    }));

    /* ─── Registration ────────────────────────────────────────────────── */
    Alpine.data('registrationTracker', () => ({
        selected: null,
        submitted: false,
        startTime: Date.now(),
        markSubmitted() { this.submitted = true; },
        pick(value) { this.selected = value; },
    }));

    /* ─── SpicyMatchHistory: rename + favorite ────────────────────────── */
    Alpine.data('historyItem', (id, renameUrl, toggleUrl, token, initialTitle = '', initialFavorite = false, fallbackTitle = '') => ({
        id,
        renameUrl,
        toggleUrl,
        token,
        editing: false,
        title: initialTitle,
        favorite: initialFavorite,
        fallbackTitle,
        init() {
            this.$watch('editing', (val) => {
                if (val) {
                    this.$nextTick(() => {
                        if (this.$refs.titleInput) this.$refs.titleInput.focus();
                    });
                }
            });
        },
        displayTitle() { return this.title || this.fallbackTitle; },
        startEdit() { this.editing = true; },
        cancelEdit() { this.editing = false; },
        starButtonClass() {
            return this.favorite
                ? 'text-turmeric-500 hover:text-turmeric-400'
                : 'text-stone-300 hover:text-turmeric-400';
        },
        starIconClass() {
            return this.favorite ? 'fa-solid fa-star' : 'fa-regular fa-star';
        },
        starLabel() {
            return this.favorite ? 'Dans vos favoris' : 'Ajouter aux favoris';
        },
        starTitle() {
            return this.favorite ? 'Retirer des favoris' : 'Ajouter aux favoris';
        },
        async saveTitle() {
            const t = this.title.trim();
            try {
                await apiFetch(this.renameUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title: t, _token: this.token }),
                });
                this.editing = false;
            } catch (e) { console.error('Rename error', e); }
        },
        async toggleFavorite() {
            try {
                const res = await apiFetch(this.toggleUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': this.token },
                });
                const data = await res.json();
                this.favorite = data.favorite;
                toast(
                    data.favorite ? 'Ajouté aux favoris' : 'Supprimé des favoris',
                    data.favorite ? 'fa-solid fa-star' : 'fa-regular fa-star',
                );
            } catch (e) { console.error('Toggle error', e); }
        },
    }));

    Alpine.data('favoriteRemover', (toggleUrl, token) => ({
        toggleUrl,
        token,
        removed: false,
        async removeFavorite() {
            try {
                await apiFetch(this.toggleUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': this.token },
                });
                this.removed = true;
                toast('Supprimé des favoris', 'fa-regular fa-star');
            } catch (e) { console.error('Toggle error', e); }
        },
    }));

    /* ─── Cooking finalization (spicy_match/view) ─────────────────────── */
    Alpine.data('cookingChecklist', (spiceIdsCsv = '') => ({
        spiceStatus: {},
        nextOpenId: null,
        spiceIds: spiceIdsCsv ? spiceIdsCsv.split(',').map(s => Number(s)) : [],
        totalSpices: 0,
        init() {
            this.totalSpices = this.spiceIds.length;
            window.addEventListener('preparation-updated', (e) => this.onPrep(e));
            window.addEventListener('cooking-confirmed', (e) => this.onCooking(e));
        },
        onPrep(e) {
            const id = Number(e.detail.spiceId);
            const cur = this.spiceStatus[id] || { prep: false, cooking: false };
            this.spiceStatus = { ...this.spiceStatus, [id]: { ...cur, prep: !!e.detail.selected } };
        },
        onCooking(e) {
            const id = Number(e.detail.spiceId);
            const sel = !!e.detail.selected;
            const cur = this.spiceStatus[id] || { prep: false, cooking: false };
            this.spiceStatus = { ...this.spiceStatus, [id]: { ...cur, cooking: sel } };
            if (sel) {
                const idx = this.spiceIds.indexOf(id);
                if (idx >= 0 && idx < this.spiceIds.length - 1) {
                    this.nextOpenId = this.spiceIds[idx + 1];
                }
            }
        },
        isReady(id) {
            const s = this.spiceStatus[Number(id)];
            return !!(s && s.prep && s.cooking);
        },
        isNotReady(id) { return !this.isReady(id); },
        get readyCount() {
            return Object.values(this.spiceStatus).filter(s => s && s.prep && s.cooking).length;
        },
        get allReady() { return this.readyCount >= this.totalSpices; },
        headerBorderClass(id) {
            return this.isReady(id) ? 'border-emerald-300' : 'border-spice-border';
        },
        iconCircleClass(id) {
            return this.isReady(id)
                ? 'bg-emerald-100 border border-emerald-400 text-emerald-600'
                : 'bg-spice-surface border border-spice-border text-saffron-700';
        },
        titleClass(id) {
            return this.isReady(id) ? 'text-emerald-800' : 'text-stone-900';
        },
        ctaClass() {
            return this.allReady ? '' : 'opacity-40 pointer-events-none cursor-not-allowed';
        },
        ctaLabel() {
            return this.allReady ? 'Voir ma recette finale' : 'Finalise la préparation de chaque épice';
        },
        ctaHref(finalUrl) { return this.allReady ? finalUrl : '#'; },
        get notAllReady() { return !this.allReady; },
    }));

    Alpine.data('cookingAccordion', (spiceId = 0, initiallyOpen = false) => ({
        spiceId: Number(spiceId),
        open: initiallyOpen,
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
        init() {
            window.addEventListener('cooking-confirmed', (e) => {
                if (Number(e.detail.spiceId) === this.spiceId && e.detail.selected) this.open = false;
            });
        },
        onNextOpen(nextOpenId) {
            if (Number(nextOpenId) === this.spiceId) {
                this.open = true;
                this.$nextTick(() => requestAnimationFrame(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' })));
            }
        },
        chevronClass() { return this.open ? '' : '-rotate-180'; },
    }));

    /* ─── RGPD / onboarding (inline scripts extracted) ────────────────── */
    Alpine.data('cookieConsent', () => ({
        visible: false,
        analytics: false,
        functional: true,

        init() {
            const version = Number(this.$el.dataset.consentVersion || '0');
            try {
                const cookie = document.cookie.split('; ').find(r => r.startsWith('sm_consent='));
                if (!cookie) { this.visible = true; return; }
                const data = JSON.parse(decodeURIComponent(cookie.split('=')[1]));
                if ((data.version || 0) < version) this.visible = true;
            } catch (e) { this.visible = true; }
        },

        async accept(analytics, functional) {
            const url = this.$el.dataset.consentUrl;
            const token = this.$el.dataset.consentToken;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ analytics, functional, _token: token }),
                });
                if (!res.ok) { console.error('Consent save failed', res.status); return; }
                this.visible = false;
            } catch (e) { console.error('Consent save error', e); }
        },

        acceptAll() { return this.accept(true, true); },
        acceptChoices() { return this.accept(this.analytics, this.functional); },
        rejectAll() { return this.accept(false, false); },
    }));

    Alpine.data('onboardingWelcome', () => ({
        visible: false,
        previouslyFocused: null,

        init() {
            this.$watch('visible', (val) => {
                document.querySelectorAll('main, nav, footer').forEach(el => { el.inert = val; });
            });
            const check = () => {
                const state = localStorage.getItem('sm_onboarding');
                if (!state && window.location.pathname === '/') {
                    setTimeout(() => {
                        if (localStorage.getItem('sm_onboarding')) return;
                        if (window.location.pathname !== '/') return;
                        this.previouslyFocused = document.activeElement;
                        this.visible = true;
                        this.$nextTick(() => this.trapFocus());
                    }, 600);
                }
            };
            check();
            document.addEventListener('turbo:load', () => check());
        },

        trapFocus() {
            const focusable = this.$el.querySelectorAll(
                'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );
            if (focusable.length) focusable[0].focus();
        },

        handleTab(e) {
            const focusable = [...this.$el.querySelectorAll(
                'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )];
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        },

        start() {
            localStorage.setItem('sm_onboarding', 'spices');
            this.visible = false;
            if (this.previouslyFocused) this.previouslyFocused.focus();
        },

        skip() {
            localStorage.setItem('sm_onboarding', 'done');
            this.visible = false;
            if (this.previouslyFocused) this.previouslyFocused.focus();
        },
    }));

    Alpine.data('spotlightTour', () => ({
        active: false,
        tooltipActive: false,
        paused: false,
        currentStep: 0,
        steps: [],
        totalSteps: 0,
        tourKey: null,
        targetEl: null,
        spotlightStyle: {},
        tooltipStyle: {},
        currentTitle: '',
        currentText: '',
        showTransition: false,
        transitionTitle: '',
        transitionUrl: '',
        tours: {},
        get isLastStep() { return this.currentStep + 1 >= this.steps.length; },
        nextLabel() { return this.isLastStep ? 'Terminé !' : 'Suivant →'; },
        stepDotClass(i) {
            return i <= this.currentStep + 1
                ? 'w-5 h-1.5 bg-saffron-500 rounded-full'
                : 'w-1.5 h-1.5 bg-stone-300 rounded-full';
        },
        _resizeHandler: null,
        _targetClickHandler: null,
        _modalObserver: null,
        _modalPollInterval: null,

        init() {
            try {
                this.tours = JSON.parse(this.$el.dataset.toursJson || '{}');
            } catch (e) {
                console.error('spotlightTour: invalid tours JSON', e);
                this.tours = {};
            }
            const check = () => this.checkTour();
            check();
            document.addEventListener('turbo:load', () => setTimeout(check, 100));
            document.addEventListener('turbo:before-visit', () => this.cleanup());
        },

        checkTour() {
            if (this.paused) return;
            if (!localStorage.getItem('sm_onboarding')) return;

            const state = localStorage.getItem('sm_onboarding');
            if (!state || state === 'done') return;

            const tour = this.tours[state];
            if (!tour) return;
            if (!window.location.pathname.startsWith(tour.path)) return;

            this.tourKey = state;
            this.steps = tour.steps;
            this.totalSteps = tour.steps.length;
            this.currentStep = 0;
            this.showTransition = false;

            localStorage.setItem('sm_onboarding', tour.nextState || 'done');

            this.$nextTick(() => this.startStep());
        },

        startStep() {
            const step = this.steps[this.currentStep];
            if (!step) return this.finishTour();
            const target = document.querySelector(step.target);
            if (!target) {
                this.currentStep++;
                return this.$nextTick(() => this.startStep());
            }
            this.targetEl = target;
            this.currentTitle = step.title;
            this.currentText = step.text;
            this.active = true;
            this.positionSpotlight(target);
            this.$nextTick(() => {
                this.positionTooltip(target, step.position || 'bottom');
                this.tooltipActive = true;
            });
            if (!step.noClickAdvance) {
                this._targetClickHandler = () => setTimeout(() => this.next(), 250);
                target.addEventListener('click', this._targetClickHandler, { once: true });
            }
            this._resizeHandler = () => {
                if (this.targetEl) {
                    this.positionSpotlight(this.targetEl);
                    this.positionTooltip(this.targetEl, step.position || 'bottom');
                }
            };
            window.addEventListener('resize', this._resizeHandler);
            window.addEventListener('scroll', this._resizeHandler, { passive: true });
        },

        positionSpotlight(el) {
            const rect = el.getBoundingClientRect();
            this.spotlightStyle = {
                top: (rect.top + window.scrollY - 8) + 'px',
                left: (rect.left + window.scrollX - 8) + 'px',
                width: (rect.width + 16) + 'px',
                height: (rect.height + 16) + 'px',
            };
        },

        positionTooltip(el, position) {
            const rect = el.getBoundingClientRect();
            const margin = 12;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const tooltipW = Math.min(320, vw - margin * 2);
            const tooltipH = 200;

            // Auto-fallback: si position 'left'/'right' ne tient pas, force vertical
            let resolved = position || 'bottom';
            if (resolved === 'left' && rect.left < tooltipW + margin) {
                resolved = (rect.bottom + tooltipH + margin < vh) ? 'bottom' : 'top';
            }
            if (resolved === 'right' && vw - rect.right < tooltipW + margin) {
                resolved = (rect.bottom + tooltipH + margin < vh) ? 'bottom' : 'top';
            }
            // Auto-fallback vertical: si pas de place en bas, passe en haut (et vice-versa)
            if (resolved === 'bottom' && rect.bottom + tooltipH + margin > vh && rect.top > tooltipH + margin) {
                resolved = 'top';
            }
            if (resolved === 'top' && rect.top < tooltipH + margin && vh - rect.bottom > tooltipH + margin) {
                resolved = 'bottom';
            }

            let top; let left;
            if (resolved === 'top') {
                top = rect.top + window.scrollY - tooltipH - margin;
                left = rect.left + window.scrollX + (rect.width / 2) - (tooltipW / 2);
            } else if (resolved === 'left') {
                top = rect.top + window.scrollY;
                left = rect.left + window.scrollX - tooltipW - margin;
            } else if (resolved === 'right') {
                top = rect.top + window.scrollY;
                left = rect.right + window.scrollX + margin;
            } else {
                top = rect.bottom + window.scrollY + margin;
                left = rect.left + window.scrollX + (rect.width / 2) - (tooltipW / 2);
            }

            // Clamp dans le viewport (left + top)
            const minLeft = window.scrollX + margin;
            const maxLeft = window.scrollX + vw - tooltipW - margin;
            left = Math.max(minLeft, Math.min(left, maxLeft));
            const minTop = window.scrollY + margin;
            const maxTop = window.scrollY + vh - tooltipH - margin;
            top = Math.max(minTop, Math.min(top, maxTop));

            this.tooltipStyle = {
                top: top + 'px',
                left: left + 'px',
                width: tooltipW + 'px',
            };
        },

        next() {
            this.currentStep++;
            this.cleanupStep();
            if (this.currentStep >= this.steps.length) return this.finishTour();
            this.startStep();
        },

        skip() {
            localStorage.setItem('sm_onboarding', 'done');
            this.cleanup();
            this.active = false;
            this.tooltipActive = false;
        },

        finishTour() {
            this.cleanup();
            const tour = this.tours[this.tourKey];
            if (tour && tour.nextUrl && tour.transitionTitle) {
                this.transitionTitle = tour.transitionTitle;
                this.transitionUrl = tour.nextUrl;
                this.showTransition = true;
            } else {
                this.active = false;
                this.tooltipActive = false;
            }
        },

        acceptTransition() {
            this.showTransition = false;
            if (this.transitionUrl) window.location.href = this.transitionUrl;
        },

        skipTransition() {
            localStorage.setItem('sm_onboarding', 'done');
            this.showTransition = false;
            this.active = false;
            this.tooltipActive = false;
        },

        cleanupStep() {
            if (this._targetClickHandler && this.targetEl) {
                this.targetEl.removeEventListener('click', this._targetClickHandler);
            }
            this._targetClickHandler = null;
            this.targetEl = null;
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                window.removeEventListener('scroll', this._resizeHandler);
            }
            this._resizeHandler = null;
            if (this._modalObserver) {
                this._modalObserver.disconnect();
                this._modalObserver = null;
            }
            if (this._modalPollInterval) {
                clearInterval(this._modalPollInterval);
                this._modalPollInterval = null;
            }
            this.active = false;
            this.tooltipActive = false;
        },

        cleanup() {
            this.cleanupStep();
            this.showTransition = false;
        },
    }));
}
