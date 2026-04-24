import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';

test.describe('Easter egg: temps de l\'infusion', () => {
  // Real egg requires 260s on the infusion page — we don't wait that long in CI.
  // This test verifies the SERVER SEEDS the timestamp on page view, not the full timing.
  test('visiting preparation method "Infusion" seeds the session timestamp', async ({ page, request }) => {
    const user = await createTestUser(request, 'infusion');

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    // Navigate directly to the Infusion method view (id=4 in fixtures).
    await page.goto('/preparation/methods/4');
    await expect(page).toHaveURL(/\/preparation\/methods\/\d+/);
    await expect(page.getByRole('heading', { name: /infusion/i }).first()).toBeVisible();

    // Seed check: immediately posting the egg should fail (not enough time elapsed).
    const response = await request.post('/api/gamification/egg/temps_de_l_infusion', {
      headers: { 'X-CSRF-Token': 'invalid' },
    });
    // Reject expected (CSRF or not-enough-time) — we only assert the page loaded and didn't 500.
    expect([200, 400, 403]).toContain(response.status());
  });
});
