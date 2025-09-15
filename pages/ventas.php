<?php
// pages/ventas.php
require_once __DIR__ . '/_guard.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Id del desarrollo (desde query o sesión)
$idDesarrollo = isset($_GET['id']) ? (string)$_GET['id'] : ($_SESSION['idDesarrollo'] ?? '');
$idDesarrollo = preg_replace('/[^A-Za-z0-9_\-]/', '', $idDesarrollo);
if ($idDesarrollo === '') { header('Location: ../index.php'); exit; }
$_SESSION['idDesarrollo'] = $idDesarrollo;

// Usuario logueado (asesor)
$asesorUid   = $_SESSION['firebase_uid'] ?? $_SESSION['uid'] ?? '';
$asesorEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$asesorLabel = $asesorEmail ?: ($asesorUid ?: 'Usuario actual');
$asesorValue = $asesorUid ?: $asesorEmail;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ventas | iTrade 3.0</title>

  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <link id="pagestyle" href="../assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{
      --brand1:#6a39b6; --brand2:#7b61ff; --ink:#0f172a; --muted:#64748b;
      --chip-ok:#ede9fe; --chip-warn:#fff7ed; --chip-ink-ok:#5b21b6; --chip-ink-warn:#c2410c;
      --ok:#10b981; --danger:#ef4444;
    }
    body{ color:var(--ink); }
    .search-wrap{ max-width:420px }
    .btn-gradient{
      background:linear-gradient(90deg,var(--brand1),var(--brand2)); color:#fff;
      border:0; border-radius:12px; padding:.55rem .9rem; font-weight:800;
      box-shadow:0 10px 18px rgba(102, 51, 204, .25);
    }
    .btn-gradient:hover{ filter:brightness(1.02); color:#fff; }
    .btn-emerald{ background:var(--ok); color:#fff; border:0; border-radius:12px; padding:.55rem .9rem; font-weight:800; }
    .btn-emerald:hover{ filter:brightness(1.02); color:#fff; }

    .modal-header{
      background:linear-gradient(90deg,var(--brand1),var(--brand2)); color:#fff;
    }
    .modal-header .btn-close{ filter:invert(1) brightness(200%); opacity:.9; }

    /* Tabs */
    .nav-tabs{ border:0 }
    .nav-tabs .nav-link{
      border:0; font-weight:600; color:#64748b;
      border-radius:999px; padding:.45rem .9rem; background:#f1f5f9; margin-right:.4rem;
    }
    .nav-tabs .nav-link.active{
      color:#fff; background:linear-gradient(90deg,var(--brand1),var(--brand2));
      box-shadow:0 6px 14px rgba(107, 70, 193, .25);
    }

    .card-body .table thead th{
      position:sticky; top:0; z-index:2; background:#fff; border-bottom:1px solid #e2e8f0;
    }
    .card-body .table tbody tr:hover{ background:#fafcff; }
    .card-body .table tbody tr:nth-child(odd){ background:#fcfdff; }
    .table .text-truncate{ max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    .chip{
      display:inline-flex; align-items:center; gap:.35rem;
      padding:.25rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700;
      background:#eef2ff; color:#3730a3;
    }
    .chip i{ font-size:.75rem }
    .badge-estado{
      padding:.3rem .65rem; border-radius:999px; font-weight:800; letter-spacing:.02em;
      font-size:.72rem;
    }
    .st-liq{ background:var(--chip-ok); color:var(--chip-ink-ok); }
    .st-pend{ background:var(--chip-warn); color:var(--chip-ink-warn); }

    .small-note{ font-size:.82rem; color:#64748b }
    .form-control, .form-select{ border-radius:12px; padding:.65rem .8rem; }
    .input-group-text{ border-radius:12px; }
    .swal2-popup{ border-radius:18px; padding:1.2rem 1.2rem 1rem; }

    /* === Choices: ancho del contenedor dentro de input-group === */
.input-group > .choices { 
  flex: 1 1 auto;                 /* que ocupe todo el ancho del input-group */
  min-width: 0;                   /* evita desbordes */
}

/* === Haz más anchos los dropdowns de los selects con Choices === */
#vd_cliente + .choices,
#vl_select + .choices,
#vf_cuenta_sel + .choices {
  width: 100% !important;         /* que el control se vea del ancho del input */
}

/* El menú desplegable: más ancho y sin saltos raros */
#vd_cliente + .choices .choices__list--dropdown,
#vl_select + .choices .choices__list--dropdown,
#vf_cuenta_sel + .choices .choices__list--dropdown {
  min-width: 420px !important;    /* ajusta a gusto: 420–520px suele ir bien */
  white-space: nowrap;            /* evita que parta el texto en varias líneas */
  overflow-x: hidden;             /* oculta overflow horizontal si aplica */
}

/* Las opciones dentro del menú: mantener en una línea */
#vd_cliente + .choices .choices__list--dropdown .choices__item,
#vl_select + .choices .choices__list--dropdown .choices__item,
#vf_cuenta_sel + .choices .choices__list--dropdown .choices__item {
  white-space: nowrap;
}

  </style>
</head>
<body class="g-sidenav-show bg-gray-100">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-fluid py-4">
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between pb-0">
          <div>
            <h6 class="mb-0" style="font-weight:800">Ventas</h6>
            <small class="text-muted">Desarrollo: <code><?= h($idDesarrollo) ?></code></small>
          </div>

          <div class="ms-auto d-flex align-items-center gap-2">
            <!-- NUEVO (sin +) -->
            <button type="button" id="btnNuevo" class="btn btn-sm btn-gradient">Nuevo</button>
            <!-- PAGAR -->
            <button type="button" id="btnPagar" class="btn btn-sm btn-emerald">Pagar</button>

            <div class="search-wrap ms-2">
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="qVentas" class="form-control" placeholder="Buscar cliente, lote, contrato…">
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
                  <th>Cliente</th>
                  <th class="text-truncate">Lote-Mz</th>
                  <th>Fecha</th>
                  <th>Tip. V.</th>
                  <th class="text-end">Total</th>
                  <th>Estado</th>
                  <th>Contrato</th>
                  <th class="text-center" style="width:270px">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbVentas"></tbody>
            </table>
            <div id="emptyVentas" class="text-center text-secondary py-5">Sin registros.</div>
          </div>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
  </main>

  <!-- ========== MODAL: REGISTRAR / EDITAR VENTA ========== -->
  <div class="modal fade" id="modalRegistrarVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">
            <i class="fa-solid fa-cart-shopping me-2"></i><span id="modalTitle">Registrar Venta</span>
            <small class="ms-2 fw-normal" style="opacity:.9">— <?= h($idDesarrollo) ?></small>
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-datos" data-bs-toggle="tab" data-bs-target="#pane-datos" type="button" role="tab">Datos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-lotes" data-bs-toggle="tab" data-bs-target="#pane-lotes" type="button" role="tab">Lote(s)</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-fin" data-bs-toggle="tab" data-bs-target="#pane-fin" type="button" role="tab">Detalles de pago</button>
            </li>
          </ul>

          <form id="formVenta" class="tab-content pt-3">
            <input type="hidden" id="editId" value="">

            <!-- Datos -->
            <div class="tab-pane fade show active" id="pane-datos" role="tabpanel">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <select id="vd_cliente" class="form-select"><option value="">Seleccione...</option></select>
                  </div>
                  <div class="small-note mt-1">Puedes buscar por nombre o correo.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Fecha Venta <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="vd_fecha">
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Tipo de Venta <span class="text-danger">*</span></label>
                  <select class="form-select" id="vd_tipo">
                    <option value="">Seleccione...</option>
                    <option value="A CONTADO">A CONTADO</option>
                    <option value="A CREDITO">A CREDITO</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Asesor / Agente <span class="text-danger">*</span></label>
                  <select class="form-select" id="vd_asesor" disabled>
                    <option value="<?= h($asesorValue) ?>"
                            data-uid="<?= h($asesorUid) ?>"
                            data-email="<?= h($asesorEmail) ?>"
                            selected>
                      <?= h($asesorLabel) ?>
                    </option>
                  </select>
                  <div class="small text-muted mt-1">Se usa el usuario actual.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">N° de Contrato</label>
                  <input type="text" class="form-control" id="vd_contrato" value="Por Generar">
                  <div class="small-note mt-1">Si lo dejas en blanco, se genera con timestamp.</div>
                </div>
              </div>
            </div>

            <!-- Lotes -->
            <div class="tab-pane fade" id="pane-lotes" role="tabpanel">
              <div class="mb-3">
                <label class="form-label fw-semibold">Buscar Lote <span class="text-danger">*</span></label>
                <select id="vl_select" class="form-select"><option value="">Seleccione...</option></select>
                <div class="small-note mt-1">Sólo aparecen lotes disponibles (los vendidos se ocultan).</div>
              </div>

              <div class="row g-2 align-items-end mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Precio Venta Final ($) <span class="text-danger">*</span></label>
                  <input type="text" inputmode="decimal" class="form-control" id="vl_precio" value="0">
                </div>
                <div class="col-md-3 text-md-end">
                  <button type="button" class="btn btn-gradient w-100" id="vl_add">
                    <i class="fa-solid fa-download me-1"></i> Agregar
                  </button>
                </div>
              </div>

              <div class="table-responsive border rounded">
                <table class="table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Descripción (Lote - Manzana)</th>
                      <th class="text-end">Precio</th>
                      <th class="text-center" style="width:110px">Quitar</th>
                    </tr>
                  </thead>
                  <tbody id="vl_tbody"></tbody>
                </table>
              </div>

              <div class="d-flex justify-content-end mt-2">
                <div class="fw-semibold">Total Venta: <span class="text-teal" id="vl_total">$ 0.00</span></div>
              </div>

              <div class="mt-3">
                <label class="form-label fw-semibold">Observación sobre la Venta</label>
                <input type="text" class="form-control" id="vl_obs" placeholder="Ingrese Observación, Nota, Glosa...">
              </div>
            </div>

            <!-- Detalles de pago -->
            <div class="tab-pane fade" id="pane-fin" role="tabpanel">
              <div class="row">
                <div class="col-md-3">
                  <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action active" disabled>
                      <i class="fa-solid fa-sack-dollar me-2"></i> Enganche
                    </button>
                  </div>
                </div>
                <div class="col-md-9">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label fw-semibold">Monto Total Enganche ($) <span class="text-danger">*</span></label>
                      <input type="text" inputmode="decimal" class="form-control" id="vf_enganche">
                    </div>

                    <div class="col-12">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="vf_plan">
                        <label class="form-check-label" for="vf_plan">Crear un Plan de Pagos del Enganche</label>
                      </div>
                    </div>

                    <div class="col-12">
                      <hr>
                      <label class="form-label fw-semibold">Modalidad del Enganche</label>
                      <select class="form-select" id="vf_modalidad" disabled>
                        <option value="">Seleccione...</option>
                        <option value="SEMANAL">SEMANAL</option>
                        <option value="QUINCENAL">QUINCENAL</option>
                        <option value="MENSUAL">MENSUAL</option>
                        <option value="BIMESTRAL">BIMESTRAL</option>
                        <option value="TRIMESTRAL">TRIMESTRAL</option>
                        <option value="CUATRIMESTRAL">CUATRIMESTRAL</option>
                        <option value="SEMESTRAL">SEMESTRAL</option>
                        <option value="ANUAL">ANUAL</option>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Cantidad de Pagos</label>
                      <input type="number" class="form-control" id="vf_cuotas" disabled placeholder="###">
                    </div>

                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Fecha de Inicio</label>
                      <input type="date" class="form-control" id="vf_inicio" disabled>
                    </div>

                    <!-- Cuenta (Select) -->
                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Número de cuenta</label>
                      <select id="vf_cuenta_sel" class="form-select">
                        <option value="">Seleccione...</option>
                      </select>
                      <input type="hidden" id="vf_cuenta" value="">
                      <div class="small-note mt-1">Se lee del webhook de <b>Cuentas</b> del desarrollo.</div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label fw-semibold">Fecha de finalización</label>
                      <input type="date" class="form-control" id="vf_fin" disabled readonly>
                      <div class="small-note mt-1">Se calcula automáticamente desde Inicio + Cuotas.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div><!-- /pane-fin -->
          </form>
        </div><!-- /modal-body -->

        <div class="modal-footer">
          <button type="button" class="btn btn-gradient" id="btnGuardarVenta">
            <i class="fa-regular fa-floppy-disk me-1"></i> Guardar
          </button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa-solid fa-rectangle-xmark me-1"></i> Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== MODAL: REGISTRAR PAGO (con imagen comprobante) ========== -->
  <div class="modal fade" id="modalPagar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title"><i class="fa-solid fa-sack-dollar me-2"></i> Registrar Pago — <small class="fw-normal"><?= h($idDesarrollo) ?></small></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <form id="formPagar" class="modal-body" enctype="multipart/form-data">
          <input type="hidden" name="idDesarrollo" value="<?= h($idDesarrollo) ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Venta</label>
            <select class="form-select" id="pg_venta" name="idVenta" required>
              <option value="">Seleccione venta…</option>
            </select>
            <div class="small-note">Si abriste el modal desde una fila, ya viene seleccionada.</div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Lote</label>
              <select class="form-select" id="pg_lote" name="idLote">
                <option value="">(Si la venta tiene varios, elige uno)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Estatus</label>
              <select class="form-select" id="pg_estatus" name="Estatus">
                <option value="CONFIRMADO" selected>CONFIRMADO</option>
                <option value="PENDIENTE">PENDIENTE</option>
                <option value="RECHAZADO">RECHAZADO</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Fecha de pago</label>
              <input type="date" class="form-control" id="pg_fecha" name="FechaPago" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Forma de pago</label>
              <select class="form-select" id="pg_forma" name="FormaPago" required>
                <option value="">Seleccione…</option>
                <option>EFECTIVO</option>
                <option>TRANSFERENCIA</option>
                <option>TARJETA</option>
                <option>DEPÓSITO</option>
                <option>CHEQUE</option>
                <option>OTRO</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Comprobante (imagen o PDF)</label>
              <input type="file" class="form-control" id="pg_comp" name="Comprobante" accept="image/*,.pdf">
              <div class="small-note">Opcional. Formatos: JPG/PNG/WebP/PDF.</div>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Referencia</label>
              <input type="text" class="form-control" id="pg_ref" name="Referencia" placeholder="Folio, referencia o URL">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Total ($)</label>
              <input type="text" inputmode="decimal" class="form-control" id="pg_total" name="Total" required value="0.00">
            </div>
          </div>
        </form>
        <div class="modal-footer">
          <button type="button" class="btn btn-emerald" id="btnGuardarPago"><i class="fa-regular fa-floppy-disk me-1"></i> Guardar pago</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== MODAL: DETALLE/PAGOS DE UNA VENTA ========== -->
  <div class="modal fade" id="modalPagosVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title"><i class="fa-regular fa-eye me-2"></i> Pagos de la Venta</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="pagosVentaBody">Cargando…</div>
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
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

  <script>
    // ====== CONST DE SESIÓN ======
    const ID_DES        = <?= json_encode($idDesarrollo) ?>;
    const ASESOR_UID    = <?= json_encode($asesorUid) ?>;
    const ASESOR_EMAIL  = <?= json_encode($asesorEmail) ?>;

    // ====== Utils ======
    function parseServerJson(text) {
      let s = (text ?? '').replace(/^\uFEFF/, '').trim();
      if (s.startsWith('"') && s.endsWith('"')) { try { s = JSON.parse(s); } catch {} }
      if (typeof s === 'string') { const i = s.indexOf('{'); const j = s.lastIndexOf('}'); if (i >= 0 && j >= 0) s = s.slice(i, j + 1); }
      return (typeof s === 'string') ? JSON.parse(s) : s;
    }
    const money = (n)=> Number(n||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
    const genContratoMs = () => String(Date.now());

    function normalizeMoney(v){
      if (typeof v === 'number') return v;
      let s = (v ?? '').toString().trim();
      s = s.replace(/[^\d.,\-]/g,'');
      if (s.includes(',') && s.includes('.')) {
        if (s.lastIndexOf(',') > s.lastIndexOf('.')) { s = s.replace(/\./g,'').replace(',', '.'); }
        else { s = s.replace(/,/g,''); }
      } else if (s.includes(',')) {
        if (/,(\d{1,2})$/.test(s)) s = s.replace(',', '.'); else s = s.replace(/,/g,'');
      }
      const n = Number(s);
      return Number.isFinite(n) ? n : 0;
    }
    const norm = (s) => String(s||'').toLowerCase().replace(/\s+/g,' ').replace(/\s*[—–-]\s*/g,' - ').trim();

    // Fechas
    const parseDMY = (s) => {
      if (!s) return null;
      if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return new Date(s + 'T00:00:00');
      const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(s.trim());
      if (m) return new Date(`${m[3]}-${m[2]}-${m[1]}T00:00:00`);
      const d = new Date(s);
      return isNaN(+d) ? null : d;
    };
    const fmtDMY = (d) => d ? d.toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'}) : '—';
    const toISO  = (d) => (d ? `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}` : '');

    function addMonths(d,n){ const x=new Date(d.getTime()); const day=x.getDate(); x.setMonth(x.getMonth()+n); if(x.getDate()<day) x.setDate(0); return x; }
    function addDays(d,n){ const x=new Date(d.getTime()); x.setDate(x.getDate()+n); return x; }
    function stepByModalidad(mod, base, steps){
      const M = String(mod||'').toUpperCase();
      const map = {
        'SEMANAL':   (d,k)=>addDays(d, 7*k),
        'QUINCENAL': (d,k)=>addDays(d, 15*k),
        'MENSUAL':   (d,k)=>addMonths(d, 1*k),
        'BIMESTRAL': (d,k)=>addMonths(d, 2*k),
        'TRIMESTRAL':(d,k)=>addMonths(d, 3*k),
        'CUATRIMESTRAL':(d,k)=>addMonths(d, 4*k),
        'SEMESTRAL': (d,k)=>addMonths(d, 6*k),
        'ANUAL':     (d,k)=>addMonths(d,12*k),
      };
      const fn = map[M] || map['MENSUAL'];
      return fn(base, steps);
    }

function estadoBadge(tipo, estatusDb) {
  const t  = String(tipo||'').toUpperCase();
  const st = String(estatusDb||'').toUpperCase();
  // Si la DB ya dice LIQUIDADO, manda LIQUIDADO sin importar el tipo
  const esLiquidado = st === 'LIQUIDADO' || t.includes('CONTADO');
  return `<span class="badge-estado ${esLiquidado?'st-liq':'st-pend'}">
            ${esLiquidado?'LIQUIDADO':'PENDIENTE'}
          </span>`;
}



    // ====== Estado ======
    let LOADED=false;
    let LISTA_CLIENTES=[], LISTA_LOTES=[], LISTA_CUENTAS=[];
    const CARRITO = [];
    const MAP_LOTES = {}, MAP_CLIENTES = {};
    const MAP_LOTES_BY_LABEL = {};
    let choicesClientes = null, choicesLotes = null, choicesCuentas = null;
    let VENTAS = [];
    const modalVenta  = new bootstrap.Modal('#modalRegistrarVenta');
    const modalPagar  = new bootstrap.Modal('#modalPagar');
    const modalPagosV = new bootstrap.Modal('#modalPagosVenta');

    // ====== Tabla ======
    function renderVentas() {
      const tb = document.getElementById('tbVentas');
      const empty = document.getElementById('emptyVentas');
      const q = (document.getElementById('qVentas')?.value || '').toLowerCase().trim();
      tb.innerHTML = '';
      let n = 0;

      (VENTAS || []).filter(v => {
        if (!q) return true;
        const hay = `${v.cliente||''} ${v.lote||''} ${v.fecha||''} ${v.tipo||''} ${v.contrato||''}`.toLowerCase();
        return hay.includes(q);
      }).forEach(v => {
        const tipoChip = String(v.tipo||'').toUpperCase().includes('CREDITO')
          ? `<span class="chip"><i class="fa-solid fa-clock"></i> CRÉDITO</span>`
          : `<span class="chip" style="background:#dcfce7;color:#065f46"><i class="fa-solid fa-bolt"></i> CONTADO</span>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${++n}</td>
          <td>${v.cliente||''}</td>
          <td class="text-truncate" title="${v.lote||''}">${v.lote||''}</td>
          <td>${v.fecha||''}</td>
          <td>${tipoChip}</td>
          <td class="text-end">${money(v.total||0)}</td>
          <td>${estadoBadge(v.tipo, v.estatus)}</td>
          <td>${v.contrato||''}</td>
          <td class="text-center">
            <div class="btn-group">
              <button class="btn btn-sm btn-outline-secondary" title="Ver" data-action="ver" data-id="${v.id}">
                <i class="fa-regular fa-eye"></i>
              </button>
              <button class="btn btn-sm btn-outline-primary" title="Editar" data-action="edit" data-id="${v.id}">
                <i class="fa-regular fa-pen-to-square"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" title="Eliminar" data-action="del" data-id="${v.id}">
                <i class="fa-regular fa-trash-can"></i>
              </button>
              <button class="btn btn-sm btn-outline-warning" title="Contrato" data-action="contrato" data-id="${v.id}">
                <i class="fa-regular fa-file-lines"></i>
              </button>
              <button class="btn btn-sm btn-outline-success" title="Pagos" data-action="pagos" data-id="${v.id}">
                <i class="fa-solid fa-coins"></i>
              </button>
            </div>
          </td>
        `;
        tb.appendChild(tr);
      });

      if (empty) empty.style.display = tb.children.length ? 'none' : 'block';
    }

    async function cargarVentas() {
      const tb = document.getElementById('tbVentas');
      const empty = document.getElementById('emptyVentas');
      if (tb) tb.innerHTML = `<tr><td colspan="9" class="text-center py-4">Cargando…</td></tr>`;
      if (empty) empty.style.display = 'none';

      try {
        const res = await fetch('ventas_list.php?id=' + encodeURIComponent(ID_DES), { cache:'no-store' });
        const txt = await res.text();
        const data = parseServerJson(txt);
        if (!res.ok || !data || data.ok !== true) throw new Error((data && data.error) || ('HTTP '+res.status));
        VENTAS = data.items || [];
      } catch (e) {
        console.error(e);
        VENTAS = [];
      }
      renderVentas();
    }

    // vendidos a partir de listado
    function lotesVendidosIdSet(allowIds = []) {
      const allow = new Set((allowIds||[]).map(x => String(x)));
      const vendidos = new Set();

      (VENTAS || []).forEach(v => {
        if (Array.isArray(v.lotes) && v.lotes.length) {
          v.lotes.forEach(it => { const id=String(it?.id||''); if (id && !allow.has(id)) vendidos.add(id); });
          return;
        }
        String(v.lote||'').split(',').map(s=>norm(s)).forEach(lbl=>{
          const id = MAP_LOTES_BY_LABEL[lbl];
          if (id && !allow.has(id)) vendidos.add(id);
        });
      });
      return vendidos;
    }

    // ====== Select Lotes ======
    function buildLoteSelect(allowIds = []) {
      const allow = new Set((allowIds||[]).map(x => String(x)));
      const vendidos = lotesVendidosIdSet(allowIds);

      if (choicesLotes) { choicesLotes.destroy(); choicesLotes = null; }
      const selLot = document.getElementById('vl_select');
      if (!selLot) return;
      selLot.innerHTML = '<option value="">Seleccione...</option>';

      (LISTA_LOTES || []).forEach(l => {
        const lid = String(l.id || '');
        if (!lid) return;
        if (vendidos.has(lid) && !allow.has(lid)) return;
        const opt = document.createElement('option');
        opt.value = lid;
        opt.textContent = l.label || lid;
        selLot.appendChild(opt);
      });

      choicesLotes = new Choices('#vl_select', {
        shouldSort:false, searchResultLimit:100, itemSelectText:'', removeItemButton:false, allowHTML:true, searchPlaceholderValue:'Buscar...'
      });

      selLot.value = '';
      try { choicesLotes.removeActiveItems(); } catch {}
    }
 // --- Prefill del precio cuando se elige un lote ---
function setPrecioDesdeLote(loteId){
  const inPrecio = document.getElementById('vl_precio');
  if (!inPrecio) return;

  const lot = MAP_LOTES[String(loteId)] || null;
  if (!lot) { inPrecio.value = ''; return; }

  // Busca el campo correcto de precio
  const raw =
    lot.precio ?? lot.pventa ?? lot.Precio ?? lot.PrecioVenta ?? 0;

  inPrecio.value = Number(raw || 0);
}

// Evento: cuando elijo un lote en el select
document.getElementById('vl_select')?.addEventListener('change', (e)=>{
  setPrecioDesdeLote(e.target.value);
});

    // ====== Cargar catálogos (Clientes/Lotes) ======
    async function cargarFuentes(allowLotIds = []) {
      if (!LOADED) {
        const res = await fetch('ventas_sources.php?id=' + encodeURIComponent(ID_DES), { cache:'no-store' });
        const txt = await res.text();
        const data = parseServerJson(txt);
        if (!res.ok || !data || data.ok !== true) throw new Error((data && data.error) || ('HTTP '+res.status));
        LISTA_CLIENTES = data.clientes || [];
        LISTA_LOTES    = data.lotes    || [];
        LISTA_LOTES.forEach(l => {
          MAP_LOTES[l.id] = l;
          MAP_LOTES_BY_LABEL[norm(l.label || l.id)] = l.id;
        });
        LISTA_CLIENTES.forEach(c => MAP_CLIENTES[c.id] = c);
        LOADED = true;
      }

      if (choicesClientes) choicesClientes.destroy();
      const selCli = document.getElementById('vd_cliente');
      selCli.innerHTML = '<option value="">Seleccione...</option>';
      LISTA_CLIENTES.forEach(c => {
        const label = c.nombre ? `${c.nombre}${c.email ? ' — '+c.email : ''}` : (c.email || c.telefono || c.id);
        const opt = document.createElement('option'); opt.value = c.id; opt.textContent = label; selCli.appendChild(opt);
      });
      choicesClientes = new Choices('#vd_cliente', {
        shouldSort:false, searchResultLimit:50, itemSelectText:'', removeItemButton:false, allowHTML:true, searchPlaceholderValue:'Buscar...'
      });

      buildLoteSelect(allowLotIds);
    }

    // ====== Cuentas (Select) ======
    async function cargarCuentas(){
      try{
        const res = await fetch('cuentas_list.php?id='+encodeURIComponent(ID_DES), {cache:'no-store'});
        const data = parseServerJson(await res.text());
        if(!res.ok || !data || data.ok!==true) throw new Error((data && data.error) || ('HTTP '+res.status));
        LISTA_CUENTAS = data.items || [];
      }catch(err){
        console.error('cuentas', err);
        LISTA_CUENTAS = [];
      }
      buildCuentaSelect();
    }
    function buildCuentaSelect(preferId=''){
      const sel = document.getElementById('vf_cuenta_sel');
      const hidden = document.getElementById('vf_cuenta');
      if (!sel) return;

      if (choicesCuentas) { choicesCuentas.destroy(); choicesCuentas=null; }
      sel.innerHTML = '<option value="">Seleccione...</option>';

      (LISTA_CUENTAS||[]).forEach(c=>{
        const opt = document.createElement('option');
        opt.value = c.id || '';
        opt.setAttribute('data-id', c.id || '');
        opt.textContent = `${c.banco||'Banco'} (${c.clabe||''}) — ${c.beneficiario||''}`;
        sel.appendChild(opt);
      });

      choicesCuentas = new Choices('#vf_cuenta_sel', {
        shouldSort:false, searchResultLimit:100, itemSelectText:'', removeItemButton:false,
        allowHTML:false, searchPlaceholderValue:'Buscar...'
      });

      const syncHidden = ()=>{
        const id = sel.value || '';
        const item = (LISTA_CUENTAS||[]).find(x=>String(x.id)===String(id));
        const label = item ? `${item.banco||''} — ${item.beneficiario||''} — ${item.clabe||''}` : '';
        hidden.value = label;
        hidden.dataset.id = id;
      };
      sel.addEventListener('change', syncHidden);
      if (preferId){ sel.value = preferId; try{ choicesCuentas.setChoiceByValue(preferId); }catch{} }
      syncHidden();
    }

    function renderCarrito(){
      const tb = document.getElementById('vl_tbody');
      tb.innerHTML = '';
      let total = 0;
      CARRITO.forEach((it,ix)=>{
        total += Number(it.precio||0);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${(it.label||it.id)}</td>
          <td class="text-end">${money(it.precio||0)}</td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" data-ix="${ix}" title="Quitar">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>`;
        tb.appendChild(tr);
      });
      document.getElementById('vl_total').textContent = money(total);
    }

    function resetForm(){
      document.getElementById('formVenta').reset();
      document.getElementById('editId').value = '';
      document.getElementById('vd_contrato').value = genContratoMs();
      const cta = document.getElementById('vf_cuenta'); cta.value=''; cta.dataset.id='';
      CARRITO.splice(0, CARRITO.length);
      renderCarrito();
      document.getElementById('modalTitle').textContent = 'Registrar Venta';
      try { choicesLotes?.removeActiveItems(); } catch {}
      const selLot = document.getElementById('vl_select'); if (selLot) selLot.value = '';
      buildCuentaSelect(); // limpia el select
      recomputeFin();
    }

    function addLote(){
      const selLot = document.getElementById('vl_select');
      const lotId  = (selLot.value || '').trim();
      if (!lotId) { Swal.fire('Seleccione un lote','','warning'); return; }
      const lot = MAP_LOTES[lotId]; if (!lot) { Swal.fire('Lote inválido','','error'); return; }
      const precio = normalizeMoney(document.getElementById('vl_precio').value);
      if (!precio || precio <= 0) { Swal.fire('Escribe un precio válido','','warning'); return; }
      if (CARRITO.some(x => x.id === lotId)) { Swal.fire('Ese lote ya está en el carrito','','info'); return; }
      CARRITO.push({ id: lotId, label: (lot.label||lotId), precio });
      renderCarrito();
    }
    document.addEventListener('click', (ev)=>{
      const del = ev.target.closest('#vl_tbody button[data-ix]');
      if (del) { const ix = +del.dataset.ix; if (ix>=0 && ix < CARRITO.length) { CARRITO.splice(ix,1); renderCarrito(); } }
    });
// === WEBHOOK APPHIVE ===
function buildFechasPago(modalidad, cuotas, inicioISO, montoPorCuota){
  const out = {};
  const n = Number(cuotas||0);
  if (!modalidad || !n || !inicioISO) return out;
  let base = new Date(inicioISO + 'T00:00:00');

  for (let i=0; i<n; i++){
    let f;
    switch((modalidad||'').toUpperCase()){
      case 'SEMANAL':        f = addDays(base, 7*i); break;
      case 'QUINCENAL':      f = addDays(base, 15*i); break;
      case 'MENSUAL':        f = addMonths(base, 1*i); break;
      case 'BIMESTRAL':      f = addMonths(base, 2*i); break;
      case 'TRIMESTRAL':     f = addMonths(base, 3*i); break;
      case 'CUATRIMESTRAL':  f = addMonths(base, 4*i); break;
      case 'SEMESTRAL':      f = addMonths(base, 6*i); break;
      case 'ANUAL':          f = addMonths(base,12*i); break;
      default:               f = addMonths(base, 1*i);
    }
    const key = toISO(f);
    out[key] = { Fecha: key, Monto: Number(montoPorCuota||0) };
  }
  return out;
}

// Construye el payload EXACTO para Apphive
function buildWebhookPayload({ idVenta, idDesarrollo, clienteId, clienteLabel, lotes, fechaVentaISO }){
  // Lote a enviar: si hay varios, Apphive espera un objeto; tomamos el primero.
  const lot0 = (lotes && lotes[0]) ? lotes[0] : {};

  // Cuenta bancaria (desde el select de cuentas)
  const cuentaSel = document.getElementById('vf_cuenta_sel');
  const ctaId  = (cuentaSel?.value||'').trim();
  const hidden = document.getElementById('vf_cuenta');
  const ctaStr = (hidden?.value||'').trim(); // "Banco — Beneficiario — CLABE"
  const [banco='', beneficiario='', clabe=''] = ctaStr.split('—').map(s=>String(s||'').trim());

  // Detalles del enganche / plan
  const plan       = !!document.getElementById('vf_plan')?.checked;
  const enganche   = normalizeMoney(document.getElementById('vf_enganche')?.value||0);
  const modalidad  = document.getElementById('vf_modalidad')?.value || '';
  const cuotas     = Number(document.getElementById('vf_cuotas')?.value||0);
  const inicioISO  = document.getElementById('vf_inicio')?.value || '';
  const finISO     = document.getElementById('vf_fin')?.value || '';
  const montoPorCuota = plan && cuotas>0 ? (enganche / cuotas) : 0;

  const FechasPago = plan
    ? buildFechasPago(modalidad, cuotas, inicioISO, montoPorCuota)
    : {}; // si no hay plan, lo mandamos vacío

  // Vendedor: saco lo que ya tienes en sesión (email/uid)
  const vendedorId   = ASESOR_UID || ASESOR_EMAIL || '';
  const vendedorName = ASESOR_EMAIL || 'Usuario actual';

  // Foto de desarrollo si la tienes en tu catálogo de lotes
  const foto = (MAP_LOTES[lot0.id]?.foto || MAP_LOTES[lot0.id]?.FotoDesarrollo || '');

  return {
    ids: {
      idVenta: String(idVenta||''),
      idDesarrollo: String(idDesarrollo||'')
    },
    Lote: {
      id: String(lot0.id||''),
      NombreLote: String(lot0.label||''),
      PrecioLote: Number(lot0.precio||0),
      idManzana: String(MAP_LOTES[lot0.id]?.idManzana || 'idManzana'),
      FotoDesarrollo: String(foto||'')
    },
    CuentaBancaria: {
      id: String(ctaId||''),
      Banco: banco,
      CLABE: clabe,
      Beneficiario: beneficiario
    },
    Vendedor: {
      id: String(vendedorId||''),
      NombreVendedor: String(vendedorName||'')
    },
    DetallesVenta: {
      Enganche: Number(enganche||0),
      EstatusFiniquitado: plan ? 'Pendiente' : 'Liquidado',
      FechaFinalizacion: String(finISO||''),
      FechaInicio: String(inicioISO||''),
      FechaVenta: String(fechaVentaISO||''),
      ModalidadPagos: plan ? String(modalidad||'Mensual') : ''
    },
    Cliente: {
      idCliente: String(clienteId||''),
      NombreCliente: String(clienteLabel||clienteId||'')
    },
    FechasPago // objeto con claves YYYY-MM-DD
  };
}

// POST JSON al PHP que reenvía al Webhook
async function postToApphive(payload){
  const res = await fetch('enviar_webhook.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const txt = await res.text();
  let data;
  try { data = parseServerJson(txt); } catch { data = null; }
  if (!res.ok || !data || data.status === 'error'){
    throw new Error((data?.message || data?.apphive_raw || ('HTTP '+res.status)).toString().slice(0,250));
  }
  return data;
}

    // ====== Guardar (crear / editar) ======
  // ====== Guardar (crear / editar) ======
async function guardarVenta(){
  const clienteId = (document.getElementById('vd_cliente').value||'').trim();
  const fecha     = (document.getElementById('vd_fecha').value||'').trim();
  const tipo      = (document.getElementById('vd_tipo').value||'').trim();
  let   contrato  = (document.getElementById('vd_contrato').value||'').trim();
  const obs       = (document.getElementById('vl_obs').value||'').trim();
  const editId    = (document.getElementById('editId').value||'').trim();

  if (!clienteId || !fecha || !tipo){ Swal.fire('Completa los campos obligatorios','','warning'); return; }
  if (CARRITO.length === 0){ Swal.fire('Agrega al menos un lote','','warning'); return; }

  if (!contrato || /^por\s*generar$/i.test(contrato)) {
    contrato = genContratoMs();
    document.getElementById('vd_contrato').value = contrato;
  }

  const clienteLabel = (() => {
    const c = MAP_CLIENTES[clienteId];
    return c ? (c.nombre || c.email || c.telefono || c.id) : clienteId;
  })();

  // (ya tienes esto) construir payload para tu propio backend
  const ctaEl = document.getElementById('vf_cuenta');
  const payload = {
    idDesarrollo: ID_DES,
    cliente: clienteId,
    clienteLabel,
    fecha, tipo, contrato,
    asesorId: ASESOR_UID || '',
    asesorRef: ASESOR_EMAIL || ASESOR_UID || '',
    lotes: CARRITO.map(x => ({ id: x.id, label: x.label, precio: Number(x.precio||0) })),
    obs,
    enganche: {
      total: normalizeMoney(document.getElementById('vf_enganche').value),
      plan: !!document.getElementById('vf_plan').checked,
      modalidad: document.getElementById('vf_modalidad').value||'',
      cuotas: Number(document.getElementById('vf_cuotas').value||0),
      inicio: document.getElementById('vf_inicio').value||'',
      cuenta: (ctaEl.value||'').trim(),
      cuentaId: ctaEl.dataset.id || '',
      fin: document.getElementById('vf_fin').value||'',
    }
  };

  const url  = editId ? 'venta_editar.php' : 'registrar_venta_backend.php';
  const body = editId ? JSON.stringify({ idVenta: editId, ...payload }) : JSON.stringify(payload);

  // 1) guardas en tu backend (si aplica)
  const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body });
  let data; try { data = parseServerJson(await res.text()); } catch { data = null; }

  if (res.ok && data && data.ok) {
    // ID VENTA (si tu backend lo devuelve, úsalo; si no, usa contrato/timestamp)
    const idVenta = data.idVenta || editId || contrato;

    // 2) arma payload exacto para Apphive
    const payloadApphive = buildWebhookPayload({
      idVenta,
      idDesarrollo: ID_DES,
      clienteId,
      clienteLabel,
      lotes: CARRITO,
      fechaVentaISO: fecha
    });

    try{
      // 3) envía DIRECTO a Apphive (via enviar_webhook.php)
      const resp = await postToApphive(payloadApphive);
      console.log('Webhook OK:', resp);

      Swal.fire({icon:'success', title: editId?'Venta actualizada y enviada':'Venta registrada y enviada', timer:1600, showConfirmButton:false});
      modalVenta.hide();
      await cargarVentas();

    }catch(err){
      console.error('Webhook error:', err);
      Swal.fire({icon:'warning', title:'Guardado, pero no se pudo enviar a Apphive', text:String(err).slice(0,280)});
      modalVenta.hide();
      await cargarVentas();
    }
  } else {
    Swal.fire({icon:'error', title:'No se pudo guardar', text: (data?.error || ('HTTP '+res.status))});
  }
}

    // ====== Ver (con plan) ======
    async function verVenta(id){
      const v = (VENTAS || []).find(x => x.id === id);
      if (!v) return;
      const esCredito = String(v.tipo||'').toUpperCase().includes('CREDITO');

      let planHtml = '';
      if (esCredito) {
        try {
          const res = await fetch('venta_detalle.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ idVenta: id })
          });
          const data = parseServerJson(await res.text());
          if (res.ok && data?.ok) {
            const det = data.venta || {};
            planHtml = `
              <div class="mt-2 p-2 rounded" style="background:#f8fafc">
                <div><b>Plan de pagos:</b> ${det.ModalidadPagos || '—'} ${det.CantidadCuotas?`· ${det.CantidadCuotas} cuotas`:''}</div>
                <div><b>Inicio:</b> ${det.FechaInicio || '—'} · <b>Fin:</b> ${det.FechaFinalizacion || '—'}</div>
              </div>
            `;
          }
        } catch (e) { /* opcional */ }
      }

      const estado = estadoBadge(v.tipo);
      Swal.fire({
        title: '<span style="font-weight:800">Detalle de Venta</span>',
        html: `
          <div class="text-start" style="font-size:14px">
            <div class="mb-2"><b>Cliente:</b> ${v.cliente||''}</div>
            <div class="mb-2"><b>Fecha de inicio:</b> ${v.fecha||''}</div>
            <div class="mb-2"><b>Tipo:</b> ${v.tipo||''} &nbsp; ${estado}</div>
            <div class="mb-2"><b>Total:</b> ${money(v.total||0)}</div>
            <div class="mb-2"><b>Lote(s):</b>
              <div style="background:#f8fafc;padding:8px;border-radius:8px">${v.lote||''}</div>
            </div>
            ${planHtml}
            <div class="mb-2"><b>Contrato:</b> ${v.contrato||''}</div>
          </div>
        `,
        confirmButtonText: 'Cerrar',
        width: 650
      });
    }

    // ====== Cargar una venta en el modal (Editar) ======
    async function abrirEditar(id){
      if (!VENTAS.length) await cargarVentas();
      resetForm();
      document.getElementById('modalTitle').textContent = 'Editar Venta';

      const v = (VENTAS || []).find(x => x.id === id);
      if (!v) { Swal.fire('No se encontró la venta','','error'); return; }

      let allowIds = [];
      if (Array.isArray(v.lotes) && v.lotes.length) {
        allowIds = v.lotes.map(it => it.id);
      } else if (typeof v.lote === 'string' && v.lote.trim() !== '') {
        allowIds = v.lote.split(',').map(s => MAP_LOTES_BY_LABEL[norm(s)]).filter(Boolean);
      }

      await cargarFuentes(allowIds);
      await cargarCuentas();

      document.getElementById('editId').value = id;
      document.getElementById('vd_fecha').value = v.fecha || '';
      document.getElementById('vd_tipo').value  = v.tipo  || '';
      document.getElementById('vd_contrato').value =
        (v.contrato && v.contrato.trim() !== '') ? v.contrato : genContratoMs();

      if (v.clienteId && MAP_CLIENTES[v.clienteId]) {
        document.getElementById('vd_cliente').value = v.clienteId;
      }

      if (Array.isArray(v.lotes) && v.lotes.length) {
        CARRITO.splice(0, CARRITO.length, ...v.lotes.map(it => ({
          id: it.id, label: (it.label||it.id), precio: Number(it.precio||0)
        })));
      } else if (typeof v.lote === 'string' && v.lote.trim() !== '') {
        const parts = v.lote.split(',').map(s => s.trim()).filter(Boolean);
        CARRITO.splice(0, CARRITO.length, ...parts.map(lbl => {
          const id = MAP_LOTES_BY_LABEL[norm(lbl)] || lbl;
          const lot = MAP_LOTES[id];
          return { id, label: (lot?.label || lbl), precio: Number(lot?.precio || 0) };
        }));
      }
      renderCarrito();
const selLot = document.getElementById('vl_select');
if (selLot && selLot.value) setPrecioDesdeLote(selLot.value);

      buildCuentaSelect();
      modalVenta.show();
    }

    // ====== Eliminar ======
    async function eliminarVenta(id){
      const v = (VENTAS || []).find(x => x.id === id);
      if (!v) return;
      const c = await Swal.fire({
        icon:'warning',
        title:'Eliminar venta',
        html:`¿Eliminar la venta de <b>${v.cliente||''}</b> del <b>${v.fecha||''}</b>?`,
        showCancelButton:true, confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
      });
      if (!c.isConfirmed) return;

      const res = await fetch('venta_eliminar.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ idVenta: id, idDesarrollo: ID_DES })
      });
      let data; try{ data = parseServerJson(await res.text()); }catch{ data=null; }

      if (res.ok && data && data.ok) {
        Swal.fire({icon:'success', title:'Venta eliminada', timer:1200, showConfirmButton:false});
        await cargarVentas();
      } else {
        Swal.fire({icon:'error', title:'No se pudo eliminar', text:(data?.error||('HTTP '+res.status))});
      }
    }

    // ====== Contrato (DOCX/PDF) ======
    async function generarContrato(id, formato = 'docx') {
      const v = (VENTAS || []).find(x => x.id === id);
      if (!v) { Swal.fire({icon:'error', title:'No se encontró la venta'}); return; }

      const payload = { idVenta: id, idDesarrollo: ID_DES, formato, venta: v };

      try {
        const res = await fetch('contrato_generar.php', {
          method: 'POST',
          headers: { 'Content-Type':'application/json' },
          body: JSON.stringify(payload)
        });

        const txt  = await res.text();
        const theData = parseServerJson(txt);
        if (!res.ok || !theData || !theData.ok) throw new Error((theData && theData.error) || ('HTTP ' + res.status));

        const urlDocx = theData.url_docx || theData.url || '';
        const urlPdf  = theData.url_pdf  || '';
        const primaryUrl   = (formato === 'pdf' && urlPdf) ? urlPdf : urlDocx;
        const primaryLabel = (formato === 'pdf' && urlPdf) ? 'PDF'   : 'DOCX';

        Swal.fire({
          icon: 'success',
          title: 'Contrato generado',
          html: `
            <div class="text-start">
              <p>Tu contrato está listo:</p>
              <p>
                <a class="btn btn-sm btn-gradient" href="${primaryUrl}" target="_blank" rel="noopener">
                  Descargar ${primaryLabel}
                </a>
              </p>
              <hr>
              <div class="d-flex gap-2">
                <button id="dlDocx" class="btn btn-light btn-sm">DOCX</button>
                <button id="dlPdf"  class="btn btn-light btn-sm">PDF</button>
              </div>
            </div>
          `,
          didOpen: () => {
            const btnDocx = document.getElementById('dlDocx');
            const btnPdf  = document.getElementById('dlPdf');
            if (btnDocx) {
              if (urlDocx) btnDocx.addEventListener('click', ()=> generarContrato(id, 'docx'));
              else { btnDocx.disabled = true; btnDocx.classList.add('disabled'); }
            }
            if (btnPdf)  {
              if (urlPdf) btnPdf.addEventListener('click', ()=> generarContrato(id, 'pdf'));
              else { btnPdf.disabled = true; btnPdf.classList.add('disabled'); }
            }
          }
        });
      } catch (err) {
        Swal.fire({icon:'error', title:'Error', text:String(err).slice(0,280)});
      }
    }

    // ====== PAGOS ======

    // Rellena el select de ventas (para el modal de pagar)
    function fillVentasSelect(select, preferId = ''){
      select.innerHTML = '<option value="">Seleccione venta…</option>';
      (VENTAS||[]).forEach(v=>{
        const esCredito = String(v.tipo||'').toUpperCase().includes('CREDITO');
        if (!esCredito) return; // solo crédito
        const opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = `${v.cliente||'Cliente'} — ${v.contrato||''} — ${money(v.total||0)}`;
        select.appendChild(opt);
      });
      if (preferId){ select.value = preferId; }
      buildLotesDeVentaEnPagar(preferId);
    }

    // Cuando elijo una venta para pagar, cargar sus lotes
    function buildLotesDeVentaEnPagar(ventaId){
      const sel = document.getElementById('pg_lote');
      sel.innerHTML = '<option value="">(Si la venta tiene varios, elige uno)</option>';
      if (!ventaId) return;
      const v = (VENTAS||[]).find(x=>x.id===ventaId);
      if (!v) return;

      if (Array.isArray(v.lotes) && v.lotes.length){
        v.lotes.forEach(it=>{
          const opt=document.createElement('option');
          opt.value = it.id || '';
          opt.textContent = `${it.label||it.id} — ${money(it.precio||0)}`;
          sel.appendChild(opt);
        });
      }else if (v.lote){
        const opt=document.createElement('option');
        opt.value = v.lote; opt.textContent = v.lote;
        sel.appendChild(opt);
      }
    }

    // Abre modal de pagar
    function openModalPagar(preferVentaId=''){
      const f = document.getElementById('formPagar');
      f.reset();
      document.getElementById('pg_fecha').value = toISO(new Date());
      fillVentasSelect(document.getElementById('pg_venta'), preferVentaId);
      modalPagar.show();
    }

    // Guardar pago (con archivo)
async function guardarPago(){
  const f = document.getElementById('formPagar');
  const ventaId = document.getElementById('pg_venta').value;
  const loteId  = document.getElementById('pg_lote').value;
  const total   = normalizeMoney(document.getElementById('pg_total').value);

  if (!ventaId || !total || total <= 0){
    Swal.fire('Completa la venta y el total válido','','warning');
    return;
  }

  const fd = new FormData(f);
  fd.set('idVenta', ventaId);   // 🔴 aseguramos idVenta
  fd.set('idLote', loteId);     // 🔴 aseguramos idLote (opcional, si aplica)
  fd.set('Total', String(total));

  try {
    const res  = await fetch('pagar_registrar.php', { method:'POST', body: fd });
    const text = await res.text();
    const data = parseServerJson(text);

    if (res.ok && data && data.ok){
      Swal.fire({icon:'success', title:'Pago registrado', timer:1400, showConfirmButton:false});
      modalPagar.hide();
      await cargarVentas();
    } else {
      Swal.fire({icon:'error', title:'No se pudo registrar', text:(data?.error || ('HTTP '+res.status))});
    }
  } catch(err){
    Swal.fire({icon:'error', title:'Error', text:String(err).slice(0,280)});
  }
}

    // Ver pagos de la venta
    async function verPagosVenta(ventaId){
      const body = document.getElementById('pagosVentaBody');
      body.innerHTML = 'Cargando…';
      try{
        const res = await fetch('pagar_detalle.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ idVenta: ventaId, idDesarrollo: ID_DES })
        });
        const data = parseServerJson(await res.text());
        if (!res.ok || !data || data.ok!==true) throw new Error(data?.error||('HTTP '+res.status));

        const pagos = data.pagos||[];
        const totalV = Number((VENTAS.find(v=>v.id===ventaId)||{}).total||0);
        const abonado = pagos.reduce((a,p)=>a+Number(p.Total||0),0);
        const resta   = Math.max(totalV - abonado, 0);

        body.innerHTML = `
          <div class="mb-2"><b>Contrato:</b> ${data.contrato||'—'}</div>
          <div class="mb-2"><b>Total Venta:</b> ${money(totalV)} · <b>Abonado:</b> ${money(abonado)} · <b>Restante:</b> ${money(resta)}</div>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th>Fecha</th><th>Forma</th><th>Estatus</th><th>Referencia</th><th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                ${pagos.map(p=>`
                  <tr>
                    <td>${p.FechaPago||'—'}</td>
                    <td>${p.FormaPago||'—'}</td>
                    <td>${p.Estatus||'—'}</td>
                    <td class="text-truncate">${p.Referencia||'—'}</td>
                    <td class="text-end">${money(p.Total||0)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      }catch(e){
        body.innerHTML = `<div class="text-danger">No fue posible cargar el detalle. ${String(e).slice(0,180)}</div>`;
      }
      modalPagosV.show();
    }

    // ====== Init ======
    document.addEventListener('DOMContentLoaded', () => {
      cargarVentas();
      document.getElementById('qVentas')?.addEventListener('input', renderVentas);

      // NUEVO
     document.getElementById('btnNuevo')?.addEventListener('click', async () => {
  await cargarVentas();
  await cargarFuentes([]);
  await cargarCuentas();
  resetForm();
  const sel = document.getElementById('vl_select');
  if (sel && sel.value) setPrecioDesdeLote(sel.value); // precarga si hay lote
  modalVenta.show();
});


      // PAGAR (sin selección, abre modal vacío con ventas a crédito)
      document.getElementById('btnPagar')?.addEventListener('click', async ()=>{
        if (!VENTAS.length) await cargarVentas();
        openModalPagar('');
      });

      // Guardar venta
      document.getElementById('vl_add')?.addEventListener('click', addLote);
      document.getElementById('btnGuardarVenta')?.addEventListener('click', guardarVenta);

      // Guardar pago
      document.getElementById('btnGuardarPago')?.addEventListener('click', guardarPago);

      // Cambio de venta en modal de pagar
      document.getElementById('pg_venta')?.addEventListener('change', (e)=>{
        buildLotesDeVentaEnPagar(e.target.value||'');
      });

      // Acciones por fila
      document.getElementById('tbVentas').addEventListener('click', (ev)=>{
        const btn = ev.target.closest('button[data-action]'); if (!btn) return;
        const id  = btn.dataset.id;
        const act = btn.dataset.action;
        if (act==='ver')      verVenta(id);
        if (act==='edit')     abrirEditar(id);
        if (act==='del')      eliminarVenta(id);
        if (act==='contrato') generarContrato(id, 'docx');
        if (act==='pagos')    verPagosVenta(id);
      });

      // Config de plan (habilitar/deshabilitar)
      const selModalidad = document.getElementById('vf_modalidad');
      const modalidadChoices = new Choices(selModalidad, { searchPlaceholderValue:'Buscar...', shouldSort:false, itemSelectText:'', removeItemButton:false, allowHTML:true });
      const plan   = document.getElementById('vf_plan');
      const cuotas = document.getElementById('vf_cuotas');
      const inicio = document.getElementById('vf_inicio');

      const recomputeFin = ()=>{
        const disabled = !plan.checked;
        selModalidad.disabled = disabled;
        if (disabled) modalidadChoices.disable(); else modalidadChoices.enable();
        cuotas.disabled = disabled; inicio.disabled = disabled;

        const mod  = selModalidad.value || '';
        const n    = Number(cuotas.value || 0);
        const iniS = inicio.value || '';
        const finI = document.getElementById('vf_fin');
        if (disabled || !mod || !n || !iniS){ finI.value=''; return; }
        const base = parseDMY(iniS); if (!base){ finI.value=''; return; }
        const fin = stepByModalidad(mod, base, n-1);
        finI.value = toISO(fin);
      };

      plan.addEventListener('change', recomputeFin);
      selModalidad.addEventListener('change', recomputeFin);
      cuotas.addEventListener('input',  recomputeFin);
      inicio.addEventListener('change', recomputeFin);
      window.recomputeFin = recomputeFin; // para resetForm
    });
  </script>
</body>
</html>
