<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan - Wawi Kadio</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        .header { background: #15803d; color: white; padding: 20px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; font-weight: bold; }
        .header p { font-size: 11px; opacity: 0.85; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 10px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #15803d; color: white; padding: 8px; text-align: left; font-size: 10px; }
        td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .text-emerald { color: #15803d; }
        .text-rose { color: #be123c; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Keuangan (Accounting) — Wawi Kadio Resort</h1>
        <p>Periode: {{ $periodFrom }} s/d {{ $periodTo }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="meta">
        <span>Total Pemasukan: <strong class="text-emerald">Rp {{ number_format($income, 0, ',', '.') }}</strong></span>
        <span>Total Pengeluaran: <strong class="text-rose">Rp {{ number_format($expense, 0, ',', '.') }}</strong></span>
        <span>Net: <strong>Rp {{ number_format($income - $expense, 0, ',', '.') }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Nominal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td>{{ $trx->transaction_date->format('d/m/Y') }}</td>
                <td>
                    @if($trx->type == 'income')
                        <span class="text-emerald">INCOME</span>
                    @else
                        <span class="text-rose">EXPENSE</span>
                    @endif
                </td>
                <td>{{ $trx->category }}</td>
                <td>{{ $trx->description }}</td>
                <td style="text-align:right">
                    @if($trx->type == 'income')
                        <span class="text-emerald">+{{ number_format($trx->amount, 0, ',', '.') }}</span>
                    @else
                        <span class="text-rose">-{{ number_format($trx->amount, 0, ',', '.') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan ini digenerate secara otomatis oleh Sistem Informasi Wawi Kadio Resort
    </div>
</body>
</html>
