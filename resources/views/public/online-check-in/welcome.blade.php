@php
    $translations = [
        'es' => ['language' => 'Idioma', 'welcome' => '¡Bienvenido!', 'subtitle' => 'Completa tu registro de entrada en línea', 'reservation' => 'Tengo una reserva', 'reservation_detail' => 'Booking.com, reserva directa u otra agencia', 'new' => 'No tengo una reserva', 'new_detail' => 'Registrarme como nuevo huésped', 'soon' => 'Esta opción estará disponible próximamente.', 'back' => 'Volver'],
        'en' => ['language' => 'Language', 'welcome' => 'Welcome!', 'subtitle' => 'Complete your online check-in', 'reservation' => 'I have a reservation', 'reservation_detail' => 'Booking.com, direct booking or another agency', 'new' => 'I do not have a reservation', 'new_detail' => 'Register as a new guest', 'soon' => 'This option will be available soon.', 'back' => 'Back'],
        'pt' => ['language' => 'Idioma', 'welcome' => 'Boas-vindas!', 'subtitle' => 'Complete seu check-in online', 'reservation' => 'Tenho uma reserva', 'reservation_detail' => 'Booking.com, reserva direta ou outra agência', 'new' => 'Não tenho uma reserva', 'new_detail' => 'Cadastrar-me como novo hóspede', 'soon' => 'Esta opção estará disponível em breve.', 'back' => 'Voltar'],
        'fr' => ['language' => 'Langue', 'welcome' => 'Bienvenue !', 'subtitle' => 'Complétez votre enregistrement en ligne', 'reservation' => 'J’ai une réservation', 'reservation_detail' => 'Booking.com, réservation directe ou autre agence', 'new' => 'Je n’ai pas de réservation', 'new_detail' => 'M’inscrire en tant que nouveau client', 'soon' => 'Cette option sera bientôt disponible.', 'back' => 'Retour'],
    ];
    $t = $translations[$language];
    $searchTranslations = [
        'es' => ['label' => 'Nombre o apellido de la reserva', 'search' => 'Buscar reserva', 'invalid' => 'Escribe entre 3 y 100 caracteres.', 'empty' => 'No encontramos reservas vigentes con ese nombre.', 'results' => 'Reservas encontradas', 'arrival' => 'Entrada', 'departure' => 'Salida', 'privacy' => 'Por privacidad, los nombres se muestran parcialmente.'],
        'en' => ['label' => 'Reservation first or last name', 'search' => 'Search reservation', 'invalid' => 'Enter between 3 and 100 characters.', 'empty' => 'No current reservations found for that name.', 'results' => 'Reservations found', 'arrival' => 'Check-in', 'departure' => 'Check-out', 'privacy' => 'Names are partially hidden for privacy.'],
        'pt' => ['label' => 'Nome ou sobrenome da reserva', 'search' => 'Buscar reserva', 'invalid' => 'Digite entre 3 e 100 caracteres.', 'empty' => 'Nenhuma reserva vigente encontrada para esse nome.', 'results' => 'Reservas encontradas', 'arrival' => 'Entrada', 'departure' => 'Saída', 'privacy' => 'Por privacidade, os nomes são parcialmente ocultados.'],
        'fr' => ['label' => 'Prénom ou nom de la réservation', 'search' => 'Rechercher une réservation', 'invalid' => 'Saisissez entre 3 et 100 caractères.', 'empty' => 'Aucune réservation en cours trouvée pour ce nom.', 'results' => 'Réservations trouvées', 'arrival' => 'Arrivée', 'departure' => 'Départ', 'privacy' => 'Les noms sont partiellement masqués pour protéger la vie privée.'],
    ];
    $s = $searchTranslations[$language];
    $roomTypeLabel = ['es' => 'Tipo de cama', 'en' => 'Bed type', 'pt' => 'Tipo de cama', 'fr' => 'Type de lit'][$language];
    $spaceLabel = ['es' => 'Espacio', 'en' => 'Property', 'pt' => 'Espaço', 'fr' => 'Hébergement'][$language];
    $roomTypeMissing = ['es' => 'No especificado', 'en' => 'Not specified', 'pt' => 'Não especificado', 'fr' => 'Non précisé'][$language];
    $selectLabel = ['es' => 'Seleccionar reserva', 'en' => 'Select reservation', 'pt' => 'Selecionar reserva', 'fr' => 'Sélectionner la réservation'][$language];
    $sentMessage = ['es' => 'Tus datos fueron enviados a recepción para su revisión. El check-in aún no está completado.', 'en' => 'Your details were sent to reception for review. Check-in is not yet complete.', 'pt' => 'Seus dados foram enviados à recepção para revisão. O check-in ainda não foi concluído.', 'fr' => 'Vos informations ont été envoyées à la réception pour vérification. L’enregistrement n’est pas encore terminé.'][$language];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t['welcome'] }} | {{ $company->name }}</title>
    <style>
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f6fa;color:#172c42;font-family:system-ui,-apple-system,sans-serif;padding:24px} main{width:min(100%,540px);background:#fff;border:1px solid #e0e7ef;border-radius:20px;box-shadow:0 18px 55px #17325012;padding:clamp(24px,6vw,48px)} .top{display:flex;justify-content:space-between;align-items:center;gap:16px}.company{font-weight:700;overflow-wrap:anywhere} label{font-size:.8rem;color:#54677b} select{display:block;margin-top:4px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:white;color:inherit} h1{font-size:clamp(2rem,7vw,2.6rem);margin:48px 0 8px;letter-spacing:-.04em} p{color:#607185;line-height:1.5;margin:0 0 30px}.option{display:block;text-decoration:none;color:inherit;border:1px solid #dbe3ed;border-radius:12px;padding:20px;margin-top:12px}.option:hover,.option:focus-visible{border-color:#2563eb;background:#f6f9ff;outline-color:#2563eb}.option strong,.option span{display:block}.option span{color:#607185;font-size:.9rem;margin-top:5px}.notice{border-radius:12px;padding:18px;background:#edf5ff;color:#214b76}.back{display:inline-block;margin-top:20px;color:#245cc5} .logo{max-width:64px;max-height:64px;object-fit:contain;margin-right:10px;vertical-align:middle}
        .search-label{display:block;font-size:.95rem;font-weight:600}.search-input{width:100%;margin:8px 0 12px;padding:12px;border:1px solid #b9c6d4;border-radius:8px;font:inherit}.search-button{border:0;border-radius:8px;background:#245cc5;color:white;padding:12px 18px;font:inherit;font-weight:600;cursor:pointer}.result{border:1px solid #dbe3ed;border-radius:12px;padding:16px;margin-top:12px}.result span{display:block;margin-top:6px;color:#607185}.search-feedback{margin-top:18px}.privacy{font-size:.85rem;margin:12px 0 0}
    </style>
</head>
<body>
<main>
    <div class="top">
        <div class="company">@if ($company->logo_url)<img class="logo" src="{{ $company->logo_url }}" alt="">@endif{{ $company->name }}</div>
        <form method="GET" action="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug]) }}"><label>{{ $t['language'] }}<select name="lang" onchange="this.form.submit()"><option value="es" @selected($language === 'es')>Español</option><option value="en" @selected($language === 'en')>English</option><option value="pt" @selected($language === 'pt')>Português</option><option value="fr" @selected($language === 'fr')>Français</option></select></label>@if ($choice)<input type="hidden" name="choice" value="{{ $choice }}">@endif<noscript><button type="submit">OK</button></noscript></form>
    </div>
    <h1>{{ $t['welcome'] }}</h1>
    <p>{{ $t['subtitle'] }}</p>
    @if (request()->query('sent') === '1')
        <div class="notice" role="status">{{ $sentMessage }}</div>
        <a class="back" href="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language]) }}">← {{ $t['back'] }}</a>
    @elseif ($choice === 'reservation')
        <form method="POST" action="{{ route('public.online-check-in.search', ['company' => $company->public_slug]) }}">
            @csrf
            <input type="hidden" name="lang" value="{{ $language }}">
            <label class="search-label" for="guest-name">{{ $s['label'] }}</label>
            <input class="search-input" id="guest-name" name="name" value="{{ $name ?? '' }}" minlength="3" maxlength="100" required autocomplete="name">
            <button class="search-button" type="submit">{{ $s['search'] }}</button>
        </form>
        @if ($searched ?? false)
            <div class="search-feedback" role="status">
                @if ($invalidName)
                    <div class="notice">{{ $s['invalid'] }}</div>
                @elseif ($results->isEmpty())
                    <div class="notice">{{ $s['empty'] }}</div>
                @else
                    <strong>{{ $s['results'] }}</strong>
                    @foreach ($results as $result)
                        <div class="result"><strong>{{ collect(preg_split('/\s+/u', trim($result->guest_name)))->map(fn ($part) => mb_substr($part, 0, 3).'***')->implode(' ') }}</strong><span>{{ $s['arrival'] }}: {{ $result->check_in->format('d/m/Y') }} · {{ $s['departure'] }}: {{ $result->check_out->format('d/m/Y') }}</span><span>{{ $spaceLabel }}: {{ $result->spaces->implode(', ') ?: $roomTypeMissing }}</span><span>{{ $roomTypeLabel }}: {{ $result->bed_types->implode(', ') ?: $roomTypeMissing }}</span>
                            <form method="POST" action="{{ route('public.online-check-in.form', ['company' => $company->public_slug]) }}">@csrf<input type="hidden" name="lang" value="{{ $language }}"><input type="hidden" name="token" value="{{ \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['company' => $company->id, 'kind' => $result instanceof \App\Models\ReservationGroup ? 'group' : 'single', 'id' => $result->id])) }}"><button class="search-button" style="margin-top:12px" type="submit">{{ $selectLabel }}</button></form>
                        </div>
                    @endforeach
                    <p class="privacy">{{ $s['privacy'] }}</p>
                @endif
            </div>
        @endif
        <a class="back" href="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language]) }}">← {{ $t['back'] }}</a>
    @elseif ($choice === 'new')
        <div class="notice" role="status">{{ $t['soon'] }}</div>
        <a class="back" href="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language]) }}">← {{ $t['back'] }}</a>
    @else
        <a class="option" href="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language, 'choice' => 'reservation']) }}"><strong>{{ $t['reservation'] }} →</strong><span>{{ $t['reservation_detail'] }}</span></a>
        <a class="option" href="{{ route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language, 'choice' => 'new']) }}"><strong>{{ $t['new'] }} →</strong><span>{{ $t['new_detail'] }}</span></a>
    @endif
</main>
</body>
</html>
