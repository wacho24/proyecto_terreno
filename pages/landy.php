<?php
// landy.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>NombreMarca — Tagline del producto/servicio</title>
  <meta name="description" content="Descripción corta de la propuesta de valor." />
  <meta property="og:title" content="NombreMarca — Tagline" />
  <meta property="og:description" content="Descripción social para compartir." />
  <meta property="og:type" content="website" />
  <meta name="theme-color" content="#0b5cff" />
  <meta name="color-scheme" content="light" />

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#ffffff;
      --text:#1f2937;
      --muted:#6b7280;
      --primary:#0b5cff;
      --primary-dark:#094bcc;
      --accent:#f3f6fb;
      --card:#fff;
      --radius:16px;
      --shadow:0 8px 20px rgba(0,0,0,.08);
      --maxw:1160px;
      --font:'Inter',sans-serif;
    }
    body{margin:0;font-family:var(--font);background:var(--bg);color:var(--text);line-height:1.5}
    .container{max-width:var(--maxw);margin:auto;padding:0 24px}

    /* HEADER */
    header{position:sticky;top:0;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 4px 10px rgba(0,0,0,.05);z-index:10}
    .nav{display:flex;align-items:center;justify-content:space-between;padding:16px 0}
    nav ul{list-style:none;display:flex;gap:24px;margin:0;padding:0}
    nav a{color:var(--text);text-decoration:none;font-weight:600;transition:.2s}
    nav a:hover{color:var(--primary)}
    .nav-cta{display:flex;gap:12px}

    /* BOTONES */
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:var(--radius);font-weight:600;text-decoration:none;cursor:pointer;transition:.2s}
    .btn.primary{background:var(--primary);color:#fff;box-shadow:var(--shadow)}
    .btn.primary:hover{background:var(--primary-dark)}
    .btn.ghost{background:#fff;border:1px solid #d1d5db;color:var(--text)}
    .btn.ghost:hover{background:var(--accent)}

    /* HERO */
    .hero{padding:120px 0;text-align:center;background:linear-gradient(180deg,#f9fafb 0%,#fff 100%)}
    .hero h1{font-size:clamp(32px,5vw,56px);margin:0 0 12px;font-weight:700}
    .hero .highlight{color:var(--primary)}
    .hero .lead{font-size:18px;color:var(--muted);max-width:700px;margin:0 auto}
    .hero-ctas{margin-top:28px;display:flex;gap:14px;justify-content:center;flex-wrap:wrap}

    /* SECCIONES */
    section{padding:72px 0}
    .section-hd h2{margin:0;font-size:clamp(24px,3vw,32px);color:var(--primary)}
    .section-hd p{color:var(--muted)}

    .grid{display:grid;gap:24px}
    .grid.cols-3{grid-template-columns:repeat(3,1fr)}
    @media(max-width:900px){.grid.cols-3{grid-template-columns:1fr}}

    /* CARDS */
    .card{background:var(--card);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);transition:.3s}
    .card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,.12)}
    .card .icon{font-size:36px;margin-bottom:16px;color:var(--primary)}
    .card h3{margin:8px 0;font-weight:600;color:var(--text)}

    /* FORM */
    form input,form textarea{width:100%;padding:14px 16px;margin-top:10px;border:1px solid #d1d5db;border-radius:var(--radius);font-family:var(--font)}
    form textarea{resize:none}
    form button{margin-top:16px}

    /* FOOTER */
    footer{background:#f3f6fb;color:var(--muted);text-align:center;padding:24px 0;font-size:14px;margin-top:40px}

    /* ANIMACIONES */
    .reveal{opacity:0;transform:translateY(24px);transition:all .7s ease}
    .reveal.visible{opacity:1;transform:none}
  </style>
</head>
<body>
  <!-- HEADER -->
  <header>
    <div class="container nav">
      <a class="brand" href="#home"><img src="imagen/logo.svg" alt="NombreMarca" class="brand-logo" height="36"/></a>
      <nav>
        <ul>
          <li><a href="#seccion-1">Sección 1</a></li>
          <li><a href="#seccion-2">Sección 2</a></li>
          <li><a href="#seccion-3">Sección 3</a></li>
        </ul>
      </nav>
      <div class="nav-cta">
        <a class="btn ghost" href="#seccion-1">Acción secundaria</a>
        <a class="btn primary" href="#contacto">Llamada a la acción</a>
      </div>
    </div>
  </header>

  <main id="home">
    <!-- HERO -->
    <section class="hero reveal">
      <div class="container">
        <h1>Tu propuesta en <span class="highlight">una línea</span></h1>
        <p class="lead">Texto introductorio corto. Explica qué haces y para quién.</p>
        <div class="hero-ctas">
          <a class="btn primary" href="#contacto">CTA principal</a>
          <a class="btn ghost" href="#seccion-1">Saber más</a>
        </div>
      </div>
    </section>

    <!-- SECCIÓN 1 -->
    <section id="seccion-1" class="reveal">
      <div class="container">
        <div class="section-hd"><h2>Sección 1</h2><p>Descripción corta.</p></div>
        <div class="grid cols-3">
          <div class="card"><div class="icon">⚡</div><h3>Beneficio 1</h3><p class="muted">Texto descriptivo.</p></div>
          <div class="card"><div class="icon">💼</div><h3>Beneficio 2</h3><p class="muted">Texto descriptivo.</p></div>
          <div class="card"><div class="icon">🔒</div><h3>Beneficio 3</h3><p class="muted">Texto descriptivo.</p></div>
        </div>
      </div>
    </section>

    <!-- SECCIÓN 2 -->
    <section id="seccion-2" class="reveal">
      <div class="container">
        <div class="section-hd"><h2>Sección 2</h2><p>Breve descripción.</p></div>
        <div class="card"><h3>Título destacado</h3><p>Texto para destacar algo importante.</p></div>
      </div>
    </section>

    <!-- SECCIÓN 3 -->
    <section id="seccion-3" class="reveal">
      <div class="container">
        <div class="section-hd"><h2>Sección 3</h2><p>Otra breve descripción.</p></div>
        <div class="grid cols-3">
          <div class="card"><div class="icon">📈</div><h3>Tema 1</h3><p class="muted">Detalle.</p></div>
          <div class="card"><div class="icon">🧩</div><h3>Tema 2</h3><p class="muted">Detalle.</p></div>
          <div class="card"><div class="icon">⚙️</div><h3>Tema 3</h3><p class="muted">Detalle.</p></div>
        </div>
      </div>
    </section>

    <!-- CONTACTO -->
    <section id="contacto" class="reveal">
      <div class="container">
        <div class="section-hd"><h2>Contáctanos</h2><p>Cuéntanos tu necesidad.</p></div>
        <form method="post" action="api/guardar_formulario.php">
          <input type="text" name="nombre" placeholder="Tu nombre" required>
          <input type="email" name="correo" placeholder="Tu correo" required>
          <textarea name="mensaje" placeholder="Tu mensaje" rows="5" required></textarea>
          <button type="submit" class="btn primary">Enviar</button>
        </form>
      </div>
    </section>
  </main>

  <footer>
    <div class="container">© <?php echo date('Y'); ?> NombreMarca. Todos los derechos reservados.</div>
  </footer>

  <script>
    // Animaciones reveal
    const io=new IntersectionObserver((entries)=>{
      entries.forEach(en=>{
        if(en.isIntersecting){
          en.target.classList.add('visible');
          io.unobserve(en.target);
        }
      });
    },{threshold:.15});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
  </script>
</body>
</html>
