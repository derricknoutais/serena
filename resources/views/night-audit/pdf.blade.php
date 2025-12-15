<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Night Audit {{ $report['business_date'] ?? '' }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3 { margin: 0 0 8px; }
        .muted { color: #6b7280; font-size: 12px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .section { margin-top: 18px; }
    </style>
</head>
<body>
    <h1>Night Audit - {{ $report['business_date'] ?? '' }}</h1>
    <p class="muted">{{ $report['hotel']['name'] ?? '' }}</p>

    <div class="card">
        <h3>Occupation</h3>
        <table>
            <tr>
                <th>Total chambres</th>
                <th>Occupées</th>
                <th>Disponibles</th>
                <th>Taux d’occupation</th>
            </tr>
            <tr>
                <td>{{ $report['occupancy']['total_rooms'] ?? 0 }}</td>
                <td>{{ $report['occupancy']['occupied_rooms'] ?? 0 }}</td>
                <td>{{ $report['occupancy']['available_rooms'] ?? 0 }}</td>
                <td>{{ $report['occupancy']['occupancy_rate'] ?? 0 }}%</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Arrivées</h3>
        <table>
            <tr>
                <th>Code</th>
                <th>Chambre</th>
                <th>Client</th>
                <th>Check-in</th>
            </tr>
            @forelse($report['movements']['arrivals'] ?? [] as $arrival)
                <tr>
                    <td>{{ $arrival['code'] ?? '' }}</td>
                    <td>{{ $arrival['room'] ?? '' }}</td>
                    <td>{{ $arrival['guest'] ?? '' }}</td>
                    <td>{{ $arrival['check_in_at'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucune arrivée.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <h3>Départs</h3>
        <table>
            <tr>
                <th>Code</th>
                <th>Chambre</th>
                <th>Client</th>
                <th>Check-out</th>
            </tr>
            @forelse($report['movements']['departures'] ?? [] as $departure)
                <tr>
                    <td>{{ $departure['code'] ?? '' }}</td>
                    <td>{{ $departure['room'] ?? '' }}</td>
                    <td>{{ $departure['guest'] ?? '' }}</td>
                    <td>{{ $departure['check_out_at'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun départ.</td></tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <h3>Revenus</h3>
        <table>
            <tr>
                <th>Chambres</th>
                <th>POS / Bar</th>
                <th>Taxes</th>
                <th>Total</th>
            </tr>
            <tr>
                <td>{{ number_format($report['revenue']['room_revenue'] ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</td>
                <td>{{ number_format($report['revenue']['pos_revenue'] ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</td>
                <td>{{ number_format($report['revenue']['tax_total'] ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</td>
                <td>{{ number_format($report['revenue']['total_revenue'] ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Paiements par méthode</h3>
        <table>
            <tr>
                <th>Méthode</th>
                <th>Montant</th>
            </tr>
            @forelse($report['payments_by_method'] ?? [] as $method => $amount)
                <tr>
                    <td>{{ $method }}</td>
                    <td>{{ number_format($amount ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Aucun paiement.</td></tr>
            @endforelse
            <tr>
                <th>Total</th>
                <th>{{ number_format($report['total_payments'] ?? 0, 0, ',', ' ') }} {{ $report['hotel']['currency'] ?? '' }}</th>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Réconciliation des caisses</h3>
        <table>
            <tr>
                <th>POS</th>
                <th>Ouverture</th>
                <th>Clôture</th>
                <th>Ouvert par</th>
                <th>Fermé par</th>
                <th>Fond initial</th>
                <th>Encaissements</th>
                <th>Attendu</th>
                <th>Compté</th>
                <th>Écart</th>
            </tr>
            @forelse($report['cash_reconciliation']['sessions'] ?? [] as $session)
                <tr>
                    <td>{{ $session['type'] ?? '' }}</td>
                    <td>{{ $session['opened_at'] ?? '' }}</td>
                    <td>{{ $session['closed_at'] ?? '' }}</td>
                    <td>{{ $session['opened_by'] ?? '' }}</td>
                    <td>{{ $session['closed_by'] ?? '' }}</td>
                    <td>{{ number_format($session['opening_amount'] ?? 0, 0, ',', ' ') }}</td>
                    <td>{{ number_format($session['cash_in'] ?? 0, 0, ',', ' ') }}</td>
                    <td>{{ number_format($session['expected_close'] ?? 0, 0, ',', ' ') }}</td>
                    <td>{{ number_format($session['actual_close'] ?? 0, 0, ',', ' ') }}</td>
                    <td>{{ number_format($session['difference'] ?? 0, 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Aucune session clôturée.</td></tr>
            @endforelse
        </table>

        <div class="section card">
            <h4>Totaux</h4>
            <table>
                <tr>
                    <th>POS</th>
                    <th>Fond initial</th>
                    <th>Encaissements</th>
                    <th>Attendu</th>
                    <th>Compté</th>
                    <th>Écart</th>
                </tr>
                @foreach(($report['cash_reconciliation']['totals'] ?? []) as $pos => $totals)
                    <tr>
                        <td>{{ $pos }}</td>
                        <td>{{ number_format($totals['opening_amount'] ?? 0, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totals['cash_in'] ?? 0, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totals['expected_close'] ?? 0, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totals['actual_close'] ?? 0, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totals['difference'] ?? 0, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</body>
</html>
