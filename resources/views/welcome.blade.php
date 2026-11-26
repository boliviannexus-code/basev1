<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Nexgol te ayuda a organizar torneos, equipos, jugadores y resultados en un solo lugar.">
    <title>Nexgol | Gestión para ligas de fútbol</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#102a30;--green:#126b57;--mint:#d7eee6;--paper:#f7faf8;--orange:#efaa50;--muted:#58706e}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink);font-family:Manrope,Arial,sans-serif}a{color:inherit;text-decoration:none}a:focus-visible{outline:3px solid var(--orange);outline-offset:4px}.wrap{width:min(1160px,calc(100% - 48px));margin:auto}.header{height:84px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:32px;letter-spacing:-.04em}.brand span{color:var(--green)}.nav{display:flex;align-items:center;gap:34px;font-size:13px;font-weight:800}.nav-link{color:var(--muted)}.button{display:inline-flex;align-items:center;justify-content:center;gap:16px;padding:15px 22px;border-radius:8px;background:var(--green);color:white;font-size:13px;font-weight:800;transition:transform .2s,background .2s}.button:hover{background:#0d5545;transform:translateY(-2px)}.button-light{background:white;color:var(--ink)}.button-light:hover{background:#e8f4ef}.hero{padding:66px 0 76px;overflow:hidden}.hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:75px;align-items:center}.eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:800;color:var(--green)}h1,h2,h3,p{margin-top:0}h1,h2{font-family:'Barlow Condensed',Impact,sans-serif;text-transform:uppercase;letter-spacing:-.025em}h1{font-size:clamp(58px,6.5vw,94px);line-height:.92;margin:18px 0 24px;max-width:650px}h1 span{color:var(--green)}.lead{color:var(--muted);font-size:16px;line-height:1.8;max-width:480px}.hero-actions{display:flex;align-items:center;gap:25px;margin-top:30px}.text-link{font-size:13px;font-weight:800}.text-link span{color:var(--green);margin-left:8px}.visual{position:relative;min-height:440px;background:#c6e4d7;border-radius:14px;overflow:hidden;display:flex;align-items:center;justify-content:center}.pitch{position:absolute;inset:28px;border:1px solid rgba(18,107,87,.3);border-radius:3px}.pitch:before{content:'';position:absolute;top:50%;left:0;right:0;border-top:1px solid rgba(18,107,87,.3)}.pitch:after{content:'';position:absolute;top:50%;left:50%;width:115px;height:115px;border:1px solid rgba(18,107,87,.3);border-radius:50%;transform:translate(-50%,-50%)}.board{z-index:1;width:75%;max-width:340px;background:#fff;border-radius:12px;padding:23px;box-shadow:0 22px 55px #1c5d4930;transform:rotate(-3deg)}.board-top{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e7eeeb;padding-bottom:18px}.board-label{font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800}.board-title{font-family:'Barlow Condensed',sans-serif;font-size:25px;font-weight:700;margin-top:4px}.live{background:#e4f5ed;color:var(--green);font-size:10px;font-weight:800;padding:7px 9px;border-radius:20px}.row{display:flex;align-items:center;gap:12px;padding:15px 0;border-bottom:1px solid #edf2ef;font-size:12px;font-weight:700}.row:last-child{border:0;padding-bottom:2px}.badge{width:30px;height:30px;border-radius:50%;background:#dfefe9;color:var(--green);display:grid;place-items:center;font-size:11px}.row small{display:block;color:#8aa09a;font-size:10px;font-weight:500;margin-top:4px}.row-arrow{margin-left:auto;color:var(--green);font-size:18px}.strip{background:var(--ink);color:white;padding:22px 0}.strip-inner{display:flex;align-items:center;justify-content:space-between;gap:20px}.strip strong{font-family:'Barlow Condensed',sans-serif;font-size:23px;text-transform:uppercase;letter-spacing:.02em}.strip p{margin:0;color:#b5ceca;font-size:12px}.features{padding:100px 0}.section-head{display:flex;justify-content:space-between;align-items:end;gap:30px;margin-bottom:42px}.section-head h2{font-size:clamp(42px,5vw,64px);line-height:1;margin:12px 0 0;max-width:600px}.section-head p{max-width:320px;color:var(--muted);line-height:1.7;font-size:14px;margin:0}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:white;border:1px solid #e2ede8;border-radius:10px;padding:29px;min-height:220px}.icon{height:42px;width:42px;display:grid;place-items:center;border-radius:10px;background:var(--mint);color:var(--green);font-size:20px;font-weight:800;margin-bottom:27px}.card h3{font-size:16px;margin-bottom:10px}.card p{font-size:13px;line-height:1.75;color:var(--muted);margin-bottom:0}.closing{background:var(--green);color:white;border-radius:14px;padding:48px 55px;display:flex;align-items:center;justify-content:space-between;gap:30px}.closing h2{font-size:clamp(38px,4vw,55px);line-height:1;margin:0 0 10px}.closing p{color:#cce6dc;margin:0;font-size:14px;line-height:1.6}.footer{padding:40px 0;color:var(--muted);font-size:12px}.footer-inner{display:flex;justify-content:space-between;align-items:center;gap:20px}.footer .brand{color:var(--ink);font-size:26px}@media(max-width:800px){.hero-grid{grid-template-columns:1fr;gap:42px}.hero{padding:40px 0 55px}.visual{min-height:370px}.cards{grid-template-columns:repeat(2,1fr)}.section-head{display:block}.section-head p{margin-top:20px}.features{padding:70px 0}.closing{padding:36px;align-items:flex-start;flex-direction:column}}@media(max-width:520px){.wrap{width:min(100% - 32px,1160px)}.header{height:72px}.nav{gap:14px}.nav-link{display:none}.nav .button{padding:12px 15px}.hero-actions{align-items:flex-start;flex-direction:column;gap:20px}.visual{min-height:310px}.cards{grid-template-columns:1fr}.card{min-height:0}.strip-inner,.footer-inner{align-items:flex-start;flex-direction:column}.closing{padding:30px}.board{width:80%}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.button{transition:none}}
    </style>
