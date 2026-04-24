/* Alpine.data() registry — all components used across the SpicyMatch UI.
 *
 * Prereq for CSP Phase 2 (ADR-007): with the `@alpinejs/csp` build, inline
 * expressions like `x-data="{ open: false }"` or `@click="open = !open"` are
 * rejected. Every component must be declared here and referenced by name.
 *
 * Import this file AFTER `alpinejs` but BEFORE `Alpine.start()`.
 *
 * Usage in Twig:
 *   <div x-data="toggle"> → { open: false, toggle(), open(), close() }
 *   <div x-data="spicesLimit"> → { gridMode, limit, showAll(), toggleGrid() }
 *   <button @click="open"> // invokes the open() method
 */
export default function registerAlpineComponents(Alpine) {
    Alpine.data('toggle', (initial = false) => ({
        open: initial,
        show: initial,
        toggle() { this.open = !this.open; this.show = this.open; },
        setOpen(v) { this.open = !!v; this.show = this.open; },
    }));

    Alpine.data('dropdown', () => ({
        dropdownOpen: false,
        toggle() { this.dropdownOpen = !this.dropdownOpen; },
        close() { this.dropdownOpen = false; },
    }));

    Alpine.data('equipButton', () => ({
        equipping: false,
        submit() { this.equipping = true; },
    }));

    Alpine.data('submitOnce', () => ({
        submitting: false,
        selected: null,
        pickAndSubmit(id) {
            if (this.submitting) return false;
            this.selected = id;
            this.submitting = true;
            return true;
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

    Alpine.data('spicesLimit', () => ({
        gridMode: false,
        limit: 8,
        setGrid(v) { this.gridMode = !!v; },
        toggleGrid() { this.gridMode = !this.gridMode; },
        showAll() { this.limit = 9999; },
    }));

    Alpine.data('quickSpiceView', () => ({
        modalOpen: false,
        selectedUrl: null,
        fullPageUrl: null,
        open(url, fullUrl) {
            this.selectedUrl = url;
            this.fullPageUrl = fullUrl;
            this.modalOpen = true;
        },
        close() {
            this.modalOpen = false;
            this.selectedUrl = null;
            this.fullPageUrl = null;
        },
    }));

    Alpine.data('counter', (initial = 0) => ({
        count: initial,
        bump(delta = 1) { this.count += delta; },
        set(v) { this.count = v; },
    }));

    Alpine.data('modeSelector', (defaultMode = '', defaultDifficulty = 'easy') => ({
        selectedMode: defaultMode,
        selectedDifficulty: defaultDifficulty,
        pickMode(mode) { this.selectedMode = mode; },
        pickDifficulty(diff) { this.selectedDifficulty = diff; },
    }));

    Alpine.data('registrationTracker', () => ({
        selected: null,
        submitted: false,
        startTime: Date.now(),
        markSubmitted() { this.submitted = true; },
        pick(value) { this.selected = value; },
    }));

    Alpine.data('notificationHost', () => ({
        toasts: [],
        push(toast) { this.toasts.push(toast); },
        dismiss(index) { this.toasts.splice(index, 1); },
    }));

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
    }));

    /* Onboarding welcome modal + spotlight tour — full logic imported from
     * templates/components/_onboarding.html.twig (Phase 1 extraction).
     *
     * The TOURS config (target CSS selectors, titles, texts) is injected via
     * the template's `data-tours-json` attribute since it contains Twig path()
     * interpolations resolved at render time.
     */
    Alpine.data('onboardingWelcome', () => ({
        visible: false,
        previouslyFocused: null,

        init() {
            const check = () => {
                const state = localStorage.getItem('sm_onboarding');
                if (!state && window.location.pathname === '/') {
                    setTimeout(() => {
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
}
