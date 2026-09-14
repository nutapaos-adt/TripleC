import {
  doc, getDoc, updateDoc, deleteDoc, serverTimestamp, collection, addDoc,
} from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { db } from "./firebase-config.js";
import { requireLogin, renderNav } from "./session.js";
import { callAiJson, AI_MODEL } from "./ai-service.js";

const REFERRAL_ID = new URLSearchParams(location.search).get("id");

const NAV_LINKS = {
  ward_staff: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
  home_visit_team: [],
  admin: [{ href: "referral-create.html", label: "+ เพิ่มเคสใหม่" }],
};

const SOURCE_TYPE_TEXT = {
  ward: "หอผู้ป่วย (Ward)",
  opd: "OPD",
  internal_dept: "แผนกภายในโรงพยาบาล",
  external_hospital: "โรงพยาบาลภายนอก (ส่งต่อ)",
};

let referral = null;
let session = null;

const STEPS = [
  { key: "pending_review", label: "รอตรวจสอบ" },
  { key: "plan_confirmed", label: "ยืนยันแผนแล้ว" },
  { key: "in_progress", label: "ติดตามอาการ" },
  { key: "closed", label: "ปิดเคส" },
];

const STATUS_TEXT = {
  pending_review: "รอตรวจสอบ",
  plan_confirmed: "ยืนยันแผนแล้ว",
  in_progress: "อยู่ระหว่างติดตาม",
  closed: "ปิดเคสแล้ว",
};

const el = {
  navMount: document.getElementById("nav-mount"),
  deleteBtn: document.getElementById("delete-btn"),
  caseId: document.getElementById("case-id"),
  patientName: document.getElementById("patient-name"),
  statusBadge: document.getElementById("status-badge"),
  caseType: document.getElementById("case-type"),
  sourceType: document.getElementById("source-type"),
  createdBy: document.getElementById("created-by"),
  createdAt: document.getElementById("created-at"),
  stepper: document.getElementById("stepper"),
  rawNotes: document.getElementById("raw-notes"),
  aiSummary: document.getElementById("ai-summary"),
  confirmedSummary: document.getElementById("confirmed-summary"),
  confirmedTag: document.getElementById("confirmed-tag"),
  confirmMeta: document.getElementById("confirm-meta"),
  confirmBtn: document.getElementById("confirm-btn"),
  actionHint: document.querySelector(".action-panel .hint"),
  roundsCard: document.getElementById("rounds-card"),
  roundList: document.getElementById("round-list"),
  aiGeneratePanel: document.getElementById("ai-generate-panel"),
  aiGenerateBtn: document.getElementById("ai-generate-btn"),
  aiGenerateError: document.getElementById("ai-generate-error"),
};

function renderStepper() {
  const currentIndex = STEPS.findIndex((s) => s.key === referral.status);
  el.stepper.innerHTML = STEPS.map((step, i) => {
    const state = i < currentIndex ? "done" : i === currentIndex ? "current" : "";
    return `
      <div class="step ${state}">
        <div class="line"></div>
        <div class="dot">${i < currentIndex ? "✓" : i + 1}</div>
        <div class="label">${step.label}</div>
      </div>`;
  }).join("");
}

function renderHeader() {
  el.caseId.textContent = referral.id;
  el.patientName.textContent = referral.patientName;
  el.caseType.textContent = referral.caseType;
  el.sourceType.textContent = referral.sourceType;
  el.createdBy.textContent = referral.createdByName;
  el.createdAt.textContent = referral.createdAt;

  el.statusBadge.textContent = STATUS_TEXT[referral.status];
  el.statusBadge.className = `status-badge status-${referral.status}`;
}

function renderSummaries() {
  el.rawNotes.textContent = referral.rawNotes || "(ไม่มีบันทึกดิบ)";
  el.aiSummary.textContent = referral.aiSummary || "(ยังไม่มีร่างจาก AI)";

  if (referral.confirmedSummary) {
    el.confirmedSummary.value = referral.confirmedSummary;
    el.confirmedSummary.readOnly = true;
    el.confirmedTag.hidden = false;
    el.confirmMeta.textContent = `ยืนยันโดย ${referral.confirmedByName} เมื่อ ${referral.confirmedAt}`;
  } else {
    el.confirmedSummary.value = referral.aiSummary || "";
    el.confirmedSummary.readOnly = false;
    el.confirmedTag.hidden = true;
    el.confirmMeta.textContent = "";
  }
}

