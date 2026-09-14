@php
    $company = $season->company;
    $primary = $company?->interface_primary_color ?: '#12395b';
    $secondary = $company?->interface_secondary_color ?: '#0f766e';
    $accent = $company?->interface_accent_color ?: '#f5c542';
    $sidebar = $company?->interface_sidebar_color ?: '#132f4c';
    $title = ($matchday->name ?? 'Jornada '.$matchday->number).' · '.$season->name;
    $companyName = $company?->name ?? config('app.name', 'Nexgol');
    $logoUrl = $company?->logo_display_url;

    $initials = str($companyName)->squish()->explode(' ')->filter()->take(3)->map(fn ($word) => mb_substr($word, 0, 1))->implode('');
    $teamLabel = fn ($team, $seed): string => $team?->name ?? $seed ?? 'Por definir';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir {{ $title }}</title>
    <style>
        :root { --primary: {{ $primary }}; --secondary: {{ $secondary }}; --accent: {{ $accent }}; --deep: {{ $sidebar }}; --ink: #17212f; --muted: #64748b; --line: #d8e0ea; --paper: #f4f7fb; --soft: #f8fbff; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: Inter, "Segoe UI", Arial, sans-serif; }
        .print-toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 18px; background: #fff; border-bottom: 1px solid var(--line); box-shadow: 0 8px 24px rgba(15, 23, 42, .08); }
        .print-toolbar strong { display: block; font-size: 14px; }
        .print-toolbar span { color: var(--muted); font-size: 12px; }
        .print-actions { display: flex; gap: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--ink); font-weight: 800; text-decoration: none; cursor: pointer; }
        .btn-primary { border-color: var(--primary); background: var(--primary); color: #fff; }
        .sheet { position: relative; width: min(1120px, calc(100% - 32px)); margin: 24px auto; overflow: hidden; background: #fff; border: 1px solid var(--line); box-shadow: 0 24px 60px rgba(15, 23, 42, .12); }
        .sheet::before { content: ""; position: absolute; inset: 0 0 auto; height: 8px; background: linear-gradient(90deg, var(--accent), var(--secondary), var(--primary)); }
        .hero { display: grid; grid-template-columns: 170px 1fr 190px; gap: 24px; align-items: center; padding: 34px 30px 28px; border-bottom: 1px solid rgba(255,255,255,.16); background: radial-gradient(circle at 18% 0%, rgba(255,255,255,.18), transparent 32%), linear-gradient(135deg, var(--deep), var(--primary)); color: #fff; }
        .logo-box { display: grid; place-items: center; min-height: 126px; padding: 14px; border: 1px solid rgba(255,255,255,.38); border-radius: 10px; background: #fff; box-shadow: 0 18px 38px rgba(0,0,0,.18); }
        .logo-image { display: block; width: 132px; max-width: 100%; height: 98px; object-fit: contain; }
        .logo-box span { display: grid; width: 96px; height: 96px; place-items: center; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; font-size: 26px; font-weight: 950; }
        .league-kicker { margin: 0 0 8px; color: var(--accent); font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 34px; line-height: 1.02; letter-spacing: 0; }
        .hero-meta { margin-top: 10px; color: rgba(255,255,255,.82); font-size: 13px; line-height: 1.5; }
        .journey-badge { display: grid; place-items: center; min-height: 120px; border: 1px solid rgba(255,255,255,.3); border-radius: 8px; background: rgba(255,255,255,.1); text-align: center; }
        .journey-badge small { display: block; color: rgba(255,255,255,.8); font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .journey-badge strong { display: block; color: #fff; font-size: 52px; line-height: .95; }
        .content { padding: 22px; }
        .date-block { break-inside: avoid; margin-bottom: 22px; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; box-shadow: 0 10px 28px rgba(15, 23, 42, .07); }
        .date-head { display: grid; place-items: center; padding: 15px 18px; background: linear-gradient(90deg, rgba(15, 118, 110, .12), #fff, rgba(245, 197, 66, .16)); border-left: 8px solid var(--secondary); border-right: 8px solid var(--accent); text-align: center; }
        .date-head h2 { margin: 0; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 9px 10px; background: var(--deep); color: #fff; font-size: 11px; text-align: left; text-transform: uppercase; }
        th:nth-child(3), td:nth-child(3) { text-align: center; }
        td { padding: 11px 10px; border-top: 1px solid var(--line); vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fbff; }
        .time { color: var(--primary); font-size: 18px; font-weight: 950; white-space: nowrap; }
        .matchup { display: grid; grid-template-columns: minmax(0, 1fr) 42px minmax(0, 1fr); align-items: center; justify-content: center; gap: 12px; width: min(100%, 620px); margin: 0 auto; font-weight: 950; }
        .team-name { min-width: 0; padding: 8px 10px; border: 1px solid #e8eef6; border-radius: 8px; background: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.7); text-align: center; }
        .vs { display: grid; place-items: center; width: 42px; height: 34px; border-radius: 8px; background: var(--accent); color: #111827; font-size: 12px; box-shadow: 0 8px 18px rgba(15, 23, 42, .12); }
        .match-detail { width: min(100%, 620px); margin: 7px auto 0; color: var(--muted); font-size: 11px; font-weight: 700; text-align: center; }
        .detail { margin-top: 4px; color: var(--muted); font-size: 11px; font-weight: 700; }
        .category { color: var(--primary); font-weight: 900; }
        .empty { padding: 28px; color: var(--muted); text-align: center; }
        .footer { display: flex; justify-content: space-between; gap: 16px; padding: 14px 22px; background: var(--deep); color: rgba(255,255,255,.82); font-size: 11px; }
        @media print { @page { size: A4 portrait; margin: 10mm; } body { background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; } .print-toolbar { display: none; } .sheet { width: 100%; margin: 0; border: 0; box-shadow: none; } .hero { grid-template-columns: 128px 1fr 150px; padding: 18px; } .logo-box { min-height: 96px; } .logo-image { width: 104px; height: 78px; } h1 { font-size: 28px; } .content { padding: 14px 0; } .date-block { margin-bottom: 14px; box-shadow: none; } th, td { padding: 7px 8px; } .team-name { padding: 6px 8px; box-shadow: none; } }
        @media (max-width: 760px) { .hero { grid-template-columns: 1fr; } .matchup { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong>{{ $title }}</strong>
            <span>{{ $companyName }}</span>
        </div>
        <div class="print-actions">
            <a class="btn" href="{{ route('matchdays.configure', $matchday) }}">Volver</a>
            <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <main class="sheet">
        <section class="hero">
            <div class="logo-box">
                @if ($logoUrl)
                    <img class="logo-image" src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>
            <div>
                <p class="league-kicker">Rol oficial de partidos</p>
                <h1>{{ $companyName }}</h1>
                <div class="hero-meta">
                    {{ $season->name }}<br>
                    {{ $company?->foundation_date ? 'Fundado el '.$company->foundation_date->copy()->locale('es')->translatedFormat('d \d\e F Y') : '' }}
                    {{ $company?->legal_personality ? ' · P.J. '.$company->legal_personality : '' }}
                </div>
            </div>
            <div class="journey-badge">
                <div>
                    <small>Jornada</small>
                    <strong>{{ $matchday->number }}</strong>
                </div>
            </div>
        </section>

        <section class="content">
            @forelse ($dates as $date)
                <article class="date-block">
                    <div class="date-head">
                        <div>
                            <h2>{{ ucfirst($date->date->copy()->locale('es')->translatedFormat('l d \d\e F \d\e Y')) }}</h2>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 86px;">Hora</th>
                                <th style="width: 130px;">Categoria</th>
                                <th>Partido</th>
                                <th style="width: 170px;">Fiscal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($date->fixtureMatches as $match)
                                @php($fiscal = $match->getRelation('fiscalAssignment'))
                                <tr>
                                    <td class="time">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</td>
                                    <td class="category">{{ $match->category?->name ?? '-' }}</td>
                                    <td>
                                        <div class="matchup">
                                            <div class="team-name">{{ $teamLabel($match->homeTeam, $match->home_seed) }}</div>
                                            <div class="vs">VS</div>
                                            <div class="team-name">{{ $teamLabel($match->awayTeam, $match->away_seed) }}</div>
                                        </div>
                                        <div class="match-detail">
                                            @if ($match->series)
                                                {{ \App\Models\TournamentRegistration::SERIES[$match->series] ?? $match->series }} ·
                                            @endif
                                            {{ $match->tournament?->name ?? '-' }}
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $fiscal?->team?->name ?? '-' }}</strong>
                                        @if ($fiscal)
                                            <div class="detail">{{ \Carbon\Carbon::parse($fiscal->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($fiscal->end_time)->format('H:i') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="empty" colspan="4">No hay partidos programados en esta fecha.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>
            @empty
                <div class="empty">No hay fechas configuradas para esta jornada.</div>
            @endforelse
        </section>

        <footer class="footer">
            <span>{{ $companyName }}</span>
            <span>Generado el {{ now()->format('d/m/Y H:i') }}</span>
        </footer>
    </main>
</body>
</html>
