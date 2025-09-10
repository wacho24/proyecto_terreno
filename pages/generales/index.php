<?php
// pages/generales/index.php
require_once __DIR__ . '/../_guard.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$idDesarrollo = isset($_GET['id']) ? (string)$_GET['id'] : ($_SESSION['idDesarrollo'] ?? '');
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', $idDesarrollo);
if ($idDesarrollo === '') { header('Location: ../../index.php'); exit; }
$_SESSION['idDesarrollo'] = $idDesarrollo;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generales del Desarrollo | iTrade 3.0</title>

  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="../../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../../assets/css/nucleo-svg.css" rel="stylesheet" />
  <link id="pagestyle" href="../../assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root{ --brand1:#6a39b6; --brand2:#7b61ff; }
    .btn-gradient{ background:linear-gradient(90deg,var(--brand1),var(--brand2)); color:#fff; border:0; border-radius:12px; box-shadow:0 10px 18px rgba(102,51,204,.25); }
    .btn-gradient:hover{ filter:brightness(1.03); color:#fff; }
    .card .form-control{ border-radius:12px; padding:.7rem .9rem; }
    .card .form-label{ font-weight:600; }
    .section-title{ font-weight:800; letter-spacing:.01em; }
    .preview-img{ width:100%; height:160px; object-fit:cover; border-radius:12px; border:1px solid #e5e7eb; background:#f8fafc; }
    .chip{ display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .55rem; border-radius:999px; font-size:.75rem; background:#eef2ff; color:#3730a3; font-weight:700; }
    .table thead th{ font-weight:700; color:#3b2a72; }
    .file-pill{ display:inline-flex; align-items:center; gap:.5rem; background:#f1f5f9; border:1px solid #e5e7eb; border-radius:999px; padding:.35rem .7rem; font-size:.8rem; }
    .file-pill i{ opacity:.85; }
  </style>
</head>
<body class="g-sidenav-show bg-gray-100">
  <?php require_once __DIR__ . '/../sidebar.php'; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php require_once __DIR__ . '/../header.php'; ?>

    <div class="container-fluid py-4">
      <div class="row">
        <div class="col-12">
          <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div>
                <h6 class="mb-0 section-title">Información general</h6>
                <small class="text-muted">Desarrollo: <code><?= h($idDesarrollo) ?></code></small>
              </div>
              <button type="button" id="btnGuardar" class="btn btn-gradient">
                <i class="fa-regular fa-floppy-disk me-1"></i> Guardar cambios
              </button>
            </div>

            <div class="card-body">
              <form id="frmGenerales" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= h($idDesarrollo) ?>">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Nombre del desarrollo</label>
                    <input type="text" name="NombreDesarrollo" id="NombreDesarrollo" class="form-control" placeholder="Ej. Residencial Las Palmas">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Responsable</label>
                    <input type="text" name="Responsable" id="Responsable" class="form-control" placeholder="Nombre del responsable">
                  </div>

                  <!-- NUEVO: Número de Expediente -->
                  <div class="col-md-4">
                    <label class="form-label">Número de expediente</label>
                    <input type="text" name="NumeroExpediente" id="NumeroExpediente" class="form-control" placeholder="Ej. EXP-2025-00123">
                  </div>

                  <div class="col-md-8">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="Direccion" id="Direccion" class="form-control" placeholder="Calle, número, colonia, ciudad">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="Telefono" id="Telefono" class="form-control" placeholder="Ej. 555-123-4567">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="Email" id="Email" class="form-control" placeholder="correo@dominio.com">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Latitud</label>
                    <input type="text" name="Latitude" id="Latitude" class="form-control" placeholder="19.4326">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Longitud</label>
                    <input type="text" name="Longitude" id="Longitude" class="form-control" placeholder="-99.1332">
                  </div>

                  <!-- PORTADA (imagen) -->
                  <div class="col-md-6">
                    <label class="form-label">Portada (URL)</label>
                    <input type="text" name="Portada" id="Portada" class="form-control" placeholder="https://.../imagen.jpg">
                    <div class="form-text">También puedes subir un archivo.</div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Portada (Archivo)</label>
                    <input type="file" name="PortadaFile" id="PortadaFile" accept="image/*" class="form-control">
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <img id="PortadaPreview" class="preview-img" alt="Portada" src="">
                  </div>

                  <!-- NUEVO: PLANO GENERAL (PDF) -->
                  <div class="col-md-6">
                    <label class="form-label">Plano general (URL PDF)</label>
                    <input type="text" name="PlanoGeneral" id="PlanoGeneral" class="form-control" placeholder="https://.../plano.pdf">
                    <div class="form-text">Si Apphive genera una URL del PDF, pégala aquí.</div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Plano general (Archivo PDF)</label>
                    <input type="file" name="PlanoGeneralFile" id="PlanoGeneralFile" accept="application/pdf" class="form-control">
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <div id="PlanoBadge" class="file-pill" style="display:none;">
                      <i class="fa-regular fa-file-pdf"></i>
                      <a id="PlanoLink" href="#" target="_blank" rel="noopener">Abrir plano</a>
                    </div>
                  </div>
                </div>
              </form>

              <hr class="my-4">

              <!-- ================= Cuentas ================= -->
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="section-title">Cuentas bancarias <span class="chip"><i class="fa-regular fa-credit-card"></i> del desarrollo</span></div>
                <button type="button" class="btn btn-sm btn-gradient" id="btnNuevaCta">
                  <i class="fa-solid fa-plus me-1"></i> Nueva cuenta
                </button>
              </div>

              <div class="table-responsive border rounded">
                <table class="table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Banco</th>
                      <th>Beneficiario</th>
                      <th>CLABE</th>
                      <th>idCuenta</th>
                      <th class="text-center" style="width:150px">Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="tbCuentas"></tbody>
                </table>
              </div>

              <div class="d-flex justify-content-end mt-3">
                <button type="button" id="btnGuardar2" class="btn btn-gradient">
                  <i class="fa-regular fa-floppy-disk me-1"></i> Guardar cambios
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../footer.php'; ?>
  </main>

  <!-- Core JS -->
  <script src="../../assets/js/core/popper.min.js"></script>
  <script src="../../assets/js/core/bootstrap.min.js"></script>
  <script src="../../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../../assets/js/soft-ui-dashboard.min.js?v=1.1.0"></script>

  <script>
    const ID_DES = <?= json_encode($idDesarrollo) ?>;

    // endpoints
    const SAVE_GENERALES_URL = '../config_general/general_guardar.php';
    const SAVE_CUENTAS_URL   = 'save.php';
    const GET_URL            = 'get.php';

    const esc = (s)=> String(s ?? '').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
    function parseServerJson(text){
      let s = (text ?? '').replace(/^\uFEFF/,'').trim();
      if (s.startsWith('"') && s.endsWith('"')) { try { s = JSON.parse(s); } catch{} }
      if (typeof s === 'string') { const i=s.indexOf('{'), j=s.lastIndexOf('}'); if(i>=0 && j>=0) s = s.slice(i, j+1); }
      return (typeof s === 'string') ? JSON.parse(s) : s;
    }

    function setPlanoBadge(url){
      const badge = document.getElementById('PlanoBadge');
      const link  = document.getElementById('PlanoLink');
      if(url){
        link.href = url;
        link.textContent = 'Abrir plano';
        badge.style.display = 'inline-flex';
      }else{
        badge.style.display = 'none';
      }
    }

    function fillForm(data){
      const g = (k)=> (data && data[k] != null) ? String(data[k]) : '';
      NombreDesarrollo.value = g('NombreDesarrollo');
      Responsable.value      = g('Responsable');
      Direccion.value        = g('Direccion');
      Telefono.value         = g('Telefono');
      Email.value            = g('Email');
      Latitude.value         = g('Latitude');
      Longitude.value        = g('Longitude');
      NumeroExpediente.value = g('NumeroExpediente'); // nuevo

      Portada.value          = g('FotoDesarrollo') || g('Portada');
      PortadaPreview.src     = Portada.value || 'https://via.placeholder.com/600x300?text=Portada';

      const plano = g('PlanoGeneral') || g('Plano');
      PlanoGeneral.value = plano;
      setPlanoBadge(plano);
    }

    async function cargar(){
      try{
        const res = await fetch(`${GET_URL}?id=${encodeURIComponent(ID_DES)}`, {cache:'no-store'});
        const json = parseServerJson(await res.text());
        if(!res.ok || !json || json.ok!==true) throw new Error((json && json.error) || ('HTTP '+res.status));
        fillForm(json.item || {});
      }catch(e){
        console.error(e);
        Swal.fire({icon:'error', title:'No se pudo cargar', text:String(e).slice(0,260)});
      }
    }

    async function guardar(){
      const fd = new FormData(document.getElementById('frmGenerales'));
      fd.set('idDesarrollo', ID_DES);
      fd.set('_dbg_ts', new Date().toISOString());

      try{
        const res = await fetch(SAVE_GENERALES_URL, { method:'POST', body: fd });
        const raw = await res.text();
        const j   = parseServerJson(raw);

        if(!res.ok || !j || (j.ok!==true && j.status!=='ok')) {
          throw new Error((j && (j.error||j.message)) || ('HTTP '+res.status));
        }
        Swal.fire({icon:'success', title:'Cambios guardados', timer:1400, showConfirmButton:false});

        const urlPortada = j.portadaUrl || j.urlPortada;
        if(urlPortada){ Portada.value=urlPortada; PortadaPreview.src=urlPortada; }

        const urlPlano = j.planoUrl || j.urlPlano || j.plano_general_url;
        if(urlPlano){ PlanoGeneral.value = urlPlano; setPlanoBadge(urlPlano); }
      }catch(e){
        console.error(e);
        Swal.fire({icon:'error', title:'No se pudo guardar', text:String(e).slice(0,260)});
      }
    }

    Portada.addEventListener('input', ()=> {
      PortadaPreview.src = Portada.value || 'https://via.placeholder.com/600x300?text=Portada';
    });
    PortadaFile.addEventListener('change', (e)=>{
      const f = e.target.files?.[0];
      if(!f) return;
      PortadaPreview.src = URL.createObjectURL(f);
    });

    PlanoGeneral.addEventListener('input', ()=>{
      const url = PlanoGeneral.value.trim();
      setPlanoBadge(url || '');
    });
    PlanoGeneralFile.addEventListener('change', (e)=>{
      const f = e.target.files?.[0];
      if(!f) { setPlanoBadge(''); return; }
      setPlanoBadge('#');
      document.getElementById('PlanoLink').textContent = `Archivo seleccionado: ${f.name}`;
      document.getElementById('PlanoLink').removeAttribute('href');
    });

    // ---------- Cuentas ----------
    async function cargarCuentas(){
      try{
        const res = await fetch(`${GET_URL}?cuentas=1&id=${encodeURIComponent(ID_DES)}`, {cache:'no-store'});
        const j = parseServerJson(await res.text());
        const tb = document.getElementById('tbCuentas'); tb.innerHTML='';
        (j.items || []).forEach(cta=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${esc(cta.banco)}</td>
            <td>${esc(cta.beneficiario)}</td>
            <td>${esc(cta.clabe)}</td>
            <td>${esc(cta.idCuenta)}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-primary me-1" data-edit="${cta.id}" data-row='${JSON.stringify(cta).replace(/'/g,"&#39;")}' title="Editar">
                <i class="fa-regular fa-pen-to-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" data-rid="${cta.id}" title="Eliminar">
                <i class="fa-regular fa-trash-can"></i>
              </button>
            </td>`;
          tb.appendChild(tr);
        });
      }catch(e){
        console.error(e);
        Swal.fire({icon:'error', title:'No se pudieron cargar las cuentas', text:String(e).slice(0,260)});
      }
    }

    async function nuevaCuenta(){
      const { value: formValues } = await Swal.fire({
        title:'Nueva cuenta',
        html: `
          <input id="swBanco" class="swal2-input" placeholder="Banco">
          <input id="swBenef" class="swal2-input" placeholder="Beneficiario">
          <input id="swClabe" class="swal2-input" placeholder="CLABE">
          <input id="swIdCta" class="swal2-input" placeholder="idCuenta (opcional)">
        `,
        focusConfirm:false,
        preConfirm: () => {
          return {
            banco: document.getElementById('swBanco').value.trim(),
            beneficiario: document.getElementById('swBenef').value.trim(),
            clabe: document.getElementById('swClabe').value.trim(),
            idCuenta: document.getElementById('swIdCta').value.trim()
          };
        },
        showCancelButton:true,
        confirmButtonText:'Guardar'
      });
      if(!formValues) return;

      const res = await fetch(SAVE_CUENTAS_URL, {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'acct_add', id:ID_DES, data:formValues })
      });
      const j = parseServerJson(await res.text());
      if(j?.ok){ await cargarCuentas(); Swal.fire({icon:'success', title:'Cuenta agregada', timer:1200, showConfirmButton:false}); }
      else { Swal.fire({icon:'error', title:'No se pudo guardar', text:j?.error||'Error'}); }
    }

    async function editarCuenta(row){
      const { value: formValues } = await Swal.fire({
        title:'Editar cuenta',
        html: `
          <input id="swBanco" class="swal2-input" placeholder="Banco" value="${esc(row.banco)}">
          <input id="swBenef" class="swal2-input" placeholder="Beneficiario" value="${esc(row.beneficiario)}">
          <input id="swClabe" class="swal2-input" placeholder="CLABE" value="${esc(row.clabe)}">
          <input id="swIdCta" class="swal2-input" placeholder="idCuenta (opcional)" value="${esc(row.idCuenta)}">
        `,
        focusConfirm:false,
        preConfirm: () => {
          return {
            banco: document.getElementById('swBanco').value.trim(),
            beneficiario: document.getElementById('swBenef').value.trim(),
            clabe: document.getElementById('swClabe').value.trim(),
            idCuenta: document.getElementById('swIdCta').value.trim()
          };
        },
        showCancelButton:true,
        confirmButtonText:'Actualizar'
      });
      if(!formValues) return;

      const res = await fetch(SAVE_CUENTAS_URL, {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'acct_edit', id:ID_DES, rid:row.id, data:formValues })
      });
      const j = parseServerJson(await res.text());
      if(j?.ok){ await cargarCuentas(); Swal.fire({icon:'success', title:'Cuenta actualizada', timer:1200, showConfirmButton:false}); }
      else { Swal.fire({icon:'error', title:'No se pudo actualizar', text:j?.error||'Error'}); }
    }

    document.addEventListener('click', async (ev)=>{
      const btnNew = ev.target.closest('#btnNuevaCta');
      if(btnNew){ nuevaCuenta(); return; }

      const edit = ev.target.closest('#tbCuentas button[data-edit]');
      if(edit){
        const row = JSON.parse(edit.dataset.row);
        editarCuenta(row);
        return;
      }

      const del = ev.target.closest('#tbCuentas button[data-rid]');
      if(del){
        const rid = del.dataset.rid;
        const c = await Swal.fire({icon:'warning', title:'Eliminar cuenta', showCancelButton:true, confirmButtonText:'Eliminar'});
        if(!c.isConfirmed) return;
        const r = await fetch(SAVE_CUENTAS_URL, {method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ action:'acct_del', id:ID_DES, rid })
        });
        const j = parseServerJson(await r.text());
        if(j?.ok){ await cargarCuentas(); Swal.fire({icon:'success', title:'Eliminada', timer:1000, showConfirmButton:false}); }
        else { Swal.fire({icon:'error', title:'No se pudo eliminar', text:j?.error||'Error'}); }
      }
    });

    document.getElementById('btnNuevaCta').addEventListener('click', nuevaCuenta);
    document.getElementById('btnGuardar').addEventListener('click', guardar);
    document.getElementById('btnGuardar2').addEventListener('click', guardar);

    document.addEventListener('DOMContentLoaded', async ()=>{
      await cargar();
      await cargarCuentas();
    });
  </script>
</body>
</html>
