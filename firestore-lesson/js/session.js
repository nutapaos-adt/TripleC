// Shared auth/session helpers used by every page except login.html.
// Every page that reads or writes Firestore must go through requireLogin() first —
// this is the client-side half of "ต้องล็อกอินก่อนอ่านหรือเขียน" (the other half is firestore.rules).
import { onAuthStateChanged, signOut } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-auth.js";
import { auth } from "./firebase-config.js";

export const ROLE_TEXT = {
  ward_staff: "เจ้าหน้าที่หอผู้ป่วย",
  home_visit_team: "พยาบาลทีมเยี่ยมบ้าน",
  admin: "แอดมิน",
};

/**
 * Resolves once with the signed-in user + role, or redirects to login.html
 * (preserving the current page as ?next=) if nobody is signed in.
 */
export function requireLogin() {
  return new Promise((resolve) => {
    onAuthStateChanged(auth, async (user) => {
      if (!user) {
        const next = encodeURIComponent(location.pathname + location.search);
        location.replace(`login.html?next=${next}`);
        return;
      }
      const tokenResult = await user.getIdTokenResult();
      const role = tokenResult.claims.role ?? null;
      resolve({ user, role, name: tokenResult.claims.name ?? user.email });
    });
  });
}

export async function logout() {
  await signOut(auth);
  location.replace("login.html");
}

/**
 * Renders the shared top nav into `mountEl`. `roleLinks` maps role -> array of
 * { href, label } shown only to that role (admin always sees every link).
 */
export function renderNav(mountEl, session, roleLinks = {}) {
  const links = session.role === "admin"
    ? Object.values(roleLinks).flat()
    : roleLinks[session.role] ?? [];

  const uniqueLinks = [...new Map(links.map((l) => [l.href, l])).values()];

  mountEl.innerHTML = `
    <nav class="app-nav">
      <a class="app-nav-brand" href="index.html">ProjectTripleC</a>
      <div class="app-nav-links">
        ${uniqueLinks.map((l) => `<a href="${l.href}">${l.label}</a>`).join("")}
      </div>
      <div class="app-nav-user">
        <span class="role-badge role-${session.role}">${ROLE_TEXT[session.role] ?? session.role}</span>
        <span class="app-nav-name">${session.name}</span>
        <button class="btn btn-secondary btn-sm" id="nav-logout">ออกจากระบบ</button>
      </div>
    </nav>`;

  mountEl.querySelector("#nav-logout").addEventListener("click", logout);
}
