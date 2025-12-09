const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  use: {
    baseURL: 'http://localhost:8080',
    browserName: 'chromium',
    headless: false,
    trace: 'on',
    video: 'on',
    screenshot: 'on',
  },
});
