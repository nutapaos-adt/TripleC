// Test 5 — Security: a second, unrelated ward_staff account must not be able to
// read the first ward_staff account's referral. This is the most important test in
// the suite — it exercises firestore.rules' `isOwner()` check directly (via a
// bypassing getDoc call), not just the client's `where('createdBy','==',uid)` query
// filter that referrals-list.js applies.
const { test, expect } = require("@playwright/test");
const {
  registerUser,
  uniqueEmail,
  createReferral,
  logout,
  attemptReadSpecificReferral,
  TEST_PASSWORD,
} = require("./helpers");

test("a second ward_staff account cannot read the first ward_staff account's referral", async ({ page }) => {
  const emailA = uniqueEmail("wardA-isolation");
  const emailB = uniqueEmail("wardB-isolation");
  const patientName = `Isolation Test Patient ${Date.now()}`;

  // Account A creates a referral.
  await registerUser(page, {
    name: "Tester WardA Isolation",
    email: emailA,
    password: TEST_PASSWORD,
    role: "ward_staff",
  });
  const referralId = await createReferral(page, {
    patientName,
    hn: `HN-ISO-${Date.now()}`,
    rawNotes: "บันทึกดิบทดสอบอัตโนมัติสำหรับทดสอบ cross-account isolation (Playwright test 5)",
  });
  await logout(page);

  // Account B (unrelated ward_staff) logs in.
  await registerUser(page, {
    name: "Tester WardB Isolation",
    email: emailB,
    password: TEST_PASSWORD,
    role: "ward_staff",
  });

  // (a) B's own case list (index.html, already loaded post-registration) must not
  // contain A's case — this is the client query filter (where createdBy == uid).
  await expect(page).toHaveURL(/index\.html/);
  await expect(page.locator(".referral-list")).not.toContainText(patientName);
  await expect(page.locator(".referral-list a.referral-row")).toHaveCount(0);

  // (b) The important one: a direct getDoc() on A's specific referral by id, as B,
  // must be denied server-side by firestore.rules — not merely absent from a list.
  const result = await page.evaluate(attemptReadSpecificReferral, referralId);

  expect(result.ok).toBe(false);
  expect(result.code).toBe("permission-denied");
});
