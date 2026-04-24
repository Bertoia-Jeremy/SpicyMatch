import { chromium } from 'playwright';

const BASE = 'https://spicymatch.sf4.p84.dbm-local.com';

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--ignore-certificate-errors'] });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    // Collect console errors + failed requests
    const errors = [];
    const failed = [];
    page.on('console', msg => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', err => errors.push(err.message));
    page.on('requestfailed', req => failed.push(`${req.failure()?.errorText} — ${req.url()}`));
    page.on('response', res => {
        if (res.status() === 404) failed.push(`404 — ${res.url()}`);
    });

    // 1. Login
    console.log('--- Logging in ---');
    await page.goto(`${BASE}/login`);
    await page.waitForLoadState('domcontentloaded');
    console.log('Login page URL:', page.url());

    await page.fill('input[name="username"]', 'alice');
    await page.fill('input[name="password"]', 'Alice1234!');
    await page.locator('form').evaluate(form => form.submit());
    await page.waitForLoadState('networkidle', { timeout: 15000 });
    console.log('After login:', page.url());

    // 2. Set onboarding to done so it doesn't interfere
    await page.evaluate(() => localStorage.setItem('sm_onboarding', 'done'));

    // 3. Go to /epices/
    console.log('--- Going to /epices/ ---');
    await page.goto(`${BASE}/epices/`);
    await page.waitForLoadState('networkidle');
    console.log('Page loaded:', page.url());

    // 4. Check if spice cards exist
    const cardCount = await page.locator('.card-warm').count();
    console.log(`Found ${cardCount} spice cards`);

    if (cardCount === 0) {
        console.log('No cards found, aborting');
        await browser.close();
        return;
    }

    // 5. Check for any overlay / fixed element blocking clicks
    const fixedElements = await page.evaluate(() => {
        const els = document.querySelectorAll('.fixed, [style*="position: fixed"]');
        return [...els].map(el => ({
            tag: el.tagName,
            classes: el.className.substring(0, 100),
            display: getComputedStyle(el).display,
            zIndex: getComputedStyle(el).zIndex,
            pointerEvents: getComputedStyle(el).pointerEvents,
            visible: el.offsetParent !== null || getComputedStyle(el).display !== 'none',
            inert: el.inert,
        }));
    });
    console.log('\n--- Fixed/overlay elements ---');
    fixedElements.filter(e => e.visible).forEach(e => {
        console.log(`  ${e.tag} z:${e.zIndex} pe:${e.pointerEvents} display:${e.display} | ${e.classes.substring(0, 80)}`);
    });

    // 6. Check if main content is inert
    const mainInert = await page.evaluate(() => document.getElementById('main-content')?.inert);
    console.log(`\nmain#main-content inert: ${mainInert}`);

    // 7. Click on first card image area
    console.log('\n--- Clicking first spice card ---');
    const firstCard = page.locator('.card-warm').first();
    await firstCard.scrollIntoViewIfNeeded();

    // Check what events are bound to the card
    const cardHTML = await firstCard.evaluate(el => el.outerHTML.substring(0, 300));
    console.log('Card HTML:', cardHTML);

    // Try clicking the image area (which triggers quick view)
    const imgArea = firstCard.locator('.aspect-square, img').first();
    const imgExists = await imgArea.count();
    console.log(`Image area found: ${imgExists > 0}`);

    if (imgExists > 0) {
        await imgArea.click();
    } else {
        await firstCard.click();
    }

    // Wait a bit for modal to potentially open
    await page.waitForTimeout(1000);

    // 8. Check if quick view modal is visible
    const modalState = await page.evaluate(() => {
        const modal = document.querySelector('[x-data*="modalOpen"]');
        if (!modal) return { exists: false };
        const innerDiv = modal.querySelector('.fixed.inset-0');
        // Alpine 3 stores component data on _x_dataStack
        const alpineData = modal._x_dataStack?.[0];
        return {
            exists: true,
            alpineInitialized: !!alpineData,
            modalOpen: alpineData?.modalOpen ?? 'no alpine data',
            innerDisplay: innerDiv ? getComputedStyle(innerDiv).display : 'no inner div',
            mainInert: document.getElementById('main-content')?.inert,
            cardClickHasAlpine: !!document.querySelector('.aspect-square')?._x_bindings,
        };
    });
    console.log('\n--- Quick view modal state after click ---');
    console.log(JSON.stringify(modalState, null, 2));

    // 9. Try dispatching the event manually — bypasses click handler entirely
    console.log('\n--- Testing manual event dispatch ---');
    await page.evaluate(() => {
        window.dispatchEvent(new CustomEvent('open-spice-modal', {
            detail: { url: '/epices/1/apercu', fullUrl: '/epices/1' },
            bubbles: true,
        }));
    });
    await page.waitForTimeout(500);

    const modalAfterManual = await page.evaluate(() => {
        const modal = document.querySelector('[x-data*="modalOpen"]');
        const innerDiv = modal?.querySelector('.fixed.inset-0');
        const alpineData = modal?._x_dataStack?.[0];
        return {
            modalOpen: alpineData?.modalOpen ?? 'no alpine data',
            innerDisplay: innerDiv ? getComputedStyle(innerDiv).display : 'no div',
        };
    });
    console.log('After manual dispatch:', JSON.stringify(modalAfterManual));

    // 10. Screenshot
    await page.screenshot({ path: '/tmp/spice-debug.png', fullPage: false });
    console.log('\nScreenshot saved to /tmp/spice-debug.png');

    // 11. Report
    if (failed.length) {
        console.log('\n--- Failed requests / 404s ---');
        failed.forEach(f => console.log(`  ${f}`));
    }
    if (errors.length) {
        console.log('\n--- JS Errors ---');
        errors.forEach(e => console.log(`  ${e}`));
    } else {
        console.log('\nNo JS errors.');
    }

    await browser.close();
})();
