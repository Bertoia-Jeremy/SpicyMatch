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

    /* Navbar — overlay (desktop) + sheet (mobile). */
    Alpine.data('navMenu', () => ({
        open: false,
        mobileOpen: false,
        _keyHandler: null,

        init() {
            this.$watch('open', (v) => {
                document.body.style.overflow = v ? 'hidden' : '';
                const main = document.getElementById('main-content');
                if (main) main.inert = v;
                if (v) {
                    this.$nextTick(() => {
                        const first = this.$el.querySelector('#nav-overlay a, #nav-overlay button');
                        if (first) first.focus();
                    });
                }
            });
            this.$watch('mobileOpen', (v) => {
                document.body.style.overflow = v ? 'hidden' : '';
                if (v) {
                    this.$nextTick(() => {
                        const first = this.$el.querySelector('#nav-sheet input, #nav-sheet a, #nav-sheet button');
                        if (first) first.focus();
                    });
                }
            });
            this._keyHandler = (e) => {
                if (e.key === 'Escape') { this.open = false; this.mobileOpen = false; }
            };
            window.addEventListener('keydown', this._keyHandler);
        },

        destroy() {
            window.removeEventListener('keydown', this._keyHandler);
        },

        toggle()       { this.open = !this.open; },
        close()        { this.open = false; },
        mobileToggle() { this.mobileOpen = !this.mobileOpen; },
        mobileClose()  { this.mobileOpen = false; },

        toqueClass()        { return this.open ? 'is-open' : ''; },
        toqueLabel()        { return this.open ? 'Refermer' : 'Ouvrir'; },
        toqueColor()        { return this.open ? 'color: var(--color-paprika-700)' : 'color: var(--color-ink-deep)'; },
        mobileToqueClass()  { return this.mobileOpen ? 'is-open' : ''; },
        mobileToqueColor()  { return this.mobileOpen ? 'color: var(--color-paprika-700)' : 'color: var(--color-ink-deep)'; },
        mobileToqueLabel()  { return this.mobileOpen ? 'Fermer le menu' : 'Ouvrir le menu'; },
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

    Alpine.data('confirmQuit', (url) => ({
        quit() {
            if (confirm('Quitter la partie ? Ta progression sera perdue.')) {
                window.location.href = url;
            }
        },
    }));

    Alpine.data('answerOnce', () => ({
        answered: false,
        pick() { this.answered = true; },
        isAnswered() { return this.answered; },
    }));

    Alpine.data('hangmanKeyboard', () => ({
        pending: {},

        init() {
            new MutationObserver(() => { this.pending = {}; })
                .observe(this.$el, { attributes: true, attributeFilter: ['data-word-num'] });
        },

        guess(letter) {
            this.pending[letter] = true;
        },

        isPending(letter) {
            return !!this.pending[letter];
        },
    }));

    Alpine.data('guessWhoAutocomplete', (allNames) => ({
        query: '',
        suggestions: [],
        selectedIndex: -1,
        selectedGuess: '',

        init() {
            this.$nextTick(() => {
                if (this.$refs.queryInput) this.$refs.queryInput.focus();
            });
        },

        normalizeStr(s) {
            return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        },

        filter() {
            const q = this.normalizeStr(this.query);
            if (q.length < 2) {
                this.suggestions = [];
                this.selectedIndex = -1;
                return;
            }
            this.suggestions = allNames
                .filter(n => this.normalizeStr(n).includes(q))
                .slice(0, 6);
            this.selectedIndex = -1;
        },

        arrowDown() {
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.suggestions.length - 1);
        },

        arrowUp() {
            this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
        },

        pick(name) {
            this.selectedGuess = name;
            this.query = name;
            this.suggestions = [];
            this.selectedIndex = -1;
            const btn = this.$refs.submitGuessBtn;
            if (!btn) return;
            this.$nextTick(() => { if (btn.isConnected) btn.click(); });
        },

        submitEnter() {
            if (this.selectedIndex >= 0 && this.suggestions[this.selectedIndex]) {
                this.pick(this.suggestions[this.selectedIndex]);
                return;
            }
            const q = this.normalizeStr(this.query);
            const exact = allNames.find(n => this.normalizeStr(n) === q);
            if (exact) this.pick(exact);
        },

        closeSuggestions() {
            this.suggestions = [];
            this.selectedIndex = -1;
        },

        hasSuggestions() {
            return this.suggestions.length > 0;
        },

        suggestionClass(index) {
            return index === this.selectedIndex ? 'bg-saffron-50 text-saffron-800' : 'text-stone-800';
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
    Alpine.data('difficultySelector', (initial = 'easy') => ({
        difficulty: initial,
        pick(diff) { this.difficulty = diff; },
        buttonClass(diff) {
            return this.difficulty === diff
                ? 'border-saffron-500 bg-saffron-50 text-saffron-700 ring-2 ring-saffron-200'
                : 'border-stone-200 bg-white text-stone-700 hover:border-saffron-300 hover:bg-cream';
        },
        isSelected(diff) { return this.difficulty === diff; },
    }));

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

    /* ─── Finalisation du mélange L'Étamine (spicy_match/view) ──────────── */
    Alpine.data('finalisationMelange', (spiceIdsCsv, historyUrl, csrf) => ({
        spiceIds: spiceIdsCsv ? spiceIdsCsv.split(',') : [],
        spiceNames: {},
        current: null,
        results: {},
        toast: { visible: false, text: '' },
        _toastT: null,
        _autoT: null,
        _saveT: null,
        _abort: null,

        init() {
            this.spiceIds.forEach((id, i) => {
                this.spiceNames[id] = this.$el.dataset['spiceName' + i] || id;
                this.results[id] ??= { cooking: null, preparation: null };
            });
            this.current = this.spiceIds[0] ?? null;
        },

        /* ——— Computed ——— */
        get allSealed() {
            return this.spiceIds.every(id => this.results[id] && this.results[id].cooking && this.results[id].preparation);
        },
        get ctaLabel() {
            if (this.allSealed) return "Sceller l'association";
            const done = this.spiceIds.filter(id => this.results[id] && this.results[id].cooking && this.results[id].preparation).length;
            const total = this.spiceIds.length;
            if (total - done === 1) return "Une dernière épice à finaliser";
            return `${done} / ${total} épice(s) scellée(s)`;
        },

        /* ——— UI helpers (méthodes pour compatibilité CSP Alpine) ——— */
        isCurrentSpice(spiceId) {
            return this.current === spiceId;
        },
        stationClass(spiceId) {
            if (this.done(spiceId)) return 'done';
            if (spiceId === this.current) return 'active';
            return '';
        },
        pipClass(spiceId, key) {
            const r = this.results[spiceId] || {};
            if (r[key]) return 'filled';
            if (spiceId === this.current && this.expectedNext(spiceId) === key) return 'current';
            return '';
        },
        expectedNext(spiceId) {
            const r = this.results[spiceId] || {};
            if (!r.cooking) return 'cooking';
            if (!r.preparation) return 'preparation';
            return null;
        },
        statusLabel(spiceId) {
            const r = this.results[spiceId] || {};
            const isDone = r.cooking && r.preparation;
            if (isDone) return spiceId === this.current ? 'scellée ✓' : 'scellée';
            if (spiceId === this.current) return !r.cooking ? 'choisir le temps' : 'choisir la main';
            return (r.cooking || r.preparation) ? 'en cours' : 'à venir';
        },
        done(spiceId) {
            const r = this.results[spiceId] || {};
            return !!(r.cooking && r.preparation);
        },
        timingTileClass(spiceId, tipId) {
            const r = this.results[spiceId];
            return r && r.cooking === tipId ? 'selected' : '';
        },
        methodTileClass(spiceId, tipId) {
            const r = this.results[spiceId];
            return r && r.preparation === tipId ? 'selected' : '';
        },

        /* ——— Actions ——— */
        toggleCooking(spiceId, tipId) {
            const r = this.results[spiceId];
            r.cooking = (r.cooking === tipId) ? null : tipId;
            this.persist({ spiceId, cookingId: tipId });
            this.maybeAdvance(spiceId);
        },
        togglePreparation(spiceId, tipId) {
            const r = this.results[spiceId];
            r.preparation = (r.preparation === tipId) ? null : tipId;
            this.persist({ spiceId, preparationId: tipId });
            this.maybeAdvance(spiceId);
        },
        goToHistory(url) {
            if (this.allSealed) window.location.href = url;
        },

        /* ——— Auto-avance + toast ——— */
        maybeAdvance(spiceId) {
            clearTimeout(this._autoT);
            const r = this.results[spiceId];
            if (!r.cooking || !r.preparation) return;
            const next = this.spiceIds.find(id => id !== spiceId && !this.done(id));
            const label = this.spiceNames[spiceId] || '';
            if (!next) {
                this.showToast(`${label} scellée — mélange complet`);
                return;
            }
            this.showToast(`${label} scellée — ${this.spiceNames[next]} →`);
            this._autoT = setTimeout(() => {
                this.current = next;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 700);
        },
        scrollToMethodStep(spiceId) {
            setTimeout(() => {
                const el = document.querySelector('[data-step-method-spice="' + spiceId + '"]');
                if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 24, behavior: 'smooth' });
            }, 250);
        },
        showToast(text) {
            this.toast.text = text;
            this.toast.visible = true;
            clearTimeout(this._toastT);
            this._toastT = setTimeout(() => { this.toast.visible = false; }, 1500);
        },

        /* ——— Persistance (fetch vers edit_spicy_match_history) ——— */
        persist(params) {
            clearTimeout(this._saveT);
            this._saveT = setTimeout(async () => {
                if (this._abort) this._abort.abort();
                this._abort = new AbortController();
                try {
                    await fetch(historyUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrf,
                        },
                        body: new URLSearchParams(params),
                        signal: this._abort.signal,
                    });
                } catch (e) {
                    if (e.name !== 'AbortError') console.error('Persist error', e);
                }
            }, 150);
        },
    }));

    /* ─── Cooking finalization legacy (spicy_match/view) ─────────────────── */
    Alpine.data('cookingChecklist', (spiceIdsCsv = '') => ({
        spiceStatus: {},
        nextOpenId: null,
        spiceIds: spiceIdsCsv ? spiceIdsCsv.split(',').map(s => Number(s)) : [],
        totalSpices: 0,
        _prepHandler: null,
        _cookingHandler: null,
        init() {
            this.totalSpices = this.spiceIds.length;
            this._prepHandler = (e) => this.onPrep(e);
            this._cookingHandler = (e) => this.onCooking(e);
            window.addEventListener('preparation-updated', this._prepHandler);
            window.addEventListener('cooking-confirmed', this._cookingHandler);
        },
        destroy() {
            window.removeEventListener('preparation-updated', this._prepHandler);
            window.removeEventListener('cooking-confirmed', this._cookingHandler);
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
        _cookingHandler: null,
        toggle() { this.open = !this.open; },
        close() { this.open = false; },
        init() {
            this._cookingHandler = (e) => {
                if (Number(e.detail.spiceId) === this.spiceId && e.detail.selected) this.open = false;
            };
            window.addEventListener('cooking-confirmed', this._cookingHandler);
        },
        destroy() {
            window.removeEventListener('cooking-confirmed', this._cookingHandler);
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

    /* ─── Homepage — Toile des Arômes (système solaire moléculaire) ──── */
    Alpine.data('toile', () => {
        const makeMol = (id, x, y, accent, grad, delay, name, formula, badge, descr, shared) => ({
            id, x, y, accent, grad, delay, name, formula, badge, descr, shared,
            molStyle: `--mol-x:${x}px; --mol-y:${y}px; --mol-accent:${accent}; --mol-delay:${delay}; background:${grad}`,
        });

        const molecules = [
            makeMol('m1', -100, -120, '#D97706',
                'radial-gradient(circle at 35% 30%,#E8B547,#8a5a0c)', '0s',
                'Linalol', 'C₁₀H₁₈O', 'Terpène · Floral',
                "Note florale fraîche aux accents d'agrumes. Présent dans plus de 200 plantes aromatiques — c'est la signature parfumée de la lavande, mais aussi le secret de bien d'autres.",
                ['Coriandre', 'Basilic', 'Cardamome', 'Thym', '+ 14']),
            makeMol('m2', 112, -108, '#991B1B',
                'radial-gradient(circle at 35% 30%,#C04020,#5a0e0e)', '1.2s',
                'Pipérine', 'C₁₇H₁₉NO₃', 'Alcaloïde · Piquant',
                "Le composé qui donne au poivre noir son mordant caractéristique. Active les récepteurs TRPV1 — les mêmes que la capsaïcine, mais avec une douceur boisée.",
                ['Poivre noir', 'Poivre long', 'Cubèbe', '+ 6']),
            makeMol('m3', -180, 0, '#4D7C0F',
                'radial-gradient(circle at 35% 30%,#7AA31C,#2f4a08)', '2.4s',
                'α-Terpinéol', 'C₁₀H₁₈O', 'Terpène · Floral-Pin',
                "Arôme à la fois floral et résineux, comme du lilas frotté contre un pin. Apprécié en parfumerie autant qu'en cuisine méditerranéenne.",
                ['Cardamome', 'Marjolaine', 'Niaouli', '+ 9']),
            makeMol('m4', 188, -8, '#B87333',
                'radial-gradient(circle at 35% 30%,#B86A20,#5c2c08)', '0.7s',
                'Eugénol', 'C₁₀H₁₂O₂', 'Phénol · Boisé-Clou',
                "Le cœur du clou de girofle. Anesthésique léger, parfum dense — une signature impossible à oublier qui se loge dans la mémoire olfactive.",
                ['Clou de girofle', 'Cannelle', 'Muscade', 'Basilic', '+ 8']),
            makeMol('m5', -110, 160, '#D97706',
                'radial-gradient(circle at 35% 30%,#D97706,#7c3a04)', '3s',
                '1,8-Cinéole', 'C₁₀H₁₈O', 'Oxyde · Frais-Camphré',
                "Cette fraîcheur qu'on appelle parfois eucalyptée. Ouvre les voies respiratoires et la palette aromatique d'un plat avec la même franchise.",
                ['Cardamome', 'Romarin', 'Laurier', 'Sauge', '+ 11']),
            makeMol('m6', 130, 150, '#7c3a04',
                'radial-gradient(circle at 35% 30%,#6E4128,#2a160a)', '1.8s',
                'Géraniol', 'C₁₀H₁₈O', 'Terpène · Rose-Citrus',
                "L'âme parfumée du géranium et de la rose. En cuisine, un fil ténu entre la peau d'agrume et le pétale — à manier avec révérence.",
                ['Coriandre', 'Citronnelle', 'Carotte', '+ 7']),
        ];

        return {
            activeId: 'm1',
            molecules,
            get active() { return this.molecules.find(m => m.id === this.activeId); },
            setActive(id) { this.activeId = id; },
            isActive(id) { return this.activeId === id; },
            cardStyle() { return `border-color: ${this.active.accent}`; },
        };
    });
}
