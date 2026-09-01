// Firebase JS SDK v7.20.0 and later, measurementId is optional
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-analytics.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";

const firebaseConfig = {
  apiKey: "AIzaSyBjnj_Zp2IikBFy56DEx3o8ZPBBrAMcQ7E",
  authDomain: "triplec-a5e75.firebaseapp.com",
  projectId: "triplec-a5e75",
  storageBucket: "triplec-a5e75.firebasestorage.app",
  messagingSenderId: "1069957586187",
  appId: "1:1069957586187:web:0956d3db62fd5f4d77960e",
  measurementId: "G-MY8DRVYXT4",
};

const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);
const db = getFirestore(app);

export { app, analytics, db };
