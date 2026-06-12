import { test, expect } from '@playwright/test';
import { createTestUser } from '../fixtures/user';
import { execSync } from 'node:child_process';

function seedFiveIntrusSessionsToday(userId: number): void {
  // Seed 5 finished GameSession rows for today so the 6th attempt trips the daily limit.
  // Runs against the dev DB used by the Apache container — same as the test hits.
  const sql =
    `INSERT INTO game_session (user_id, game_mode, difficulty, score, correct_answers, total_questions, started_at, finished_at, duration_seconds) ` +
    `SELECT ${userId}, 'intrus', 'easy', 0, 0, 10, NOW(), NOW(), 60 FROM information_schema.tables LIMIT 5`;
  execSync(`docker exec p8.4 php /var/www/html/spicymatch/bin/console doctrine:query:sql ${JSON.stringify(sql)}`, { stdio: 'pipe' });
}

test.describe('Academy daily session limit', () => {
  test('6th Intrus session is blocked with a warning flash', async ({ page, request }) => {
    const user = await createTestUser(request, 'daily_limit');

    seedFiveIntrusSessionsToday(user.id);

    await page.goto('/login');
    await page.getByLabel(/pseudo|identifiant|utilisateur/i).fill(user.username);
    await page.getByLabel(/mot de passe/i).fill(user.password);
    await page.getByRole('button', { name: /connexion|se connecter/i }).click();
    await page.waitForURL(/\/$|\/users\/?$/);

    // The 6th attempt is redirected back to the hub with a warning flash.
    await page.goto('/fr/education/play-live/intrus?difficulty=easy');
    await expect(page).toHaveURL(/\/education\/?$/);
    await expect(page.getByText(/limite quotidienne|5 sessions/i).first()).toBeVisible();
  });
});
