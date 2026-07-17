<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Cafe - Wawi Kadio</title>
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
        <h1>Laporan Pemesanan Cafe — Wawi Kadio Resort</h1>
        <p>Periode: {{ $periodFrom }} s/d {{ $periodTo }} | Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="meta">
        <span>Total Pesanan: <strong>{{ $orders->count() }}</strong></span>
        <span>Total Pendapatan: <strong>Rp {{ number_format($revenue, 0, ',', '.') }}</strong></span>
        <span>Desa Tonsewer, Kabupaten Minahasa, Sulawesi Utara</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Pemesan</th>
                <th>Tipe</th>
                <th>Item & Qty</th>
                <th>Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
            <tr>
                <td>{{ explode('-', $order->id)[0] }}</td>
                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $order->user ? $order->user->name : $order->guest_name }}</td>
                <td>{{ str_replace('_', ' ', $order->order_type) }}</td>
                <td>
                    @foreach($order->items as $item)
                        {{ $item->menuItem->name ?? 'Item' }} (x{{ $item->quantity }})<br>
                    @endforeach
                </td>
                <td style="text-align:right">{{ number_format($order->total_amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan ini digenerate secara otomatis oleh Sistem Informasi Wawi Kadio Resort
    </div>
</body>
</html>
