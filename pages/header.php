<?php
// ===============================
// pages/header.php
// ===============================
if (session_status() === PHP_SESSION_NONE) session_start();

// Descubre el nombre del proyecto (primer segmento, p.ej. /proyecto_terreno)
$__parts      = explode('/', trim($_SERVER['PHP_SELF'], '/'));
$__project    = $__parts[0] ?? '';
$BASE_PAGES   = '/' . $__project . '/pages';          // /proyecto_terreno/pages
?>

<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" navbar-scroll="true">
  <div class="container-fluid py-1 px-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <!-- usa ruta absoluta a Proyectos -->
        <li class="breadcrumb-item text-sm">
          <a class="opacity-5 text-dark" href="<?= $BASE_PAGES ?>/proyectos.php">G. Comercial</a>
        </li>
        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Contactos</li>
      </ol>
      <h6 class="font-weight-bolder mb-0">Contactos</h6>
    </nav>

    <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
      <ul class="navbar-nav justify-content-end ms-auto">
        <li class="nav-item d-flex align-items-center">
          <!-- usa ruta absoluta a logout.php en /pages -->
          <a href="<?= $BASE_PAGES ?>/logout.php" class="nav-link text-body font-weight-bold px-0">
            <i class="fa fa-sign-out me-sm-1"></i>
            <span class="d-sm-inline d-none">Salir</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
