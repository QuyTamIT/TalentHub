const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: __dirname,
  testMatch: 'merge-readiness-ui.spec.js',
  fullyParallel: false,
  workers: 1,
  reporter: 'line',
  use: { browserName: 'chromium', headless: true },
});
