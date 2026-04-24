import { APIRequestContext, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

export interface TestUser {
  id: number;
  username: string;
  email: string;
  password: string;
}

/**
 * Create a test user via the normal registration flow.
 * Returns credentials to use in tests.
 *
 * Prefix ensures uniqueness across parallel specs; cleanup on test teardown.
 */
export async function createTestUser(
  request: APIRequestContext,
  prefix = 'e2e',
): Promise<TestUser> {
  const suffix = Math.random().toString(36).slice(2, 10);
  const username = `${prefix}_${suffix}`;
  const email = `${prefix}_${suffix}@example.test`;
  const password = 'Pa55word!test';

  // Go through the normal registration form — posts CSRF-protected.
  const registerPage = await request.get('/register');
  expect(registerPage.ok()).toBeTruthy();
  const html = await registerPage.text();
  // Token field is `registration_form[_token]` — match that exact name.
  const csrfMatch = html.match(/name="registration_form\[_token\]"[^>]*value="([^"]+)"/);
  const csrfToken = csrfMatch?.[1];
  if (!csrfToken) {
    throw new Error('Register CSRF token not found — registration form may have changed');
  }

  const response = await request.post('/register', {
    form: {
      'registration_form[username]': username,
      'registration_form[mail]': email,
      'registration_form[plainPassword]': password,
      'registration_form[_token]': csrfToken,
    },
    maxRedirects: 0,
  });
  expect([200, 302]).toContain(response.status());

  // Resolve id via CLI — cheapest round-trip for a test helper.
  const sql = `SELECT id FROM users WHERE username = '${username}'`;
  const out = execSync(
    `docker exec p8.4 php /var/www/html/spicymatch/bin/console doctrine:query:sql ${JSON.stringify(sql)}`,
    { encoding: 'utf8' },
  );
  const idMatch = out.match(/\b(\d+)\b/);
  const id = idMatch ? Number(idMatch[1]) : Number.NaN;
  if (!Number.isFinite(id)) {
    throw new Error(`Failed to resolve id for test user ${username}: ${out}`);
  }

  return { id, username, email, password };
}

/**
 * Promote a test user to ROLE_ADMIN via direct SQL. Manual test fixture only —
 * never used outside Playwright specs that need admin access.
 */
export function promoteToAdmin(userId: number): void {
  const sql = `UPDATE users SET roles = '["ROLE_ADMIN"]' WHERE id = ${userId}`;
  execSync(
    `docker exec p8.4 php /var/www/html/spicymatch/bin/console doctrine:query:sql ${JSON.stringify(sql)}`,
    { stdio: 'pipe' },
  );
}

/**
 * Delete a test user via an admin endpoint (or mark deleted if no endpoint exists).
 * Called in afterEach — keeps the DB tidy.
 */
export async function deleteTestUser(_request: APIRequestContext, _user: TestUser): Promise<void> {
  // TODO: implement when /admin/users/{username}/delete exists.
  // For now, test users accumulate in DB — acceptable because E2E runs are
  // manual and infrequent. A cleanup command can purge `e2e_*` usernames.
}
