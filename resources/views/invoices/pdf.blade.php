@php($format = $format ?? 'standard')
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->number }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #f7f7f8;
            color: #1f2937;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            max-width: 900px;
            margin: 0 auto;
        }

        h1 {
            margin: 0;
            font-size: 1.75rem;
        }

        .meta, .info {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .info > div {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }

        th {
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            color: #6b7280;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.75rem;
        }

        td {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
        }

        .totals {
            margin-top: 1.5rem;
            width: 40%;
            margin-left: auto;
        }

        .totals td {
            border: none;
            padding: 0.35rem 0;
        }

        .totals .label {
            color: #6b7280;
        }

        .totals .value {
            text-align: right;
            font-weight: 600;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.7rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            background: #e0f2fe;
            color: #0369a1;
        }

        .format-switch {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            justify-content: flex-end;
            font-size: 0.85rem;
        }

        .format-switch a {
            padding: 0.35rem 0.8rem;
            border-radius: 9999px;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: #374151;
        }

        .format-switch a.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }

        body.is-tsp100 {
            padding: 1rem;
            background: #fff;
        }

        body.is-tsp100 .card {
            max-width: 80mm;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: none;
            margin: 0 auto;
        }

        body.is-tsp100 .meta,
        body.is-tsp100 .info {
            flex-direction: column;
            gap: 0.5rem;
        }

        body.is-tsp100 p,
        body.is-tsp100 td,
        body.is-tsp100 th,
        body.is-tsp100 h1,
        body.is-tsp100 h3 {
            font-size: 0.85rem;
        }

        body.is-tsp100 table {
            margin-top: 1rem;
        }

        body.is-tsp100 .badge {
            font-size: 0.75rem;
        }

        body.is-tsp100 .totals {
            width: 100%;
        }

        @media print {
            body {
                background: #fff;
            }

            .card {
                box-shadow: none;
                padding: 0;
            }

            body.is-tsp100 {
                padding: 0;
            }

            body.is-tsp100 .card {
                padding: 0;
            }

            body.is-tsp100 .format-switch {
                display: none;
            }

            body.is-tsp100 {
                width: 80mm;
            }

            @page {
                size: auto;
            }

            body.is-tsp100 {
                width: 80mm;
            }
        }
    </style>
</head>
<body class="{{ $format === 'tsp100' ? 'is-tsp100' : '' }}">
    <div class="card">
        <div class="format-switch">
            <a href="{{ route('invoices.pdf', ['invoice' => $invoice->id, 'format' => 'standard']) }}"
                class="{{ $format === 'standard' ? 'active' : '' }}">
                Format standard
            </a>
            <a href="{{ route('invoices.pdf', ['invoice' => $invoice->id, 'format' => 'tsp100']) }}"
                class="{{ $format === 'tsp100' ? 'active' : '' }}">
                TSP100 (80mm)
            </a>
        </div>

        <div class="meta">
            <div>
                <h1>Facture {{ $invoice->number }}</h1>
                <p class="badge">{{ ucfirst($invoice->status ?? 'émise') }}</p>
            </div>
            <div style="text-align: right;">
                <p><strong>Date d’émission :</strong> {{ $invoice->issue_date?->toDateString() ?? now()->toDateString() }}</p>
                <p><strong>Échéance :</strong> {{ $invoice->due_date?->toDateString() ?? now()->toDateString() }}</p>
            </div>
        </div>

        <div class="info">
            <div>
                <h3>Hôtel</h3>
                <p>
                    {{ $hotel?->name ?? 'Hôtel' }}<br>
                    {{ $hotel?->address }}<br>
                    {{ $hotel?->city }} {{ $hotel?->country }}
                </p>
            </div>
            <div>
                <h3>Client</h3>
                <p>
                    {{ $guest?->full_name ?? $invoice->billing_name ?? 'Client' }}<br>
                    {{ $guest?->email ?? '' }}<br>
                    {{ $guest?->phone ?? '' }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qté</th>
                    <th>PU</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>{{ number_format($item->unit_price, 2) }} {{ $invoice->currency }}</td>
                        <td>{{ number_format($item->total_amount, 2) }} {{ $invoice->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label">Sous-total</td>
                <td class="value">{{ number_format($invoice->sub_total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">Taxes</td>
                <td class="value">{{ number_format($invoice->tax_total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">Total</td>
                <td class="value">{{ number_format($invoice->total_amount, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </table>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 400);
        });
    </script>
</body>
</html>
