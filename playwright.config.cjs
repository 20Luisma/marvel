const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  timeout: 60000, // 60s por test (Hostinger puede tardar desde runners externos)
  retries: 2,     // Reintentar hasta 2 veces si falla por timeout/red
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8080',
    browserName: 'chromium',
    headless: true,
    trace: 'on',
    video: 'on',
    screenshot: 'on',
    launchOptions: {
      args: ['--disable-crash-reporter', '--crash-dumps-dir=/tmp'],
    },
  },
});
