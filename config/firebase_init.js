// config/firebase_init.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import { getDatabase, onValue, ref } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-database.js";

// 🔐 Configuración de tu proyecto Firebase
const firebaseConfig = {
  apiKey: "AIzaSyARe_xIUzyh4x991qBmzDWSAiWob49bfFI",
  authDomain: "dmgvent.firebaseapp.com",
  databaseURL: "https://dmgvent-default-rtdb.firebaseio.com",
  projectId: "dmgvent",
  storageBucket: "dmgvent.firebasestorage.app", // 👈 corrige aquí
  messagingSenderId: "1006984863608",
  appId: "1:629143016874:android:e0244eec63a034ebb507d3"
};

const app = initializeApp(firebaseConfig);
const database = getDatabase(app);

export { database, onValue, ref };

