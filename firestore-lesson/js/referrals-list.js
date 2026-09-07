import { collection, query, where, getDocs, getDoc, doc } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { db } from "./firebase-config.js";
import { requireLogin, renderNav } from "./session.js";

const STATUS_TEXT = {
  pending_review: "รอตรวจสอบ",
  plan_confirmed: "ยืนยันแผนแล้ว",
  in_progress: "อยู่ระหว่างติดตาม",
  closed: "ปิดเคสแล้ว",
};

const NAV_LINKS = {
  ward_staff: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
  home_visit_team: [],
  admin: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
};

const el = {
  navMount: document.getElementById("nav-mount"),
  newCaseLink: document.getElementById("new-case-link"),
  scopeHint: document.getElementById("scope-hint"),
  emptyState: document.getElementById("empty-state"),
  list: document.getElementById("referral-list"),
};

async function fetchPatientName(patientId) {
  if (!patientId) return "-";
  const snap = await getDoc(doc(db, "patients", patientId));
  return snap.exists() ? snap.data().fullName : patientId;
}

function formatDate(value) {
  if (!value) return "-";
  const date = typeof value?.toDate === "function" ? value.toDate() : new Date(value);
  return date.toLocaleString("th-TH", { dateStyle: "medium", timeStyle: "short" });
}

async function loadReferrals(session) {
  // Rule of thumb for Firestore security rules: the query's own filter must match
  // what firestore.rules checks, otherwise the whole `list` request is denied —
  // so a ward_staff query is scoped to their own cases up front, not filtered after the fact.
  const isOwnCasesOnly = session.role === "ward_staff";
  el.scopeHint.textContent = isOwnCasesOnly
    ? "แสดงเฉพาะเคสที่คุณเป็นผู้สร้าง"
    : "แสดงเคสทั้งหมดในระบบ";

  const base = collection(db, "referrals");
  // No orderBy here on purpose — combining it with `where` would need a composite
  // Firestore index; sorting the (small) result client-side avoids that entirely.
  const q = isOwnCasesOnly ? query(base, where("createdBy", "==", session.user.uid)) : base;

  const snap = await getDocs(q);
  const referrals = await Promise.all(
    snap.docs.map(async (d) => {
      const data = d.data();
      return {
        id: d.id,
        patientName: await fetchPatientName(data.patientId),
        status: data.status,
        createdByName: data.createdByName ?? data.createdBy,
        createdAtValue: data.createdAt,
        createdAt: formatDate(data.createdAt),
      };
    })
  );
  referrals.sort((a, b) => (b.createdAtValue?.toMillis?.() ?? 0) - (a.createdAtValue?.toMillis?.() ?? 0));
  return referrals;
}

function render(referrals) {
  el.emptyState.hidden = referrals.length > 0;
  el.list.innerHTML = referrals
    .map(
      (r) => `
      <a class="card referral-row" href="referral-detail.html?id=${r.id}">
        <div>
          <div class="case-id">${r.id}</div>
          <div class="referral-row-name">${r.patientName}</div>
          <div class="referral-row-meta">สร้างโดย ${r.createdByName} · ${r.createdAt}</div>
        </div>
        <span class="status-badge status-${r.status}">${STATUS_TEXT[r.status] ?? r.status}</span>
      </a>`
    )
    .join("");
}

requireLogin().then(async (session) => {
  renderNav(el.navMount, session, NAV_LINKS);
  el.newCaseLink.hidden = !(session.role === "ward_staff" || session.role === "admin");

  try {
    render(await loadReferrals(session));
  } catch (err) {
    console.error(err);
    el.list.innerHTML = `<div class="card" style="border-color:#c0392b"><strong>โหลดรายการเคสไม่สำเร็จ:</strong> ${err.message}</div>`;
  }
});
