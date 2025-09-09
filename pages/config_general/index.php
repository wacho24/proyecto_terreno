<?php
// pages/config_general/index.php
require_once __DIR__ . '/../_guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$idDesarrollo = isset($_GET['id']) ? (string)$_GET['id'] : ($_SESSION['idDesarrollo'] ?? '');
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', $idDesarrollo);
if ($idDesarrollo === '') { header('Location: ../index.php'); exit; }
$_SESSION['idDesarrollo'] = $idDesarrollo;

require_once __DIR__ . '/../../config/firebase_init.php';

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';
$info = [
  'NombreDashboard'   => '',
  'NombreDesarrollo'  => '',
  'Portada'           => '',
];
try {
  $snap = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo")->getSnapshot();
  $val  = $snap->getValue() ?: [];
  $info['NombreDashboard']  = (string)($val['NombreDashboard']  ?? '');
  $info['NombreDesarrollo'] = (string)($val['NombreDesarrollo'] ?? '');
  $info['Portada']          = (string)($val['Portada']          ?? '');
} catch (Throwable $e) {
  // sigue con defaults
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Información general | iTrade 3.0</title>

  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link id="pagestyle" href="../../assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{ --m1:#6a39b6; --m2:#7b61ff; }
    .bg-grad{ background:linear-gradient(90deg,var(--m1),var(--m2)); color:#fff; }
    .card-hero{ display:grid; grid-template-columns: 220px 1fr; gap:18px; align-items:center; }
    .hero-img{ width:220px; height:140px; border-radius:12px; object-fit:cover; background:#f3f4f6; border:1px solid #e5e7eb; }
    .form-label{ font-weight:700; }
    .btn-grad{ background:linear-gradient(90deg,var(--m1),var(--m2)); color:#fff; border:0; }
    .btn-grad:hover{ filter:brightness(1.03); color:#fff; }
    .table thead th{ font-weight:700; }
    .chip{ display:inline-flex; gap:.4rem; align-items:center; padding:.25rem .55rem; background:#eef2ff; color:#3730a3; border-radius:999px; font-weight:700; font-size:.78rem; }
    .help{ color:#64748b; font-size:.88rem; }
  </style>
</head>
<body class="g-sidenav-show bg-gray-100">
  <?php require_once __DIR__ . '/../sidebar.php'; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php require_once __DIR__ . '/../header.php'; ?>

    <div class="container-fluid py-4">

      <!-- ===== Información general ===== -->
      <div class="card mb-4">
        <div class="card-header bg-grad">
          <h6 class="mb-0"><i class="fa-solid fa-gears me-2"></i>Información general del desarrollo</h6>
        </div>
        <div class="card-body">
          <form id="formGeneral" enctype="multipart/form-data">
            <input type="hidden" name="idDesarrollo" value="<?= h($idDesarrollo) ?>">
            <div class="card-hero mb-3">
              <img id="portadaPreview" class="hero-img"
                   src=""
                   alt="Portada">
              <div>
                <div class="mb-3">
                  <label class="form-label">Nombre del Dashboard</label>
                  <input type="text" class="form-control" name="NombreDashboard"
                         value="<?= h($info['NombreDashboard']) ?>"
                         placeholder="Ej. Monte Alto">
                  <div class="help">Se muestra arriba a la izquierda como título principal.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Nombre del Desarrollo</label>
                  <input type="text" class="form-control" name="NombreDesarrollo"
                         value="<?= h($info['NombreDesarrollo']) ?>"
                         placeholder="Nombre comercial del desarrollo">
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Portada (opcional)</label>
              <input type="file" class="form-control" name="PortadaFile" accept="image/*">
              <div class="help">Si no eliges archivo, se conservará la portada actual.</div>
            </div>

            <button type="submit" class="btn btn-grad">
              <i class="fa-regular fa-floppy-disk me-1"></i> Guardar cambios
            </button>
          </form>
        </div>
      </div>

      <!-- ===== Cuentas bancarias ===== -->
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h6 class="mb-0"><i class="fa-regular fa-credit-card me-2"></i>Cuentas bancarias</h6>
          <button class="btn btn-sm btn-grad" id="btnNuevaCuenta"><i class="fa-solid fa-plus"></i> Nueva cuenta</button>
        </div>
        <div class="card-body pb-2">
          <div class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th style="width:60px">N°</th>
                  <th>Banco</th>
                  <th>Beneficiario</th>
                  <th>CLABE</th>
                  <th>idCuenta</th>
                  <th class="text-center" style="width:160px">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbCuentas"></tbody>
            </table>
            <div id="emptyCtas" class="text-center text-secondary py-4">Sin cuentas registradas.</div>
          </div>
        </div>
      </div>

    </div>
    <?php require_once __DIR__ . '/../footer.php'; ?>
  </main>

  <!-- Modal Cuenta -->
  <div class="modal fade" id="modalCuenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-grad">
          <h6 class="modal-title"><i class="fa-regular fa-circle-dot me-2"></i>Cuenta bancaria</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <form id="formCuenta">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="idDesarrollo" value="<?= h($idDesarrollo) ?>">
            <div class="mb-3">
              <label class="form-label">Banco</label>
              <input type="text" class="form-control" name="Banco" placeholder="Ej. Banorte" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Beneficiario</label>
              <input type="text" class="form-control" name="Beneficiario" placeholder="Titular de la cuenta" required>
            </div>
            <div class="mb-3">
              <label class="form-label">CLABE</label>
              <input type="text" class="form-control" name="CLABE" placeholder="CLABE interbancaria" required>
            </div>
            <div class="mb-3">
              <label class="form-label">idCuenta (opcional)</label>
              <input type="text" class="form-control" name="idCuenta" placeholder="Identificador interno">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-grad" id="btnGuardarCuenta">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="../../assets/js/core/popper.min.js"></script>
  <script src="../../assets/js/core/bootstrap.min.js"></script>
  <script src="../../assets/js/soft-ui-dashboard.min.js?v=1.1.0"></script>

  <script>
    const ID_DES = <?= json_encode($idDesarrollo) ?>;
    const PORTADA_RAW = <?= json_encode($info['Portada']) ?>;

    // ==== portada preview ====
    const portadaPreview = document.getElementById('portadaPreview');
    function resolvePhotoUrl(value) {
      if (!value) return '';
      if (/^https?:\/\//i.test(value)) return value;
      const bucket = "dmgvent.appspot.com"; // el mismo bucket que usas
      const path = encodeURIComponent(value);
      return `https://firebasestorage.googleapis.com/v0/b/${bucket}/o/${path}?alt=media`;
    }
    portadaPreview.src = resolvePhotoUrl(PORTADA_RAW) || '../../assets/img/placeholder.png';

    // ==== Guardar información general ====
    document.getElementById('formGeneral').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      try{
        const r = await fetch('general_guardar.php', { method:'POST', body: fd });
        const j = await r.json();
        if(!r.ok || !j.ok) throw new Error(j.error || 'No se pudo guardar');
        Swal.fire({icon:'success', title:'Guardado', timer:1400, showConfirmButton:false});
        if (j.portadaUrl) portadaPreview.src = j.portadaUrl;
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text:String(err).slice(0,280)});
      }
    });

    // ==== Cuentas – listado/CRUD ====
    const modalCuenta = new bootstrap.Modal('#modalCuenta');
    const tbCtas = document.getElementById('tbCuentas');
    const emptyCtas = document.getElementById('emptyCtas');

    async function cargarCuentas(){
      tbCtas.innerHTML = `<tr><td colspan="6" class="text-center py-3">Cargando…</td></tr>`;
      emptyCtas.style.display = 'none';
      try{
        const r = await fetch('../cuentas_list.php?id='+encodeURIComponent(ID_DES), {cache:'no-store'});
        const j = await r.json();
        if(!r.ok || !j.ok) throw new Error(j.error||'HTTP '+r.status);
        renderCuentas(j.items||[]);
      }catch(err){
        tbCtas.innerHTML = '';
        emptyCtas.style.display = 'block';
        emptyCtas.textContent = 'Error cargando: '+String(err);
      }
    }

    function renderCuentas(items){
      tbCtas.innerHTML = '';
      let n=0;
      (items||[]).forEach(ct=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${++n}</td>
          <td>${esc(ct.banco)}</td>
          <td>${esc(ct.beneficiario)}</td>
          <td>${esc(ct.clabe)}</td>
          <td>${esc(ct.idCuenta)}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary me-1" data-edit="${ct.id}"><i class="fa-regular fa-pen-to-square"></i></button>
            <button class="btn btn-sm btn-outline-danger" data-del="${ct.id}"><i class="fa-regular fa-trash-can"></i></button>
          </td>`;
        tbCtas.appendChild(tr);
      });
      emptyCtas.style.display = tbCtas.children.length ? 'none' : 'block';
    }

    function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

    document.getElementById('btnNuevaCuenta').addEventListener('click', ()=>{
      const f = document.getElementById('formCuenta');
      f.reset(); f.id.value='';
      modalCuenta.show();
    });

    document.getElementById('btnGuardarCuenta').addEventListener('click', async ()=>{
      const f = document.getElementById('formCuenta');
      const data = Object.fromEntries(new FormData(f).entries());
      try{
        const r = await fetch('cuentas_guardar.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify(data)
        });
        const j = await r.json();
        if(!r.ok || !j.ok) throw new Error(j.error||'No se pudo guardar');
        modalCuenta.hide();
        await cargarCuentas();
        Swal.fire({icon:'success', title:'Cuenta guardada', timer:1200, showConfirmButton:false});
      }catch(err){
        Swal.fire({icon:'error', title:'Error', text:String(err).slice(0,280)});
      }
    });

    tbCtas.addEventListener('click', async (e)=>{
      const btnE = e.target.closest('button[data-edit]');
      const btnD = e.target.closest('button[data-del]');
      if(btnE){
        // trae la fila actual y precarga el modal con los valores visibles
        const tr = btnE.closest('tr');
        const c = {
          id: btnE.dataset.edit,
          Banco: tr.children[1].textContent.trim(),
          Beneficiario: tr.children[2].textContent.trim(),
          CLABE: tr.children[3].textContent.trim(),
          idCuenta: tr.children[4].textContent.trim()
        };
        const f = document.getElementById('formCuenta');
        f.reset();
        f.id.value = c.id;
        f.Banco.value = c.Banco;
        f.Beneficiario.value = c.Beneficiario;
        f.CLABE.value = c.CLABE;
        f.idCuenta.value = c.idCuenta;
        modalCuenta.show();
      }
      if(btnD){
        const id = btnD.dataset.del;
        const ok = await Swal.fire({icon:'warning',title:'Eliminar cuenta',text:'¿Deseas eliminar esta cuenta?',showCancelButton:true,confirmButtonText:'Eliminar'}).then(r=>r.isConfirmed);
        if(!ok) return;
        try{
          const r = await fetch('cuentas_eliminar.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ idDesarrollo: ID_DES, id }) });
          const j = await r.json();
          if(!r.ok || !j.ok) throw new Error(j.error||'No se pudo eliminar');
          await cargarCuentas();
          Swal.fire({icon:'success', title:'Eliminado', timer:900, showConfirmButton:false});
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text:String(err).slice(0,280)});
        }
      }
    });

    cargarCuentas();
  </script>
</body>
</html>
