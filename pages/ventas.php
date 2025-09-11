<?php
session_start();

// Si ya inició sesión → ir directo a proyectos
if (!empty($_SESSION['idDesarrollo'])) {
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
    :root{ --violeta-1:#6a39b6; --violeta-2:#482b7a; --primary:#5d3db7; --primary-2:#7b61ff; --card:#ffffffec; --ring:rgba(123,97,255,.35); --input-h:52px; }
    html,body{height:100%}
    body{
      font-family:'Inter',sans-serif;
      background: url("assets/img/fondo_atras_loggin.jpg") no-repeat center center fixed;
      background-size: cover;
      min-height:100vh;
      overflow-x:hidden;
    }
    .scene{ min-height:100vh; display:flex; align-items:center; justify-content:center; padding:48px 20px; }
    .top-brand{ position:fixed; left:18px; top:14px; display:flex; align-items:center; gap:10px; color:#efeaff; font-weight:700; user-select:none; }
    .top-brand img{ height:40px; width:auto; border-radius:6px; filter:drop-shadow(0 2px 6px rgba(0,0,0,.35)); }
    .login-card{ max-width:460px; margin:0 auto; background:var(--card); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.28); border-radius:22px; padding:32px; box-shadow:0 18px 40px rgba(0,0,0,.26); }
    .login-title{ font-weight:800; color:#1f2430; margin-bottom:6px; } .login-sub{ color:#6b7280; margin-bottom:18px; }
    .form-label{ font-weight:600; color:#444; margin-bottom:.5rem; }
    .field{ position:relative; width:100%; }
    .field .form-control{ width:100%; height:var(--input-h); border-radius:16px; padding:12px 14px; border:1px solid #e6e6e6; background:#fff; }
    .field .field-icon{ position:absolute; left:14px; top:50%; transform:translateY(-50%); width:20px; height:20px; opacity:.6; pointer-events:none; display:block; }
    .field.has-left .form-control{ padding-left:44px; }
    .field .form-control:focus{ border-color:var(--primary-2); box-shadow:0 0 0 .22rem var(--ring); outline:none; }
    .password-field .form-control{ padding-right:44px; }
    .password-field .toggle-pass{ position:absolute; right:12px; top:50%; transform:translateY(-50%); border:none; background:transparent; padding:6px; cursor:pointer; opacity:.7; }
    .password-field .toggle-pass:hover{ opacity:1; }
    .btn-login{ width:100%; border:none; border-radius:14px; padding:12px 16px; font-weight:800; color:#fff; background:linear-gradient(90deg, var(--primary), var(--primary-2)); box-shadow:0 8px 18px rgba(93,61,183,.35); }
  </style>
</head>
<body>

  <div class="top-brand">
    <img src="assets/img/land_administration.jpg" alt="Landi" onerror="this.style.display='none'">
    <span>Landi</span>
  </div>

  <main class="scene">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-5 form-col">
          <div class="login-card">
            <h4 class="login-title">Inicio de sesión</h4>
            <div class="login-sub small">Ingresa tus credenciales</div>

            <form method="POST" action="pages/procesar_login.php" id="formLogin" autocomplete="off">
              <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico <span class="text-danger">*</span></label>
                <div class="field has-left">
                  <svg class="field-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 2v.01L12 13 4 6.01V6h16zM4 18V8.236l7.386 5.866a1 1 0 0 0 1.228 0L20 8.236V18H4z"/>
                  </svg>
                  <input type="email" class="form-control" id="email" name="email" placeholder="tucorreo@dominio.com" autocomplete="username" required autofocus>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label" for="password">Contraseña <span class="text-danger">*</span></label>
                <div class="field has-left password-field">
                  <svg class="field-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2H10V6zm7 12H7v-8h10v8z"/>
                  </svg>
                  <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                  <button type="button" class="toggle-pass" aria-label="Mostrar/ocultar contraseña" onclick="togglePassword()">
                    <svg id="eyeOn" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 1 1 .001-10.001A5 5 0 0 1 12 17zm0-8a3 3 0 1 0 .001 6.001A3 3 0 0 0 12 9z"/></svg>
                    <svg id="eyeOff" viewBox="0 0 24 24" style="display:none"><path fill="currentColor" d="M2 4.27 3.28 3 21 20.72 19.73 22l-3.1-3.11A12.52 12.52 0 0 1 12 19C5 19 1 12 1 12a21.79 21.79 0 0 1 6.36-6.36L2 4.27zM12 7a5 5 0 0 1 5 5c0 .86-.22 1.67-.6 2.37l-6.77-6.77A4.92 4.92 0 0 1 12 7zm-5 5a5 5 0 0 1 .6-2.37l7.77 7.77A4.92 4.92 0 0 1 12 17a5 5 0 0 1-5-5z"/></svg>
                  </button>
                </div>
              </div>

              <button type="submit" class="btn btn-login" id="btnLogin">Iniciar sesión</button>

              <div class="text-center mt-3">
                <small class="muted">© <?php echo date('Y'); ?> Landi</small>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php if (!empty($errorMessage)): ?>
    <script>Swal.fire({icon:'error',title:'Error al iniciar sesión',text:<?php echo json_encode($errorMessage); ?>,confirmButtonColor:'#5d3db7'});</script>
  <?php elseif (!empty($accessDenied)): ?>
    <script>Swal.fire({icon:'warning',title:'Acceso denegado',text:<?php echo json_encode($accessDenied); ?>,confirmButtonColor:'#5d3db7'});</script>
  <?php endif; ?>

  <script>
    function togglePassword(){
      const input = document.getElementById('password');
      const on = document.getElementById('eyeOn');
      const off = document.getElementById('eyeOff');
      const type = input.type === 'password' ? 'text' : 'password';
      input.type = type;
      const showing = type === 'text';
      on.style.display = showing ? 'none' : 'block';
      off.style.display = showing ? 'block' : 'none';
    }

    document.getElementById('formLogin').addEventListener('submit', function(){
      const btn = document.getElementById('btnLogin');
      btn.disabled = true; btn.style.opacity=.85;
      Swal.fire({ title:'Validando…', allowOutsideClick:false, didOpen:() => Swal.showLoading() });
    });

    document.getElementById('password').addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ document.getElementById('formLogin').requestSubmit(); }
    });
  </script>

  <script src="assets/js/core/popper.min.js"></script>
  <script src="assets/js/core/bootstrap.min.js"></script>
  <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
</body>
</html>
