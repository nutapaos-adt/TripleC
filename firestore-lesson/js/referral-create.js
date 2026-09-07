import {
  collection, addDoc, getDocs, query, where, serverTimestamp,
} from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { db } from "./firebase-config.js";
import { requireLogin, renderNav } from "./session.js";

const NAV_LINKS = {
  ward_staff: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
  home_visit_team: [],
  admin: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
};

const el = {
  navMount: document.getElementById("nav-mount"),
  form: document.getElementById("create-form"),
  patientName: document.getElementById("patient-name"),
  patientHn: document.getElementById("patient-hn"),
  zone: document.getElementById("zone"),
  caseType: document.getElementById("case-type"),
  sourceType: document.getElementById("source-type"),
  sourceDetail: document.getElementById("source-detail"),
  rawNotes: document.getElementById("raw-notes"),
  error: document.getElementById("form-error"),
  submitBtn: document.getElementById("submit-btn"),
};

async function loadCaseTypes() {
  const snap = await getDocs(query(collection(db, "caseTypes"), where("isActive", "==", true)));
  el.caseType.innerHTML = snap.docs
    .map((d) => `<option value="${d.id}">${d.data().name}</option>`)
    .join("");
}

async function createReferral(session) {
  const patientRef = await addDoc(collection(db, "patients"), {
    fullName: el.patientName.value.trim(),
    hn: el.patientHn.value.trim(),
    zone: el.zone.value,
  });

  await addDoc(collection(db, "referrals"), {
    patientId: patientRef.id,
    caseTypeId: el.caseType.value,
    sourceType: el.sourceType.value,
    sourceDetail: el.sourceDetail.value.trim(),
    createdBy: session.user.uid,
    createdByName: session.name,
    rawNotes: el.rawNotes.value.trim(),
    aiSummary: null,
    confirmedSummary: null,
    confirmedBy: null,
    confirmedAt: null,
    zone: el.zone.value,
    status: "pending_review",
    closedAt: null,
    createdAt: serverTimestamp(),
  });
}

requireLogin().then(async (session) => {
  renderNav(el.navMount, session, NAV_LINKS);

  if (session.role !== "ward_staff" && session.role !== "admin") {
    document.querySelector(".card").innerHTML =
      '<p class="summary-empty">บทบาทของคุณไม่มีสิทธิ์เพิ่มเคสใหม่ — เฉพาะเจ้าหน้าที่หอผู้ป่วยและแอดมินเท่านั้น</p>';
    return;
  }

  await loadCaseTypes();

  el.form.addEventListener("submit", async (event) => {
    event.preventDefault();
    el.error.hidden = true;
    el.submitBtn.disabled = true;
    el.submitBtn.textContent = "กำลังบันทึก...";

    try {
      await createReferral(session);
      location.href = "index.html";
    } catch (err) {
      console.error(err);
      el.error.textContent = `บันทึกไม่สำเร็จ: ${err.message}`;
      el.error.hidden = false;
      el.submitBtn.disabled = false;
      el.submitBtn.textContent = "บันทึกเคส";
    }
  });
});
