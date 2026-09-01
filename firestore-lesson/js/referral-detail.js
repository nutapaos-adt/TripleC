// Mock data for one Referral (เคส) — UI/UX demo only, not wired to Firestore yet.
const referral = {
  id: "referral_001",
  patientName: "คุณสมชาย ใจดี",
  caseType: "Palliative Care",
  sourceType: "หอผู้ป่วย (Ward)",
  createdBy: "เจ้าหน้าที่หอผู้ป่วย — สมหญิง",
  createdAt: "1 ก.ย. 2569, 09:12",
  status: "pending_review",
  aiSummary:
    "ผู้ป่วยมะเร็งระยะท้าย ปวดระดับ 6/10 ควบคุมด้วยมอร์ฟีนวันละ 2 ครั้ง " +
    "ครอบครัวต้องการดูแลต่อที่บ้าน แนะนำเยี่ยมบ้านสัปดาห์ละ 2 ครั้ง " +
    "ประเมินความปวดและสภาพจิตใจผู้ดูแลร่วมด้วย",
  confirmedSummary: null,
  confirmedBy: null,
  confirmedAt: null,
  rounds: [
    { label: "รอบติดตามที่ 1", decision: null },
    { label: "รอบติดตามที่ 2 (ปิดเคส)", decision: null },
  ],
};

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

render();
