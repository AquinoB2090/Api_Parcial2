import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  timeout: 150000,
  expect: { timeout: 12000 },
  workers: 1,
  fullyParallel: false,
  use: { baseURL: 'http://127.0.0.1:8010', timezoneId: 'UTC', viewport: { width: 1440, height: 1000 }, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  webServer: { command: 'node scripts/e2e-server.mjs', url: 'http://127.0.0.1:8010/up', timeout: 90000, reuseExistingServer: false },
});
