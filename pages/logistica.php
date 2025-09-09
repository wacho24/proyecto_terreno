<?php
// pages/logistica.php
require_once __DIR__ . '/_guard.php';
session_start();

$idDesarrollo = isset($_GET['id']) ? (string)$_GET['id'] : ($_SESSION['idDesarrollo'] ?? '');
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', $idDesarrollo);
if ($idDesarrollo === '') { header('Location: ../index.php'); exit; }
$_SESSION['idDesarrollo'] = $idDesarrollo;

require_once __DIR__ . '/../config/firebase_init.php';

$ROOT_PREFIX = 'projects/proj_8HNCM2DFob/data';

/* ===================== CARGA MANZANAS ===================== */
$desarrolloIdCampo = '';
try {
  $snapInfo = $database->getReference("$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo")->getSnapshot();
  $tmp = $snapInfo->getValue() ?: [];
  $desarrolloIdCampo = $tmp['idDesarrollo'] ?? '';
} catch(Throwable $e){}

$pathManzanas = "$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo/Manzanas";
$snap = $database->getReference($pathManzanas)->getSnapshot();
$rows = $snap->getValue() ?: [];

$manzanas = [];
foreach ($rows as $pushId => $item) {
  $manzanas[] = [
    'id'       => $pushId,
    'manzana'  => $item['NombreManzana'] ?? $pushId,
    'proyecto' => ''
  ];
}

