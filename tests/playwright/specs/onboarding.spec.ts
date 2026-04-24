import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';

test.describe('Onboarding', () => {
  test('new user completes signup and lands on the home page logged in', async ({ page, request }) => {
    const user = await createTestUser(request, 'onboard');

    await page.goto('/login');
    await page.getByLabel(/identifiant|pseudo|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();

    await page.waitForURL(/\/$|\/users\/?$/);
    await expect(page.getByText(user.username, { exact: false }).first()).toBeVisible();
  });
});
