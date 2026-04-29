import { chromium } from 'playwright';
import { writeFileSync } from 'fs';

const BASE = 'https://spicymatch.sf4.p84.dbm-local.com';
const browser = await chromium.launch({ headless: true, args: ['--ignore-certificate-errors'] });
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();

await page.goto(`${BASE}/login`);
await page.fill('input[name="username"]', 'alice');
await page.fill('input[name="password"]', 'Alice1234!');
await page.locator('form').evaluate(f => f.submit());
await page.waitForURL(`${BASE}/`, { timeout: 10000 });

// Verify auth works
await page.goto(`${BASE}/spicymatch/`);
const url = page.url();
console.log('Lab URL after nav:', url);

const cookies = await ctx.cookies();
const session = cookies.find(c => c.name.startsWith('PHPSESSID') || c.name === 'session' || c.name.toLowerCase().includes('sess'));
console.log('Session cookie:', JSON.stringify(session));

const cookieHeader = cookies.map(c => `${c.name}=${c.value}`).join('; ');
writeFileSync('/tmp/lh-headers.json', JSON.stringify({ Cookie: cookieHeader }));
console.log('Wrote /tmp/lh-headers.json');

await browser.close();