/* ===================== CARGA LOTES ===================== */
$lotes = [];
try {
  $pathLotes = "$ROOT_PREFIX/DesarrollosGenerales/$idDesarrollo/Lotes";
  $snapLotes = $database->getReference($pathLotes)->getSnapshot();
  $rowsLotes = $snapLotes->getValue() ?: [];

  foreach ($rowsLotes as $loteId => $l) {
    // Compatibilidad nombres
    $mf = (float)($l['MedidaFrente']     ?? $l['MedidaFrontal']   ?? $l['MF']  ?? 0);
    $md = (float)($l['MedidaDerecho']    ?? $l['MedidaDerecha']   ?? $l['MCD'] ?? $l['MD'] ?? 0);
    $mi = (float)($l['MedidaIzquierdo']  ?? $l['MedidaIzquierda'] ?? $l['MI']  ?? 0);
    $mp = (float)($l['MedidaFondo']      ?? $l['MedidaPosterior'] ?? $l['MP']  ?? 0);

    $areaCalc = ($md + $mi) * ($mf + $mp);

    // Posible sub-nodo Venta pegado al lote
    $venta = $l['Venta'] ?? null;

    $lotes[] = [
      'id'          => $loteId,
      'manzanaId'   => (string)($l['idManzana'] ?? $l['ManzanaId'] ?? ''),
      'descripcion' => (string)($l['NombreLote'] ?? $l['Descripcion'] ?? ''),
      'costo'       => (float)($l['Costo'] ?? 0),
      'pventa'      => (float)($l['Precio'] ?? $l['PrecioVenta'] ?? 0),
      'nota'        => (string)($l['Nota'] ?? ''),
      'mf'          => $mf,
      'md'          => $md,
      'mi'          => $mi,
      'mp'          => $mp,
      'area'        => (float)($l['Area'] ?? $areaCalc),
      'estado'      => (string)($l['Estatus'] ?? $l['Estado'] ?? 'DISPONIBLE'),
      'venta'       => $venta ? [
        'comprador'   => (string)($venta['Comprador']   ?? ''),
        'fecha'       => (string)($venta['Fecha']       ?? ''),
        'precio'      => (float) ($venta['Precio']      ?? 0),
        'observacion' => (string)($venta['Observacion'] ?? '')
      ] : null,
    ];
  }

  /* ===== MERGE con VentasGenerales (completar detalles de venta) ===== */
  try {
    $snapVG = $database->getReference("$ROOT_PREFIX/VentasGenerales")->getSnapshot();
    $rowsVG = $snapVG->getValue() ?: [];

    $byId = []; $byName = [];
    foreach ($rowsVG as $rid => $row) {
      if (isset($row['idDesarrollo']) && (string)$row['idDesarrollo'] !== (string)$idDesarrollo) continue;

      $ventaNorm = [
        'comprador'   => (string)($row['NombreCliente'] ?? $row['Cliente'] ?? ''),
        'fecha'       => (string)($row['FechaVenta']    ?? $row['Fecha'] ?? ''),
        'precio'      => (float)  ($row['PrecioLote']   ?? $row['Precio'] ?? 0),
        'observacion' => (string)($row['Observacion']   ?? $row['Obs'] ?? '')
      ];

      $loteIdVG   = (string)($row['LoteId']     ?? $row['idLote'] ?? '');
      $loteNameVG = (string)($row['NombreLote'] ?? '');

      if ($loteIdVG   !== '') $byId[$loteIdVG]     = $ventaNorm;
      if ($loteNameVG !== '') $byName[$loteNameVG] = $ventaNorm;
    }

    foreach ($lotes as &$L) {
      // si ya trae 'venta', no la pisamos; si no, buscamos por id o por descripción
      if (!$L['venta']) {
        if (!empty($L['id']) && isset($byId[$L['id']])) {
          $L['venta'] = $byId[$L['id']];
        } elseif (!empty($L['descripcion']) && isset($byName[$L['descripcion']])) {
          $L['venta'] = $byName[$L['descripcion']];
        }
      }
      if ($L['venta'] && strtoupper($L['estado'] ?? '') !== 'VENDIDO') {
        $L['estado'] = 'VENDIDO';
      }
    }
    unset($L);
  } catch(Throwable $eMerge) {
    // silencioso
  }

} catch(Throwable $e){}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Logística | iTrade 3.0</title>

  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <link id="pagestyle" href="../assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .table thead th{ font-weight:700; }
    .search-wrap{ max-width:420px }
    .btn-action{ padding:6px 10px; border-radius:8px }
    .badge-estado{ padding:.35rem .5rem; border-radius:.5rem; font-weight:700; }
    .st-vendido{ background:#ede9fe; color:#5b21b6; }
    .st-disponible{ background:#e6fffa; color:#0f766e; }
    .st-apartado{ background:#fff7ed; color:#c2410c; }
    .modal-header{ background:linear-gradient(90deg,#6a39b6,#7b61ff); color:#fff; }
    .modal-header .btn-close{ filter:invert(1) brightness(200%); opacity:.9; }
    .nav-tabs .nav-link{ color:#6b7280; font-weight:600; }
    .nav-tabs .nav-link.active{ color:#6a39b6 !important; border-bottom:2px solid #6a39b6 !important; }
    .form-label{ font-weight:600; }
  </style>
</head>
<body class="g-sidenav-show bg-gray-100">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-fluid py-4">

      <!-- ===================== MANZANAS ===================== -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between pb-0">
          <div>
            <h6 class="mb-0">Manzanas</h6>
            <small class="text-muted">Desarrollo: <code><?= h($idDesarrollo) ?></code></small>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalNuevaManzana">+ Nuevo</button>
            <div class="search-wrap ms-2">
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="qManzanas" class="form-control" placeholder="Buscar manzana...">
              </div>
            </div>
          </div>
        </div>

        <div class="card-body px-0 pt-3 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th style="width:60px">N°</th>
                  <th>Manzana</th>
                  <th>Nombre Del Proyecto</th>
                  <th class="text-center" style="width:200px">Edición</th>
                </tr>
              </thead>
              <tbody id="tbManzanas"></tbody>
            </table>
            <div id="emptyManzanas" class="text-center text-secondary py-5">No hay manzanas aún.</div>
          </div>
        </div>
      </div>

      <!-- ===================== LOTES ===================== -->
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between pb-0">
          <div>
            <h6 class="mb-0">Lotes</h6>
            <small class="text-muted">Listado de Lotes — Desarrollo: <code><?= h($idDesarrollo) ?></code></small>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm bg-gradient-primary" data-bs-toggle="modal" data-bs-target="#modalLote">
              <i class="fa-solid fa-plus"></i> Nuevo
            </button>
            <div class="search-wrap ms-2">
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="qLotes" class="form-control" placeholder="Buscar por lote, estado...">
              </div>
            </div>
          </div>
        </div>

        <div class="card-body px-0 pt-3 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th style="width:60px">N°</th>
                    <th>Descripción</th>
                    <th class="text-end">P. Venta</th>
                    <th class="text-center">M. Frente (ML)</th>
                    <th class="text-center">M. C. Der. (ML)</th>
                    <th class="text-center">M. C. Izq. (ML)</th>
                    <th class="text-center">M. Fondo (ML)</th>
                    <th class="text-center">Área (m²)</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center" style="width:220px">Edición</th>
                </tr>
              </thead>
              <tbody id="tbLotes"></tbody>
            </table>
            <div id="emptyLotes" class="text-center text-secondary py-5">No hay lotes para mostrar.</div>
          </div>
        </div>
      </div>

    </div>
    <?php require_once __DIR__ . '/footer.php'; ?>
  </main>

  <!-- Hidden -->
  <input type="hidden" id="desarrolloRecordId" value="<?= h($idDesarrollo) ?>">
  <?php if ($desarrolloIdCampo !== ''): ?>
    <input type="hidden" id="desarrolloId" value="<?= h($desarrolloIdCampo) ?>">
  <?php endif; ?>

  <!-- ===================== MODAL: NUEVA MANZANA ===================== -->
  <div class="modal fade" id="modalNuevaManzana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form id="formNuevaManzana" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nueva Manzana</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre de la Manzana</label>
            <input type="text" id="nombreManzana" name="nombreManzana" class="form-control" placeholder="Ej: Manzana A" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===================== MODAL: EDITAR MANZANA ===================== -->
  <div class="modal fade" id="modalEditManzana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form id="formEditManzana" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Editar Manzana</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="manzanaIdEdit">
          <div class="mb-3">
            <label class="form-label">Nombre de la Manzana</label>
            <input type="text" id="nombreManzanaEdit" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===================== MODAL: LOTE ===================== -->
  <div class="modal fade" id="modalLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form id="formLote" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="titleLote">Registrar Nuevo Lote de Terreno</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <ul class="nav nav-tabs" id="tabsLote" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-desc" data-bs-toggle="tab" data-bs-target="#pane-desc" type="button" role="tab">Descripción</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-medidas" data-bs-toggle="tab" data-bs-target="#pane-medidas" type="button" role="tab">Medidas</button>
            </li>
          </ul>

          <div class="tab-content pt-3">
            <!-- Descripción -->
            <div class="tab-pane fade show active" id="pane-desc" role="tabpanel">
              <input type="hidden" id="loteIdEdit" value="">
              <div class="mb-3">
                <label class="form-label">Manzana/Bloque *</label>
                <select id="loteManzana" class="form-select" required>
                  <option value="">Seleccione…</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Denominación del Lote *</label>
                <input type="text" id="loteDescripcion" class="form-control" placeholder="Ej: MANZANA 05 - LOTE 28" required>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Costo Aproximado ($)</label>
                  <input type="number" step="0.01" id="loteCosto" class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Precio Venta Contado ($) *</label>
                  <input type="number" step="0.01" id="lotePrecio" class="form-control" placeholder="0.00" required>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label class="form-label">Estado *</label>
                  <select id="loteEstado" class="form-select" required>
                    <option value="DISPONIBLE">DISPONIBLE</option>
                    <option value="APARTADO">APARTADO</option>
                    <option value="VENDIDO">VENDIDO</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Nota adicional</label>
                  <input type="text" id="loteNota" class="form-control" placeholder="Observación o nota interna">
                </div>
              </div>
            </div>

            <!-- Medidas -->
            <div class="tab-pane fade" id="pane-medidas" role="tabpanel">
              <div class="mb-3">
                <label class="form-label">Medida, Frontal / Norte / NorEste (ML) *</label>
                <input type="number" step="0.01" id="medFrontal" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Medida, Costado Derecho / Este / SurEste (ML) *</label>
                <input type="number" step="0.01" id="medDer" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Medida, Costado Izquierdo / Oeste / NorOeste (ML) *</label>
                <input type="number" step="0.01" id="medIzq" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Medida, Posterior o Fondo / Sur / SurOeste (ML) *</label>
                <input type="number" step="0.01" id="medPost" class="form-control" required>
              </div>

              <div class="row g-3">
                <div class="col-md-6 small text-muted">
                  Fórmula: <code>(Derecho + Izquierdo) × (Frontal + Posterior)</code>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Área del Lote (m²)</label>
                  <input type="number" step="0.01" id="loteArea" class="form-control" readonly>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===================== MODAL: DETALLES DE VENTA ===================== -->
  <div class="modal fade" id="modalDetalleVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalle de venta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <dl class="row mb-0" id="ventaDetalleDL"></dl>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
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

  <script>
    // ======== Estado local ========
    const MANZANAS = <?= json_encode($manzanas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const LOTES    = <?= json_encode($lotes,    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const idDes    = <?= json_encode($idDesarrollo) ?>;

    const $  = (sel)=>document.querySelector(sel);
    const $$ = (sel)=>document.querySelectorAll(sel);

    // Parser robusto
    function parseServerJson(text, statusCode) {
      let out = (text ?? '').replace(/^\uFEFF/, '').trim();
      if (out.startsWith('"') && out.endsWith('"')) { try { out = JSON.parse(out); } catch {} }
      if (typeof out === 'string') {
        const s = out.indexOf('{'); const e = out.lastIndexOf('}');
        if (s !== -1 && e !== -1) out = out.slice(s, e + 1);
      }
      try { return (typeof out === 'string') ? JSON.parse(out) : out; }
      catch { throw new Error(`HTTP ${statusCode}: ${text.slice(0,300)}`); }
    }

    function money(n){ return Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'}); }
    function badgeEstado(e){
      const x = String(e||'').toUpperCase();
      let cls='st-disponible', txt='DISPONIBLE';
      if(x==='VENDIDO') { cls='st-vendido'; txt='VENDIDO'; }
      else if(x==='APARTADO'){ cls='st-apartado'; txt='APARTADO'; }
      return `<span class="badge-estado ${cls}">${txt}</span>`;
    }

    // Fecha robusta
    function fmtDate(v) {
      if (v == null || v === '') return '';
      if (/^\d{13}$/.test(String(v))) return new Date(Number(v)).toLocaleDateString('es-MX');
      if (/^\d{10}$/.test(String(v))) return new Date(Number(v) * 1000).toLocaleDateString('es-MX');
      if (/^\d{4}-\d{2}-\d{2}/.test(String(v))) {
        const d = new Date(String(v));
        return isNaN(d) ? String(v) : d.toLocaleDateString('es-MX');
      }
      if (/^\d{2}\/\d{2}\/\d{4}$/.test(String(v))) {
        const [dd, mm, yyyy] = String(v).split('/');
        const d = new Date(Number(yyyy), Number(mm) - 1, Number(dd));
        return isNaN(d) ? String(v) : d.toLocaleDateString('es-MX');
      }
      const d = new Date(String(v));
      return isNaN(d) ? String(v) : d.toLocaleDateString('es-MX');
    }

    // ---------- Render MANZANAS ----------
    function renderManzanas(){
      const q  = ($('#qManzanas').value || '').toLowerCase().trim();
      const tb = $('#tbManzanas');
      tb.innerHTML = '';

      const lista = MANZANAS.filter(m => {
        if (!q) return true;
        return (`${m.manzana} ${m.proyecto||''}`).toLowerCase().includes(q);
      });

      let n = 0;
      lista.forEach(m => {
        const tr = document.createElement('tr');
        const nombreSafe = (m.manzana || '').replace(/"/g, '&quot;');
        tr.innerHTML = `
          <td>${++n}</td>
          <td>${m.manzana || ''}</td>
          <td>${m.proyecto || ''}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary btn-action me-1 btn-edit-manz"
                    data-id="${m.id}" data-nombre="${nombreSafe}">
              <i class="fa-solid fa-pen-to-square"></i> Editar
            </button>
            <button class="btn btn-sm btn-outline-danger btn-action btn-del-manz"
                    data-id="${m.id}" data-nombre="${nombreSafe}">
              <i class="fa-solid fa-trash"></i> Eliminar
            </button>
          </td>
        `;
        tb.appendChild(tr);
      });

      $('#emptyManzanas').style.display = tb.children.length ? 'none' : 'block';

      // llenar <select> del modal de Lote
      const sel = $('#loteManzana');
      if (sel) {
        sel.innerHTML = '<option value="">Seleccione…</option>';
        MANZANAS.forEach(m=>{
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = m.manzana || m.id;
          sel.appendChild(opt);
        });
      }
    }

    // ---------- Render LOTES ----------
    function renderLotes(){
      const q = ($('#qLotes').value||'').toLowerCase().trim();
      const tb = $('#tbLotes');
      if(!tb) return;
      tb.innerHTML='';
      let n=0;

      LOTES.filter(l=>{
        if(!q) return true;
        const hay = `${l.descripcion||''} ${l.estado||''}`.toLowerCase();
        return hay.includes(q);
      }).forEach(l=>{
        const tr = document.createElement('tr');
        const desc = (l.descripcion||'').replace(/"/g,'&quot;');

        const vendido = String(l.estado||'').toUpperCase()==='VENDIDO';
        const acciones = vendido
          ? `
            <button class="btn btn-sm btn-outline-secondary btn-action me-1 btn-detalle-venta"
                    data-id="${l.id}">
              <i class="fa-solid fa-receipt"></i> Detalles
            </button>`
          : `
            <button class="btn btn-sm btn-outline-primary btn-action me-1 btn-edit-lote"
                    data-id="${l.id}" data-desc="${desc}">
              <i class="fa-solid fa-pen-to-square"></i> Editar
            </button>
            <button class="btn btn-sm btn-outline-danger btn-action btn-del-lote"
                    data-id="${l.id}" data-desc="${desc}">
              <i class="fa-solid fa-trash"></i> Eliminar
            </button>`;

        tr.innerHTML = `
  <td>${++n}</td>
  <td>${l.descripcion || ''}</td>
  <td class="text-end">${money(l.pventa || 0)}</td>
  <td class="text-center">${l.mf ?? ''}</td>
  <td class="text-center">${l.md ?? ''}</td>
  <td class="text-center">${l.mi ?? ''}</td>
  <td class="text-center">${l.mp ?? ''}</td>
  <td class="text-center">${l.area ?? ''}</td>
  <td class="text-center">${badgeEstado(l.estado || 'DISPONIBLE')}</td>
  <td class="text-center">
    ${acciones}
  </td>
`;
        tb.appendChild(tr);
      });
      const empty = document.getElementById('emptyLotes');
      if (empty) empty.style.display = tb.children.length ? 'none' : 'block';
    }

    // ---------- Cálculo de área ----------
    function num(v){ const x=parseFloat(v); return Number.isFinite(x)?x:0; }
    function calcAreaLote(){
      const f = num($('#medFrontal').value);
      const d = num($('#medDer').value);
      const i = num($('#medIzq').value);
      const p = num($('#medPost').value);
      const area = (d + i) * (f + p);
      $('#loteArea').value = area ? area.toFixed(2) : '';
    }
    ['medFrontal','medDer','medIzq','medPost'].forEach(id=>{
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', calcAreaLote);
    });

    // ---------- Buscadores y primer render ----------
    $('#qManzanas').addEventListener('input', renderManzanas);
    if ($('#qLotes')) $('#qLotes').addEventListener('input', renderLotes);
    renderManzanas();
    renderLotes();

    // ========== MANZANAS: Guardar nueva ==========
    document.getElementById('formNuevaManzana').addEventListener('submit', async (e) => {
      e.preventDefault();
      const nombreManzana      = document.getElementById('nombreManzana').value.trim();
      const desarrolloRecordId = (document.getElementById('desarrolloRecordId')?.value || '').trim();
      const desarrolloId       = (document.getElementById('desarrolloId')?.value || '').trim(); // opcional

      if (!nombreManzana)      return Swal.fire({icon:'warning', title:'Escribe el nombre de la manzana.'});
      if (!desarrolloRecordId) return Swal.fire({icon:'warning', title:'Falta el Record id del desarrollo.'});

      try {
        const resp = await fetch('guardar_manzana_backend.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
          credentials: 'same-origin',
          body: new URLSearchParams({ nombreManzana, desarrolloRecordId, desarrolloId, replicarEmpresario: 1 })
        });
        const raw  = await resp.text();
        const data = parseServerJson(raw, resp.status);
        if (!resp.ok || !data || data.status !== 'ok') {
          throw new Error((data && data.message) || `HTTP ${resp.status}`);
        }

        (bootstrap.Modal.getInstance(document.getElementById('modalNuevaManzana'))
          || new bootstrap.Modal(document.getElementById('modalNuevaManzana'))).hide();
        document.getElementById('nombreManzana').value = '';

        MANZANAS.push({ id: data.idManzana, manzana: data.nombreManzana, proyecto: '' });
        renderManzanas();

        Swal.fire({ icon: 'success', title: 'Manzana creada', timer: 1600, showConfirmButton: false });
      } catch (err) {
        console.error(err);
        Swal.fire({icon:'error', title:'Error de red o servidor', text: String(err).slice(0,280)});
      }
    });

    // ========== MANZANAS: Editar/Eliminar ==========
    document.getElementById('tbManzanas').addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button');
      if (!btn) return;

      if (btn.classList.contains('btn-edit-manz')) {
        const id     = btn.dataset.id;
        const nombre = btn.dataset.nombre?.replace(/&quot;/g, '"') || '';
        $('#manzanaIdEdit').value      = id;
        $('#nombreManzanaEdit').value  = nombre;
        (new bootstrap.Modal(document.getElementById('modalEditManzana'))).show();
        return;
      }

      if (btn.classList.contains('btn-del-manz')) {
        const id     = btn.dataset.id;
        const nombre = btn.dataset.nombre?.replace(/&quot;/g, '"') || '';
        const desarrolloRecordId = (document.getElementById('desarrolloRecordId')?.value || '').trim();

        if (!desarrolloRecordId) {
          Swal.fire({icon:'warning', title:'Falta el Record id del desarrollo.'});
          return;
        }

        const { value: password, isConfirmed } = await Swal.fire({
          title: 'Confirma tu contraseña',
          input: 'password',
          inputLabel: `Eliminar “${nombre}”`,
          inputPlaceholder: 'Contraseña',
          inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
          showCancelButton: true,
          confirmButtonText: 'Confirmar',
          cancelButtonText: 'Cancelar'
        });

        if (!isConfirmed) return;
        if (!password) { Swal.fire({icon:'warning', title:'Escribe tu contraseña.'}); return; }

        try {
          const resp = await fetch('eliminar_manzana_backend.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
            credentials: 'same-origin',
            body: new URLSearchParams({ desarrolloRecordId, manzanaId: id, password })
          });
          const raw  = await resp.text();
          const data = parseServerJson(raw, resp.status);
          if (!resp.ok || !data || data.status !== 'ok') {
            throw new Error((data && (data.auth_error_code || data.message)) || `HTTP ${resp.status}`);
          }

          const idx = MANZANAS.findIndex(m => m.id === id);
          if (idx !== -1) MANZANAS.splice(idx, 1);
          renderManzanas();
          Swal.fire({icon:'success', title:'Eliminado', timer:1200, showConfirmButton:false});
        } catch (err) {
          console.error(err);
          Swal.fire({icon:'error', title:'No se pudo eliminar', text:String(err).slice(0,280)});
        }
      }
    });

    document.getElementById('formEditManzana').addEventListener('submit', async (e) => {
      e.preventDefault();
      const desarrolloRecordId = (document.getElementById('desarrolloRecordId')?.value || '').trim();
      const manzanaId          = document.getElementById('manzanaIdEdit').value.trim();
      const nombreManzana      = document.getElementById('nombreManzanaEdit').value.trim();

      if (!nombreManzana) return Swal.fire({icon:'warning', title:'Escribe el nombre.'});
      if (!desarrolloRecordId || !manzanaId) return Swal.fire({icon:'warning', title:'Faltan datos.'});

      try {
        const resp = await fetch('actualizar_manzana_backend.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
          credentials: 'same-origin',
          body: new URLSearchParams({ desarrolloRecordId, manzanaId, nombreManzana })
        });
        const raw  = await resp.text();
        const data = parseServerJson(raw, resp.status);
        if (!resp.ok || !data || data.status !== 'ok') {
          throw new Error((data && data.message) || `HTTP ${resp.status}`);
        }

        const item = MANZANAS.find(m => m.id === manzanaId);
        if (item) item.manzana = nombreManzana;
        renderManzanas();
        (bootstrap.Modal.getInstance(document.getElementById('modalEditManzana'))).hide();
        Swal.fire({icon:'success', title:'Actualizado', timer:1200, showConfirmButton:false});
      } catch (err) {
        console.error(err);
        Swal.fire({icon:'error', title:'No se pudo actualizar', text:String(err).slice(0,280)});
      }
    });

    // ========== LOTES: abrir modal (modo nuevo) ==========
    document.getElementById('modalLote').addEventListener('show.bs.modal', () => {
      $('#titleLote').textContent = 'Registrar Nuevo Lote de Terreno';
      $('#loteIdEdit').value = '';
      $('#loteManzana').value = '';
      $('#loteDescripcion').value = '';
      $('#loteCosto').value = '';
      $('#lotePrecio').value = '';
      $('#loteEstado').value = 'DISPONIBLE';
      $('#loteNota').value = '';
      ['medFrontal','medDer','medIzq','medPost','loteArea'].forEach(id => { const el = document.getElementById(id); if (el) el.value=''; });
    });

    // ========== LOTES: guardar (crear o actualizar) ==========
    document.getElementById('formLote').addEventListener('submit', async (e)=>{
      e.preventDefault();

      const desarrolloRecordId = (document.getElementById('desarrolloRecordId')?.value || '').trim();
      const loteId  = $('#loteIdEdit').value.trim();
      const manzana = $('#loteManzana').value.trim();
      const desc    = $('#loteDescripcion').value.trim();
      const costo   = $('#loteCosto').value.trim();
      const precio  = $('#lotePrecio').value.trim();
      const estado  = $('#loteEstado').value.trim();
      const nota    = $('#loteNota').value.trim();
      const mf = $('#medFrontal').value.trim();
      const md = $('#medDer').value.trim();
      const mi = $('#medIzq').value.trim();
      const mp = $('#medPost').value.trim();
      const area = $('#loteArea').value.trim();

      if (!desarrolloRecordId || !manzana || !desc || !precio || !mf || !md || !mi || !mp) {
        Swal.fire({icon:'warning', title:'Completa los campos obligatorios'}); return;
      }

      const params = new URLSearchParams({
        desarrolloRecordId, manzanaId:manzana, descripcion:desc,
        costo, precio, estado, nota, mf, md, mi, mp, area
      });

      const isEdit = !!loteId;
      let url = isEdit ? 'actualizar_lote_backend.php' : 'guardar_lote_backend.php';
      if (isEdit) params.append('loteId', loteId);

      try {
        const resp = await fetch(url, {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
          credentials:'same-origin',
          body: params
        });
        const raw  = await resp.text();
        const data = parseServerJson(raw, resp.status);
        if (!resp.ok || !data || data.status !== 'ok') {
          throw new Error((data && data.message) || `HTTP ${resp.status}`);
        }

        if (isEdit) {
          const it = LOTES.find(x=>x.id===loteId);
          if (it) {
            it.manzanaId = manzana; it.descripcion=desc; it.costo=Number(costo||0);
            it.pventa=Number(precio||0); it.estado=estado; it.nota=nota;
            it.mf=Number(mf||0); it.md=Number(md||0); it.mi=Number(mi||0); it.mp=Number(mp||0);
            it.area=Number(area||0);
          }
        } else {
          LOTES.push({
            id: data.loteId, manzanaId:manzana, descripcion:desc, costo:Number(costo||0),
            pventa:Number(precio||0), estado, nota, mf:Number(mf||0), md:Number(md||0),
            mi:Number(mi||0), mp:Number(mp||0), area:Number(area||0)
          });
        }

        renderLotes();
        (bootstrap.Modal.getInstance(document.getElementById('modalLote'))).hide();
        Swal.fire({icon:'success', title: isEdit?'Lote actualizado':'Lote creado', timer:1200, showConfirmButton:false});
      } catch (err) {
        console.error(err);
        Swal.fire({icon:'error', title:'No se pudo guardar', text:String(err).slice(0,280)});
      }
    });

    // ========== LOTES: editar / detalles / eliminar ==========
    document.getElementById('tbLotes').addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('button'); if(!btn) return;

      // Editar
      if (btn.classList.contains('btn-edit-lote')) {
        const id = btn.dataset.id;
        const it = LOTES.find(x=>x.id===id); if(!it) return;

        $('#titleLote').textContent = 'Editar Lote';
        $('#loteIdEdit').value = id;
        $('#loteManzana').value = it.manzanaId || '';
        $('#loteDescripcion').value = it.descripcion || '';
        $('#loteCosto').value = it.costo ?? '';
        $('#lotePrecio').value = it.pventa ?? '';
        $('#loteEstado').value = it.estado || 'DISPONIBLE';
        $('#loteNota').value = it.nota || '';
        $('#medFrontal').value = it.mf ?? '';
        $('#medDer').value     = it.md ?? '';
        $('#medIzq').value     = it.mi ?? '';
        $('#medPost').value    = it.mp ?? '';
        $('#loteArea').value   = it.area ?? '';

        (new bootstrap.Modal(document.getElementById('modalLote'))).show();
        return;
      }

      // Detalles de venta
      if (btn.classList.contains('btn-detalle-venta')) {
        const id = btn.dataset.id;
        const it = LOTES.find(x=>x.id===id); if(!it) return;

        const v = it.venta || {};
        const dl = $('#ventaDetalleDL');
        dl.innerHTML = `
          <dt class="col-5">Lote</dt><dd class="col-7">${it.descripcion||id}</dd>
          <dt class="col-5">Comprador</dt><dd class="col-7">${v.comprador||'-'}</dd>
          <dt class="col-5">Fecha</dt><dd class="col-7">${fmtDate(v.fecha||'')}</dd>
          <dt class="col-5">Precio</dt><dd class="col-7">${money(v.precio||0)}</dd>
          <dt class="col-5">Observación</dt><dd class="col-7">${v.observacion||'-'}</dd>
        `;
        (new bootstrap.Modal(document.getElementById('modalDetalleVenta'))).show();
        return;
      }

      // Eliminar
      if (btn.classList.contains('btn-del-lote')) {
        const id = btn.dataset.id;
        const it = LOTES.find(x=>x.id===id); if(!it) return;
        const desarrolloRecordId = (document.getElementById('desarrolloRecordId')?.value || '').trim();

        const { value: password, isConfirmed } = await Swal.fire({
          title: 'Confirma tu contraseña',
          input: 'password',
          inputLabel: `Eliminar lote “${it.descripcion || id}”`,
          inputPlaceholder: 'Contraseña',
          inputAttributes: { autocapitalize:'off', autocorrect:'off' },
          showCancelButton: true,
          confirmButtonText: 'Confirmar',
          cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed || !password) return;

        try {
          const resp = await fetch('eliminar_lote_backend.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
            credentials: 'same-origin',
            body: new URLSearchParams({ desarrolloRecordId, loteId:id, password })
          });
          const raw  = await resp.text();
          const data = parseServerJson(raw, resp.status);
          if (!resp.ok || !data || data.status!=='ok') {
            throw new Error((data && (data.auth_error_code || data.message)) || `HTTP ${resp.status}`);
          }

          const idx = LOTES.findIndex(x=>x.id===id);
          if (idx>-1) LOTES.splice(idx,1);
          renderLotes();
          Swal.fire({icon:'success', title:'Lote eliminado', timer:1200, showConfirmButton:false});
        } catch (err) {
          console.error(err);
          Swal.fire({icon:'error', title:'No se pudo eliminar', text:String(err).slice(0,280)});
        }
      }
    });
  </script>
</body>
</html>
