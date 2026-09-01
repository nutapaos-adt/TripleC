/**
 * Firestore seed script — Triple C "Referral" entity
 *
 * ก่อนรัน:
 *   1. npm install firebase-admin
 *   2. ไปที่ Firebase Console > Project Settings > Service Accounts > Generate new private key
 *      แล้วบันทึกไฟล์เป็น serviceAccountKey.json ไว้โฟลเดอร์เดียวกับสคริปต์นี้
 *   3. node seed.js
 *
 * โครงสร้างข้อมูลจำลองมาจาก Referral.php / migration ของโปรเจกต์ Triple C:
 * - referrals   : เคสหลัก (สถานะ pending_review -> plan_confirmed -> in_progress -> closed)
 * - patients    : ผู้ป่วยอ้างอิงโดย referrals.patientId
 * - caseTypes   : ประเภทเคสอ้างอิงโดย referrals.caseTypeId
 * - users       : เจ้าหน้าที่/พยาบาลอ้างอิงโดย referrals.createdBy / confirmedBy
 */

const admin = require('firebase-admin');
const serviceAccount = require('./serviceAccountKey.json');

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const db = admin.firestore();

// ---------- Supporting reference data ----------

const users = [
  { id: 'user_ward01', name: 'พยาบาลสมศรี ใจดี', role: 'ward_staff', department: 'หอผู้ป่วยอายุรกรรม' },
  { id: 'user_nurse01', name: 'พยาบาลวิภา ติดตามผล', role: 'home_visit_team', department: 'ทีมเยี่ยมบ้าน' },
  { id: 'user_admin01', name: 'แอดมิน ระบบดี', role: 'admin', department: 'IT' },
  { id: 'user_ward02', name: 'พยาบาลนภา ส่งต่อดี', role: 'ward_staff', department: 'OPD อายุรกรรม' },
  { id: 'user_nurse02', name: 'พยาบาลสุดา เยี่ยมบ้านไว', role: 'home_visit_team', department: 'ทีมเยี่ยมบ้าน' },
];

const caseTypes = [
  { id: 'ct_palliative', name: 'Palliative Care', slug: 'palliative-care', isActive: true },
  { id: 'ct_bedridden', name: 'ผู้ป่วยติดเตียง', slug: 'bedridden', isActive: true },
  { id: 'ct_copd', name: 'COPD ติดตามหลังจำหน่าย', slug: 'copd-followup', isActive: true },
  { id: 'ct_stroke', name: 'ผู้ป่วยโรคหลอดเลือดสมอง', slug: 'stroke-followup', isActive: true },
  { id: 'ct_dm_wound', name: 'แผลเบาหวาน', slug: 'diabetic-wound-care', isActive: true },
];

const patients = [
  { id: 'patient_001', fullName: 'นายสมชาย เดินทางไกล', hn: 'HN-000123', zone: 'in_area' },
  { id: 'patient_002', fullName: 'นางสมหญิง พักผ่อนดี', hn: 'HN-000456', zone: 'out_area' },
  { id: 'patient_003', fullName: 'นายอนุชา ฟื้นตัวช้า', hn: 'HN-000789', zone: 'in_area' },
  { id: 'patient_004', fullName: 'นางสาวปราณี ใกล้บ้าน', hn: 'HN-000999', zone: 'in_area' },
  { id: 'patient_005', fullName: 'นายวิชัย หายใจลำบาก', hn: 'HN-001111', zone: 'out_area' },
];

// ---------- Referrals (5 ตัวอย่าง ครอบคลุมสถานะครบทั้ง 4 แบบตาม status enum จริงของระบบ) ----------

