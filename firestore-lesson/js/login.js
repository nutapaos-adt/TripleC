import { signInWithEmailAndPassword, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-auth.js";
import { auth } from "./firebase-config.js";

const el = {
  form: document.getElementById("login-form"),
  email: document.getElementById("email"),
  password: document.getElementById("password"),
  error: document.getElementById("form-error"),
  submitBtn: document.getElementById("submit-btn"),
};

const ERROR_TEXT = {
  "auth/invalid-email": "รูปแบบอีเมลไม่ถูกต้อง",
  "auth/invalid-credential": "อีเมลหรือรหัสผ่านไม่ถูกต้อง",
  "auth/user-disabled": "บัญชีนี้ถูกระงับการใช้งาน",
  "auth/too-many-requests": "ลองผิดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่",
};

function nextUrl() {
  const params = new URLSearchParams(location.search);
  return params.get("next") || "index.html";
}

// Already signed in? skip straight past the login form.
onAuthStateChanged(auth, (user) => {
  if (user) location.replace(nextUrl());
});

el.form.addEventListener("submit", async (event) => {
  event.preventDefault();
  el.error.hidden = true;
  el.submitBtn.disabled = true;
  el.submitBtn.textContent = "กำลังเข้าสู่ระบบ...";

  try {
    await signInWithEmailAndPassword(auth, el.email.value.trim(), el.password.value);
    location.replace(nextUrl());
  } catch (err) {
    el.error.textContent = ERROR_TEXT[err.code] ?? `เข้าสู่ระบบไม่สำเร็จ: ${err.message}`;
    el.error.hidden = false;
    el.submitBtn.disabled = false;
    el.submitBtn.textContent = "เข้าสู่ระบบ";
  }
});
