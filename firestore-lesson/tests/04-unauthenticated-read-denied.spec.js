// Test 4 — Security: an unauthenticated visitor must not be able to read protected
// data. Verifies the actual Firestore read is denied (firestore.rules' isSignedIn()
// gate), not just that the page redirects to login.html.
const { test, expect } = require("@playwright/test");
const { attemptReadReferralsCollection } = require("./helpers");

test("unauthenticated visitor cannot read the referrals collection via a direct Firestore call", async ({ page }) => {
  // Navigate to login.html specifically: it initializes the default Firebase app
  // (via firebase-config.js) but never signs anyone in and never calls
  // requireLogin(), so auth.currentUser stays null here — this is a real
  // unauthenticated Firestore client, not just "a page that redirected."
  await page.goto("/login.html");

  const isSignedIn = await page.evaluate(() => {
    // Cheap sanity check that we really are anonymous before probing.
    return document.getElementById("login-form") !== null;
  });
  expect(isSignedIn).toBe(true);

  const result = await page.evaluate(attemptReadReferralsCollection);

  expect(result.ok).toBe(false);
  expect(result.code).toBe("permission-denied");
});