const referrals = [
  {
    id: 'referral_001',
    patientId: 'patient_001',
    caseTypeId: 'ct_palliative',
    sourceType: 'ward',
    sourceDetail: 'หอผู้ป่วยอายุรกรรมชาย 3',
    createdBy: 'user_ward01',
    rawNotes: 'ผู้ป่วยระยะท้าย ต้องการติดตามอาการปวดและดูแลแบบประคับประคองที่บ้าน',
    aiSummary: {
      patientType: 'palliative',
      keyIssues: ['ปวดจากมะเร็งระยะท้าย', 'ต้องการอุปกรณ์ทางการแพทย์ที่บ้าน'],
      riskSignals: ['ผู้ดูแลหลักมีคนเดียว'],
    },
    aiSummaryGeneratedAt: '2026-08-20T09:15:00+07:00',
    confirmedSummary: null,
    confirmedBy: null,
    confirmedAt: null,
    zone: 'in_area',
    status: 'pending_review', // ยังไม่ผ่านการยืนยันของพยาบาล
    closedAt: null,
    createdAt: '2026-08-20T09:00:00+07:00',
  },
  {
    id: 'referral_002',
    patientId: 'patient_002',
    caseTypeId: 'ct_bedridden',
    sourceType: 'opd',
    sourceDetail: 'OPD อายุรกรรม',
    createdBy: 'user_ward01',
    rawNotes: 'ผู้ป่วยติดเตียงหลังโรคหลอดเลือดสมอง ต้องการวางแผนเยี่ยมบ้านต่อเนื่อง',
    aiSummary: {
      patientType: 'bedridden',
      keyIssues: ['เสี่ยงแผลกดทับ', 'ต้องฝึกกายภาพบำบัดต่อเนื่อง'],
      riskSignals: [],
    },
    aiSummaryGeneratedAt: '2026-08-18T10:30:00+07:00',
    confirmedSummary: {
      patientType: 'bedridden',
      keyIssues: ['เสี่ยงแผลกดทับ', 'ต้องฝึกกายภาพบำบัดต่อเนื่อง'],
      riskSignals: [],
      nurseNote: 'ยืนยันตามร่าง AI ไม่มีการแก้ไข',
    },
    confirmedBy: 'user_nurse01',
    confirmedAt: '2026-08-18T14:00:00+07:00',
    zone: 'out_area',
    status: 'plan_confirmed', // พยาบาลยืนยันแผนดูแลแล้ว รอสร้าง/เริ่มกำหนดการติดตาม
    closedAt: null,
    createdAt: '2026-08-18T10:00:00+07:00',
  },
  {
    id: 'referral_003',
    patientId: 'patient_003',
    caseTypeId: 'ct_copd',
    sourceType: 'internal_dept',
    sourceDetail: 'แผนกเวชศาสตร์ฟื้นฟู',
    createdBy: 'user_ward01',
    rawNotes: 'ผู้ป่วย COPD จำหน่ายจากโรงพยาบาล ต้องติดตามอาการหอบเหนื่อยที่บ้าน',
    aiSummary: {
      patientType: 'copd',
      keyIssues: ['หอบเหนื่อยง่าย', 'ใช้ออกซิเจนที่บ้าน'],
      riskSignals: ['เคยกลับเข้า ER ใน 30 วันที่ผ่านมา'],
    },
    aiSummaryGeneratedAt: '2026-08-10T08:45:00+07:00',
    confirmedSummary: {
      patientType: 'copd',
      keyIssues: ['หอบเหนื่อยง่าย', 'ใช้ออกซิเจนที่บ้าน'],
      riskSignals: ['เคยกลับเข้า ER ใน 30 วันที่ผ่านมา'],
      nurseNote: 'เพิ่มความถี่การเยี่ยมเป็นทุก 3 วันในสัปดาห์แรก',
    },
    confirmedBy: 'user_nurse01',
    confirmedAt: '2026-08-10T13:20:00+07:00',
    zone: 'in_area',
    status: 'in_progress', // อยู่ระหว่างรอบการเยี่ยมบ้าน/โทรติดตาม
    closedAt: null,
    createdAt: '2026-08-10T08:00:00+07:00',
  },
  {
    id: 'referral_004',
    patientId: 'patient_004',
    caseTypeId: 'ct_bedridden',
    sourceType: 'external_hospital',
    sourceDetail: 'โรงพยาบาลชุมชนใกล้เคียง (ส่งต่อ)',
    createdBy: 'user_ward01',
    rawNotes: 'ส่งต่อเคสผู้ป่วยติดเตียงที่ดูแลจนอาการคงที่และญาติดูแลได้เองแล้ว',
    aiSummary: {
      patientType: 'bedridden',
      keyIssues: ['อาการคงที่'],
      riskSignals: [],
    },
    aiSummaryGeneratedAt: '2026-07-01T09:00:00+07:00',
    confirmedSummary: {
      patientType: 'bedridden',
      keyIssues: ['อาการคงที่'],
      riskSignals: [],
      nurseNote: 'ญาติดูแลได้เอง ไม่มีความเสี่ยงเพิ่มเติม',
    },
    confirmedBy: 'user_nurse01',
    confirmedAt: '2026-07-01T11:00:00+07:00',
    zone: 'in_area',
    status: 'closed', // ปิดเคสแล้วหลังพยาบาลตัดสินใจ "ปิดเคส"
    closedAt: '2026-08-25T16:00:00+07:00',
    createdAt: '2026-07-01T08:30:00+07:00',
  },
  {
    id: 'referral_005',
    patientId: 'patient_005',
    caseTypeId: 'ct_copd',
    sourceType: 'ward',
    sourceDetail: 'หอผู้ป่วยอายุรกรรมชาย 1',
    createdBy: 'user_ward01',
    rawNotes: 'ผู้ป่วย COPD เพิ่งจำหน่ายจากโรงพยาบาลเมื่อเช้านี้ หอบเหนื่อยง่าย อยู่นอกเขตพื้นที่รับผิดชอบ',
    aiSummary: {
      patientType: 'copd',
      keyIssues: ['หอบเหนื่อยง่าย', 'อยู่นอกเขตพื้นที่ (out_area) ต้องประสาน รพ.สต. ในพื้นที่'],
      riskSignals: ['ผู้ป่วยอาศัยคนเดียว'],
    },
    aiSummaryGeneratedAt: '2026-08-30T08:20:00+07:00',
    confirmedSummary: null,
    confirmedBy: null,
    confirmedAt: null,
    zone: 'out_area',
    status: 'pending_review', // เพิ่งสร้างเคส ยังไม่ผ่านการยืนยันของพยาบาล
    closedAt: null,
    createdAt: '2026-08-30T08:00:00+07:00',
  },
];

async function seedCollection(collectionName, items) {
  const batch = db.batch();
  items.forEach((item) => {
    const { id, ...data } = item;
    const ref = db.collection(collectionName).doc(id);
    batch.set(ref, {
      ...data,
      createdAt: admin.firestore.Timestamp.fromDate(new Date(item.createdAt || Date.now())),
    });
  });
  await batch.commit();
  console.log(`  ✔ seeded ${items.length} docs into "${collectionName}"`);
}

async function main() {
  console.log('Seeding Firestore for Triple C (Referral entity)...');
  await seedCollection('users', users);
  await seedCollection('caseTypes', caseTypes);
  await seedCollection('patients', patients);
  await seedCollection('referrals', referrals);
  console.log('Done.');
  process.exit(0);
}

main().catch((err) => {
  console.error('Seed failed:', err);
  process.exit(1);
});