function renderRounds() {
  const isTrackingPhase = referral.status !== "pending_review";
  el.roundsCard.hidden = !isTrackingPhase;
  if (!isTrackingPhase) return;

  const canDecide = canConfirm();
  el.roundList.innerHTML = referral.rounds
    .map((round, i) => {
      const isNext = referral.rounds.slice(0, i).every((r) => r.decision) && !round.decision;
      const stateClass = round.decision ? "" : "pending";
      const action = round.decision
        ? `<span class="round-decision">${round.decision}</span>`
        : isNext && canDecide
        ? `<button class="btn btn-secondary" data-round="${i}">พยาบาลยืนยันรอบนี้</button>`
        : isNext
        ? `<span class="round-decision">ต้องให้พยาบาลเป็นผู้ยืนยัน</span>`
        : `<span class="round-decision">รอรอบก่อนหน้า</span>`;
      return `
        <div class="round-item ${stateClass}">
          <span class="round-label">${round.label}</span>
          ${action}
        </div>`;
    })
    .join("");

  el.roundList.querySelectorAll("[data-round]").forEach((btn) => {
    btn.addEventListener("click", () => onConfirmRound(Number(btn.dataset.round)));
  });
}

// Self-approve ban: whoever created the case can never be the one who confirms it
// (mirrors the ACL rule enforced server-side in firestore.rules).
function canConfirm() {
  if (session.role !== "home_visit_team" && session.role !== "admin") return false;
  return referral.createdBy !== session.user.uid;
}

function renderConfirmButton() {
  if (referral.status !== "pending_review") {
    el.confirmBtn.hidden = true;
    return;
  }

  if (!canConfirm()) {
    el.confirmBtn.hidden = true;
    el.actionHint.textContent = session.role === "ward_staff"
      ? "เฉพาะพยาบาลทีมเยี่ยมบ้านหรือแอดมินเท่านั้นที่ยืนยันแผนดูแลได้"
      : "คุณเป็นผู้สร้างเคสนี้ ตามกฎห้ามอนุมัติของตัวเอง จึงต้องให้พยาบาลท่านอื่นเป็นผู้ยืนยัน";
    return;
  }

  el.confirmBtn.hidden = false;
  el.confirmBtn.textContent = "ยืนยันแผนดูแล";
  el.confirmBtn.disabled = false;
}

function renderDeleteButton() {
  const canDelete = session.role === "admin"
    || (session.role === "ward_staff" && referral.createdBy === session.user.uid && referral.status === "pending_review");
  el.deleteBtn.hidden = !canDelete;
}

// ผู้ช่วย AI ระดับ 2 (agentic) โชว์ปุ่มเฉพาะพยาบาล/แอดมินที่มีสิทธิ์ยืนยันเคสนี้
// (เงื่อนไขเดียวกับปุ่มยืนยันแผนดูแล — ห้ามอนุมัติของตัวเอง) และเฉพาะก่อนยืนยันแผนแล้วเท่านั้น
function renderAiGenerateButton() {
  el.aiGeneratePanel.hidden = !(referral.status === "pending_review" && canConfirm());
}

function render() {
  renderHeader();
  renderStepper();
  renderSummaries();
  renderConfirmButton();
  renderAiGenerateButton();
  renderRounds();
  renderDeleteButton();
}

function nowThai() {
  return new Date().toLocaleString("th-TH", { dateStyle: "medium", timeStyle: "short" });
}

function formatDate(value) {
  if (!value) return "-";
  const date = typeof value?.toDate === "function" ? value.toDate() : new Date(value);
  return date.toLocaleString("th-TH", { dateStyle: "medium", timeStyle: "short" });
}

function formatSummary(summary) {
  if (!summary) return "";
  const parts = [];
  if (summary.keyIssues?.length) parts.push(`ปัญหาหลัก: ${summary.keyIssues.join(", ")}`);
  if (summary.riskSignals?.length) parts.push(`สัญญาณเสี่ยง: ${summary.riskSignals.join(", ")}`);
  if (summary.reasoning) parts.push(`เหตุผลจาก AI: ${summary.reasoning}`);
  if (summary.nurseNote) parts.push(`บันทึกพยาบาล: ${summary.nurseNote}`);
  return parts.join("\n");
}

