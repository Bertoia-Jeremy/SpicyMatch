import { chromium } from 'playwright';
const BASE = 'https://spicymatch.sf4.p84.dbm-local.com';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--ignore-certificate-errors'] });
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    // Login
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="username"]', 'alice');
    await page.fill('input[name="password"]', 'Alice1234!');
    await page.locator('form').evaluate(f => f.submit());
    await page.waitForLoadState('networkidle', { timeout: 15000 });

    // Activer le tour "spices"
    await page.evaluate(() => localStorage.setItem('sm_onboarding', 'spices'));
    await page.goto(`${BASE}/epices/`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);  // laisser le spotlight s'initialiser

    // 1. Vérifier que le spotlight est actif sur l'input search (step 0)
    let state = await page.evaluate(() => {
        const tour = document.querySelector('[x-data="spotlightTour()"]');
        const d = tour?._x_dataStack?.[0];
        return { active: d?.active, tooltipActive: d?.tooltipActive, step: d?.currentStep };
    });
    console.log('Step 0 (search):', JSON.stringify(state));

    // 2. Cliquer "Suivant" pour passer à la carte (step 1)
    await page.locator('button:has-text("Suivant")').first().click();
    await page.waitForTimeout(800);

    state = await page.evaluate(() => {
        const tour = document.querySelector('[x-data="spotlightTour()"]');
        const d = tour?._x_dataStack?.[0];
        const panel = document.querySelector('[data-tour="spice-modal-panel"]');
        return {
            step: d?.currentStep,
            active: d?.active,
            targetTag: d?.targetEl?.tagName,
            targetClass: d?.targetEl?.className?.substring(0, 30),
            panelRect: panel?.getBoundingClientRect().width,
        };
    });
    console.log('Step 1 (card):', JSON.stringify(state));

    // 3. Cliquer sur l'image de la carte (.aspect-square à l'intérieur de .card-warm)
    await page.locator('.card-warm .aspect-square').first().click();
    await page.waitForTimeout(1500);

    state = await page.evaluate(() => {
        const tour = document.querySelector('[x-data="spotlightTour()"]');
        const d = tour?._x_dataStack?.[0];
        const panel = document.querySelector('[data-tour="spice-modal-panel"]');
        const panelRect = panel?.getBoundingClientRect();
        const modalContainer = panel?.closest('.fixed.inset-0');
        const backdrop = modalContainer?.querySelector('.modal-backdrop');
        return {
            step: d?.currentStep,
            active: d?.active,
            tooltipActive: d?.tooltipActive,
            paused: d?.paused,
            targetIsPanel: d?.targetEl?.dataset?.tour === 'spice-modal-panel',
            modalZIndex: modalContainer?.style.zIndex,
            backdropDisplay: backdrop?.style.display,
            panelVisible: panelRect && panelRect.width > 0 && panelRect.height > 0,
            spotlightTop: d?.spotlightStyle?.top,
            spotlightWidth: d?.spotlightStyle?.width,
        };
    });
    console.log('After card click → modal spotlight:', JSON.stringify(state, null, 2));

    await page.screenshot({ path: '/tmp/modal-spotlight.png' });
    console.log('Screenshot: /tmp/modal-spotlight.png');

    await browser.close();
})();
