import {
  collection, addDoc, getDocs, query, where, serverTimestamp,
} from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { db } from "./firebase-config.js";
import { requireLogin, renderNav } from "./session.js";
import { callAiJson } from "./ai-service.js";

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
  aiSuggestBtn: document.getElementById("ai-suggest-btn"),
  aiSuggestBox: document.getElementById("ai-suggest-box"),
  aiSuggestText: document.getElementById("ai-suggest-text"),
  aiSuggestApply: document.getElementById("ai-suggest-apply"),
  aiSuggestError: document.getElementById("ai-suggest-error"),
};

let caseTypeOptions = [];

async function loadCaseTypes() {
  const snap = await getDocs(query(collection(db, "caseTypes"), where("isActive", "==", true)));
  caseTypeOptions = snap.docs.map((d) => ({ id: d.id, name: d.data().name }));
  el.caseType.innerHTML = caseTypeOptions
    .map((ct) => `<option value="${ct.id}">${ct.name}</option>`)
    .join("");
}

// ผู้ช่วย AI ระดับ 1 — งานเดียว: อ่านบันทึกดิบแล้วแนะนำประเภทเคสที่ตรงที่สุด
// เป็นแค่ "ร่าง" เสมอ — ไม่เซ็ตค่าลง dropdown อัตโนมัติ ต้องกด "ใช้คำแนะนำนี้" เอง
let lastSuggestion = null;

async function onAiSuggest() {
  const rawNotes = el.rawNotes.value.trim();
  el.aiSuggestError.hidden = true;
  el.aiSuggestBox.hidden = true;

  if (!rawNotes) {
    el.aiSuggestError.textContent = "กรุณากรอกบันทึกดิบก่อน ให้ AI ช่วยแนะนำประเภทเคส";
    el.aiSuggestError.hidden = false;
    return;
  }

  el.aiSuggestBtn.disabled = true;
  el.aiSuggestBtn.textContent = "🤖 กำลังวิเคราะห์...";

  try {
    const optionsText = caseTypeOptions.map((ct) => `- ${ct.id}: ${ct.name}`).join("\n");
    const prompt = `คุณเป็นผู้ช่วยของทีมรับเคสในโรงพยาบาล อ่านบันทึกดิบของเจ้าหน้าที่ต่อไปนี้ แล้วเลือกประเภทเคส (caseType) ที่ตรงที่สุดจากรายการที่กำหนด ตอบกลับเป็น JSON เท่านั้น รูปแบบ {"caseTypeId": "...", "caseTypeName": "...", "reason": "เหตุผลสั้นๆ ภาษาไทย"} ห้ามมีข้อความอื่นนอกจาก JSON

บันทึกดิบ: "${rawNotes}"

ประเภทเคสที่เลือกได้:
${optionsText}`;

    const result = await callAiJson(prompt);
    if (!result.caseTypeId || !caseTypeOptions.some((ct) => ct.id === result.caseTypeId)) {
      throw new Error("AI แนะนำประเภทเคสที่ไม่อยู่ในรายการ");
    }

    lastSuggestion = result;
    el.aiSuggestText.textContent = `แนะนำ: ${result.caseTypeName || result.caseTypeId}${result.reason ? ` — ${result.reason}` : ""}`;
    el.aiSuggestApply.textContent = "ใช้คำแนะนำนี้";
    el.aiSuggestApply.disabled = false;
    el.aiSuggestBox.hidden = false;
  } catch (err) {
    console.error(err);
    el.aiSuggestError.textContent = err.message;
    el.aiSuggestError.hidden = false;
  } finally {
    el.aiSuggestBtn.disabled = false;
    el.aiSuggestBtn.textContent = "🤖 ให้ AI ช่วยแนะนำประเภทเคส (จากบันทึกดิบด้านบน)";
  }
}

function onApplySuggestion() {
  if (!lastSuggestion) return;
  el.caseType.value = lastSuggestion.caseTypeId;
  el.aiSuggestApply.textContent = "ใช้คำแนะนำนี้แล้ว ✓";
  el.aiSuggestApply.disabled = true;
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

  el.aiSuggestBtn.addEventListener("click", onAiSuggest);
  el.aiSuggestApply.addEventListener("click", onApplySuggestion);

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
