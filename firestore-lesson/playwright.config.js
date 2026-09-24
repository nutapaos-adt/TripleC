// Playwright config for the firestore-lesson "Referral" test suite.
// Runs against the REAL deployed site + REAL Firebase project — there is no mock
// backend, no local dev server started here. See tests/README.md (helpers.js) for
// the account-creation strategy.
const { defineConfig, devices } = require("@playwright/test");

module.exports = defineConfig({
  testDir: "./tests",
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1, // serial — these tests create real Firebase Auth users / Firestore docs
  retries: 0,
  reporter: [["list"], ["json", { outputFile: "test-results/results.json" }]],
  use: {
    baseURL: "https://triplec-a5e75.web.app",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
