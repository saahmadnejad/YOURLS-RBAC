import { defineConfig } from '@playwright/test';

const BASE = process.env.RBAC_E2E_BASE_URL || 'http://localhost:8081';

export default defineConfig({
  testDir: './specs',
  timeout: 30_000,
  retries: 0,
  workers: 1, // tests share DB state; serialize
  use: {
    baseURL: BASE,
    channel: 'chrome', // system Chrome; avoids Playwright browser download
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
