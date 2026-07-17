<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Inventori - Wawi Kadio</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Transaksi Inventori — Wawi Kadio Resort</h1>
        <p>Periode: {{ $periodFrom }} s/d {{ $periodTo }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="meta">
        <span>Total Transaksi: <strong>{{ $transactions->count() }}</strong></span>
        <span>Total Nilai Transaksi: <strong>Rp {{ number_format($totalCost, 0, ',', '.') }}</strong></span>
        <span>Desa Tonsewer, Kabupaten Minahasa, Sulawesi Utara</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama Bahan Baku</th>
                <th>Tipe</th>
                <th>Jumlah</th>
                <th>Stok Akhir</th>
                <th>Total Biaya/Rugi (Rp)</th>
                <th>Oleh</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td>{{ $trx->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $trx->inventory->name ?? 'Unknown' }}</td>
                <td>{{ strtoupper($trx->type) }}</td>
                <td>{{ $trx->quantity }} {{ $trx->inventory->unit ?? '' }}</td>
                <td>{{ $trx->stock_after }} {{ $trx->inventory->unit ?? '' }}</td>
                <td style="text-align:right">{{ number_format($trx->total_cost, 0, ',', '.') }}</td>
                <td>{{ $trx->user->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan ini digenerate secara otomatis oleh Sistem Informasi Wawi Kadio Resort
    </div>
</body>
</html>
