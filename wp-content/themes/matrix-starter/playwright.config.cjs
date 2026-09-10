const { defineConfig } = require('@playwright/test');

const baseURL = process.env.BASE_URL || process.env.WP_HOME || process.env.NETWORK_BASE_URL || 'http://localhost:10014';

module.exports = defineConfig({
  testDir: './tests',
  testMatch: ['**/*.spec.js'],
  timeout: 60 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  retries: 1,
  reporter: [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  outputDir: 'tests/pw-artifacts/e2e-results',
});