async function fetchDoc(collectionName, id) {
  if (!id) return null;
  const snap = await getDoc(doc(db, collectionName, id));
  return snap.exists() ? snap.data() : null;
}

async function loadReferral() {
  const data = await fetchDoc("referrals", REFERRAL_ID);
  if (!data) throw new Error(`ไม่พบเอกสาร referrals/${REFERRAL_ID} ใน Firestore`);

  const [patient, caseType, confirmedByUser] = await Promise.all([
    fetchDoc("patients", data.patientId),
    fetchDoc("caseTypes", data.caseTypeId),
    fetchDoc("users", data.confirmedBy),
  ]);

  referral = {
    id: REFERRAL_ID,
    patientName: patient?.fullName ?? data.patientId,
    caseType: caseType?.name ?? data.caseTypeId,
    sourceType: SOURCE_TYPE_TEXT[data.sourceType] ?? data.sourceType,
    createdBy: data.createdBy,
    createdByName: data.createdByName ?? data.createdBy,
    createdAt: formatDate(data.createdAt),
    status: data.status,
    zone: data.zone,
    rawNotes: data.rawNotes,
    aiSummary: formatSummary(data.aiSummary),
    confirmedSummary: data.confirmedSummary ? formatSummary(data.confirmedSummary) : null,
    confirmedBy: data.confirmedBy,
    confirmedByName: confirmedByUser?.name ?? data.confirmedBy,
    confirmedAt: data.confirmedAt ? formatDate(data.confirmedAt) : null,
    // Follow-up rounds are illustrative only in this lesson build — no `followUpPlans`
    // subcollection exists yet (see SCOPE.md), so decisions here aren't persisted.
    rounds: [
      { label: "รอบติดตามที่ 1", decision: null },
      { label: "รอบติดตามที่ 2 (ปิดเคส)", decision: null },
    ],
  };
}

async function onConfirmPlan() {
  const confirmedText = el.confirmedSummary.value.trim();
  if (!confirmedText) return;

  el.confirmBtn.disabled = true;
  try {
    await updateDoc(doc(db, "referrals", REFERRAL_ID), {
      confirmedSummary: { nurseNote: confirmedText },
      confirmedBy: session.user.uid,
      confirmedAt: serverTimestamp(),
      status: "plan_confirmed",
    });
    await loadReferral();
    render();
  } catch (err) {
    console.error(err);
    alert(`ยืนยันไม่สำเร็จ: ${err.message}`);
    el.confirmBtn.disabled = false;
  }
}

const ZONE_TEXT = { in_area: "ในเขตพื้นที่รับผิดชอบ", out_area: "นอกเขตพื้นที่รับผิดชอบ" };

