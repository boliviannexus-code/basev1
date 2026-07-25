<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --ink:#111827; --field:#0f7b5f; --line:#dbe7df; --sun:#f5c542; --paper:#f7faf7; --red:#b91c1c; }
        body { margin:0; background:var(--paper); color:var(--ink); font-family: Inter, system-ui, sans-serif; }
        .public-shell { width:min(1120px, calc(100% - 32px)); margin:0 auto; }
        .public-hero { min-height:560px; color:#fff; background-image:linear-gradient(90deg, rgba(8,38,30,.92), rgba(8,38,30,.62)), var(--hero-image); background-size:100% 100%, contain; background-repeat:no-repeat; background-position:center; background-color:#08261e; }
        .page-hero { min-height:360px; background-image:linear-gradient(90deg, rgba(8,38,30,.92), rgba(8,38,30,.54)), var(--hero-image); background-size:100% 100%, contain; }
        .public-nav { display:flex; justify-content:space-between; align-items:center; padding:22px 0; gap:16px; }
        .public-nav a { color:#fff; text-decoration:none; margin-left:18px; font-weight:700; }
        .brand { display:flex; align-items:center; gap:12px; margin-left:0!important; }
        .brand img { width:48px; height:48px; object-fit:contain; background:#fff; border-radius:8px; padding:5px; }
        .login-link { opacity:.78; padding:6px 8px; border-radius:6px; font-size:13px; font-weight:700; }
        .login-link:hover { opacity:1; background:rgba(255,255,255,.12); }
        .hero-grid { display:grid; grid-template-columns:minmax(0, 860px); gap:32px; align-items:end; padding:90px 0 70px; }
        .page-hero-copy { padding:54px 0 70px; }
        .eyebrow { color:var(--sun); font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
        h1 { font-size:clamp(42px, 7vw, 82px); line-height:.95; margin:0 0 22px; font-weight:950; max-width:820px; }
        .hero-copy { font-size:21px; line-height:1.45; max-width:720px; color:#e8fff5; }
        .hero-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }
        .hero-actions a, .whatsapp-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:10px 16px; border-radius:6px; text-decoration:none; font-weight:900; }
        .hero-actions a { background:#fff; color:#0c4738; }
        .hero-actions a.secondary { background:rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.46); }
        .content-section { padding:48px 0; display:grid; gap:24px; }
        .public-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:28px; }
        .public-card h2 { margin:0 0 12px; font-size:28px; }
        .public-card p { white-space:pre-line; font-size:18px; line-height:1.6; color:#475569; }
        .wide { grid-column:1 / -1; }
        .image-pair { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .photo-tile { aspect-ratio:16 / 9; border-radius:8px; overflow:hidden; background:#dbe7df; }
        .photo-tile img { width:100%; height:100%; object-fit:cover; }
        .photo-placeholder { height:100%; display:grid; place-items:center; font-weight:800; color:#476055; }
        .quick-links { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; }
        .quick-links a { background:var(--field); color:#fff; text-decoration:none; padding:18px; border-radius:8px; font-weight:900; }
        .social-links { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .social-links a { border:1px solid var(--line); border-radius:6px; padding:9px 12px; color:var(--field); text-decoration:none; font-weight:800; }
        .whatsapp-button { background:#128c7e; color:#fff; margin-top:12px; width:max-content; }
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; }
        .stat-box { background:#fff; border:1px solid var(--line); border-radius:8px; padding:16px; }
        .stat-box span { color:#64748b; display:block; font-size:12px; font-weight:900; text-transform:uppercase; }
        .stat-box strong { display:block; font-size:26px; margin-top:4px; color:var(--field); }
        .muted { color:#64748b!important; }
        .public-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); }
        .public-table th, .public-table td { padding:12px; border-bottom:1px solid var(--line); text-align:left; }
        .public-table th { background:#eaf3ee; font-size:12px; text-transform:uppercase; }
        .filter-card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:18px; margin:24px 0; display:flex; gap:12px; flex-wrap:wrap; }
        .filter-card select, .filter-card input { padding:10px 12px; border:1px solid var(--line); border-radius:6px; min-width:220px; }
        .filter-card button { background:var(--field); color:#fff; border:0; border-radius:6px; padding:10px 16px; font-weight:800; }
        .fixture-board { display:grid; gap:22px; }
        .fixture-round { background:#fff; border:1px solid var(--line); border-radius:8px; overflow:hidden; box-shadow:0 18px 44px rgba(15, 123, 95, .10); }
        .fixture-round-header { display:flex; justify-content:space-between; gap:16px; align-items:center; padding:18px 22px; background:linear-gradient(135deg, #0f7b5f, #123c34); color:#fff; }
        .fixture-round-header h2 { margin:0; font-size:24px; font-weight:950; }
        .fixture-count { background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28); border-radius:999px; padding:7px 12px; font-size:12px; font-weight:900; text-transform:uppercase; white-space:nowrap; }
        .fixture-date-block { border-top:1px solid var(--line); }
        .fixture-court-bar { background:#132f4c; color:#fff; text-align:center; padding:8px 14px; font-size:12px; font-weight:950; text-transform:uppercase; }
        .fixture-date-bar { background:#0f7b5f; color:#fff; text-align:center; padding:11px 14px; font-size:16px; font-weight:950; text-transform:uppercase; }
        .fixture-list { display:grid; gap:1px; background:var(--line); }
        .fixture-match { background:#fff; display:grid; grid-template-columns:100px minmax(0, 1fr) 150px; gap:14px; align-items:center; padding:12px 18px; }
        .fixture-time { color:#132f4c; font-size:22px; font-weight:950; text-align:center; }
        .fixture-time span { display:block; color:#64748b; font-size:10px; text-transform:uppercase; }
        .fixture-teams { display:grid; grid-template-columns:minmax(0, 1fr) 46px minmax(0, 1fr); gap:10px; align-items:center; }
        .fixture-team { min-height:44px; display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid #e3ece6; border-radius:8px; background:#f8fbf9; font-weight:950; font-size:17px; line-height:1.18; }
        .fixture-team.home { justify-content:flex-end; text-align:right; }
        .fixture-team-name { min-width:0; }
        .fixture-team-score { flex:0 0 auto; min-width:36px; border-radius:6px; padding:5px 8px; background:#132f4c; color:#fff; font-size:16px; text-align:center; }
        .fixture-wo { flex:0 0 auto; border-radius:999px; padding:5px 8px; background:#dc2626; color:#fff; font-size:11px; font-weight:950; }
        .fixture-vs { height:40px; display:grid; place-items:center; border-radius:8px; background:#f5c542; color:#1d2939; font-weight:950; box-shadow:0 10px 22px rgba(245,197,66,.22); }
        .fixture-detail { display:grid; gap:5px; color:#475569; font-size:12px; font-weight:800; text-align:right; }
        .fixture-detail strong { color:#132f4c; font-size:13px; }
        .fixture-empty { background:#fff; border:1px dashed #b8c9bf; border-radius:8px; padding:28px; color:#64748b; font-size:18px; }
        footer { padding:32px 0; color:#64748b; }
        @media (max-width: 900px) { .fixture-match { grid-template-columns:1fr; } .fixture-time { text-align:left; } }
        @media (max-width: 760px) { .hero-grid, .image-pair, .quick-links, .stats-grid { grid-template-columns:1fr; } .public-nav { align-items:flex-start; flex-direction:column; } .public-nav a { margin:0 14px 8px 0; display:inline-block; } .fixture-round-header, .fixture-teams { grid-template-columns:1fr; } .fixture-round-header { align-items:flex-start; flex-direction:column; } .fixture-team, .fixture-team.home { justify-content:center; text-align:center; } .fixture-vs { margin:auto; } }
    </style>
</head>
<body>
