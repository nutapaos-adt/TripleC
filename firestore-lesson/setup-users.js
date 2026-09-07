/**
 * One-off admin script: provisions a single `admin` account. This is the ONLY way to
 * get an admin account — self-registration (register.html) only ever allows
 * `ward_staff` or `home_visit_team` (see the `create` rule on /users/{userId} in
 * firestore.rules). ward_staff/home_visit_team accounts should just use
 * register.html instead of this script.
 *
 * Credentials are read from environment variables, never hardcoded/committed:
 *
 *   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=... ADMIN_NAME="Your Name" node setup-users.js
 *
 * Needs serviceAccountKey.json in this folder (see README.md). Safe to re-run: an
 * existing account with the same email is updated in place rather than duplicated.
 */

const admin = require('firebase-admin');
const serviceAccount = require('./serviceAccountKey.json');

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const db = admin.firestore();

const email = process.env.ADMIN_EMAIL;
const password = process.env.ADMIN_PASSWORD;
const name = process.env.ADMIN_NAME || 'Admin';

if (!email || !password) {
  console.error('Set ADMIN_EMAIL and ADMIN_PASSWORD environment variables first, e.g.:');
  console.error('  ADMIN_EMAIL=you@example.com ADMIN_PASSWORD=... node setup-users.js');
  process.exit(1);
}

async function main() {
  let user;
  try {
    user = await admin.auth().getUserByEmail(email);
    await admin.auth().updateUser(user.uid, { password, displayName: name });
  } catch (err) {
    if (err.code !== 'auth/user-not-found') throw err;
    user = await admin.auth().createUser({ email, password, displayName: name });
  }

  // Admin SDK writes bypass firestore.rules entirely, so this is the one place a
  // `role: 'admin'` document can legitimately be created.
  await db.collection('users').doc(user.uid).set({ name, role: 'admin', email });

  console.log(`✔ admin account ready: ${email}  (uid: ${user.uid})`);
  process.exit(0);
}

main().catch((err) => {
  console.error('Setup failed:', err);
  process.exit(1);
});
