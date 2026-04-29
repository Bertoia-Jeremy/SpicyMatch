import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';

test.describe('Gamification opt-out', () => {
  test('disabled gamification hides the home banner (level + XP progress)', async ({ page, request }) => {
    const user = await createTestUser(request, 'optout');

    // Skip onboarding modal in tests — we don't want it interfering with click targets.
    await page.goto('/');
    await page.evaluate(() => localStorage.setItem('sm_onboarding', 'done'));

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    // Home banner shows "encore X XP → niv. Y" when gamification is enabled.
    await page.goto('/');
    await expect(page.locator('main').getByText(/Niveau\s*\d+/i).first()).toBeVisible();

    await page.goto('/users/configuration');
    // Remove any stale onboarding overlays (DEV-only race) before clicking.
    await page.evaluate(() => {
        document.querySelectorAll('[role="dialog"], .sf-toolbar').forEach(el => el.remove());
    });
    await page.getByRole('button', { name: /d(é|e)sactiver/i }).first().click();
    await page.waitForLoadState('networkidle');

    // After opt-out the home banner disappears entirely.
    await page.goto('/');
    await expect(page.getByText(/encore.*XP.*niv/i)).toHaveCount(0);
  });
});
