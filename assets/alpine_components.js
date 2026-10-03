
import { t } from './i18n.js';

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

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const focusFirst = (root) => {
    const el = root.querySelector(FOCUSABLE);
    if (el) el.focus();
};

const nextTab = (event, tabs, current) => {
    const i = tabs.indexOf(current);
    const moves = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 };
    if (!(event.key in moves)) return null;
    event.preventDefault();
    return tabs[(moves[event.key] + tabs.length) % tabs.length];
};

const AD_SCRIPTS = {
    ethicalads: 'https://media.ethicalads.io/media/client/ethicalads.min.js',
    carbon: 'https://cdn.carbonads.com/carbon.js',
};

const loadEthicalAds = () => {
    if (window.ethicalads) {
        window.ethicalads.load();
        return;
    }
    if (document.querySelector('script[data-ad-script="ethicalads"]')) return;
    const script = document.createElement('script');
    script.async = true;
    script.src = AD_SCRIPTS.ethicalads;
    script.dataset.adScript = 'ethicalads';
    script.addEventListener('load', () => window.ethicalads?.load());
    document.head.appendChild(script);
};

const loadCarbon = (slot, serve) => {
    if (!serve || document.getElementById('_carbonads_js')) return;
    const script = document.createElement('script');
    script.async = true;
    script.id = '_carbonads_js';
    script.src = `${AD_SCRIPTS.carbon}?serve=${encodeURIComponent(serve)}&placement=spicymatch`;
    slot.appendChild(script);
};

const TOUR_STORE_KEY = 'sm_tours';
const TOUR_SKIP_ALL = '*';
const WELCOME_TOUR = 'welcome';

const readTours = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(TOUR_STORE_KEY) || '{}');
        return stored && typeof stored === 'object' ? stored : {};
    } catch {
        return {};
    }
};

const tourSeen = (key, version) => {
    const stored = readTours();
    return Boolean(stored[TOUR_SKIP_ALL]) || (Number(stored[key]) || 0) >= version;
};

const markTourSeen = (key, version) => {
    try {
        localStorage.setItem(TOUR_STORE_KEY, JSON.stringify({ ...readTours(), [key]: version }));
        return true;
    } catch {
        return false;
    }
};

const resetTours = () => {
    try {
        localStorage.removeItem(TOUR_STORE_KEY);
        return true;
    } catch {
        return false;
    }
};

const isGateOpen = () => {
    const gate = document.querySelector('[x-data="gateLoginModal"]');
    return Boolean(gate && window.Alpine?.$data(gate)?.open);
};

const loopTab = (e, root) => {
    const focusable = [...root.querySelectorAll(FOCUSABLE)].filter((el) => el.getClientRects().length > 0);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
    }
};

