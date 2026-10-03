import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { createTestUser } from '../fixtures/user';

/**
 * Accessibility regression guard. Runs axe-core on the public pages below.
 * Assertion: zero violations at impact `serious` or `critical`.
 *
 * Tag @a11y lets us run just these via `yarn e2e:a11y`.
 */
test.describe('@a11y Accessibility', () => {
  const publicPages = [
    { name: 'home', path: '/' },
    { name: 'login', path: '/login' },
    { name: 'register', path: '/register' },
    { name: 'spices', path: '/fr/epices/' },
    { name: 'contact', path: '/fr/contact' },
    { name: 'help', path: '/fr/help' },
    { name: 'spice types', path: '/fr/epices/types-epices/' },
    { name: 'spice detail', path: '/fr/epices/cannelle' },
    { name: 'compound detail', path: '/fr/epices/composes-aromatiques/carvacrol' },
    { name: 'flavor detail', path: '/fr/epices/saveurs-aromatiques/herbace-sauvage' },
    { name: 'group detail', path: '/fr/epices/groupes-aromatiques/capsaicinoides-alcaloides' },
    { name: 'type detail', path: '/fr/epices/types-epices/graine' },
    { name: 'method detail', path: '/fr/methodes-preparation/infusion' },
    { name: 'faq', path: '/fr/faq' },
  ];

  for (const { name, path } of publicPages) {
    test(`${name} has no serious/critical axe violations`, async ({ page }) => {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        // Symfony dev toolbar lives in a shadow DOM-ish wrapper; axe flags it
        // even though it never ships to prod.
        .exclude('#sfToolbar')
        .exclude('[id^="sfToolbarToggleButton"]')
        .exclude('[id^="sfToolbarMainContent"]')
        .exclude('[id^="sfMiniToolbar"]')
        .analyze();

      const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
      );

      if (blocking.length > 0) {
        // Make the report readable when the test fails.
        console.error('Axe violations on', path, JSON.stringify(blocking, null, 2));
      }

      expect(blocking).toHaveLength(0);
    });
  }

  test('academy index has no serious/critical violations when logged in', async ({ page, request }) => {
    const user = await createTestUser(request, 'a11y');
    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/fr/education/');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .exclude('#sfToolbar')
      .exclude('[id^="sfToolbarToggleButton"]')
      .exclude('[id^="sfToolbarMainContent"]')
      .exclude('[id^="sfMiniToolbar"]')
      .analyze();

    const blocking = results.violations.filter(
      (v) => v.impact === 'serious' || v.impact === 'critical',
    );
    expect(blocking).toHaveLength(0);
  });

  test('academy briefings have no serious/critical violations', async ({ page, request }) => {
    const user = await createTestUser(request, 'a11y_brief');
    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    for (const mode of ['qcm', 'chrono']) {
      await page.goto(`/fr/education/briefing?mode=${mode}`);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .exclude('#sfToolbar')
        .exclude('[id^="sfToolbarToggleButton"]')
        .exclude('[id^="sfToolbarMainContent"]')
        .exclude('[id^="sfMiniToolbar"]')
        .analyze();

      const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
      );
      if (blocking.length > 0) {
        console.error('Axe violations on briefing', mode, JSON.stringify(blocking, null, 2));
      }
      expect(blocking).toHaveLength(0);
    }
  });

  test('game result has no serious/critical violations', async ({ page, request }) => {
    test.setTimeout(120_000);
    const user = await createTestUser(request, 'a11y_result');
    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/fr/education/briefing?mode=qcm&difficulty=easy');
    await page.locator('form.brief-form button[type="submit"]').click();

    for (let step = 0; step < 40 && !/\/education\/result\//.test(page.url()); step++) {
      const option = page.locator('#answerForm input[name="answer"]').first();
      if (await option.count()) {
        await option.check();
        await page.locator('#answerForm button[type="submit"]').click();
      } else {
        await page.getByRole('link', { name: /question suivante/i }).click();
      }
      await page.waitForLoadState('domcontentloaded');
    }
    await expect(page).toHaveURL(/\/education\/result\/\d+/);

    if (await page.locator('#result-xp [data-xp-pending]').count()) {
      const refresh = await page.waitForResponse(
        (response) => response.request().headers()['turbo-frame'] === 'result-xp',
        { timeout: 5_000 },
      );
      expect(refresh.status()).toBe(200);
      await expect(page.locator('#result-xp .result-xp')).toHaveCount(1);
    }

    for (const width of [360, 1280]) {
      await page.setViewportSize({ width, height: 800 });
      await page.reload();
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .exclude('#sfToolbar')
        .exclude('[id^="sfToolbarToggleButton"]')
        .exclude('[id^="sfToolbarMainContent"]')
        .exclude('[id^="sfMiniToolbar"]')
        .analyze();

      const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
      );
      if (blocking.length > 0) {
        console.error('Axe violations on result', width, JSON.stringify(blocking, null, 2));
      }
      expect(blocking).toHaveLength(0);

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow).toBeLessThanOrEqual(0);
    }

    await page.setViewportSize({ width: 360, height: 800 });
    const toggle = page.locator('#result-answers-toggle');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.result-cta a').first()).toBeVisible();
    const small = await page.locator('.result-cta a, .result-pair, #result-answers-toggle').evaluateAll((els) =>
      els.filter((el) => el.getBoundingClientRect().height < 44).length,
    );
    expect(small).toBe(0);
  });

  test('dashboard has no serious/critical violations', async ({ page, request }) => {
    const user = await createTestUser(request, 'a11y_dash');
    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/fr/users');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .exclude('#sfToolbar')
      .exclude('[id^="sfToolbarToggleButton"]')
      .exclude('[id^="sfToolbarMainContent"]')
      .exclude('[id^="sfMiniToolbar"]')
      .analyze();

    const blocking = results.violations.filter(
      (v) => v.impact === 'serious' || v.impact === 'critical',
    );
    expect(blocking).toHaveLength(0);
  });
});
