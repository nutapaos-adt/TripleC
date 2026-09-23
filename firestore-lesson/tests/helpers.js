// Shared helpers for the Triple C "Referral" Playwright suite.
//
// These tests run against the REAL deployed site (https://triplec-a5e75.web.app) and
// the REAL Firebase project (triplec-a5e75) — there is no emulator/mock backend.
// Every account used here is registered fresh through the app's own self-service
// register.html flow (never a shared/reused real credential), using obviously-fake
// @example.com addresses stamped with a timestamp + random suffix so parallel/repeat
// runs never collide.
//
// A few tests need to verify what Firestore itself allows/denies, not just what the
// UI shows (e.g. "does an unauthenticated read actually get rejected server-side").
// For those we reach into the page's *already-initialized* Firebase app (every page
// except login.html/register.html calls requireLogin(), which itself initializes the
// default app via firebase-config.js) and call the Firestore SDK directly via
// page.evaluate(). Because these functions are passed BY REFERENCE to page.evaluate,
// Playwright serializes them by source text and re-runs them inside the browser page
// — so each function below must be fully self-contained (only dynamic `import()` of
// the same gstatic CDN URLs the app itself uses, no closing over outer Node scope).

const { expect } = require("@playwright/test");

const TEST_PASSWORD = "TestPass123!";

/** Builds an obviously-fake, collision-resistant test email for a given purpose. */
function uniqueEmail(purpose) {
  const ts = Date.now();
  const rand = Math.random().toString(36).slice(2, 8);
  return `tester-${purpose}-${ts}-${rand}@example.com`;
}

/**
 * Registers a fresh account through register.html's own self-service flow (never
 * writes to Firestore/Auth directly) and waits for the redirect to index.html that
 * register.js performs on success.
 */
async function registerUser(page, { name, email, password = TEST_PASSWORD, role }) {
  await page.goto("/register.html");
  await page.locator("#name").fill(name);
  await page.locator("#email").fill(email);
  await page.locator("#role").selectOption(role);
  await page.locator("#password").fill(password);
  await page.locator("#password-confirm").fill(password);
  await page.locator("#submit-btn").click();
  await page.waitForURL("**/index.html", { timeout: 15000 });
}

/** Logs in an already-registered account via login.html's own form. */
async function loginUser(page, email, password = TEST_PASSWORD) {
  await page.goto("/login.html");
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await page.locator("#submit-btn").click();
  await page.waitForURL("**/index.html", { timeout: 15000 });
}

/** Logs out via the shared nav's logout button (only present on pages with nav-mount). */
async function logout(page) {
  const btn = page.locator("#nav-logout");
  await btn.click();
  await page.waitForURL("**/login.html", { timeout: 15000 });
}

/**
 * Fills and submits referral-create.html as whichever account is currently signed
 * in, then reads the resulting referral's id back out of index.html's list (the
 * newly-created case is always the first row for a fresh ward_staff test account).
 * Returns the referral id.
 */
async function createReferral(page, { patientName, hn, rawNotes }) {
  await page.goto("/referral-create.html");
  // loadCaseTypes() is async; the submit handler is only attached once it resolves,
  // so wait for at least one <option> before interacting with the form to avoid a
  // race where clicking submit does a native (un-intercepted) form GET instead.
  await expect(page.locator("#case-type option")).not.toHaveCount(0);

  await page.locator("#patient-name").fill(patientName);
  await page.locator("#patient-hn").fill(hn);
  await page.locator("#zone").selectOption("in_area");
  await page.locator("#source-type").selectOption("ward");
  await page.locator("#raw-notes").fill(rawNotes);
  await page.locator("#submit-btn").click();

  await page.waitForURL("**/index.html", { timeout: 15000 });

  const href = await page.locator(".referral-list a.referral-row").first().getAttribute("href");
  const url = new URL(href, page.url());
  return url.searchParams.get("id");
}

// ---------------------------------------------------------------------------
// Direct-Firestore probes (run inside the page via page.evaluate). Each reuses
// the page's already-initialized default Firebase app via getApp() instead of
// calling initializeApp() again (which would throw "app already exists").
// ---------------------------------------------------------------------------