export default function registerAlpineComponents(Alpine) {
    Alpine.data('toggle', (initial = false) => ({
        open: initial,
        show: initial,
        toggle() { this.open = !this.open; this.show = this.open; },
        openIt() { this.open = true; this.show = true; },
        close() { this.open = false; this.show = false; },
        chevronClass() { return this.open ? '' : '-rotate-180'; },
    }));

    Alpine.data('deleteAccount', () => ({
        confirming: false,
        get idle() { return !this.confirming; },
        ask() { this.confirming = true; },
        cancel() { this.confirming = false; },
    }));

    Alpine.data('navMenu', () => ({
        open: false,
        profileOpen: false,
        _returnFocus: null,
        _keyHandler: null,
        _gateHandler: null,
        _cacheHandler: null,

        init() {
            this.$watch('open', (v) => {
                this._lockPage(v);
                if (v) {
                    this.profileOpen = false;
                    this.$nextTick(() => this._focusPanel());
                } else {
                    this._restoreFocus();
                }
            });
            this._keyHandler = (e) => {
                if (e.key === 'Escape') {
                    if (this.profileOpen) this.closeProfile(true);
                    else if (this.open) this.close();
                }
                if (e.key === 'Tab' && this.open) loopTab(e, this.$el);
            };
            window.addEventListener('keydown', this._keyHandler);
            this._gateHandler = () => this._reset();
            window.addEventListener('gate-login', this._gateHandler);
            this._cacheHandler = () => this._reset();
            document.addEventListener('turbo:before-cache', this._cacheHandler);
        },

        destroy() {
            window.removeEventListener('keydown', this._keyHandler);
            window.removeEventListener('gate-login', this._gateHandler);
            document.removeEventListener('turbo:before-cache', this._cacheHandler);
            this._lockPage(false);
        },

        _lockPage(locked) {
            document.documentElement.classList.toggle('nav-locked', locked);
            const main = document.getElementById('main-content');
            if (main) main.inert = locked;
            const footer = document.querySelector('footer');
            if (footer) footer.inert = locked;
        },

        _focusPanel() {
            const panel = document.getElementById('nav-panel');
            if (!panel) return;
            const desktop = window.matchMedia('(min-width: 64rem)').matches;
            const target = (desktop && panel.querySelector('input:not([disabled])'))
                || panel.querySelector('.nav-panel-grid a[href]')
                || panel.querySelector(FOCUSABLE);
            if (target) target.focus();
        },

        _restoreFocus() {
            let el = this._returnFocus;
            this._returnFocus = null;
            if (!el || !el.isConnected || el === document.body || !el.getClientRects().length) {
                el = [...this.$el.querySelectorAll('[aria-controls="nav-panel"]')].find((b) => b.getClientRects().length);
            }
            if (el) el.focus();
        },

        _reset() {
            this._returnFocus = null;
            this.open = false;
            this.profileOpen = false;
            this._lockPage(false);
            this.$el.querySelectorAll('.is-open').forEach((el) => el.classList.remove('is-open'));
            this.$el.querySelectorAll('[aria-expanded="true"]').forEach((el) => el.setAttribute('aria-expanded', 'false'));
        },

        toggle() {
            if (this.open) { this.close(); return; }
            this._returnFocus = document.activeElement;
            this.open = true;
        },
        close() { this.open = false; },

        toggleProfile() {
            if (this.open) { this._returnFocus = null; this.open = false; }
            this.profileOpen = !this.profileOpen;
        },
        closeProfile(restore) {
            if (!this.profileOpen) return;
            this.profileOpen = false;
            if (restore && this.$refs.profileTrigger) this.$refs.profileTrigger.focus();
        },
        onProfileFocusOut(e) {
            if (e.relatedTarget && !e.currentTarget.contains(e.relatedTarget)) this.closeProfile(false);
        },

        toqueLabel() { return this.open ? t('nav.close') : t('nav.open'); },
        mobileToqueLabel() { return this.open ? t('nav.menu_close') : t('nav.menu_open'); },
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
        label() { return this.show ? t('password.hide') : t('password.show'); },
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
            if (confirm(t('game.quit_confirm'))) {
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

            window.addEventListener('keydown', (e) => {
                const letter = e.key.toUpperCase();
                if (!/^[A-Z]$/.test(letter)) return;
                const btn = this.$el.querySelector(`button[data-letter="${letter}"]`);
                if (btn && !btn.disabled) btn.click();
            });
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

        optionId(index) {
            return 'gwg-opt-' + index;
        },

        isSelectedOption(index) {
            return index === this.selectedIndex ? 'true' : 'false';
        },

        activeDescendant() {
            return this.selectedIndex >= 0 ? 'gwg-opt-' + this.selectedIndex : null;
        },
    }));

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

    Alpine.data('quickSpiceView', () => ({
        modalOpen: false,
        selectedUrl: null,
        fullPageUrl: null,
        previouslyFocused: null,
        openSpice(evt) {
            this.selectedUrl = evt.detail?.url ?? null;
            this.fullPageUrl = evt.detail?.fullUrl ?? null;
            this.previouslyFocused = document.activeElement;
            this.modalOpen = true;
            this.$nextTick(() => focusFirst(this.$el));
        },
        close() {
            this.modalOpen = false;
            this.selectedUrl = null;
            this.fullPageUrl = null;
            if (this.previouslyFocused) {
                this.previouslyFocused.focus();
                this.previouslyFocused = null;
            }
        },
        handleTab(e) {
            if (!this.modalOpen) return;
            loopTab(e, this.$el);
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
            if (msg === t('favorites.added')) this.count++;
            else if (msg === t('favorites.removed')) this.count--;
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

    Alpine.data('registrationTracker', () => ({
        selected: null,
        submitted: false,
        startTime: Date.now(),
        markSubmitted() { this.submitted = true; },
        pick(value) { this.selected = value; },
    }));

    Alpine.data('recetteView', () => ({
        tab: 'timeline',
        openSpiceId: null,
        favorite: false,
        favError: false,
        favPending: false,
        favTarget: null,
        title: '',
        placeholder: '',
        wakeLock: null,
        cook: { active: false, step: 0, total: 1, done: {} },
        _wakeSentinel: null,
        _onBeforeCache: null,
        _onVisibility: null,

        init() {
            const d = this.$root.dataset;
            this.favorite = d.favorite === '1';
            this.title = d.title || '';
            this.placeholder = d.placeholder || '';
            this.cook.total = parseInt(d.cookSteps, 10) || 1;

            this._onBeforeCache = () => this.exitCook(false);
            this._onVisibility = () => {
                if (document.visibilityState === 'visible' && this.cook.active) this.acquireWakeLock();
            };
            document.addEventListener('turbo:before-cache', this._onBeforeCache);
            document.addEventListener('visibilitychange', this._onVisibility);
        },

        destroy() {
            document.removeEventListener('turbo:before-cache', this._onBeforeCache);
            document.removeEventListener('visibilitychange', this._onVisibility);
            this.releaseWakeLock();
        },

        toggleSpice(id) {
            this.openSpiceId = this.openSpiceId === id ? null : id;
        },

        goToSpice(id) {
            this.tab = 'spices';
            this.openSpiceId = id;
            this.$nextTick(() => {
                const card = document.getElementById(`recipe-spice-${id}`);
                const seg = this.$root.querySelector('.recipe-segment-wrap');
                if (!card) return;
                const offset = (seg ? seg.getBoundingClientRect().bottom : 0) + 8;
                window.scrollTo({ top: card.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
            });
        },

        tabKey(event) {
            const next = nextTab(event, ['timeline', 'spices'], this.tab);
            if (!next) return;
            this.tab = next;
            this.$nextTick(() => document.getElementById(`recipe-tab-${next}`)?.focus());
        },

        async setFavorite(next) {
            if (this.favPending) return;
            if (this.$root.dataset.guest === '1') {
                await this.gateFavorite(next);
                return;
            }
            const previous = this.favorite;
            this.favTarget = next;
            this.favorite = next;
            this.favError = false;
            this.favPending = true;
            try {
                const res = await apiFetch(this.$root.dataset.favoriteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.$root.dataset.token },
                    body: JSON.stringify({ favorite: next }),
                });
                const data = await res.json();
                this.favorite = data.favorite === true;
            } catch (e) {
                this.favorite = previous;
                this.favError = true;
            } finally {
                this.favPending = false;
            }
        },

        gateRename() {
            window.dispatchEvent(new CustomEvent('gate-login', {
                detail: { url: window.location.pathname + window.location.search, tab: 'register', context: 'rename' },
            }));
        },

        async gateFavorite(next) {
            if (!next) return;
            this.favPending = true;
            try {
                const res = await fetch(this.$root.dataset.favoriteUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', 'X-CSRF-Token': this.$root.dataset.token },
                    body: JSON.stringify({ favorite: true }),
                });
                if (!res.ok && res.status !== 401) throw new Error(`HTTP ${res.status}`);
            } catch (e) {
                this.favError = true;
                return;
            } finally {
                this.favPending = false;
            }
            window.dispatchEvent(new CustomEvent('gate-login', {
                detail: { url: window.location.pathname + window.location.search, tab: 'register', context: 'favorite' },
            }));
        },

        retryFavorite() {
            if (this.favTarget !== null) this.setFavorite(this.favTarget);
        },

        focusTitle(el) {
            if (!this.title) el.textContent = '';
        },

        async saveTitle(el) {
            const text = (el.textContent || '').trim().slice(0, 120);
            if (text === this.title) {
                el.textContent = this.title || this.placeholder;
                return;
            }
            const previous = this.title;
            this.title = text;
            el.textContent = text || this.placeholder;
            try {
                const res = await apiFetch(this.$root.dataset.renameUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title: text, _token: this.$root.dataset.token }),
                });
                const data = await res.json();
                this.title = data.title || '';
            } catch (e) {
                this.title = previous;
                toast(t('common.save_failed'), 'fa-solid fa-triangle-exclamation');
            }
            el.textContent = this.title || this.placeholder;
        },

        storageKey() {
            return `recipe-cook-${this.$root.dataset.id}`;
        },

        enterCook() {
            let done = {};
            try {
                done = JSON.parse(localStorage.getItem(this.storageKey()) || '{}') || {};
            } catch (e) {
                done = {};
            }
            this.cook = { ...this.cook, active: true, step: 0, done };
            this.acquireWakeLock();
            this.$nextTick(() => this.$refs.cookClose?.focus());
        },

        exitCook(restoreFocus = true) {
            if (!this.cook.active) return;
            this.cook.active = false;
            this.releaseWakeLock();
            if (restoreFocus) this.$nextTick(() => this.$refs.cookCta?.focus());
        },

        cookPrev() {
            if (this.cook.step > 0) this.cook.step--;
        },

        cookNext() {
            if (this.cook.step < this.cook.total - 1) this.cook.step++;
            this.$root.querySelector('.recipe-cook-scroll')?.scrollTo({ top: 0 });
        },

        toggleDone(key) {
            this.cook.done = { ...this.cook.done, [key]: !this.cook.done[key] };
            try {
                localStorage.setItem(this.storageKey(), JSON.stringify(this.cook.done));
            } catch (e) {}
        },

        async acquireWakeLock() {
            if (!('wakeLock' in navigator)) return;
            try {
                this._wakeSentinel = await navigator.wakeLock.request('screen');
                this.wakeLock = true;
                this._wakeSentinel.addEventListener('release', () => { this.wakeLock = false; });
            } catch (e) {
                this.wakeLock = false;
            }
        },

        releaseWakeLock() {
            this._wakeSentinel?.release().catch(() => {});
            this._wakeSentinel = null;
            this.wakeLock = false;
        },
    }));

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
            return this.favorite ? t('favorites.in') : t('favorites.add');
        },
        starTitle() {
            return this.favorite ? t('favorites.remove') : t('favorites.add');
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.token },
                    body: JSON.stringify({ favorite: !this.favorite }),
                });
                const data = await res.json();
                this.favorite = data.favorite;
                toast(
                    data.favorite ? t('favorites.added') : t('favorites.removed'),
                    data.favorite ? 'fa-solid fa-star' : 'fa-regular fa-star',
                );
            } catch (e) { console.error('Toggle error', e); }
        },
    }));

    Alpine.data('finalisationMelange', (spiceIdsCsv, historyUrl, csrf) => ({
        spiceIds: spiceIdsCsv ? spiceIdsCsv.split(',') : [],
        spiceNames: {},
        duoMap: { byPrep: {}, byCook: {} },
        current: null,
        results: {},
        toast: { visible: false, text: '' },
        _toastT: null,
        _autoT: null,
        _saveTimers: {},
        _failed: new Set(),
        _pending: new Set(),
        _chain: Promise.resolve(),
        _onPageHide: null,

        init() {
            this._onPageHide = () => this.flushOnUnload();
            window.addEventListener('pagehide', this._onPageHide);
            let selections = {};
            try {
                selections = JSON.parse(this.$el.dataset.selections || '{}');
            } catch (e) { console.error('selections parse error', e); }
            this.spiceIds.forEach((id, i) => {
                this.spiceNames[id] = this.$el.dataset['spiceName' + i] || id;
                const saved = selections[id] || {};
                this.results[id] = { cooking: saved.cooking ?? null, preparation: saved.preparation ?? null };
            });
            this.current = this.spiceIds.find(id => !this.done(id)) ?? this.spiceIds[0] ?? null;
            try {
                const parsed = JSON.parse(this.$el.dataset.duoMap || '{}');
                this.duoMap = { byPrep: parsed.byPrep || {}, byCook: parsed.byCook || {} };
            } catch (e) { console.error('duoMap parse error', e); }
        },
        destroy() {
            window.removeEventListener('pagehide', this._onPageHide);
            this.flushOnUnload();
        },

        get allSealed() {
            return this.spiceIds.every(id => this.results[id] && this.results[id].cooking && this.results[id].preparation);
        },
        get ctaLabel() {
            if (this.allSealed) return t('melange.seal_assoc');
            const done = this.spiceIds.filter(id => this.results[id] && this.results[id].cooking && this.results[id].preparation).length;
            const total = this.spiceIds.length;
            if (total - done === 1) return t('melange.last_spice');
            return t('melange.sealed_count', `${done} / ${total}`).replace('%done%', done).replace('%total%', total);
        },

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
            if (isDone) return spiceId === this.current ? t('melange.status_sealed_current') : t('melange.status_sealed');
            if (spiceId === this.current) return !r.cooking ? t('melange.status_choose_time') : t('melange.status_choose_hand');
            return (r.cooking || r.preparation) ? t('melange.status_ongoing') : t('melange.status_upcoming');
        },
        done(spiceId) {
            const r = this.results[spiceId] || {};
            return !!(r.cooking && r.preparation);
        },
        duoPartners(spiceId, kind) {
            const r = this.results[spiceId];
            if (!r) return null;
            const map = kind === 'cooking' ? this.duoMap.byPrep : this.duoMap.byCook;
            const counterpart = kind === 'cooking' ? r.preparation : r.cooking;
            const list = counterpart ? map[counterpart] : null;
            return list && list.length ? list : null;
        },
        duoState(spiceId, kind, tipId) {
            const partners = this.duoPartners(spiceId, kind);
            if (!partners) return '';
            const key = kind === 'cooking' ? 'c' : 'p';
            const hit = partners.find(d => d[key] === tipId);
            if (!hit) return 'muted';
            return hit.r === 1 ? 'recommended' : 'possible';
        },
        duoTipActive(spiceId, kind, prepId, cookId) {
            const r = this.results[spiceId];
            if (!r) return false;
            return kind === 'cooking' ? r.preparation === prepId : r.cooking === cookId;
        },
        describedBy(spiceId, kind, tipId) {
            const r = this.results[spiceId];
            if (!r) return false;
            if (kind === 'cooking') {
                const list = r.preparation ? this.duoMap.byCook[tipId] : null;
                return list && list.some(d => d.p === r.preparation) ? `duo-c-${r.preparation}-${tipId}` : false;
            }
            const list = r.cooking ? this.duoMap.byPrep[tipId] : null;
            return list && list.some(d => d.c === r.cooking) ? `duo-p-${tipId}-${r.cooking}` : false;
        },
        timingTileClass(spiceId, tipId) {
            const r = this.results[spiceId];
            if (r && r.cooking === tipId) return 'selected';
            return this.duoState(spiceId, 'cooking', tipId);
        },
        methodTileClass(spiceId, tipId) {
            const r = this.results[spiceId];
            if (r && r.preparation === tipId) return 'selected';
            return this.duoState(spiceId, 'preparation', tipId);
        },

        toggleCooking(spiceId, tipId) {
            const r = this.results[spiceId];
            r.cooking = (r.cooking === tipId) ? null : tipId;
            this.persist(spiceId, 'cooking');
            this.maybeAdvance(spiceId);
        },
        togglePreparation(spiceId, tipId) {
            const r = this.results[spiceId];
            r.preparation = (r.preparation === tipId) ? null : tipId;
            this.persist(spiceId, 'preparation');
            this.maybeAdvance(spiceId);
        },
        async goToHistory(url) {
            if (!this.allSealed) return;
            await this.flushSaves();
            if (this._failed.size > 0) {
                this.showToast(t('melange.save_error'), 3000);
                return;
            }
            window.location.href = url;
        },

        maybeAdvance(spiceId) {
            clearTimeout(this._autoT);
            const r = this.results[spiceId];
            if (!r.cooking || !r.preparation) return;
            const next = this.spiceIds.find(id => id !== spiceId && !this.done(id));
            const label = this.spiceNames[spiceId] || '';
            if (!next) {
                this.showToast(t('melange.toast_complete').replace('%spice%', label));
                return;
            }
            this.showToast(t('melange.toast_next').replace('%spice%', label).replace('%next%', this.spiceNames[next]));
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
        showToast(text, duration = 1500) {
            this.toast.text = text;
            this.toast.visible = true;
            clearTimeout(this._toastT);
            this._toastT = setTimeout(() => { this.toast.visible = false; }, duration);
        },

        persist(spiceId, kind) {
            const key = spiceId + ':' + kind;
            clearTimeout(this._saveTimers[key]);
            this._saveTimers[key] = setTimeout(() => {
                delete this._saveTimers[key];
                this.send(spiceId, kind);
            }, 150);
        },
        request(spiceId, kind, keepalive = false) {
            const tipId = this.results[spiceId]?.[kind] ?? 0;
            return fetch(historyUrl, {
                method: 'POST',
                keepalive,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf,
                },
                body: new URLSearchParams({ spiceId, kind, tipId }),
            });
        },
        send(spiceId, kind) {
            const key = spiceId + ':' + kind;
            if (this._pending.has(key)) return this._chain;
            this._pending.add(key);
            this._chain = this._chain.then(async () => {
                if (!this._pending.delete(key)) return;
                try {
                    const response = await this.request(spiceId, kind);
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    this._failed.delete(key);
                } catch (e) {
                    console.error('Persist error', e);
                    this._failed.add(key);
                    this.showToast(t('melange.save_error'), 3000);
                }
            });
            return this._chain;
        },
        flushSaves() {
            Object.keys(this._saveTimers).forEach(key => {
                clearTimeout(this._saveTimers[key]);
                delete this._saveTimers[key];
                this._failed.add(key);
            });
            [...this._failed].forEach(key => {
                const [spiceId, kind] = key.split(':');
                this.send(spiceId, kind);
            });
            return this._chain;
        },
        flushOnUnload() {
            const keys = new Set([...Object.keys(this._saveTimers), ...this._failed, ...this._pending]);
            this._pending.clear();
            keys.forEach(key => {
                clearTimeout(this._saveTimers[key]);
                delete this._saveTimers[key];
                const [spiceId, kind] = key.split(':');
                this.request(spiceId, kind, true).catch(() => {});
            });
            this._failed.clear();
        },
    }));

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
            return this.allReady ? t('cooking.cta_final') : t('cooking.cta_incomplete');
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

    Alpine.data('faqAccordion', () => ({
        open: null,
        init() {
            this._onHashChange = () => this.syncFromHash();
            window.addEventListener('hashchange', this._onHashChange);
            this.syncFromHash();
        },
        destroy() {
            window.removeEventListener('hashchange', this._onHashChange);
        },
        syncFromHash() {
            const match = window.location.hash.match(/^#faq-(\d+)-[qa]$/);
            if (!match || !this.$root.querySelector(`button[data-faq-id="${match[1]}"]`)) return;
            this.open = match[1];
            this.$nextTick(() => document.getElementById(`faq-${match[1]}-q`)?.scrollIntoView({ block: 'start' }));
        },
        isOpen(id) { return this.open === id; },
        toggle(id) { this.open = this.open === id ? null : id; },
    }));

    Alpine.data('faqFilter', () => ({
        active: 'all',
        init() {
            this._onHashChange = () => this.syncFromHash();
            window.addEventListener('hashchange', this._onHashChange);
            this.syncFromHash();
        },
        destroy() {
            window.removeEventListener('hashchange', this._onHashChange);
        },
        syncFromHash() {
            const hash = window.location.hash;
            const category = hash.match(/^#faq-cat-([a-z0-9-]+)$/);
            if (category && this.$root.querySelector(`section[data-category="${category[1]}"]`)) {
                this.active = category[1];
                return;
            }
            const question = hash.match(/^#faq-(\d+)-[qa]$/);
            const section = question && this.$root.querySelector(`button[data-faq-id="${question[1]}"]`)?.closest('section[data-category]');
            if (section && !this.shows(section.dataset.category)) this.active = 'all';
        },
        isActive(code) { return this.active === code; },
        shows(code) { return this.active === 'all' || this.active === code; },
        select(code) {
            this.active = code;
            const url = new URL(window.location.href);
            url.hash = code === 'all' ? '' : `faq-cat-${code}`;
            history.replaceState(history.state, '', url.hash ? url : url.pathname + url.search);
        },
    }));

    Alpine.data('adSlot', () => ({
        observer: null,

        init() {
            const provider = this.$root.dataset.adProvider;
            if (!Object.hasOwn(AD_SCRIPTS, provider) || !('IntersectionObserver' in window)) return;
            this.observer = new IntersectionObserver((entries) => {
                if (!entries.some(entry => entry.isIntersecting)) return;
                this.observer.disconnect();
                this.observer = null;
                if (provider === 'carbon') loadCarbon(this.$root, this.$root.dataset.adPublisher);
                else loadEthicalAds();
            }, { rootMargin: '200px' });
            this.observer.observe(this.$root);
            this._onBeforeCache = () => {
                this.$root.querySelectorAll('#_carbonads_js, #carbonads').forEach(node => node.remove());
            };
            document.addEventListener('turbo:before-cache', this._onBeforeCache);
        },

        destroy() {
            this.observer?.disconnect();
            if (this._onBeforeCache) document.removeEventListener('turbo:before-cache', this._onBeforeCache);
        },
    }));

    Alpine.data('contentRead', () => ({
        remaining: 5000,
        startedAt: null,
        timer: null,
        sent: false,

        init() {
            const { readUrl, readTicket, readDelay } = this.$root.dataset;
            if (!readUrl || !readTicket) return;
            this.remaining = Number(readDelay) * 1000 || this.remaining;
            this._onVisibility = () => (document.hidden ? this.pause() : this.resume());
            this._onBeforeCache = () => this.pause();
            document.addEventListener('visibilitychange', this._onVisibility);
            document.addEventListener('turbo:before-cache', this._onBeforeCache);
            if (!document.hidden) this.resume();
        },

        resume() {
            if (this.sent || this.timer !== null) return;
            this.startedAt = Date.now();
            this.timer = setTimeout(() => this.send(), this.remaining);
        },

        pause() {
            if (this.timer === null) return;
            clearTimeout(this.timer);
            this.timer = null;
            this.remaining = Math.max(0, this.remaining - (Date.now() - this.startedAt));
        },

        async send() {
            this.timer = null;
            this.sent = true;
            const { readUrl, readTicket } = this.$root.dataset;
            try {
                const response = await fetch(readUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ ticket: readTicket }),
                });
                if (!response.ok) this.sent = false;
            } catch {
                this.sent = false;
            }
        },

        destroy() {
            if (this.timer !== null) clearTimeout(this.timer);
            if (this._onVisibility) document.removeEventListener('visibilitychange', this._onVisibility);
            if (this._onBeforeCache) document.removeEventListener('turbo:before-cache', this._onBeforeCache);
        },
    }));

    Alpine.data('onboardingWelcome', () => ({
        visible: false,
        previouslyFocused: null,
        _timer: null,
        _onBeforeCache: null,

        init() {
            this.$watch('visible', (val) => {
                document.querySelectorAll('main, nav, footer').forEach(el => { el.inert = val; });
            });
            this._onBeforeCache = () => { this.visible = false; };
            document.addEventListener('turbo:before-cache', this._onBeforeCache);
            if (tourSeen(WELCOME_TOUR, 1)) return;
            if (document.getElementById('account-created-title')) {
                window.addEventListener('account-created-closed', () => this.open(), { once: true });
                return;
            }
            this._timer = setTimeout(() => this.open(), 600);
        },

        destroy() {
            clearTimeout(this._timer);
            document.removeEventListener('turbo:before-cache', this._onBeforeCache);
            document.querySelectorAll('main, nav, footer').forEach(el => { el.inert = false; });
        },

        open() {
            if (this.visible || tourSeen(WELCOME_TOUR, 1) || isGateOpen()) return;
            this.previouslyFocused = document.activeElement;
            this.visible = true;
            markTourSeen(WELCOME_TOUR, 1);
            this.$nextTick(() => focusFirst(this.$el));
        },

        handleTab(e) {
            loopTab(e, this.$el);
        },

        start(event) {
            const destination = event.currentTarget.closest('a')?.href || event.currentTarget.href;
            this.visible = false;
            window.location.href = destination;
        },

        skip() {
            markTourSeen(TOUR_SKIP_ALL, 1);
            this.visible = false;
            if (this.previouslyFocused) this.previouslyFocused.focus();
        },
    }));

    Alpine.data('onboardingReset', () => ({
        reset() {
            resetTours();
            window.location.href = this.$el.dataset.homeUrl;
        },
    }));

    Alpine.data('gateTrigger', (url = '', tab = 'login') => ({
        url,
        tab,
        trigger(event) {
            if (!document.querySelector('[x-data="gateLoginModal"]')) return;
            event.preventDefault();
            const target = this.url || (window.location.pathname + window.location.search);
            window.dispatchEvent(new CustomEvent('gate-login', { detail: { url: target, tab: this.tab } }));
        },
    }));

    Alpine.data('gateLoginModal', () => ({
        open: false,
        activeTab: 'login',
        context: '',
        targetUrl: '',
        previouslyFocused: null,

        init() {
            document.addEventListener('turbo:before-visit', () => { this.open = false; });
        },

        onGate(evt) {
            this.activeTab = evt.detail.tab || 'login';
            this.context = evt.detail.context || '';
            this.targetUrl = evt.detail.url;
            this.previouslyFocused = document.activeElement;
            this.open = true;
            this.$nextTick(() => focusFirst(this.$el));
        },

        close() {
            if (!this.open) return;
            this.open = false;
            if (this.previouslyFocused) this.previouslyFocused.focus();
            this.previouslyFocused = null;
            this.targetUrl = '';
        },

        handleTab(e) {
            if (!this.open) return;
            loopTab(e, this.$el);
        },

        showLogin() {
            this.activeTab = 'login';
        },

        showRegister() {
            this.activeTab = 'register';
        },

        tabKey(event) {
            const next = nextTab(event, ['login', 'register'], this.activeTab);
            if (!next) return;
            this.activeTab = next;
            this.$nextTick(() => document.getElementById(`gate-tab-${next}`)?.focus());
        },

        isLoginTab() {
            return 'login' === this.activeTab;
        },

        isRegisterTab() {
            return 'register' === this.activeTab;
        },

        isContext(name) {
            return name === this.context;
        },

        hasContext() {
            return '' !== this.context;
        },
    }));

    Alpine.data('pageTour', () => ({
        key: '',
        version: 1,
        steps: [],
        currentStep: 0,
        firstShown: null,
        active: false,
        tooltipActive: false,
        targetEl: null,
        spotlightStyle: {},
        tooltipStyle: {},
        currentTitle: '',
        currentText: '',
        showTransition: false,
        transitionTitle: '',
        sheetMode: false,
        sheetTop: false,
        _timer: null,
        _resizeHandler: null,
        _targetClickHandler: null,
        _onBeforeCache: null,
        _onAccountClosed: null,
        get isLastStep() { return this.currentStep + 1 >= this.steps.length; },
        get hasPrev() { return this.firstShown !== null && this.currentStep > this.firstShown; },
        nextLabel() { return this.isLastStep ? t('tour.done') : t('tour.next'); },
        stepCounter() { return (this.currentStep + 1) + ' / ' + this.steps.length; },
        stepDotClass(i) {
            return i <= this.currentStep + 1
                ? 'w-5 h-1.5 bg-saffron-500 rounded-full'
                : 'w-1.5 h-1.5 bg-stone-300 rounded-full';
        },
        tooltipClass() {
            if (!this.sheetMode) return 'max-w-xs rounded-xl p-5';
            return this.sheetTop
                ? 'inset-x-0 top-0 w-full rounded-b-2xl p-6'
                : 'inset-x-0 bottom-0 w-full rounded-t-2xl p-6 pb-7';
        },
        prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        init() {
            const root = this.$root;
            this.key = root.dataset.tourKey || '';
            this.version = Number(root.dataset.tourVersion) || 1;
            this.transitionTitle = root.dataset.tourTransition || '';
            this.steps = [...root.querySelectorAll('[data-tour-step]')].map(el => ({
                target: el.dataset.tourStep,
                position: el.dataset.tourPosition || 'bottom',
                advance: el.dataset.tourAdvance === '1',
                title: el.dataset.tourTitle,
                text: el.dataset.tourText,
            }));
            this._onBeforeCache = () => this.skip();
            document.addEventListener('turbo:before-cache', this._onBeforeCache);
            if (!this.key || !this.steps.length || tourSeen(this.key, this.version)) return;
            if (document.getElementById('account-created-title')) {
                this._onAccountClosed = () => this.$nextTick(() => this.begin());
                window.addEventListener('account-created-closed', this._onAccountClosed, { once: true });
                return;
            }
            this._timer = setTimeout(() => this.begin(), 300);
        },

        destroy() {
            clearTimeout(this._timer);
            this.cleanup();
            document.removeEventListener('turbo:before-cache', this._onBeforeCache);
            if (this._onAccountClosed) window.removeEventListener('account-created-closed', this._onAccountClosed);
        },

        begin() {
            if (this.active || tourSeen(this.key, this.version) || isGateOpen()) return;
            this.steps = this.steps.filter(step => this.resolveTarget(step.target));
            if (!this.steps.length) return;
            this.currentStep = 0;
            this.firstShown = null;
            this.startStep();
        },

        resolveTarget(name) {
            for (const el of document.querySelectorAll(`[data-tour="${CSS.escape(name)}"]`)) {
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0 && getComputedStyle(el).visibility !== 'hidden') return el;
            }
            return null;
        },

        startStep() {
            const step = this.steps[this.currentStep];
            if (!step) return this.finishTour();
            const target = this.resolveTarget(step.target);
            if (!target) {
                this.currentStep++;
                return this.$nextTick(() => this.startStep());
            }
            if (this.firstShown === null) {
                this.firstShown = this.currentStep;
                markTourSeen(this.key, this.version);
            }
            this.targetEl = target;
            if (getComputedStyle(target).position !== 'fixed') {
                target.scrollIntoView({ behavior: this.prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
            }
            this.currentTitle = step.title;
            this.currentText = step.text;
            this.active = true;
            this.positionSpotlight(target);
            this.$nextTick(() => {
                this.positionTooltip(target, step.position);
                this.tooltipActive = true;
                this.$nextTick(() => {
                    if (this.$refs.tooltip) this.$refs.tooltip.focus({ preventScroll: true });
                });
            });
            if (step.advance) {
                this._targetClickHandler = () => setTimeout(() => this.next(), 250);
                target.addEventListener('click', this._targetClickHandler, { once: true });
            }
            this._resizeHandler = () => {
                if (this.targetEl) {
                    this.positionSpotlight(this.targetEl);
                    this.positionTooltip(this.targetEl, step.position);
                }
            };
            window.addEventListener('resize', this._resizeHandler);
            window.addEventListener('scroll', this._resizeHandler, { passive: true });
        },

        positionSpotlight(el) {
            const rect = el.getBoundingClientRect();
            this.spotlightStyle = {
                top: (rect.top - 8) + 'px',
                left: (rect.left - 8) + 'px',
                width: (rect.width + 16) + 'px',
                height: (rect.height + 16) + 'px',
            };
        },

        positionTooltip(el, position) {
            const rect = el.getBoundingClientRect();
            const margin = 12;
            const vw = window.innerWidth;
            const vh = window.innerHeight;

            if (vw < 640) {
                this.sheetMode = true;
                this.sheetTop = rect.top > vh * 0.55;
                this.tooltipStyle = {};
                return;
            }
            this.sheetMode = false;

            const tooltipW = Math.min(320, vw - margin * 2);
            const tooltipH = 200;

            let resolved = position || 'bottom';
            if (resolved === 'left' && rect.left < tooltipW + margin) {
                resolved = (rect.bottom + tooltipH + margin < vh) ? 'bottom' : 'top';
            }
            if (resolved === 'right' && vw - rect.right < tooltipW + margin) {
                resolved = (rect.bottom + tooltipH + margin < vh) ? 'bottom' : 'top';
            }
            if (resolved === 'bottom' && rect.bottom + tooltipH + margin > vh && rect.top > tooltipH + margin) {
                resolved = 'top';
            }
            if (resolved === 'top' && rect.top < tooltipH + margin && vh - rect.bottom > tooltipH + margin) {
                resolved = 'bottom';
            }

            let top; let left;
            if (resolved === 'top') {
                top = rect.top - tooltipH - margin;
                left = rect.left + (rect.width / 2) - (tooltipW / 2);
            } else if (resolved === 'left') {
                top = rect.top;
                left = rect.left - tooltipW - margin;
            } else if (resolved === 'right') {
                top = rect.top;
                left = rect.right + margin;
            } else {
                top = rect.bottom + margin;
                left = rect.left + (rect.width / 2) - (tooltipW / 2);
            }

            left = Math.max(margin, Math.min(left, vw - tooltipW - margin));
            top = Math.max(margin, Math.min(top, vh - tooltipH - margin));

            this.tooltipStyle = {
                top: top + 'px',
                left: left + 'px',
                width: tooltipW + 'px',
            };
        },

        handleTab(e) {
            if (this.$refs.tooltip) loopTab(e, this.$refs.tooltip);
        },

        next() {
            this.currentStep++;
            this.cleanupStep();
            if (this.currentStep >= this.steps.length) return this.finishTour();
            this.startStep();
        },

        prev() {
            if (!this.hasPrev) return;
            this.currentStep--;
            this.cleanupStep();
            this.startStep();
        },

        skip() {
            this.cleanup();
        },

        finishTour() {
            this.cleanup();
            if (this.transitionTitle) this.showTransition = true;
        },

        closeTransition() {
            this.showTransition = false;
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
            this.active = false;
            this.tooltipActive = false;
        },

        cleanup() {
            this.cleanupStep();
            this.showTransition = false;
        },
    }));

    Alpine.data('chefWord', () => ({
        chefTab: '',
        tabs: [],

        init() {
            this.tabs = (this.$root.dataset.tabs || '').split(',').filter(Boolean);
            this.chefTab = this.tabs[0] || '';
        },

        resetTab() {
            this.chefTab = this.tabs[0] || '';
        },

        isTab(key) {
            return key === this.chefTab;
        },

        tabKey(event) {
            const next = nextTab(event, this.tabs, this.chefTab);
            if (!next) return;
            this.chefTab = next;
            this.$nextTick(() => this.$root.querySelector(`[data-tab="${next}"]`)?.focus());
        },
    }));

    Alpine.data('profileTabs', (initial) => ({
        tabs: ['dashboard', 'grimoire', 'history', 'lab'],
        tab: initial,
        init() {
            if (!this.tabs.includes(this.tab)) {
                this.tab = 'dashboard';
            }
            window.addEventListener('popstate', () => {
                const t = new URL(window.location).searchParams.get('tab');
                this.tab = this.tabs.includes(t) ? t : 'dashboard';
            });
        },
        go(t) {
            if (!this.tabs.includes(t) || t === this.tab) {
                return;
            }
            this.tab = t;
            const url = new URL(window.location);
            url.searchParams.set('tab', t);
            history.pushState({ tab: t }, '', url);
        },
        isActive(t) {
            return this.tab === t;
        },
        tabKey(event) {
            const next = nextTab(event, this.tabs, this.tab);
            if (!next) return;
            this.go(next);
            this.$nextTick(() => document.getElementById(`tab-${next}`)?.focus());
        },
    }));

    Alpine.data('toile', () => ({
        activeId: 'm1',
        molecules: [],
        init() {
            try {
                const raw = JSON.parse(this.$el.dataset.molecules || '[]');
                this.molecules = raw.map(m => ({
                    ...m,
                    molStyle: `--mol-x:${m.x}px; --mol-y:${m.y}px; --mol-accent:${m.accent}; --mol-delay:${m.delay}; background:${m.grad}`,
                }));
            } catch (e) {
                console.error('toile: invalid molecules JSON', e);
                this.molecules = [];
            }
            if (this.molecules.length) this.activeId = this.molecules[0].id;
        },
        get active() { return this.molecules.find(m => m.id === this.activeId) || {}; },
        setActive(id) { this.activeId = id; },
        isActive(id) { return this.activeId === id; },
        cardStyle() { return `border-color: ${this.active.accent || 'transparent'}`; },
    }));

    Alpine.data('homeDemo', () => ({
        state: 'idle',
        loading: false,
        selectedId: null,
        selectedSlug: '',
        selectedName: '',
        results: [],
        labels: {},

        init() {
            try {
                this.labels = JSON.parse(this.$root.dataset.labels || '{}');
            } catch (e) {
                this.labels = {};
            }
        },

        get labHref() {
            const url = new URL(this.$root.dataset.labUrl, window.location.origin);
            if (this.selectedSlug) url.searchParams.set('spice', this.selectedSlug);
            return url.pathname + url.search;
        },

        get ctaLabel() {
            return (this.$root.dataset.ctaTemplate || '').replace('__SPICE__', this.selectedName);
        },

        async pick(button) {
            if (this.loading) return;
            this.selectedId = Number(button.dataset.id);
            this.selectedSlug = button.dataset.slug;
            this.selectedName = button.dataset.name;
            this.loading = true;
            this.state = 'loading';
            this.results = [];

            try {
                const url = new URL(this.$root.dataset.apiUrl, window.location.origin);
                url.searchParams.set('spices', String(this.selectedId));
                url.searchParams.set('limit', '3');
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'Accept-Language': document.documentElement.lang || 'fr',
                    },
                });
                if (!response.ok) {
                    this.state = 'error';
                    return;
                }
                const data = await response.json();
                const results = Array.isArray(data.results) ? data.results : [];
                this.results = results.slice(0, 3).map(r => ({
                    id: r.id,
                    name: r.name,
                    label: this.labels[r.affinity] || '',
                }));
                this.state = this.results.length ? 'done' : 'empty';
            } catch (e) {
                this.state = 'error';
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('homeStickyCta', () => ({
        visible: false,
        heroPassed: false,
        finalReached: false,
        observer: null,

        init() {
            const hero = document.querySelector('[data-home-cta="lab"]');
            const final = document.querySelector('.home-final');
            if (!hero || !final || !('IntersectionObserver' in window)) {
                return;
            }
            this.observer = new IntersectionObserver(() => {
                this.heroPassed = hero.getBoundingClientRect().bottom < 0;
                this.finalReached = final.getBoundingClientRect().top < window.innerHeight;
                this.visible = this.heroPassed && !this.finalReached;
                document.documentElement.classList.toggle('home-sticky-on', this.visible);
            });
            [hero, final, document.querySelector('.site-footer')].forEach((el) => el && this.observer.observe(el));
        },

        destroy() {
            this.observer?.disconnect();
            document.documentElement.classList.remove('home-sticky-on');
        },
    }));

    Alpine.data('errorCountdown', (seconds = 10, url = '/') => ({
        remaining: Number(seconds),
        paused: false,
        _timer: null,
        _pauseHandler: null,
        _resumeHandler: null,

        init() {
            this._pauseHandler = () => { this.paused = true; };
            this._resumeHandler = () => { this.paused = false; };
            this.$el.addEventListener('mouseenter', this._pauseHandler);
            this.$el.addEventListener('focusin', this._pauseHandler);
            this.$el.addEventListener('mouseleave', this._resumeHandler);
            this.$el.addEventListener('focusout', this._resumeHandler);
            this._timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            this._stop();
            this.$el.removeEventListener('mouseenter', this._pauseHandler);
            this.$el.removeEventListener('focusin', this._pauseHandler);
            this.$el.removeEventListener('mouseleave', this._resumeHandler);
            this.$el.removeEventListener('focusout', this._resumeHandler);
        },

        tick() {
            if (this.paused) return;
            this.remaining -= 1;
            if (this.remaining <= 0) {
                this.remaining = 0;
                this._stop();
                window.location.assign(url);
            }
        },

        _stop() {
            if (this._timer === null) return;
            clearInterval(this._timer);
            this._timer = null;
        },
    }));
}
