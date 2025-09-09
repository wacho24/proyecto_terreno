<?php
// =====================
// BASE para enlaces absolutos a /<proyecto>/pages
// =====================
$__parts     = explode('/', trim($_SERVER['PHP_SELF'], '/'));
$__project   = $__parts[0] ?? '';                 // p.ej. proyecto_terreno
$BASE_PAGES  = '/' . $__project . '/pages';       // /proyecto_terreno/pages

// Marca activo si el fragmento aparece en la URL actual
function nav_active(string $needle): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  return (stripos($uri, $needle) !== false) ? ' active' : '';
}
?>

<style>
  :root{ --morado:#6a39b6; --morado2:#7b61ff; }
  .bg-grad-itrade{ background:linear-gradient(135deg,var(--morado),var(--morado2))!important; }

  #sidenav-main .nav-link{
    border-radius:12px; margin:4px 8px; padding:8px 12px;
    display:flex; align-items:center;
  }
  #sidenav-main .nav-link:hover{ background:rgba(123,97,255,.08); }
  #sidenav-main .nav-link.active{ background:rgba(123,97,255,.12); }

  #sidenav-main .icon{
    width:36px; height:36px; border-radius:10px;
  }
  #sidenav-main .icon i{ font-size:16px; line-height:1; color:#fff; }

  .section-label{ padding-left:1.2rem; margin-left:.35rem; font-size:.72rem;
    text-transform:uppercase; font-weight:700; opacity:.6; letter-spacing:.06em; }
</style>

<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3 bg-white" id="sidenav-main">
  <div class="sidenav-header">
    <a class="navbar-brand m-0" href="<?= $BASE_PAGES ?>/proyectos.php">
      <span class="ms-1 font-weight-bold">iTrade 3.0</span>
    </a>
  </div>
  <hr class="horizontal dark mt-0">

  <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
    <ul class="navbar-nav">

      <!-- Proyectos -->
      <li class="nav-item">
        <a class="nav-link<?= nav_active('/proyectos.php') ?>" href="<?= $BASE_PAGES ?>/proyectos.php">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-layer-group"></i>
          </div>
          <span class="nav-link-text ms-1">Proyectos</span>
        </a>
      </li>

      <li class="nav-item mt-3">
        <h6 class="section-label">G. Comercial</h6>
      </li>

      <!-- Contactos -->
      <li class="nav-item">
        <a class="nav-link<?= nav_active('/dashboard.php') ?>" href="<?= $BASE_PAGES ?>/dashboard.php">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-address-book"></i>
          </div>
          <span class="nav-link-text ms-1">Contactos</span>
        </a>
      </li>

      <!-- Logística -->
      <li class="nav-item">
        <a class="nav-link<?= nav_active('/logistica.php') ?>" href="<?= $BASE_PAGES ?>/logistica.php">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-truck"></i>
          </div>
          <span class="nav-link-text ms-1">Logística</span>
        </a>
      </li>

      <!-- Ventas -->
      <li class="nav-item">
        <a class="nav-link<?= nav_active('/ventas.php') ?>" href="<?= $BASE_PAGES ?>/ventas.php">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-cart-shopping"></i>
          </div>
          <span class="nav-link-text ms-1">Ventas</span>
        </a>
      </li>

      <!-- Generales -->
      <li class="nav-item mt-2">
        <a class="nav-link<?= nav_active('/generales/') ?>" href="<?= $BASE_PAGES ?>/generales/index.php">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-sliders"></i>
          </div>
          <span class="nav-link-text ms-1">Generales</span>
        </a>
      </li>

      <li class="nav-item mt-3">
        <h6 class="section-label">Más</h6>
      </li>

      <!-- Cobros -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-coins"></i>
          </div>
          <span class="nav-link-text ms-1">Cobros</span>
        </a>
      </li>

      <!-- Cotizaciones -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-file-invoice-dollar"></i>
          </div>
          <span class="nav-link-text ms-1">Cotizaciones</span>
        </a>
      </li>

      <!-- Reservas -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-calendar-check"></i>
          </div>
          <span class="nav-link-text ms-1">Reservas</span>
        </a>
      </li>

      <!-- Tesorería -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-wallet"></i>
          </div>
          <span class="nav-link-text ms-1">Tesorería</span>
        </a>
      </li>

      <!-- Ajustes -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-gear"></i>
          </div>
          <span class="nav-link-text ms-1">Ajustes</span>
        </a>
      </li>

      <!-- Reportes -->
      <li class="nav-item">
        <a class="nav-link" href="#">
          <div class="icon bg-grad-itrade shadow text-center me-2 d-flex align-items-center justify-content-center">
            <i class="fa-solid fa-chart-line"></i>
          </div>
          <span class="nav-link-text ms-1">Reportes</span>
        </a>
      </li>

    </ul>
  </div>
</aside>
 