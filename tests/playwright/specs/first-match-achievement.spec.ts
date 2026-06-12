import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';

/**
 * Smoke test: a logged-in brand-new user reaches the Lab and sees the XP CTA + selectable spices.
 *
 * Full match → messenger → achievement flow is covered by the PHP integration tests
 * (GamificationHandlerTest, AchievementCheckerTest). Reproducing it end-to-end here would
 * require the messenger worker running in sync transport, which is infra-fragile in Playwright.
 */
test.describe('First match gamification — Lab entry point', () => {
  test('new user lands on the Lab with XP gauge and selectable spices', async ({ page, request }) => {
    const user = await createTestUser(request, 'first_match');

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/fr/spicymatch/');
    await page.waitForLoadState('networkidle');

    // toBeAttached : à 0 XP la jauge a une largeur nulle, donc "non visible"
    await expect(page.locator('.gauge-bar').first()).toBeAttached();
    await expect(page.locator('input[id^="spice_"]').first()).toBeVisible();
  });
});
