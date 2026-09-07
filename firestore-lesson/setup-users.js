/**
 * One-off admin script: creates the 3 demo Firebase Auth accounts used to grade this
 * homework (one per role) and sets the `role` custom claim Firestore Security Rules
 * read via `request.auth.token.role`. Also writes a matching `users/{uid}` Firestore
 * doc so the UI can show a display name.
 *
 * Run once after `npm install` (needs serviceAccountKey.json — see README.md):
 *   node setup-users.js
 *
 * Safe to re-run: existing accounts are updated in place rather than duplicated.
 */

const admin = require('firebase-admin');
const serviceAccount = require('./serviceAccountKey.json');

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const db = admin.firestore();

const DEMO_USERS = [
  { email: 'ward1@triplec.demo', password: 'Ward@1234', name: 'พยาบาลสมศรี ใจดี (ward_staff)', role: 'ward_staff' },
  { email: 'nurse1@triplec.demo', password: 'Nurse@1234', name: 'พยาบาลวิภา ติดตามผล (home_visit_team)', role: 'home_visit_team' },
  { email: 'admin1@triplec.demo', password: 'Admin@1234', name: 'แอดมิน ระบบดี (admin)', role: 'admin' },
];

async function upsertUser({ email, password, name, role }) {
  let user;
  try {
    user = await admin.auth().getUserByEmail(email);
    await admin.auth().updateUser(user.uid, { password, displayName: name });
  } catch (err) {
    if (err.code !== 'auth/user-not-found') throw err;
    user = await admin.auth().createUser({ email, password, displayName: name });
  }

  await admin.auth().setCustomUserClaims(user.uid, { role, name });
  await db.collection('users').doc(user.uid).set({ name, role, email });

  console.log(`  ✔ ${role.padEnd(16)} ${email}  (uid: ${user.uid})`);
}

async function main() {
  console.log('Provisioning demo accounts for Triple C week7 homework...');
  for (const demoUser of DEMO_USERS) {
    await upsertUser(demoUser);
  }
  console.log('Done. Sign-in credentials are listed in README.md.');
  process.exit(0);
}

main().catch((err) => {
  console.error('Setup failed:', err);
  process.exit(1);
});
