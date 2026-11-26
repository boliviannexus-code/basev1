<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | NEXGOL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{margin:0;color:#102a30;background:#f7faf8;font-family:Manrope,Arial,sans-serif}a{color:inherit;text-decoration:none}a:focus-visible,button:focus-visible,input:focus-visible{outline:3px solid #efaa50;outline-offset:3px}.shell{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.story{position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;min-height:100vh;padding:44px clamp(36px,6vw,90px);background:#126b57;color:#fff}.brand{position:relative;z-index:1;font-family:'Barlow Condensed',Impact,sans-serif;font-size:34px;font-weight:800;letter-spacing:-.04em}.brand span{color:#b8e6d4}.story-content{position:relative;z-index:1;max-width:510px;margin:80px 0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.story .eyebrow{color:#b8e6d4}.story h1{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(64px,6.4vw,96px);font-weight:800;line-height:.9;letter-spacing:-.025em;text-transform:uppercase;margin:20px 0 25px}.story p{max-width:400px;color:#d5eee4;font-size:15px;line-height:1.8;margin:0}.pitch{position:absolute;width:78%;height:70%;right:-28%;top:15%;border:1px solid #ffffff45;border-radius:4px;transform:rotate(-12deg)}.pitch:before{content:'';position:absolute;top:50%;left:0;right:0;border-top:1px solid #ffffff45}.pitch:after{content:'';position:absolute;top:50%;left:50%;width:150px;height:150px;border:1px solid #ffffff45;border-radius:50%;transform:translate(-50%,-50%)}.story-footer{position:relative;z-index:1;color:#c5e5d9;font-size:12px}.panel{display:flex;flex-direction:column;min-height:100vh;padding:44px clamp(28px,6vw,90px)}.back{align-self:flex-end;color:#58706e;font-size:12px;font-weight:800}.back span{margin-right:8px;color:#126b57}.form-wrap{width:min(100%,400px);margin:auto; padding:60px 0}.form-wrap .eyebrow{color:#126b57}.form-wrap h2{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(48px,5vw,66px);line-height:1;text-transform:uppercase;letter-spacing:-.025em;margin:13px 0}.intro{color:#58706e;font-size:14px;line-height:1.7;margin:0 0 36px}.field{margin-bottom:22px}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:9px}.field input{display:block;width:100%;height:54px;border:1px solid #cdded7;border-radius:7px;background:#fff;color:#102a30;padding:0 16px;font:500 14px Manrope,Arial,sans-serif;transition:border-color .2s}.field input:focus{border-color:#126b57}.field input[aria-invalid=true]{border-color:#b83d37}.error{color:#a72f2a;font-size:12px;margin:7px 0 0;line-height:1.5}.remember{display:flex;align-items:center;gap:10px;font-size:12px;font-weight:700;color:#58706e;margin:4px 0 28px;cursor:pointer}.remember input{width:17px;height:17px;accent-color:#126b57;margin:0}.submit{width:100%;height:54px;display:flex;justify-content:space-between;align-items:center;padding:0 19px;border:0;border-radius:7px;background:#126b57;color:white;font:800 13px Manrope,Arial,sans-serif;cursor:pointer;transition:background .2s}.submit:hover{background:#0d5545}.submit span{font-size:19px}.panel-footer{color:#809690;font-size:11px}.mobile-brand{display:none}@media(max-width:800px){.shell{grid-template-columns:1fr}.story{min-height:0;padding:28px 24px}.story-content{margin:48px 0 24px}.story h1{font-size:clamp(50px,10vw,70px);max-width:460px}.story p{font-size:13px}.story-footer{display:none}.pitch{height:120%;top:-10%;right:-35%}.panel{min-height:0;padding:26px 24px 30px}.form-wrap{padding:48px 0 65px}.back{align-self:flex-start}.panel-footer{text-align:center}}@media(prefers-reduced-motion:reduce){.field input,.submit{transition:none}}
    </style>
</head>
<body>
<main class="shell">
    <section class="story" aria-label="Bienvenido a NEXGOL">
        <div class="pitch" aria-hidden="true"></div>
        <a class="brand" href="{{ route('home') }}" aria-label="NEXGOL, inicio">nex<span>gol.</span></a>
        <div class="story-content">
            <div class="eyebrow">Gestión deportiva · Fútbol</div>
            <h1>Tu liga, lista para jugar.</h1>
            <p>Todo lo que necesitas para organizar tu campeonato, desde el primer registro hasta el último partido.</p>
        </div>
        <div class="story-footer">NEXGOL · Gestión para ligas de fútbol</div>
    </section>
    <section class="panel" aria-label="Inicio de sesión">
        <a class="back" href="{{ route('home') }}"><span aria-hidden="true">←</span> Volver al inicio</a>
        <div class="form-wrap">
            <div class="eyebrow">Bienvenido de nuevo</div>
            <h2>Entra a tu cuenta.</h2>
            <p class="intro">Ingresa tus datos para continuar gestionando tu liga.</p>
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="field">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email') <p class="error" id="email-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password') <p class="error" id="password-error">{{ $message }}</p> @enderror
                </div>
                <label class="remember" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))> Recordarme</label>
                <button class="submit" type="submit">Ingresar <span aria-hidden="true">↗</span></button>
            </form>
        </div>
        <div class="panel-footer">© {{ date('Y') }} NEXGOL</div>
    </section>
</main>
</body>
</html>
