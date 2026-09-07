import { createUserWithEmailAndPassword, updateProfile, signOut } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-auth.js";
import { doc, setDoc } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";
import { auth, db } from "./firebase-config.js";

const el = {
  form: document.getElementById("register-form"),
  name: document.getElementById("name"),
  email: document.getElementById("email"),
  role: document.getElementById("role"),
  password: document.getElementById("password"),
  passwordConfirm: document.getElementById("password-confirm"),
  error: document.getElementById("form-error"),
  submitBtn: document.getElementById("submit-btn"),
};

const ERROR_TEXT = {
  "auth/email-already-in-use": "อีเมลนี้ถูกใช้ลงทะเบียนแล้ว",
  "auth/invalid-email": "รูปแบบอีเมลไม่ถูกต้อง",
  "auth/weak-password": "รหัสผ่านสั้นเกินไป (อย่างน้อย 6 ตัวอักษร)",
};

el.form.addEventListener("submit", async (event) => {
  event.preventDefault();
  el.error.hidden = true;

  if (el.password.value !== el.passwordConfirm.value) {
    el.error.textContent = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    el.error.hidden = false;
    return;
  }

  el.submitBtn.disabled = true;
  el.submitBtn.textContent = "กำลังสร้างบัญชี...";

  const name = el.name.value.trim();
  const email = el.email.value.trim();
  const role = el.role.value;

  try {
    const credential = await createUserWithEmailAndPassword(auth, email, el.password.value);
    await updateProfile(credential.user, { displayName: name });

    // Only role a self-registered user may ever hold — firestore.rules rejects
    // anything else (see the `create` rule on /users/{userId}).
    await setDoc(doc(db, "users", credential.user.uid), { name, role, email });

    location.href = "index.html";
  } catch (err) {
    console.error(err);
    // The Firestore profile write can fail *after* the Auth account already exists
    // (e.g. rules rejected an unexpected role) — sign back out so requireLogin()
    // on other pages doesn't get stuck on a half-created account.
    await signOut(auth).catch(() => {});
    el.error.textContent = ERROR_TEXT[err.code] ?? `สร้างบัญชีไม่สำเร็จ: ${err.message}`;
    el.error.hidden = false;
    el.submitBtn.disabled = false;
    el.submitBtn.textContent = "สร้างบัญชี";
  }
});
