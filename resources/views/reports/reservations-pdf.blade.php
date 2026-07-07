<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Reservasi - Wawi Kadio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .header { background: #15803d; color: white; padding: 20px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; font-weight: bold; }
        .header p { font-size: 11px; opacity: 0.85; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 10px; color: #6b7280; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .summary-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .summary-card .value { font-size: 18px; font-weight: bold; color: #15803d; }
        .summary-card .label { font-size: 10px; color: #6b7280; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #15803d; color: white; padding: 8px; text-align: left; font-size: 10px; }
        td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .badge { padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Reservasi — Wawi Kadio Resort</h1>
        <p>Periode: {{ $period_from }} s/d {{ $period_to }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="meta">
        <span>Total Reservasi: <strong>{{ $reservations->count() }}</strong></span>
        <span>Total Pendapatan: <strong>Rp {{ number_format($revenue, 0, ',', '.') }}</strong></span>
        <span>Desa Tonsewer, Kabupaten Minahasa, Sulawesi Utara</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tamu</th>
                <th>Fasilitas</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Tamu</th>
                <th>Total (Rp)</th>
                <th>Status</th>
                <th>Pembayaran</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reservations as $res)
            <tr>
                <td>{{ $res->user?->name }}</td>
                <td>{{ $res->facility?->name }}</td>
                <td>{{ $res->check_in_date?->format('d/m/Y') }}</td>
                <td>{{ $res->check_out_date?->format('d/m/Y') }}</td>
                <td>{{ $res->guest_count }}</td>
                <td style="text-align:right">{{ number_format($res->total_amount, 0, ',', '.') }}</td>
                <td>
                    <span class="badge badge-{{ $res->status }}">{{ ucfirst($res->status) }}</span>
                </td>
                <td>{{ ucfirst($res->payment_status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan ini digenerate secara otomatis oleh Sistem Informasi Wawi Kadio Resort
    </div>
</body>
</html>
