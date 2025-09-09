<?php
// pages/proyectos.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* (opcional) valida login de usuario aquí si lo necesitas:
if (empty($_SESSION['user_id'])) {
  header("Location: ../index.php");
  exit;
}
*/

/* Importante: esta vista NO debe depender de idDesarrollo.
   Limpiamos cualquier rastro que pueda “amarrar” la ruta. */
unset($_SESSION['idDesarrollo'], $_SESSION['nodoActual'], $_SESSION['rutaNodo'], $_SESSION['empresaNodo']);

// Evita cache para que siempre recargue la lista
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Monte Alto</title>
<style>
  :root{
    --bg-1:#4b3b8f; --bg-2:#5a4aa6; --card:#ffffff; --ink:#2b2b2b; --ink-2:#6b6b6b;
    --brand:#7b61ff; --soft:#f2f2f6; --ok:#6c5ce7; --ok-2:#5748d8; --radius:16px;
    --shadow:0 10px 30px rgba(0,0,0,.12); --danger:#e74c3c;
  }
  *{box-sizing:border-box} html,body{height:100%}
  body{
    margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans";
    color:var(--ink);
    background: radial-gradient(1200px 800px at 10% 100%, rgba(255,255,255,.05), transparent 60%),
                linear-gradient(135deg, var(--bg-2), var(--bg-1));
  }
  .shell{ max-width:1280px; margin:0 auto; padding:24px; }
  .header{ display:flex; align-items:center; justify-content:space-between; gap:16px; background:#fff; border-radius:var(--radius); padding:12px 16px; box-shadow:var(--shadow); }
  .hdr-left{ display:flex; align-items:center; gap:12px; }
  .brand-logo{ width:44px; height:44px; border-radius:10px; object-fit:contain; background:#f7f7fb; }
  .hdr-title{ display:flex; flex-direction:column; line-height:1.1; }
  .hdr-title h1{ margin:0; font-size:22px; letter-spacing:.5px; font-weight:700; color:#3a2f7a; }
  .hdr-title small{ margin-top:2px; color:var(--ink-2); font-weight:600; }
  .hdr-actions{ display:flex; align-items:center; gap:10px; }
  .icon-btn{ width:36px; height:36px; border:none; border-radius:10px; display:grid; place-items:center; background:var(--soft); cursor:pointer; }
  .icon{ width:20px; height:20px; display:block; fill:#6a6a80 }
  .primary-btn{ height:36px; border:none; border-radius:10px; padding:0 12px; font-weight:800; cursor:pointer; background:var(--brand); color:#fff; box-shadow:var(--shadow); display:inline-flex; align-items:center; gap:8px; }
  .primary-btn .icon{ fill:#fff }

  .toolbar{ margin-top:14px; background:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,.35); backdrop-filter: blur(6px); border-radius:var(--radius); padding:14px; }
  .toolbar-row{ display:grid; grid-template-columns: 1fr auto auto 1.2fr auto; gap:10px; }
  .input{ display:flex; align-items:center; gap:8px; background:#fff; border-radius:12px; padding:10px 12px; box-shadow: var(--shadow); border:1px solid #ececf6; }
  .input input{ border:none; outline:none; width:100%; font-size:14px; color:var(--ink); background:transparent; }
  .toggle-btn{ width:42px; height:42px; border:none; border-radius:12px; background:#fff; box-shadow:var(--shadow); cursor:pointer; display:grid; place-items:center; border:1px solid #ececf6; }
  .toggle-btn.active{ background:var(--brand) } .toggle-btn.active .icon{ fill:#fff }
  .search-btn{ width:46px; height:42px; border:none; border-radius:12px; background:var(--brand); color:#fff; cursor:pointer; box-shadow:var(--shadow); }

  .grid{ margin-top:18px; display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:16px; }
  @media (max-width:1200px){ .grid{ grid-template-columns: repeat(3, 1fr); } }
  @media (max-width:900px){ .grid{ grid-template-columns: repeat(2, 1fr); } }
  @media (max-width:600px){ .grid{ grid-template-columns: 1fr; } }

  .card{ background:#fff; border-radius:18px; box-shadow:var(--shadow); overflow:hidden; display:flex; flex-direction:column; min-height:360px; }
  .card-media{ background:#fff; border-bottom:1px solid #f0eff8; display:grid; place-items:center; padding:12px; aspect-ratio:16/10; }
  .card-media img{ width:100%; height:100%; object-fit:contain; }
  .card-body{ padding:16px; display:flex; flex-direction:column; gap:10px; background:linear-gradient(180deg, #ffffff, #fafaff); }
  .proj-title{ font-weight:800; color:#2c2266; letter-spacing:.4px; font-size:18px; }
  .proj-sub{ color:#7c7c92; font-weight:600; margin-top:-2px; font-size:13px; }
  .btn{ appearance:none; border:none; cursor:pointer; padding:10px 14px; border-radius:12px; font-weight:700; background:var(--ok); color:#fff; box-shadow:var(--shadow); display:inline-flex; align-items:center; gap:10px; width:100%; justify-content:center; }
  .btn:hover{ background:var(--ok-2) }
  .stats{ margin-top:auto; display:grid; gap:8px; padding-top:8px; border-top:1px dashed #eae7ff; }
  .stat{ display:flex; gap:8px; align-items:flex-start; color:#4b4b63; font-weight:600; font-size:13px; }
  .dot{ width:9px; height:9px; border-radius:99px; background:var(--brand); margin-top:7px; }
  .empty{ display:flex; align-items:center; justify-content:center; color:#fff; opacity:.9; padding:40px 0; }

  .modal-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; padding:20px; z-index:50; }
  .modal{ width:100%; max-width:560px; background:#fff; border-radius:20px; box-shadow:var(--shadow); overflow:hidden; }
  .modal-header{ padding:14px 16px; border-bottom:1px solid #f0eff8; display:flex; align-items:center; justify-content:space-between; }
  .modal-title{ font-weight:800; color:#2c2266; letter-spacing:.4px; }
  .close-btn{ border:none; background:transparent; cursor:pointer; width:36px; height:36px; border-radius:10px; display:grid; place-items:center; }
  .modal-body{ padding:16px; display:grid; gap:12px; }
  .field{ display:grid; gap:6px; }
  .field label{ font-weight:700; color:#3a2f7a; font-size:14px; }
  .field input[type="text"], .field input[type="file"], .field select{ border:1px solid #ececf6; border-radius:12px; padding:10px 12px; font-size:14px; outline:none; }
  .help{ color:#7c7c92; font-size:12px; }
  .actions{ padding:14px 16px; border-top:1px solid #f0eff8; display:flex; gap:10px; justify-content:flex-end; }
  .btn-secondary{ border:none; background:#f2f2f6; color:#2b2b2b; font-weight:700; border-radius:12px; padding:10px 14px; cursor:pointer; }
  .btn-primary{ border:none; background:var(--ok); color:#fff; font-weight:800; border-radius:12px; padding:10px 14px; cursor:pointer; }
  .btn-primary[disabled]{ opacity:.6; cursor:not-allowed; }
  .preview{ display:flex; gap:12px; align-items:flex-start; }
  .preview img{ width:120px; height:90px; object-fit:cover; border:1px solid #ececf6; border-radius:12px; background:#fafafa; }

  .toast{ position:fixed; bottom:20px; right:20px; background:#111; color:#fff; padding:12px 14px; border-radius:10px; box-shadow:var(--shadow); font-weight:700; opacity:0; transform:translateY(20px); transition:.3s; z-index:60; }
  .toast.show{ opacity:1; transform:translateY(0); }
  .toast.error{ background:var(--danger); }
</style>
</head>
<body>
  <div class="shell">
    <header class="header">
      <div class="hdr-left">
        <img class="brand-logo" src="../assets/img/monte_alto.png" alt="Logo empresa">
        <div class="hdr-title">
          <h1>MONTE ALTO</h1>
          <small>RFC: GUGX900726V20</small>
        </div>
      </div>
      <div class="hdr-actions">
        <button id="btnOpenCreate" class="primary-btn" title="Crear un nuevo proyecto">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
          Crear un nuevo proyecto
        </button>
        <button class="icon-btn" title="Modo oscuro">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 0 1 11.21 3 7 7 0 1 0 21 12.79z"/></svg>
        </button>
        <a class="icon-btn" title="Salir" href="../index.php">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5v3H3v4h7v3zM20 3h-8v2h8v14h-8v2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/></svg>
        </a>
      </div>
    </header>

    <div class="toolbar">
      <div class="toolbar-row">
        <div class="input">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 2a8 8 0 105.293 14.293l4.707 4.707 1.414-1.414-4.707-4.707A8 8 0 0010 2zm0 2a6 6 0 110 12A6 6 0 0110 4z"/></svg>
          <input id="filtroTxt" type="text" placeholder="Filtrar proyectos..." />
        </div>
        <button class="toggle-btn active" title="Vista cuadrícula">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/></svg>
        </button>
        <button class="toggle-btn" title="Vista lista">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/></svg>
        </button>
        <div class="input">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 2a8 8 0 105.293 14.293l4.707 4.707 1.414-1.414-4.707-4.707A8 8 0 0010 2zm0 2a6 6 0 110 12A6 6 0 0110 4z"/></svg>
          <input id="buscarClienteTxt" type="text" placeholder="Buscar Proyecto por Cliente..." />
        </div>
        <button id="buscarBtn" class="search-btn" title="Buscar">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" style="fill:#fff"><path d="M10 2a8 8 0 105.293 14.293l4.707 4.707 1.414-1.414-4.707-4.707A8 8 0 0010 2z"/></svg>
        </button>
      </div>
    </div>

    <main id="cards" class="grid"></main>
    <div id="empty" class="empty" style="display:none;">No hay desarrollos para mostrar.</div>
  </div>

  <!-- Modal Crear -->
  <div id="modalOverlay" class="modal-overlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header">
        <div class="modal-title" id="modalTitle">Crear un nuevo proyecto</div>
        <button type="button" class="close-btn" id="btnCloseModal" aria-label="Cerrar">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.71L12 12.01l-6.29-6.3-1.42 1.42 6.3 6.29-6.3 6.29 1.42 1.42 6.29-6.3 6.29 6.3 1.42-1.42-6.3-6.29 6.3-6.29z"/></svg>
        </button>
      </div>
      <form id="formCreate" class="modal-body" enctype="multipart/form-data">
        <div class="field">
          <label for="empresarioSel">Empresario</label>
          <select id="empresarioSel" name="idEmpresario" required>
            <option value="" selected>Selecciona…</option>
          </select>
          <div class="help">El desarrollo se guardará dentro del empresario seleccionado.</div>
        </div>
        <div class="field">
          <label for="nombreDesarrollo">Nombre del Desarrollo</label>
          <input type="text" id="nombreDesarrollo" name="NombreDesarrollo" placeholder="Ej. Torre Primavera" required />
        </div>
        <div class="field">
          <label for="direccion">Dirección</label>
          <input type="text" id="direccion" name="Direccion" placeholder="Calle, número, colonia, ciudad" required />
        </div>
        <div class="field">
          <label for="fotoDesarrollo">Foto del Desarrollo (1 imagen)</label>
          <input type="file" id="fotoDesarrollo" name="FotoDesarrollo" accept="image/*" required />
          <div class="help">Formatos aceptados: JPG/PNG/WebP.</div>
          <div class="preview" id="previewBox" style="display:none;">
            <img id="previewImg" alt="Vista previa">
          </div>
        </div>
        <div class="actions">
          <button type="button" class="btn-secondary" id="btnCancel">Cancelar</button>
          <button type="submit" class="btn-primary" id="btnSave">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div id="appToast" class="toast">Mensaje</div>

  <!-- JS -->
  <script type="module">
    import { database, onValue, ref as dbRef } from "../config/firebase_init.js";

    // UI
    const cardsContainer = document.getElementById('cards');
    const empty = document.getElementById('empty');

    const filtroTxt = document.getElementById('filtroTxt');
    const buscarClienteTxt = document.getElementById('buscarClienteTxt');
    const buscarBtn = document.getElementById('buscarBtn');

    const btnOpenCreate = document.getElementById('btnOpenCreate');
    const modalOverlay  = document.getElementById('modalOverlay');
    const btnCloseModal = document.getElementById('btnCloseModal');
    const btnCancel     = document.getElementById('btnCancel');
    const formCreate    = document.getElementById('formCreate');
    const fotoInput     = document.getElementById('fotoDesarrollo');
    const previewBox    = document.getElementById('previewBox');
    const previewImg    = document.getElementById('previewImg');
    const toastEl       = document.getElementById('appToast');
    const btnSave       = document.getElementById('btnSave');
    const empresarioSel = document.getElementById('empresarioSel');

    // Cache local de datos
    let allPairs = [];         // [ [keyProyecto, dataProyecto], ... ]
    let empresariosIndex = {}; // { idEmpresario: {Nombre?: string} }

    function openModal(){
      modalOverlay.style.display='flex';
      modalOverlay.setAttribute('aria-hidden','false');
      document.getElementById('nombreDesarrollo').focus();
    }
    function closeModal(){
      modalOverlay.style.display='none';
      modalOverlay.setAttribute('aria-hidden','true');
      formCreate.reset();
      previewBox.style.display='none'; previewImg.src='';
    }
    function showToast(msg, type=''){
      toastEl.textContent = msg;
      toastEl.className = 'toast show' + (type==='error'?' error':'');
      setTimeout(()=>toastEl.classList.remove('show'), 2600);
    }

    btnOpenCreate.addEventListener('click', openModal);
    btnCloseModal.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e)=>{ if(e.target===modalOverlay) closeModal(); });

    // Vista previa imagen
    fotoInput.addEventListener('change', (e)=>{
      const f = e.target.files && e.target.files[0];
      if(!f){ previewBox.style.display='none'; previewImg.src=''; return; }
      if(!f.type.startsWith('image/')){ e.target.value=''; showToast('El archivo debe ser una imagen.', 'error'); return; }
      previewImg.src = URL.createObjectURL(f); previewBox.style.display='flex';
    });

    // Carga de TODOS los desarrollos (flatten)
    const basePath = `projects/proj_8HNCM2DFob/data/DesarrollosEmpresarios`;
    const rootRef = dbRef(database, basePath);

    onValue(rootRef, (snap)=>{
      const data = snap.val() || {};
      // Construye índice de empresarios y lista plana de desarrollos
      empresariosIndex = {};
      allPairs = [];

      for (const [empId, empNode] of Object.entries(data)) {
        empresariosIndex[empId] = {
          Nombre: empNode?.Nombre || ''
        };
        const devs = empNode?.Desarrollos || {};
        for (const [devKey, devVal] of Object.entries(devs)) {
          allPairs.push([devKey, { ...devVal, _empresario: empId }]);
        }
      }

      // Poblar select de empresario (solo si aún no tiene opciones reales)
      const currentValue = empresarioSel.value;
      empresarioSel.innerHTML = `<option value="">Selecciona…</option>` +
        Object.entries(empresariosIndex).map(([id, meta])=>{
          const label = meta.Nombre ? meta.Nombre : `Empresario ${id.slice(-4)}`;
          return `<option value="${id}">${label}</option>`;
        }).join('');

      if (Object.keys(empresariosIndex).length === 1) {
        // Selecciona el único automáticamente
        empresarioSel.value = Object.keys(empresariosIndex)[0];
      } else if (Object.keys(empresariosIndex).includes(currentValue)) {
        empresarioSel.value = currentValue;
      }

      renderDesarrollos(allPairs);
    }, (err)=>{
      empty.style.display='flex';
      empty.textContent = 'Error cargando datos: '+(err?.message||err);
    });

    // Filtros simples en cliente
    function normaliza(s){ return (s||'').toString().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''); }
    function aplicaFiltros(pairs){
      const q1 = normaliza(filtroTxt.value);
      const q2 = normaliza(buscarClienteTxt.value);
      if(!q1 && !q2) return pairs;
      return pairs.filter(([k, it])=>{
        const nombre = normaliza(it.NombreDesarrollo);
        const dir    = normaliza(it.Direccion);
        const cli    = normaliza(it.Cliente || '');
        const texto  = `${nombre} ${dir} ${cli} ${k}`;
        return (!q1 || texto.includes(q1)) && (!q2 || cli.includes(q2));
      });
    }
    filtroTxt.addEventListener('input', ()=>renderDesarrollos(aplicaFiltros(allPairs)));
    buscarBtn.addEventListener('click', ()=>renderDesarrollos(aplicaFiltros(allPairs)));

    // Guardar (vía PHP/Kreait)
    formCreate.addEventListener('submit', async (e)=>{
      e.preventDefault();

      const idEmp = empresarioSel.value.trim();
      if (!idEmp) { showToast('Selecciona un empresario.', 'error'); return; }

      const fd = new FormData(formCreate);
      fd.set('idEmpresario', idEmp); // se guarda dentro de ese nodo

      btnSave.disabled = true; btnSave.textContent = 'Guardando...';

      try {
        const resp = await fetch('crear_proyecto.php', { method:'POST', body: fd });
        const json = await resp.json();
        if(!resp.ok || json.status !== 'ok'){
          throw new Error(json.message || 'Error al guardar');
        }
        showToast('Proyecto creado');
        closeModal();
        // La lista se actualizará sola por onValue
      } catch (err) {
        console.error(err);
        showToast('Error al guardar: '+err.message, 'error');
      } finally {
        btnSave.disabled = false; btnSave.textContent = 'Guardar';
      }
    });

    function resolvePhotoUrl(value) {
      if (!value) return '';
      if (/^https?:\/\//i.test(value)) return value;
      const bucket = "dmgvent.appspot.com";
      const path = encodeURIComponent(value);
      return `https://firebasestorage.googleapis.com/v0/b/${bucket}/o/${path}?alt=media`;
    }

    function renderDesarrollos(pairs){
      cardsContainer.innerHTML = '';
      if(!pairs.length){ empty.style.display='flex'; empty.textContent='No hay desarrollos para mostrar.'; return; }
      empty.style.display='none';

      for(const [key, item] of pairs){
        const foto = resolvePhotoUrl(item.FotoDesarrollo);
        const nombre = item.NombreDesarrollo || '—';
        const direccion = item.Direccion || '—';

        const card = document.createElement('article');
        card.className = 'card';
        card.innerHTML = `
          <div class="card-media">
            <img src="${foto}" alt="${nombre}" onerror="this.src='../assets/img/placeholder.png'">
          </div>
          <div class="card-body">
            <h2 class="proj-title">${nombre}</h2>
            <div class="proj-sub">${direccion}</div>
            <button class="btn" data-key="${key}">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" style="fill:#fff">
                <path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7zm0 9.5A2.5 2.5 0 119.5 9 2.5 2.5 0 0112 11.5z"/>
              </svg>
              Ingresar
            </button>
            <div class="stats">
              <div class="stat"><span class="dot"></span> <span>Clave: <strong>${key}</strong></span></div>
            </div>
          </div>`;
        card.querySelector('.btn').addEventListener('click', ()=>{
          // Ir al dashboard del proyecto
          location.href = `dashboard.php?id=${encodeURIComponent(key)}`;
        });
        cardsContainer.appendChild(card);
      }
    }
  </script>
</body>
</html>
