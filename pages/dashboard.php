<?php
// pages/dashboard.php
require_once __DIR__ . '/_guard.php';

session_start();

/* ============================
   1) idDesarrollo: tomar de GET o sesión, limpiar y exigir
   ============================ */
$idDesarrollo = isset($_GET['id']) ? (string)$_GET['id'] : ($_SESSION['idDesarrollo'] ?? '');
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', $idDesarrollo);

if ($idDesarrollo === '') {
  // Si no hay idDesarrollo, no dejes entrar
  header('Location: ../index.php');
  exit;
}
$_SESSION['idDesarrollo'] = $idDesarrollo;

require_once __DIR__ . '/../config/firebase_init.php';

/* ============================
   2) Cargar clientes de Firebase (autodetección de nodo)
   ============================ */
$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

$clientes           = [];
$firebaseNodo       = (string)$idDesarrollo;
$loadError          = '';
$clientesPathUsed   = ''; // opcional, por si quieres mostrar de dónde se leyó
$desarrolloIdCampo  = '';

try {
  // 1) Obtén el "desarrolloId" (campo interno) del nodo Generales, por si Apphive replicó en Empresarios
  try {
    $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$firebaseNodo")->getSnapshot();
    $tmpInfo  = $snapInfo->getValue() ?: [];
    $desarrolloIdCampo = $tmpInfo['idDesarrollo'] ?? '';
  } catch (Throwable $e0) {
    // silencioso
  }

  // 2) Rutas candidatas más comunes
  $candidates = [
    "$ROOT_PREFIX/DesarrollosClientes/$firebaseNodo/Clientes",
    "$ROOT_PREFIX/DesarrollosGenerales/$firebaseNodo/Clientes",
  ];
  if ($desarrolloIdCampo !== '') {
    $candidates[] = "$ROOT_PREFIX/Empresarios/$desarrolloIdCampo/Clientes";
  }

  $raw = [];
  // 3) Intenta leer en orden
  foreach ($candidates as $p) {
    try {
      $snap = $database->getReference($p)->getSnapshot();
      $tmp  = $snap->getValue();
      if (is_array($tmp) && count($tmp)) {
        $raw = $tmp;
        $clientesPathUsed = $p;
        break;
      }
    } catch (Throwable $e1) {
      // probar siguiente
    }
  }

  // 4) Si aún no hay nada, intenta detectar cualquier llave que empiece con "clientes" en los padres
  if (!$clientesPathUsed) {
    $parents = [
      "$ROOT_PREFIX/DesarrollosClientes/$firebaseNodo",
      "$ROOT_PREFIX/DesarrollosGenerales/$firebaseNodo",
    ];
    // si tenemos id de empresarios, también probamos ese padre
    if ($desarrolloIdCampo !== '') {
      $parents[] = "$ROOT_PREFIX/Empresarios/$desarrolloIdCampo";
    }

    foreach ($parents as $parentPath) {
      try {
        $snapPar = $database->getReference($parentPath)->getSnapshot();
        $parVal  = $snapPar->getValue() ?: [];
        if (is_array($parVal)) {
          foreach ($parVal as $k => $v) {
            // acepta "Clientes", "Cliente", "clientes", etc.
            if (is_string($k) && preg_match('/^clientes?/i', $k) && is_array($v) && count($v)) {
              $raw = $v;
              $clientesPathUsed = $parentPath . '/' . $k;
              break 2;
            }
          }
        }
      } catch (Throwable $e2) {
        // probar siguiente padre
      }
    }
  }

  // 5) Normaliza al arreglo que tu tabla espera
  if (is_array($raw)) {
    foreach ($raw as $rid => $row) {
      $clientes[] = [
        'id'           => $rid,
        'curp'         => $row['Curp']         ?? '',
        'nacionalidad' => $row['Nacionalidad'] ?? '',
        'nombre'       => $row['Nombre']       ?? '',
        'telefono'     => $row['Telefono']     ?? '',
        'email'        => $row['Email']        ?? '',
        'pais'         => $row['Pais']         ?? '',
        'estado'       => $row['Estado']       ?? '',
        'municipio'    => $row['Municipio']    ?? '',
        'localidad'    => $row['Localidad']    ?? '',
        'direccion'    => $row['Calle']        ?? '',
        'codigoPostal' => $row['CodigoPostal'] ?? '',
        'notas'        => '', // tu UI lo usa; si tienes "Notas" en Firebase, cambia a $row['Notas'] ?? ''
      ];
    }
  }

} catch (Throwable $e) {
  $loadError = $e->getMessage();
}


