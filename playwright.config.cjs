const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  use: {
    baseURL: 'http://127.0.0.1:8080',
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
