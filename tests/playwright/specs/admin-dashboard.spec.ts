import { test, expect } from '@playwright/test';
import { createTestUser, promoteToAdmin } from '../fixtures/user';

/**
 * Opt-in smoke: promotes a test user to ROLE_ADMIN via direct SQL UPDATE, then
 * asserts the 4 KPI cards + 3 Chart.js canvases render on /admin.
 *
 * Destructive — skipped unless E2E_ADMIN=1 is set, to prevent accidental
 * privilege elevation when running the default `yarn e2e` sweep.
 */
const runAdminSpecs = process.env.E2E_ADMIN === '1';

test.describe('Admin dashboard', () => {
  test.skip(!runAdminSpecs, 'Set E2E_ADMIN=1 to enable admin smoke (it elevates a test user).');

  test('ROLE_ADMIN sees the 4 KPI cards + charts render on /admin', async ({ page, request }) => {
    const user = await createTestUser(request, 'admin_smoke');
    promoteToAdmin(user.id);

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/admin');
    await expect(page.getByText(/Dashboard SpicyMatch/i)).toBeVisible();

    await expect(page.getByText(/Utilisateurs/i).first()).toBeVisible();
    await expect(page.getByText(/Mélanges/i).first()).toBeVisible();
    await expect(page.getByText(/Quiz joués/i)).toBeVisible();
    await expect(page.getByText(/Succès débloqués/i)).toBeVisible();

    await expect(page.locator('canvas')).toHaveCount(3);
  });

  test('ROLE_ADMIN reaches the gamification stats page', async ({ page, request }) => {
    const user = await createTestUser(request, 'admin_gami');
    promoteToAdmin(user.id);

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/admin/gamification/stats');
    await expect(page.locator('body')).toContainText(/gamif|achievement|succès|xp/i);
  });
});