function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contactos | iTrade 3.0</title>

  <!-- Assets -->
  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <link id="pagestyle" href="../assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    .table thead th{ font-weight:700; }
    .btn-action{ padding:6px 10px; border-radius:8px }
    .search-wrap{ max-width:420px }
    .badge-note{ background:#f1f0ff; color:#5645d5; font-weight:700; }
    .nav-tabs .nav-link{ font-weight:700 }
    .form-label{ font-weight:600 }
    .required:after{ content:" *"; color:#e11d48 }

    :root{ --morado:#6a39b6; --morado-2:#7b61ff; }
    .bg-gradient-primary{ background:linear-gradient(90deg,var(--morado),var(--morado-2))!important; }
    .form-control:focus,.form-select:focus{
      border-color:#cdbbff!important; box-shadow:0 0 0 .2rem rgba(123,97,255,.22)!important;
    }
    .input-group-text{ background:#f6f3ff; border-color:#e6e0ff; color:#5a39c7; }
    .table thead th{ color:#3b2a72; }
    .modal-content{ border-radius:16px; overflow:hidden; }
    .modal-header{ background:linear-gradient(90deg, var(--morado), var(--morado-2)); color:#fff; border-bottom:none; }
    .modal-header .btn-close{ filter: invert(1) brightness(200%); opacity:.9; }
    .modal-footer{ border-top:none; }
    .nav-tabs .nav-link{ color:#6b7280; }
    .nav-tabs .nav-link.active{ color:var(--morado) !important; border-bottom:2px solid var(--morado) !important; }
    .btn-outline-primary{ border-color:var(--morado); color:var(--morado); }
    .btn-outline-primary:hover{ color:#fff; border-color:transparent; background:linear-gradient(90deg, var(--morado), var(--morado-2)); }
    .main-content{ display:flex; flex-direction:column; min-height:100vh; }
    .main-content > .container-fluid{ flex: 1 0 auto; }
    .footer-itrade{ margin-top:auto; background:transparent; border-top:1px solid #ececec; padding:12px 0; }
    .footer-itrade .nav-link{ color:#6b7280; padding:0 .5rem; }
    .footer-itrade .nav-link:hover{ color:var(--morado); }
    .footer-itrade .brand-em{ font-style: italic; font-weight:600; }
    .soft-alert{ background:#fff7e6; border:1px solid #ffe3b3; color:#7a5b2b; }

    #tabsContacto [data-bs-target="#pane-cony"],
    #tabsContacto [data-bs-target="#pane-otros"],
    #pane-cony,#pane-otros { display:none!important; }

    
  </style>
</head>

<body class="g-sidenav-show bg-gray-100">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-fluid py-4">
      <?php if ($loadError): ?>
        <div class="alert soft-alert mb-3" role="alert">
          <strong>No se pudo cargar desde Firebase:</strong> <?= h($loadError) ?><br>
          <small>Nodo fijo por <code>idDesarrollo = <?= h($idDesarrollo) ?></code>
            — nodo usado: <code><?= h($firebaseNodo) ?></code></small>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between pb-0">
              <div>
                <h6 class="mb-0">Lista de Contactos</h6>
                <small class="text-muted">idDesarrollo sesión: <code><?= h($idDesarrollo) ?></code> — nodo: <code><?= h($firebaseNodo) ?></code></small>
              </div>
              <div class="ms-auto d-flex align-items-center gap-2">
                <button class="btn btn-sm bg-gradient-primary" id="btnNuevo">
                  <i class="fa-solid fa-plus"></i> Nuevo
                </button>
                <!-- NUEVO: botón Descargar Excel -->
                <button class="btn btn-sm btn-outline-primary" id="btnExportExcel" title="Descargar Excel (.xlsx)">
                  <i class="fa-solid fa-download"></i> Descargar Excel
                </button>
                <div class="search-wrap ms-2">
                  <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="inputBuscar" class="form-control" placeholder="Buscar...">
                  </div>
                </div>
              </div>
            </div>

            <div class="card-body px-0 pt-3 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0" id="tablaContactos">
                  <thead>
                    <tr>
                      <th style="width:60px">N°</th>
                      <th>Nombres y Apellidos</th>
                      <th>CURP/RFC</th>
                      <th>Teléfono</th>
                      <th>Email</th>
                      <th>Dirección</th>
                      <th>Ubicación</th>
                      <th class="text-center" style="width:160px">Edición</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyContactos"></tbody>
                </table>
                <div id="estadoVacio" class="text-center text-secondary py-5">
                  No hay clientes aún. Usa <strong>Nuevo</strong> para agregar.
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
     </div>
    <?php require_once __DIR__ . '/footer.php'; ?>
  </main>

  <!-- Modal Crear/Editar -->
  <div class="modal fade" id="modalContacto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModal">Registrar Contacto</h5>
          <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <ul class="nav nav-tabs" id="tabsContacto" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-rep" type="button">Representante</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-res" type="button">Residencia</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-cony" type="button">Cónyuge</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-otros" type="button">Otros Datos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-benef" type="button">Beneficiario</button></li>
          </ul>

          <form id="formContacto" class="mt-3">
            <input type="hidden" id="rowIndex" />

            <div class="tab-content">
              <!-- Representante -->
              <div class="tab-pane fade show active" id="pane-rep">
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label required">CURP/RFC</label>
                    <input type="text" id="curp" class="form-control" placeholder="N° CURP/RFC || Sin Data: 000111" required>
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Nacionalidad</label>
                    <input type="text" id="nacionalidad" class="form-control" placeholder="Nacionalidad">
                  </div>

                  <div class="col-lg-12">
                    <label class="form-label required">Nombres y Apellidos</label>
                    <input type="text" id="nombre" class="form-control" required>
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label required">Teléfono de contacto</label>
                    <input type="tel" id="telefono" class="form-control" required>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label required">Correo electrónico</label>
                    <input type="email" id="email" class="form-control" placeholder="correo@dominio.com" required>
                  </div>
                </div>
              </div>

              <!-- Residencia -->
              <div class="tab-pane fade" id="pane-res">
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label required">País</label>
                    <input type="text" id="pais" class="form-control" value="MÉXICO" required>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label required">Estado</label>
                    <input type="text" id="estado" class="form-control" placeholder="Estado / Provincia" required>
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label required">Municipio</label>
                    <input type="text" id="municipio" class="form-control" required>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label required">Localidad</label>
                    <input type="text" id="localidad" class="form-control" required>
                  </div>

                  <div class="col-12">
                    <label class="form-label required">Dirección / Domicilio</label>
                    <input type="text" id="direccion" class="form-control" required>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Otra Ubicación</label>
                    <textarea id="ubicacionLibre" class="form-control" rows="2" placeholder="Dirección o domicilio en texto libre..."></textarea>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="pane-cony"></div>
              <div class="tab-pane fade" id="pane-otros"></div>

              <!-- Beneficiario -->
              <div class="tab-pane fade" id="pane-benef">
                <div class="alert soft-alert mb-3" role="alert">
                  <strong>Añade un Beneficiario a tu Prospecto.</strong> Esta sección es <b>OPCIONAL</b>.
                </div>

                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label">CURP</label>
                    <input type="text" id="benefCurp" class="form-control" placeholder="CURP del Beneficiario">
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Nacionalidad</label>
                    <input type="text" id="benefNacionalidad" class="form-control" placeholder="Nacionalidad">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Beneficiario (Nombres y Apellidos)</label>
                    <input type="text" id="benefNombre" class="form-control">
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Parentesco</label>
                    <input type="text" id="benefParentesco" class="form-control" placeholder="Parentesco con el titular">
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Teléfono de contacto</label>
                    <input type="tel" id="benefTelefono" class="form-control">
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Correo</label>
                    <input type="email" id="benefEmail" class="form-control" placeholder="correo@dominio.com">
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Fecha Nacimiento</label>
                    <input type="date" id="benefFechaNac" class="form-control">
                  </div>
                </div>
              </div>

            </div> <!-- /tab-content -->
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn bg-gradient-primary" id="btnGuardar">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Core JS -->
  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="../assets/js/soft-ui-dashboard.min.js?v=1.1.0"></script>

  <!-- NUEVO: SheetJS para Excel -->
  <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

  <script>
    /* ============================ */
    /*  CONFIG                      */
    /* ============================ */
    const WEBHOOK_PROXY  = 'enviar_cliente.php';
    const CHECK_PASSWORD = 'check_password.php';
    const ID_DESARROLLO  = <?= json_encode($idDesarrollo, JSON_UNESCAPED_UNICODE) ?>;
    console.log('ID_DESARROLLO =>', ID_DESARROLLO); // debug

    /* ============================ */
    /*  ESTADO LOCAL (tabla)        */
    /* ============================ */
    const contactos   = <?= json_encode($clientes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const tbody       = document.getElementById('tbodyContactos');
    const estadoVacio = document.getElementById('estadoVacio');
    const inputBuscar = document.getElementById('inputBuscar');
    const modal       = new bootstrap.Modal(document.getElementById('modalContacto'));
    const rowIndex    = document.getElementById('rowIndex');
    const $           = id => document.getElementById(id);

    // *** definir esc ANTES de usarse
    const esc = s => (s || '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[m]));

    function renderTabla(){
      tbody.innerHTML='';
      const q = inputBuscar.value.trim().toLowerCase();

      let n = 0;
      contactos.forEach((c,i)=>{
        const hay = `${c.nombre||''} ${c.curp||''} ${c.telefono||''} ${c.email||''} ${c.direccion||''} ${c.municipio||''} ${c.localidad||''} ${c.estado||''} ${c.pais||''} ${c.notas||''}`.toLowerCase();
        if(q && !hay.includes(q)) return;

        const tr=document.createElement('tr');
        tr.innerHTML=`
          <td>${++n}</td>
          <td class="text-uppercase">${esc(c.nombre)}</td>
          <td>${esc(c.curp)}</td>
          <td>${esc(c.telefono)}</td>
          <td>${esc(c.email||'')}</td>
          <td>${esc([c.direccion,c.codigoPostal].filter(Boolean).join(' '))}</td>
          <td>${esc([c.municipio,c.estado,c.pais].filter(Boolean).join(', '))}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary btn-action me-1" onclick="editarContacto(${i})">
              <i class="fa-solid fa-pen-to-square"></i> Editar
            </button>
            <button class="btn btn-sm btn-outline-danger btn-action" onclick="eliminarContacto(${i})">
              <i class="fa-solid fa-trash"></i> Eliminar
            </button>
          </td>`;
        tbody.appendChild(tr);
      });
      estadoVacio.style.display = tbody.children.length ? 'none':'block';
    }

    function resetForm(){
      const ids = [
        'rowIndex','curp','nacionalidad','nombre','telefono','email',
        'pais','estado','municipio','localidad','direccion','ubicacionLibre',
        'benefCurp','benefNacionalidad','benefNombre','benefParentesco','benefTelefono','benefEmail','benefFechaNac'
      ];
      ids.forEach(id=>{
        const el = document.getElementById(id);
        if(!el) return;
        if(id==='pais'){ el.value='MÉXICO'; } else el.value='';
      });

      document.querySelectorAll('#tabsContacto .nav-link').forEach(x=>x.classList.remove('active'));
      document.querySelectorAll('.tab-pane').forEach(x=>x.classList.remove('show','active'));
      document.querySelector('#tabsContacto .nav-link')?.classList.add('active');
      document.getElementById('pane-rep').classList.add('show','active');
    }

    function openNuevo(){
      resetForm();
      document.getElementById('tituloModal').textContent='Registrar Contacto';
      modal.show();
    }

    function openEditar(ix){
      resetForm();
      const c = contactos[ix]; if(!c) return;
      document.getElementById('tituloModal').textContent='Editar Contacto';
      rowIndex.value = ix;
      for(const k in c){ if($(k)) $(k).value = c[k]; }
      modal.show();
    }

    function collectForm(){
      const o = {
        curp:        $('curp')?.value.trim() || '',
        nacionalidad:$('nacionalidad')?.value.trim() || '',
        nombre:      $('nombre')?.value.trim() || '',
        telefono:    $('telefono')?.value.trim() || '',
        email:       $('email')?.value.trim().toLowerCase() || '',
        pais:        $('pais')?.value.trim() || '',
        estado:      $('estado')?.value.trim() || '',
        municipio:   $('municipio')?.value.trim() || '',
        localidad:   $('localidad')?.value.trim() || '',
        direccion:   $('direccion')?.value.trim() || '',
        ubicacionLibre: $('ubicacionLibre')?.value.trim() || '',
        benefCurp:       $('benefCurp')?.value.trim() || '',
        benefNacionalidad:$('benefNacionalidad')?.value.trim() || '',
        benefNombre:     $('benefNombre')?.value.trim() || '',
        benefParentesco: $('benefParentesco')?.value.trim() || '',
        benefTelefono:   $('benefTelefono')?.value.trim() || '',
        benefEmail:      $('benefEmail')?.value.trim().toLowerCase() || '',
        benefFechaNac:   $('benefFechaNac')?.value.trim() || ''
      };
      o.notas = o.ubicacionLibre;
      return o;
    }

    // Helper: convierte email a minúsculas y a base64 (no url-safe)
    function emailToBase64(email) {
      const raw = (email || '').trim();
      const lower = raw.toLowerCase();

      // validación mínima
      const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(lower);
      if (!ok) throw new Error('Correo inválido. Verifica el formato.');

      // btoa espera latin1; convertimos UTF-8 -> latin1 seguro
      const b64 = btoa(unescape(encodeURIComponent(lower)));
      return { emailNorm: lower, emailB64: b64 };
    }

    function buildPayload(d){
      if(!ID_DESARROLLO){
        throw new Error('Falta ID_DESARROLLO en sesión.');
      }
      // normalizar y codificar correo
      const { emailNorm, emailB64 } = emailToBase64(d.email);

      // Datos del cliente tal como los pide Apphive
      const cliente = {
        id:           emailB64,             // ← ID = email en minúsculas a base64
        Calle:        d.direccion || '',
        CodigoPostal: d.codigoPostal || '',
        Curp:         d.curp || '',
        Email:        emailNorm,            // ← siempre minúsculas
        Estado:       d.estado || '',
        Municipio:    d.municipio || '',
        Nombre:       d.nombre || '',
        Pais:         d.pais || '',
        Telefono:     d.telefono || ''
      };

      // Beneficiario (si no hay datos, se envían cadenas vacías)
      const beneficiario = {
        Curp:       d.benefCurp || '',
        Email:      (d.benefEmail || '').trim().toLowerCase(),
        Nombre:     d.benefNombre || '',
        Parentesco: d.benefParentesco || '',
        Telefono:   d.benefTelefono || ''
      };

      // Payload EXACTO que espera Apphive
      return {
        idDesarrollo: ID_DESARROLLO,
        Cliente: cliente,
        Beneficiario: beneficiario
      };
    }

    async function enviarAlWebhook(payload){
      try{
        const res = await fetch(WEBHOOK_PROXY, {
          method: 'POST',
          headers: { 'Content-Type':'application/json' },
          body: JSON.stringify(payload)
        });
        const status = res.status;
        const text   = await res.text();
        let data; try { data = JSON.parse(text); } catch { data = text; }

        return { ok: res.ok && status >= 200 && status < 300, status, data, payload };
      }catch(err){
        return { ok:false, status:0, data:{ error:String(err) }, payload };
      }
    }

    // Disponibles en el scope global (por si quedó algún nombre viejo)
    window.enviarAlWebhook = enviarAlWebhook;
    window.enviarWebhook   = enviarAlWebhook;

    // (opcional) debug rápido
    console.log('enviarAlWebhook:', typeof window.enviarAlWebhook);
    console.log('enviarWebhook  :', typeof window.enviarWebhook);

    async function guardar(){
      const d = collectForm();

      if(!d.curp || !d.nombre || !d.telefono){
        Swal.fire({ icon:'warning', title:'Completa los campos obligatorios', text:'CURP/RFC, Nombres y Teléfono.' });
        return;
      }
      if(!d.email){
        Swal.fire({ icon:'warning', title:'Falta el correo', text:'Para generar el ID se requiere el correo.' });
        return;
      }
      if(!ID_DESARROLLO){
        Swal.fire({ icon:'error', title:'Falta idDesarrollo', text:'Tu sesión no tiene idDesarrollo. Vuelve a iniciar sesión.' });
        return;
      }

      let payload;
      try{
        payload = buildPayload(d);
      }catch(err){
        Swal.fire({ icon:'error', title:'No se pudo construir el payload', text: String(err.message || err) });
        return;
      }

      // Debug
      console.log('PAYLOAD QUE SE ENVÍA →', payload);

      Swal.fire({ title:'Enviando...', text:'Creando contacto en Apphive', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
      const result = await enviarAlWebhook(payload);
      Swal.close();

      if(result.ok){
        const ix = rowIndex.value!=='' ? +rowIndex.value : -1;

        // actualizar la UI sin perder campos y mostrando al instante
        const uiPatch = {
          id:            payload?.Cliente?.id || (d.email || '').trim().toLowerCase(),
          curp:          d.curp || '',
          nacionalidad:  d.nacionalidad || '',
          nombre:        d.nombre || '',
          telefono:      d.telefono || '',
          email:         (d.email || '').trim().toLowerCase(),
          pais:          d.pais || '',
          estado:        d.estado || '',
          municipio:     d.municipio || '',
          localidad:     d.localidad || '',
          direccion:     d.direccion || '',
          codigoPostal:  d.codigoPostal || '',
          notas:         d.ubicacionLibre || ''
        };

        if(ix>=0){
          contactos[ix] = { ...contactos[ix], ...uiPatch };
        } else {
          contactos.push(uiPatch);
        }

        renderTabla();
        modal.hide();
        Swal.fire({ icon:'success', title:'Se creó con éxito el cliente.', timer:1600, showConfirmButton:false });
      }else{
        console.warn('Respuesta webhook:', result);
        Swal.fire({
          icon:'error',
          title:'No se pudo enviar',
          html:`HTTP ${result.status || '0'}<br><pre style="text-align:left;white-space:pre-wrap">${esc(JSON.stringify(result.data, null, 2))}</pre>`
        });
      }
    }

    // Confirmación de contraseña (si la usas para borrar)
    async function solicitarYValidarPassword() {
      return Swal.fire({
        title: 'Confirma tu contraseña',
        text: 'Por seguridad, escribe la contraseña de tu sesión para continuar.',
        input: 'password',
        inputPlaceholder: 'Contraseña',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: async (password) => {
          if (!password) {
            Swal.showValidationMessage('Escribe tu contraseña');
            return false;
          }
          try {
            const res  = await fetch(CHECK_PASSWORD, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ password })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
              throw new Error('Contraseña incorrecta');
            }
            return true;
          } catch (err) {
            Swal.showValidationMessage(err.message || 'No se pudo validar la contraseña');
            return false;
          }
        },
        allowOutsideClick: () => !Swal.isLoading()
      }).then(r => r.isConfirmed);
    }

    async function eliminarContacto(ix){
      const c=contactos[ix]; if(!c) return;

      const okPass = await solicitarYValidarPassword();
      if (!okPass) return;

      Swal.fire({
        icon:'warning',
        title:'Eliminar contacto',
        html:`¿Eliminar <b>${esc(c.nombre)}</b>?`,
        showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'
      }).then(r=>{
        if(r.isConfirmed){
          // Aquí podrías borrar también en Firebase
          contactos.splice(ix,1);
          renderTabla();
          Swal.fire({icon:'success',title:'Eliminado',timer:1000,showConfirmButton:false});
        }
      });
    }

    /* ============================
       NUEVO: utilidades para exportar Excel
       ============================ */
    function getFilteredContactos() {
      const q = (inputBuscar?.value || '').trim().toLowerCase();
      if (!q) return contactos.slice();
      return contactos.filter(c => {
        const hay = `${c.nombre||''} ${c.curp||''} ${c.telefono||''} ${c.email||''} ${c.direccion||''} ${c.municipio||''} ${c.localidad||''} ${c.estado||''} ${c.pais||''} ${c.notas||''}`.toLowerCase();
        return hay.includes(q);
      });
    }

    function fechaFileStamp() {
      const d = new Date();
      const pad = n => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}`;
    }

    function exportarExcel() {
      const rows = getFilteredContactos();

      const headers = [
        'N°','Nombres y Apellidos','CURP/RFC','Teléfono','Email',
        'Dirección','Ubicación','País','Estado','Municipio','Localidad','Código Postal','ID'
      ];

      const data = rows.map((c, i) => ([
        i + 1,
        c.nombre || '',
        c.curp || '',
        c.telefono || '',
        c.email || '',
        [c.direccion, c.codigoPostal].filter(Boolean).join(' '),
        [c.municipio, c.estado, c.pais].filter(Boolean).join(', '),
        c.pais || '',
        c.estado || '',
        c.municipio || '',
        c.localidad || '',
        c.codigoPostal || '',
        c.id || ''
      ]));

      const aoa = [headers, ...data];

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet(aoa);

      // Anchos de columna aproximados
      ws['!cols'] = [
        { wch: 5 }, { wch: 28 }, { wch: 18 }, { wch: 14 }, { wch: 28 },
        { wch: 30 }, { wch: 26 }, { wch: 10 }, { wch: 16 }, { wch: 18 },
        { wch: 18 }, { wch: 12 }, { wch: 26 }
      ];

      XLSX.utils.book_append_sheet(wb, ws, 'Contactos');
      const nombre = `Contactos_${ID_DESARROLLO || 'desarrollo'}_${fechaFileStamp()}.xlsx`;
      XLSX.writeFile(wb, nombre);
    }

    // Listeners
    document.getElementById('btnNuevo').addEventListener('click', openNuevo);
    document.getElementById('btnGuardar').addEventListener('click', guardar);
    document.getElementById('inputBuscar').addEventListener('input', renderTabla);
    // NUEVO: listener del botón de Excel
    document.getElementById('btnExportExcel').addEventListener('click', exportarExcel);

    renderTabla();
    window.editarContacto=openEditar;
    window.eliminarContacto=eliminarContacto;

    // Evita el warning "aria-hidden" al cerrar el modal con un elemento enfocado
    const modalEl = document.getElementById('modalContacto');
    modalEl.addEventListener('hide.bs.modal', () => {
      try { document.activeElement?.blur(); } catch {}
    });

  </script>
</body>
</html>
