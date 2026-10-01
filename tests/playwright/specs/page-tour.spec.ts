import { test, expect, Page } from '@playwright/test';

const tourDialog = (page: Page) => page.getByRole('dialog', { name: 'Étape du tutoriel' });
const welcomeDialog = (page: Page) => page.getByRole('dialog', { name: /Bienvenue sur SpicyMatch/ });
const storedTours = (page: Page) => page.evaluate(() => JSON.parse(localStorage.getItem('sm_tours') || '{}'));

test.describe('Page tours (anonymous, localStorage)', () => {
  test('welcome modal shows once on the home page', async ({ page }) => {
    await page.goto('/fr/');
    await expect(welcomeDialog(page)).toBeVisible();
    expect(await storedTours(page)).toMatchObject({ welcome: 1 });

    await page.reload();
    await page.waitForTimeout(1000);
    await expect(welcomeDialog(page)).toBeHidden();
  });

  test('spices tour shows once, then again after a version bump', async ({ page }) => {
    await page.goto('/fr/epices/');
    await expect(tourDialog(page)).toBeVisible();
    expect(await storedTours(page)).toMatchObject({ spices: 1 });

    await page.reload();
    await page.waitForTimeout(1000);
    await expect(tourDialog(page)).toBeHidden();

    await page.evaluate(() => localStorage.setItem('sm_tours', JSON.stringify({ spices: 0 })));
    await page.reload();
    await expect(tourDialog(page)).toBeVisible();
  });

  test('skipping the welcome modal silences every tour', async ({ page }) => {
    await page.goto('/fr/');
    await welcomeDialog(page).getByRole('button', { name: /Je connais déjà/ }).click();

    await page.goto('/fr/epices/');
    await page.waitForTimeout(1000);
    await expect(tourDialog(page)).toBeHidden();
  });

  for (const { name, path, first } of [
    { name: 'lab', path: '/fr/spicymatch/', first: 'Auto ou Manuel ?' },
    { name: 'academy', path: '/fr/education/', first: '6 mini-jeux' },
  ]) {
    test(`${name} tour starts on its first step`, async ({ page }) => {
      await page.goto(path);
      await expect(tourDialog(page)).toBeVisible();
      await expect(tourDialog(page)).toContainText(first);
      expect(await storedTours(page)).toMatchObject({ [name]: 1 });
    });
  }

  test('tour renders as a bottom sheet on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/fr/epices/');

    await expect(tourDialog(page)).toBeVisible();
    await expect(tourDialog(page)).toHaveClass(/inset-x-0/);
  });
});
