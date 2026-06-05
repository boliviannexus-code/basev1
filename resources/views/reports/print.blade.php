<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }} | Reportes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background: #eef1f5;
            color: #1f2937;
        }

        .report-document {
            max-width: 1100px;
            margin: 24px auto;
            background: #fff;
            border: 1px solid #dde3ea;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .12);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 28px 32px;
            border-bottom: 4px solid #206bc4;
        }

        .report-brand {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .report-logo {
            width: 76px;
            height: 76px;
            object-fit: contain;
            border: 1px solid #dde3ea;
            border-radius: 12px;
            padding: 8px;
            background: #fff;
        }

        .report-logo-placeholder {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            border-radius: 12px;
            background: #206bc4;
            color: #fff;
            font-size: 28px;
            font-weight: 700;
        }

        .report-body {
            padding: 26px 32px 32px;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .report-meta-item {
            border: 1px solid #dde3ea;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .report-actions {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            max-width: 1100px;
            margin: 16px auto -8px;
        }

        .report-footer {
            padding: 18px 32px 24px;
            color: #64748b;
            border-top: 1px solid #dde3ea;
            font-size: 12px;
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #fff;
            }

            .report-actions,
            .navbar,
            .btn {
                display: none !important;
            }

            .report-document {
                max-width: none;
                margin: 0;
                border: 0;
                box-shadow: none;
            }

            .report-header,
            .report-body,
            .report-footer {
                padding-left: 0;
                padding-right: 0;
            }

            .card {
                break-inside: avoid;
                border-color: #cbd5e1 !important;
                box-shadow: none !important;
            }

            .table {
                font-size: 11px;
            }

            .report-meta {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="report-actions">
        <button class="btn btn-primary" type="button" onclick="window.print()">
            <i class="ti ti-printer"></i>
            Imprimir / guardar PDF
        </button>
        <button class="btn btn-outline-secondary" type="button" onclick="window.close()">Cerrar</button>
    </div>

    <main class="report-document">
        <header class="report-header">
            <div class="report-brand">
                @if ($company?->logo_url)
                    <img class="report-logo" src="{{ $company->logo_url }}" alt="{{ $company->name }}">
                @else
                    <div class="report-logo-placeholder">{{ str($company?->name ?? 'POS')->substr(0, 2)->upper() }}</div>
                @endif
                <div>
                    <div class="h1 mb-1">{{ $company?->name ?? 'Todas las ligas deportivas' }}</div>
                    <div class="text-body-secondary">{{ $company ? 'Reporte de liga deportiva' : 'Reporte consolidado' }}</div>
                    <div class="small text-body-secondary">
                        {{ $company?->phone ?: '' }}
                    </div>
                    <div class="small text-body-secondary">{{ $company?->address ?: '' }}</div>
                </div>
            </div>
            <div class="text-end">
                <div class="text-uppercase text-primary fw-bold small">Reporte</div>
                <div class="h2 mb-1">{{ $reportTitle }}</div>
                <div class="text-body-secondary small">Generado: {{ now()->format('Y-m-d H:i') }}</div>
            </div>
        </header>

        <section class="report-body">
            <div class="report-meta">
                <div class="report-meta-item">
                    <div class="text-body-secondary small">Desde</div>
                    <div class="fw-semibold">{{ $filters['from']->format('Y-m-d') }}</div>
                </div>
                <div class="report-meta-item">
                    <div class="text-body-secondary small">Hasta</div>
                    <div class="fw-semibold">{{ $filters['to']->format('Y-m-d') }}</div>
                </div>
                <div class="report-meta-item">
                    <div class="text-body-secondary small">Almacen</div>
                    <div class="fw-semibold">{{ $selectedWarehouse?->name ?? 'Todos' }}</div>
                </div>
                <div class="report-meta-item">
                    <div class="text-body-secondary small">Usuario</div>
                    <div class="fw-semibold">{{ $generatedBy?->name ?? '-' }}</div>
                </div>
            </div>

            @include('reports.partials.sections')
        </section>

        <footer class="report-footer">
            {{ $company?->report_footer ?: 'Reporte generado desde Inventario POS.' }}
        </footer>
    </main>
</body>
</html>