</head>
<body>
    <header class="wrap header">
        <a class="brand" href="{{ route('home') }}" aria-label="Nexgol, inicio">nex<span>gol.</span></a>
        <nav class="nav" aria-label="Navegación principal">
            <a class="nav-link" href="#ligas">Ligas</a><a class="nav-link" href="#funciones">El sistema</a>
            @auth
                <a class="button" href="{{ route('dashboard') }}">Ir al panel <span aria-hidden="true">↗</span></a>
            @else
                <a class="button" href="{{ route('login') }}">Ingresar <span aria-hidden="true">↗</span></a>
            @endauth
        </nav>
    </header>
    <main>
        <section class="hero wrap">
            <div class="hero-grid">
                <div>
                    <div class="eyebrow">Gestión deportiva · Fútbol</div>
                    <h1>Tu liga juega <span>mejor organizada.</span></h1>
                    <p class="lead">Desde la inscripción de equipos hasta los resultados de cada jornada, Nexgol reúne la gestión de tu liga en un solo lugar.</p>
                    <div class="hero-actions"><a class="button" href="#funciones">Conoce Nexgol <span aria-hidden="true">↓</span></a><a class="text-link" href="{{ route('login') }}">Ya tengo una cuenta <span aria-hidden="true">↗</span></a></div>
                </div>
                <div class="visual" aria-label="Ilustración de las herramientas de gestión de Nexgol">
                    <div class="pitch" aria-hidden="true"></div>
                    <div class="board" aria-hidden="true"><div class="board-top"><div><div class="board-label">Todo en un lugar</div><div class="board-title">Tu liga, en orden</div></div><span class="live">NEXGOL</span></div><div class="row"><span class="badge">01</span><div>Torneos y categorías<small>Organiza la competencia</small></div><span class="row-arrow">↗</span></div><div class="row"><span class="badge">02</span><div>Equipos y jugadores<small>Gestiona sus registros</small></div><span class="row-arrow">↗</span></div><div class="row"><span class="badge">03</span><div>Partidos y resultados<small>Sigue cada jornada</small></div><span class="row-arrow">↗</span></div></div>
                </div>
            </div>
        </section>
        <div class="strip"><div class="wrap strip-inner"><strong>Más fútbol. Menos papeleo.</strong><p>Una herramienta para quienes hacen posible cada campeonato.</p></div></div>
        <section class="features wrap" id="ligas">
            <div class="section-head"><div><div class="eyebrow">El fútbol de tu liga</div><h2>Consulta los resultados de tu campeonato.</h2></div><p>Encuentra tu liga para ver sus partidos y tablas de posiciones.</p></div>
            @if ($publicLeagues->isNotEmpty())
                <div class="cards">
                    @foreach ($publicLeagues as $league)
                        <a class="card" href="{{ route('public.path.league', ['tenant' => $league->subdomain]) }}"><div class="icon" aria-hidden="true">↗</div><h3>{{ $league->name }}</h3><p>Ver partidos, resultados y posiciones.</p></a>
                    @endforeach
                </div>
            @else
                <p class="lead">Pronto podrás consultar aquí las ligas disponibles.</p>
            @endif
        </section>
        <section class="features wrap" id="funciones">
            <div class="section-head"><div><div class="eyebrow">Todo el campeonato, conectado</div><h2>Las herramientas para llevar tu liga adelante.</h2></div><p>Organiza la información deportiva y mantén cada etapa de la competencia al alcance de tu equipo.</p></div>
            <div class="cards">
                <article class="card"><div class="icon" aria-hidden="true">▦</div><h3>Torneos y fixtures</h3><p>Organiza torneos, categorías y jornadas para dar seguimiento a la competencia.</p></article>
                <article class="card"><div class="icon" aria-hidden="true">◎</div><h3>Equipos y jugadores</h3><p>Centraliza los registros, inscripciones y habilitaciones de los participantes.</p></article>
                <article class="card"><div class="icon" aria-hidden="true">↗</div><h3>Resultados y posiciones</h3><p>Registra los partidos y consulta tablas de posiciones y reportes deportivos.</p></article>
                <article class="card"><div class="icon" aria-hidden="true">▤</div><h3>Control disciplinario</h3><p>Haz seguimiento a tarjetas, sanciones y antecedentes de los jugadores.</p></article>
                <article class="card"><div class="icon" aria-hidden="true">◉</div><h3>Tu liga en la web</h3><p>Comparte partidos, posiciones y otra información deportiva en una página pública para tu liga.</p></article>
                <article class="card"><div class="icon" aria-hidden="true">◇</div><h3>Gestión centralizada</h3><p>Reúne la información de tu organización y administra el acceso de tu equipo.</p></article>
            </div>
        </section>
        <section class="wrap"><div class="closing"><div><h2>El fútbol empieza mucho antes del partido.</h2><p>Organiza cada detalle de tu liga con Nexgol.</p></div><a class="button button-light" href="{{ route('login') }}">Ingresar al sistema <span aria-hidden="true">↗</span></a></div></section>
    </main>
    <footer class="footer"><div class="wrap footer-inner"><a class="brand" href="{{ route('home') }}">nex<span>gol.</span></a><span>Gestión deportiva para ligas de fútbol.</span><span>© {{ date('Y') }} Nexgol</span></div></footer>
</body>
</html>