/** Attempts an unfiltered `getDocs` on the referrals collection as whoever (if
 * anyone) is currently signed in on the page. Returns {ok:true,count} or
 * {ok:false,code,message} — never throws, so callers can assert on the shape. */
async function attemptReadReferralsCollection() {
  const { getApp } = await import("https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js");
  const { getFirestore, collection, getDocs } = await import(
    "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js"
  );
  const db = getFirestore(getApp());
  try {
    const snap = await getDocs(collection(db, "referrals"));
    return { ok: true, count: snap.size };
  } catch (err) {
    return { ok: false, code: err.code, message: err.message };
  }
}

/** Attempts a direct `getDoc` on one specific referrals/{id} document. */
async function attemptReadSpecificReferral(referralId) {
  const { getApp } = await import("https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js");
  const { getFirestore, doc, getDoc } = await import(
    "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js"
  );
  const db = getFirestore(getApp());
  try {
    const snap = await getDoc(doc(db, "referrals", referralId));
    return { ok: true, exists: snap.exists(), data: snap.exists() ? snap.data() : null };
  } catch (err) {
    return { ok: false, code: err.code, message: err.message };
  }
}

/**
 * Bypasses referral-create.html's HTML form entirely and writes a `referrals` doc
 * (plus its `patients` doc) directly via the SDK, with empty-string content fields,
 * as whichever ward_staff/admin account is currently signed in on the page. Used to
 * check whether firestore.rules independently enforces non-empty field content.
 *
 * As of the firestore.rules update deployed after this test first ran, the
 * `create` rules for `/patients` (non-empty `fullName`/`hn` + valid `zone` enum)
 * and `/referrals` (non-empty `rawNotes` + valid `sourceType`/`zone` enums) now
 * reject this — so this function is expected to fail at the `patients` addDoc step
 * (blank `fullName`/`hn`) with `permission-denied`, and no referral/patient doc
 * should ever be created. No cleanup needed in that case since nothing gets
 * written. (Historical note: before that rules deploy, this used to succeed and
 * documented a real gap — see git history / prior test report.)
 */
async function attemptCreateReferralWithEmptyFields() {
  const { getApp } = await import("https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js");
  const { getAuth } = await import("https://www.gstatic.com/firebasejs/10.13.2/firebase-auth.js");
  const { getFirestore, collection, addDoc, doc, deleteDoc } = await import(
    "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js"
  );
  const app = getApp();
  const auth = getAuth(app);
  const db = getFirestore(app);
  const user = auth.currentUser;
  if (!user) return { ok: false, message: "not signed in on this page" };

  try {
    const patientRef = await addDoc(collection(db, "patients"), {
      fullName: "",
      hn: "",
      zone: "in_area",
    });
    const referralRef = await addDoc(collection(db, "referrals"), {
      patientId: patientRef.id,
      caseTypeId: "",
      sourceType: "ward",
      sourceDetail: "",
      createdBy: user.uid,
      createdByName: user.displayName || user.email,
      rawNotes: "",
      aiSummary: null,
      confirmedSummary: null,
      confirmedBy: null,
      confirmedAt: null,
      zone: "in_area",
      status: "pending_review",
      closedAt: null,
      createdAt: new Date(),
    });

    // Only reached if the write unexpectedly succeeded (e.g. rules regressed) —
    // clean up rather than leave junk docs behind, since this account owns it and
    // it's still pending_review.
    await deleteDoc(doc(db, "referrals", referralRef.id));

    return { ok: true, referralId: referralRef.id, patientId: patientRef.id };
  } catch (err) {
    return { ok: false, code: err.code, message: err.message };
  }
}

module.exports = {
  TEST_PASSWORD,
  uniqueEmail,
  registerUser,
  loginUser,
  logout,
  createReferral,
  attemptReadReferralsCollection,
  attemptReadSpecificReferral,
  attemptCreateReferralWithEmptyFields,
};
