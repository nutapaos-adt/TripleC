// Test 1 — Primary user workflow: ward_staff creates a referral -> it appears in
// index.html's case list. Grounded in spec.md §1 (referral-create.html / index.html)
// and ACL.md (ward_staff can create + sees their own cases).
const { test, expect } = require("@playwright/test");
const { registerUser, uniqueEmail, createReferral, TEST_PASSWORD } = require("./helpers");

test("ward_staff creates a referral and it appears in their own case list", async ({ page }) => {
  const email = uniqueEmail("ward-primary");
  const patientName = `Automated Test Patient ${Date.now()}`;
  const hn = `HN-AUTO-${Date.now()}`;

  await registerUser(page, {
    name: "Tester WardPrimary",
    email,
    password: TEST_PASSWORD,
    role: "ward_staff",
  });

  const referralId = await createReferral(page, {
    patientName,
    hn,
    rawNotes: "บันทึกดิบทดสอบอัตโนมัติ — ไม่ใช่ข้อมูลผู้ป่วยจริง (Playwright test 1)",
  });

  expect(referralId).toBeTruthy();

  // We should already be on index.html post-submit (createReferral waits for this),
  // and the new case must be visible in this ward_staff's own list.
  await expect(page).toHaveURL(/index\.html/);
  const row = page.locator(".referral-list a.referral-row", { hasText: patientName });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText("รอตรวจสอบ"); // STATUS_TEXT.pending_review
});