// ผู้ช่วย AI ระดับ 2 (agentic): อ่านข้อมูลจากหลายแหล่ง (บันทึกดิบ + ผู้ป่วย + ประเภทเคส) ที่โหลดไว้แล้วใน
// loadReferral() -> สรุปเป็นร่างเดียว -> เขียนกลับเป็น "ร่าง" ในฟิลด์ aiSummary เท่านั้น (ไม่แตะ
// confirmedSummary/status) -> จดบันทึกการทำงานลง subcollection aiLogs ให้ตรวจสอบย้อนหลังได้
// พยาบาลยังต้องกด "ยืนยันแผนดูแล" เองเสมอ — ปุ่มนี้ไม่ตัดสินใจแทน
async function onGenerateAiSummary() {
  el.aiGenerateError.hidden = true;
  el.aiGenerateBtn.disabled = true;
  el.aiGenerateBtn.textContent = "🤖 กำลังอ่านข้อมูลและสรุป...";

  const promptInput = {
    rawNotes: referral.rawNotes || "(ไม่มีบันทึกดิบ)",
    patientName: referral.patientName,
    caseType: referral.caseType,
    zone: ZONE_TEXT[referral.zone] || referral.zone || "ไม่ระบุ",
  };

  try {
    const prompt = `คุณเป็นผู้ช่วยของทีมเยี่ยมบ้าน/ติดตามอาการในโรงพยาบาล อ่านข้อมูลเคสต่อไปนี้จากหลายแหล่งแล้วสรุปเป็นร่างให้พยาบาลตรวจสอบก่อนยืนยัน ตอบกลับเป็น JSON เท่านั้น เขียนทุกฟิลด์เป็นภาษาไทยทั้งหมด ห้ามใช้ภาษาอังกฤษปนแม้แต่คำเดียว รูปแบบ {"patientType": "คำสั้นๆ ภาษาไทย บอกประเภทผู้ป่วย", "keyIssues": ["ปัญหาหลักเป็นภาษาไทย...", "..."], "riskSignals": ["สัญญาณเสี่ยงเป็นภาษาไทย...", "..."], "reasoning": "อธิบายสั้นๆ เป็นภาษาไทย ว่าทำไมถึงสรุปแบบนี้"} ห้ามมีข้อความอื่นนอกจาก JSON ถ้าไม่มีสัญญาณเสี่ยงให้ตอบเป็น array ว่าง

ข้อมูลผู้ป่วย: ${promptInput.patientName} (${promptInput.zone})
ประเภทเคส: ${promptInput.caseType}
บันทึกดิบจากผู้ส่งเคส: "${promptInput.rawNotes}"`;

    const summary = await callAiJson(prompt);

    await updateDoc(doc(db, "referrals", REFERRAL_ID), {
      aiSummary: summary,
      aiSummaryGeneratedAt: serverTimestamp(),
    });

    await addDoc(collection(db, "referrals", REFERRAL_ID, "aiLogs"), {
      triggeredBy: session.user.uid,
      triggeredByName: session.name,
      model: AI_MODEL,
      generatedAt: serverTimestamp(),
      input: promptInput,
      output: summary,
    });

    await loadReferral();
    render();
  } catch (err) {
    console.error(err);
    el.aiGenerateError.textContent = err.message;
    el.aiGenerateError.hidden = false;
  } finally {
    el.aiGenerateBtn.disabled = false;
    el.aiGenerateBtn.textContent = "🤖 ให้ AI ช่วยสรุปเคส (อ่านบันทึกดิบ + ข้อมูลผู้ป่วย + ประเภทเคส)";
  }
}

async function onConfirmRound(index) {
  // Illustrative only (see comment in loadReferral) — persists just the case-level
  // status transition, not a per-round document, since there is no rounds subcollection yet.
  referral.rounds[index].decision = `ยืนยันโดยพยาบาล — ${nowThai()}`;
  const allDone = referral.rounds.every((r) => r.decision);
  const nextStatus = allDone ? "closed" : "in_progress";

  try {
    await updateDoc(doc(db, "referrals", REFERRAL_ID), {
      status: nextStatus,
      closedAt: allDone ? serverTimestamp() : null,
    });
    referral.status = nextStatus;
    render();
  } catch (err) {
    console.error(err);
    alert(`บันทึกไม่สำเร็จ: ${err.message}`);
  }
}

async function onDelete() {
  if (!confirm(`ยืนยันลบเคส ${referral.id}? การลบนี้ย้อนกลับไม่ได้`)) return;

  el.deleteBtn.disabled = true;
  try {
    await deleteDoc(doc(db, "referrals", REFERRAL_ID));
    location.href = "index.html";
  } catch (err) {
    console.error(err);
    alert(`ลบไม่สำเร็จ: ${err.message}`);
    el.deleteBtn.disabled = false;
  }
}

el.confirmBtn.addEventListener("click", onConfirmPlan);
el.deleteBtn.addEventListener("click", onDelete);
el.aiGenerateBtn.addEventListener("click", onGenerateAiSummary);

requireLogin().then(async (loggedInSession) => {
  session = loggedInSession;
  renderNav(el.navMount, session, NAV_LINKS);

  if (!REFERRAL_ID) {
    document.querySelector(".page").insertAdjacentHTML(
      "beforeend",
      '<div class="card" style="border-color:#c0392b"><strong>ไม่พบรหัสเคส</strong> — เปิดหน้านี้ผ่านลิงก์จากรายการเคสเท่านั้น</div>'
    );
    return;
  }

  try {
    await loadReferral();
    render();
  } catch (err) {
    console.error(err);
    document
      .querySelector(".page")
      .insertAdjacentHTML(
        "afterbegin",
        `<div class="card" style="border-color:#c0392b"><strong>โหลดข้อมูลจาก Firestore ไม่สำเร็จ:</strong> ${err.message}</div>`
      );
  }
});
