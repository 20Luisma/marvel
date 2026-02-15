const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  timeout: 90000, // 90s — Hostinger puede tardar desde runners CI externos
  retries: 2, // Reintento si falla por lag de red
  use: {
    baseURL: process.env.APP_URL || 'https://iamasterbigschool.contenido.creawebes.com',
    browserName: 'chromium',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
