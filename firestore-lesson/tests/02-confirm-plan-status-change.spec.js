// Test 2 — Status-changing button: home_visit_team's "ยืนยันแผนดูแล" button on
// referral-detail.html must move status pending_review -> plan_confirmed. Uses two
// separate accounts (a ward_staff creator + a home_visit_team confirmer) because
// ACL.md/firestore.rules ban self-approval unconditionally — the confirming account
// must never be the referral's own creator.
const { test, expect } = require("@playwright/test");
const {
  registerUser,
  uniqueEmail,
  createReferral,
  logout,
  attemptReadSpecificReferral,
  TEST_PASSWORD,
} = require("./helpers");

test("home_visit_team confirming a plan moves status pending_review -> plan_confirmed", async ({ page }) => {
  const wardEmail = uniqueEmail("ward-for-confirm");
  const nurseEmail = uniqueEmail("nurse-confirm");
  const patientName = `Confirm Flow Patient ${Date.now()}`;

  // Ward staff creates the case.
  await registerUser(page, {
    name: "Tester WardForConfirm",
    email: wardEmail,
    password: TEST_PASSWORD,
    role: "ward_staff",
  });
  const referralId = await createReferral(page, {
    patientName,
    hn: `HN-CONFIRM-${Date.now()}`,
    rawNotes: "บันทึกดิบทดสอบอัตโนมัติสำหรับทดสอบการยืนยันแผนดูแล (Playwright test 2)",
  });
  await logout(page);

  // A different account (home_visit_team) confirms the plan.
  await registerUser(page, {
    name: "Tester NurseConfirm",
    email: nurseEmail,
    password: TEST_PASSWORD,
    role: "home_visit_team",
  });
  await page.goto(`/referral-detail.html?id=${referralId}`);

  await expect(page.locator("#status-badge")).toHaveText("รอตรวจสอบ");
  await expect(page.locator("#confirm-btn")).toBeVisible();

  // onConfirmPlan() no-ops silently if the confirmed-summary textarea is empty, so
  // it must be filled in before clicking (mirrors what a real nurse would type).
  await page.locator("#confirmed-summary").fill(
    "พยาบาลตรวจสอบแล้ว — ยืนยันแผนดูแลทดสอบอัตโนมัติ (Playwright test 2)"
  );
  await page.locator("#confirm-btn").click();

  // UI-level assertion: badge text + class update after the button's updateDoc + re-render.
  await expect(page.locator("#status-badge")).toHaveText("ยืนยันแผนแล้ว", { timeout: 15000 });
  await expect(page.locator("#status-badge")).toHaveClass(/status-plan_confirmed/);
  await expect(page.locator("#confirm-btn")).toBeHidden();

  // Data-layer assertion: read the actual Firestore document back directly, not
  // just trust the rendered label.
  const result = await page.evaluate(attemptReadSpecificReferral, referralId);
  expect(result.ok).toBe(true);
  expect(result.data.status).toBe("plan_confirmed");
  expect(result.data.confirmedBy).toBeTruthy();
  expect(result.data.confirmedAt).toBeTruthy();
});
