// Loaded live from Firestore (collection `referrals`, doc REFERRAL_ID) — see loadReferral().
import { doc, getDoc } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { db } from "./firebase-config.js";

const REFERRAL_ID = "referral_001";

const SOURCE_TYPE_TEXT = {
  ward: "หอผู้ป่วย (Ward)",
  opd: "OPD",
  internal_dept: "แผนกภายในโรงพยาบาล",
  external_hospital: "โรงพยาบาลภายนอก (ส่งต่อ)",
};

let referral = null;

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
  caseId: document.getElementById("case-id"),
  patientName: document.getElementById("patient-name"),
  statusBadge: document.getElementById("status-badge"),
  caseType: document.getElementById("case-type"),
  sourceType: document.getElementById("source-type"),
  createdBy: document.getElementById("created-by"),
  createdAt: document.getElementById("created-at"),
  stepper: document.getElementById("stepper"),
  aiSummary: document.getElementById("ai-summary"),
  confirmedSummary: document.getElementById("confirmed-summary"),
  confirmedTag: document.getElementById("confirmed-tag"),
  confirmMeta: document.getElementById("confirm-meta"),
  confirmBtn: document.getElementById("confirm-btn"),
  roundsCard: document.getElementById("rounds-card"),
  roundList: document.getElementById("round-list"),
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
  el.createdBy.textContent = referral.createdBy;
  el.createdAt.textContent = referral.createdAt;

  el.statusBadge.textContent = STATUS_TEXT[referral.status];
  el.statusBadge.className = `status-badge status-${referral.status}`;
}

function renderSummaries() {
  el.aiSummary.textContent = referral.aiSummary;

  if (referral.confirmedSummary) {
    el.confirmedSummary.value = referral.confirmedSummary;
    el.confirmedSummary.readOnly = true;
    el.confirmedTag.hidden = false;
    el.confirmMeta.textContent = `ยืนยันโดย ${referral.confirmedBy} เมื่อ ${referral.confirmedAt}`;
  } else {
    el.confirmedSummary.value = referral.aiSummary;
    el.confirmedSummary.readOnly = false;
    el.confirmedTag.hidden = true;
    el.confirmMeta.textContent = "";
  }
}

function renderRounds() {
  const isTrackingPhase = referral.status !== "pending_review";
  el.roundsCard.hidden = !isTrackingPhase;
  if (!isTrackingPhase) return;

  el.roundList.innerHTML = referral.rounds
    .map((round, i) => {
      const isNext = referral.rounds.slice(0, i).every((r) => r.decision) && !round.decision;
      const stateClass = round.decision ? "" : "pending";
      const action = round.decision
        ? `<span class="round-decision">${round.decision}</span>`
        : isNext
        ? `<button class="btn btn-secondary" data-round="${i}">พยาบาลยืนยันรอบนี้</button>`
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

function renderConfirmButton() {
  if (referral.status === "pending_review") {
    el.confirmBtn.hidden = false;
    el.confirmBtn.textContent = "ยืนยันแผนดูแล";
    el.confirmBtn.disabled = false;
  } else {
    el.confirmBtn.hidden = true;
  }
}

function render() {
  renderHeader();
  renderStepper();
  renderSummaries();
  renderConfirmButton();
  renderRounds();
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

  const [patient, caseType, createdByUser, confirmedByUser] = await Promise.all([
    fetchDoc("patients", data.patientId),
    fetchDoc("caseTypes", data.caseTypeId),
    fetchDoc("users", data.createdBy),
    fetchDoc("users", data.confirmedBy),
  ]);

  referral = {
    id: REFERRAL_ID,
    patientName: patient?.fullName ?? data.patientId,
    caseType: caseType?.name ?? data.caseTypeId,
    sourceType: SOURCE_TYPE_TEXT[data.sourceType] ?? data.sourceType,
    createdBy: createdByUser?.name ?? data.createdBy,
    createdAt: formatDate(data.createdAt),
    status: data.status,
    aiSummary: formatSummary(data.aiSummary),
    confirmedSummary: data.confirmedSummary ? formatSummary(data.confirmedSummary) : null,
    confirmedBy: confirmedByUser?.name ?? data.confirmedBy,
    confirmedAt: data.confirmedAt ? formatDate(data.confirmedAt) : null,
    rounds: [
      { label: "รอบติดตามที่ 1", decision: null },
      { label: "รอบติดตามที่ 2 (ปิดเคส)", decision: null },
    ],
  };
}

function onConfirmPlan() {
  referral.confirmedSummary = el.confirmedSummary.value.trim();
  if (!referral.confirmedSummary) return;

  referral.confirmedBy = "พยาบาล — สมหญิง (คุณ)";
  referral.confirmedAt = nowThai();
  referral.status = "plan_confirmed";
  render();
}

function onConfirmRound(index) {
  referral.rounds[index].decision = `ยืนยันโดยพยาบาล — ${nowThai()}`;
  const allDone = referral.rounds.every((r) => r.decision);
  referral.status = allDone ? "closed" : "in_progress";
  render();
}

el.confirmBtn.addEventListener("click", onConfirmPlan);

loadReferral()
  .then(render)
  .catch((err) => {
    console.error(err);
    document
      .querySelector(".page")
      .insertAdjacentHTML(
        "afterbegin",
        `<div class="card" style="border-color:#c0392b"><strong>โหลดข้อมูลจาก Firestore ไม่สำเร็จ:</strong> ${err.message}</div>`
      );
  });
