import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright runs are manual (no CI job) — see docs/running-e2e.md.
 * Base URL defaults to the local Docker stack; override with APP_URL.
 */
export default defineConfig({
  testDir: './specs',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never', outputFolder: '../../var/playwright-report' }]],

  use: {
    // `||` (not `??`) coerces empty env vars to the default.
    baseURL: process.env.APP_URL || 'https://spicymatch.sf4.p84.dbm-local.com',
    // Les specs matchent du texte FR
    locale: 'fr-FR',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // PW_NO_VIDEO=1 : hôtes sans binaire ffmpeg Playwright
    video: process.env.PW_NO_VIDEO ? 'off' : 'retain-on-failure',
    ignoreHTTPSErrors: true,
    // APIRequestContext inherits baseURL via `extraHTTPHeaders` alone — must set it explicitly.
    extraHTTPHeaders: {
      'X-Playwright-Run': '1',
    },
  },

  projects: [
    {
      name: 'chromium',
      // PW_CHANNEL=chrome : hôtes sans chromium Playwright
      use: { ...devices['Desktop Chrome'], channel: process.env.PW_CHANNEL || undefined },
    },
  ],
});
