import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';
import { execSync } from 'node:child_process';

function seedFreeLimitIntrusSessionsToday(userId: number): void {
  const sql =
    `INSERT INTO game_session (user_id, game_mode, difficulty, score, correct_answers, total_questions, started_at, finished_at, duration_seconds) ` +
    `SELECT ${userId}, 'intrus', 'easy', 0, 0, 10, NOW(), NOW(), 60 FROM information_schema.tables LIMIT 5`;
  execSync(`docker exec p8.5 php /var/www/html/spicymatch/bin/console doctrine:query:sql ${JSON.stringify(sql)}`, { stdio: 'pipe' });
}

test.describe('Academy daily session limit', () => {
  test('6th Intrus session is blocked with a warning flash for a free user', async ({ page, request }) => {
    const user = await createTestUser(request, 'daily_limit');

    seedFreeLimitIntrusSessionsToday(user.id);

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    await page.goto('/fr/education/play-live/intrus?difficulty=easy');
    await expect(page).toHaveURL(/\/education\/?$/);
    await expect(page.getByText(/limite quotidienne|5 sessions/i).first()).toBeVisible();
  });
});
