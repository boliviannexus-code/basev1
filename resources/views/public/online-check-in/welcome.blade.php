@php
    $translations = [
        'es' => ['language' => 'Idioma', 'welcome' => '¡Bienvenido!', 'subtitle' => 'Completa tu registro de entrada en línea', 'reservation' => 'Tengo una reserva', 'reservation_detail' => 'Booking.com, reserva directa u otra agencia', 'new' => 'No tengo una reserva', 'new_detail' => 'Registrarme como nuevo huésped', 'soon' => 'Esta opción estará disponible próximamente.', 'back' => 'Volver'],
        'en' => ['language' => 'Language', 'welcome' => 'Welcome!', 'subtitle' => 'Complete your online check-in', 'reservation' => 'I have a reservation', 'reservation_detail' => 'Booking.com, direct booking or another agency', 'new' => 'I do not have a reservation', 'new_detail' => 'Register as a new guest', 'soon' => 'This option will be available soon.', 'back' => 'Back'],
        'pt' => ['language' => 'Idioma', 'welcome' => 'Boas-vindas!', 'subtitle' => 'Complete seu check-in online', 'reservation' => 'Tenho uma reserva', 'reservation_detail' => 'Booking.com, reserva direta ou outra agência', 'new' => 'Não tenho uma reserva', 'new_detail' => 'Cadastrar-me como novo hóspede', 'soon' => 'Esta opção estará disponível em breve.', 'back' => 'Voltar'],
    ];
    $t = $translations[$language];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t['welcome'] }} | {{ $company->name }}</title>
    <style>
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f6fa;color:#172c42;font-family:system-ui,-apple-system,sans-serif;padding:24px} main{width:min(100%,540px);background:#fff;border:1px solid #e0e7ef;border-radius:20px;box-shadow:0 18px 55px #17325012;padding:clamp(24px,6vw,48px)} .top{display:flex;justify-content:space-between;align-items:center;gap:16px}.company{font-weight:700;overflow-wrap:anywhere} label{font-size:.8rem;color:#54677b} select{display:block;margin-top:4px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:white;color:inherit} h1{font-size:clamp(2rem,7vw,2.6rem);margin:48px 0 8px;letter-spacing:-.04em} p{color:#607185;line-height:1.5;margin:0 0 30px}.option{display:block;text-decoration:none;color:inherit;border:1px solid #dbe3ed;border-radius:12px;padding:20px;margin-top:12px}.option:hover,.option:focus-visible{border-color:#2563eb;background:#f6f9ff;outline-color:#2563eb}.option strong,.option span{display:block}.option span{color:#607185;font-size:.9rem;margin-top:5px}.notice{border-radius:12px;padding:18px;background:#edf5ff;color:#214b76}.back{display:inline-block;margin-top:20px;color:#245cc5} .logo{max-width:64px;max-height:64px;object-fit:contain;margin-right:10px;vertical-align:middle}
    </style>
</head>
<body>
<main>
    <div class="top">
        <div class="company">@if ($company->logo_url)<img class="logo" src="{{ $company->logo_url }}" alt="">@endif{{ $company->name }}</div>
        <form method="GET"><label>{{ $t['language'] }}<select name="lang" onchange="this.form.submit()"><option value="es" @selected($language === 'es')>Español</option><option value="en" @selected($language === 'en')>English</option><option value="pt" @selected($language === 'pt')>Português</option></select></label>@if ($choice)<input type="hidden" name="choice" value="{{ $choice }}">@endif<noscript><button type="submit">OK</button></noscript></form>
    </div>
    <h1>{{ $t['welcome'] }}</h1>
    <p>{{ $t['subtitle'] }}</p>
    @if ($choice)
        <div class="notice" role="status">{{ $t['soon'] }}</div>
        <a class="back" href="{{ route('public.online-check-in.welcome', ['company' => $company, 'lang' => $language]) }}">← {{ $t['back'] }}</a>
    @else
        <a class="option" href="{{ route('public.online-check-in.welcome', ['company' => $company, 'lang' => $language, 'choice' => 'reservation']) }}"><strong>{{ $t['reservation'] }} →</strong><span>{{ $t['reservation_detail'] }}</span></a>
        <a class="option" href="{{ route('public.online-check-in.welcome', ['company' => $company, 'lang' => $language, 'choice' => 'new']) }}"><strong>{{ $t['new'] }} →</strong><span>{{ $t['new_detail'] }}</span></a>
    @endif
</main>
</body>
</html>
