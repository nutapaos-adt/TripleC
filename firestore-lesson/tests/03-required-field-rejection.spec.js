// Test 3 — Submitting referral-create.html with a required field missing.
//
// Part A verifies the actual, currently-existing mechanism: the HTML `required`
// attribute on referral-create.html's fields (confirmed by reading the file) blocks
// native form submission client-side, so no Firestore write happens and the page
// doesn't navigate away.
//
// Part B (UPDATED after a firestore.rules deploy on the live project): a direct SDK
// write that bypasses the HTML form entirely (skipping the `required` attributes)
// used to succeed — that was a real rules-layer gap, flagged back rather than
// patched by this test suite. `firestore.rules`' `create` rules for `/patients`
// (non-empty `fullName`/`hn` + valid `zone` enum) and `/referrals` (non-empty
// `rawNotes` + valid `sourceType`/`zone` enums) were then updated server-side to
// close it, so this now asserts the bypass is REJECTED with `permission-denied`
// (previously asserted `ok === true`; expectation flipped because the app's rules
// changed, not because the test was wrong before).
const { test, expect } = require("@playwright/test");
const {
  registerUser,
  uniqueEmail,
  attemptCreateReferralWithEmptyFields,
  TEST_PASSWORD,
} = require("./helpers");

test("required fields: client-side `required` blocks empty submit; firestore.rules also rejects a direct-SDK bypass with blank content", async ({ page }) => {
  const email = uniqueEmail("ward-required-field");

  await registerUser(page, {
    name: "Tester WardRequiredField",
    email,
    password: TEST_PASSWORD,
    role: "ward_staff",
  });

  // --- Part A: client-side required attribute ---
  await page.goto("/referral-create.html");
  await expect(page.locator("#case-type option")).not.toHaveCount(0);

  await page.locator("#patient-name").fill("Required Field Test Patient");
  await page.locator("#patient-hn").fill(`HN-REQ-${Date.now()}`);
  await page.locator("#zone").selectOption("in_area");
  await page.locator("#source-type").selectOption("ward");
  // #raw-notes intentionally left blank — it has the `required` attribute.
  await page.locator("#submit-btn").click();

  // Give any (unwanted) navigation a moment to happen, then assert we're still here.
  await page.waitForTimeout(1000);
  await expect(page).toHaveURL(/referral-create\.html/);

  const rawNotesValidity = await page.locator("#raw-notes").evaluate((el) => el.validity.valid);
  expect(rawNotesValidity).toBe(false);
  const rawNotesValidationMessage = await page
    .locator("#raw-notes")
    .evaluate((el) => el.validationMessage);
  expect(rawNotesValidationMessage.length).toBeGreaterThan(0);

  // --- Part B: firestore.rules now independently enforces non-empty content ---
  // Same signed-in ward_staff account, but this bypasses the form/required attrs
  // entirely and writes straight through the SDK.
  const bypassResult = await page.evaluate(attemptCreateReferralWithEmptyFields);

  // Rules-layer enforcement confirmed: the blank-`fullName`/`hn` patient write (the
  // first of the two writes attempted) is now rejected server-side.
  expect(bypassResult.ok).toBe(false);
  expect(bypassResult.code).toBe("permission-denied");
});
