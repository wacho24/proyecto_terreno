<?php
session_start();

// Si ya inició sesión → ir directo a proyectos
if (!empty($_SESSION['user_id'])) {
  header('Location: pages/proyectos.php');
  exit;
}

// Mensajes del backend
$errorMessage  = $_SESSION['error_login']   ?? '';
$accessDenied  = $_SESSION['access_denied'] ?? '';
unset($_SESSION['error_login'], $_SESSION['access_denied']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Landi</title>

  <link href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,800" rel="stylesheet" />
  <link href="https://demos.creative-tim.com/soft-ui-dashboard/assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="https://demos.creative-tim.com/soft-ui-dashboard/assets/css/nucleo-svg.css" rel="stylesheet" />
  <link id="pagestyle" href="assets/css/soft-ui-dashboard.css?v=1.1.0" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root{
      --violeta-1:#6a39b6; --violeta-2:#482b7a;
      --primary:#5d3db7; --primary-2:#7b61ff;
      --card:#ffffffec; --ring:rgba(123,97,255,.35);
      --input-h:52px;
    }
    html,body{height:100%}
    body{
      font-family:'Inter',sans-serif;
      background: url("assets/img/fondo_atras_loggin.jpg") no-repeat center center fixed;
      background-size: cover;
      min-height:100vh; overflow-x:hidden;
      display:flex; align-items:center; justify-content:center;
      padding:20px;
    }
    .top-brand{
      position:fixed; left:18px; top:14px;
      display:flex; align-items:center; gap:10px;
      color:#efeaff; font-weight:700; user-select:none;
    }
    .top-brand img{ height:28px; width:auto; border-radius:6px; filter:drop-shadow(0 2px 6px rgba(0,0,0,.35)); }

    .login-card{
      width:100%; max-width:460px; background:var(--card);
      backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
      border:1px solid rgba(255,255,255,.28); border-radius:22px;
      padding:28px 28px 24px; box-shadow:0 18px 40px rgba(0,0,0,.26);
    }

    /* Header del card */
    .brand-logo{ height:56px; width:auto; display:block; margin:0 auto 10px; border-radius:10px; }
    .title-gradient{
      font-weight:800; font-size:22px; line-height:1.1; margin:0 auto 6px; text-align:center;
      background: linear-gradient(90deg, var(--primary), var(--primary-2));
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .login-sub{ color:#6b7280; text-align:center; margin-bottom:18px; }

    .form-label{ font-weight:600; color:#444; margin-bottom:.5rem; }
    .field{ position:relative; width:100%; }
    .field .form-control{
      width:100%; height:var(--input-h); border-radius:16px;
      padding:12px 14px; border:1px solid #e6e6e6; background:#fff;
    }
    .field .form-control:focus{ border-color:var(--primary-2); box-shadow:0 0 0 .22rem var(--ring); outline:none; }
    .password-field .form-control{ padding-right:44px; }
    .password-field .toggle-pass{
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      border:none; background:transparent; padding:6px; cursor:pointer; opacity:.7;
    }
    .password-field .toggle-pass:hover{ opacity:1; }
    .btn-login{
      width:100%; border:none; border-radius:14px; padding:12px 16px;
      font-weight:800; color:#fff; background:linear-gradient(90deg, var(--primary), var(--primary-2));
      box-shadow:0 8px 18px rgba(93,61,183,.35);
    }
  </style>
</head>
<body>

  <!-- Branding mínimo fijo -->
  <div class="top-brand">
    <img src="assets/img/land_administration.jpg" alt="Landi" onerror="this.style.display='none'">
    <span>Landi</span>
  </div>

  <!-- Card centrado -->
  <div class="login-card">
    <!-- Encabezado renovado -->
    <img class="brand-logo" src="assets/img/land_administration.jpg" alt="Landi">
    <h1 class="title-gradient">Bienvenido(a) a Landi</h1>
    <div class="login-sub small">Ingresa tus credenciales</div>

    <!-- Form -->
    <form method="POST" action="pages/procesar_login.php" id="formLogin" autocomplete="off">
      <div class="mb-3">
        <label class="form-label" for="email">Correo electrónico <span class="text-danger">*</span></label>
        <div class="field">
          <input type="email" class="form-control" id="email" name="email" placeholder="tucorreo@dominio.com" autocomplete="username" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="password">Contraseña <span class="text-danger">*</span></label>
        <div class="field password-field">
          <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" aria-label="Mostrar/ocultar contraseña" onclick="togglePassword()">👁</button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">Iniciar sesión</button>

      <div class="text-center mt-3">
        <small class="muted">© <?php echo date('Y'); ?> Landi</small>
      </div>
    </form>
  </div>

  <!-- SweetAlert desde backend -->
  <?php if (!empty($errorMessage)): ?>
    <script>Swal.fire({icon:'error',title:'Error al iniciar sesión',text:<?php echo json_encode($errorMessage); ?>,confirmButtonColor:'#5d3db7'});</script>
  <?php elseif (!empty($accessDenied)): ?>
    <script>Swal.fire({icon:'warning',title:'Acceso denegado',text:<?php echo json_encode($accessDenied); ?>,confirmButtonColor:'#5d3db7'});</script>
  <?php endif; ?>

  <script>
    function togglePassword(){
      const input = document.getElementById('password');
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    document.getElementById('formLogin').addEventListener('submit', function(){
      const btn = document.getElementById('btnLogin');
      btn.disabled = true; btn.style.opacity=.85;
      Swal.fire({ title:'Validando…', allowOutsideClick:false, didOpen:() => Swal.showLoading() });
    });
  </script>

  <script src="assets/js/core/popper.min.js"></script>
  <script src="assets/js/core/bootstrap.min.js"></script>
  <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
</body>
</html>
