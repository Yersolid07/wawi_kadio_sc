<?php

namespace App\Exports;

use App\Models\FinancialTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AccountingExport implements FromCollection, WithHeadings, WithMapping
{
    protected $periodFrom;
    protected $periodTo;

    public function __construct($periodFrom, $periodTo)
    {
        $this->periodFrom = $periodFrom;
        $this->periodTo = $periodTo;
    }

    public function collection()
    {
        return FinancialTransaction::whereBetween('transaction_date', [$this->periodFrom, $this->periodTo . ' 23:59:59'])
            ->orderBy('transaction_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Tanggal',
            'Tipe',
            'Kategori',
            'Deskripsi',
            'Pemasukan (Rp)',
            'Pengeluaran (Rp)'
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->transaction_date->format('Y-m-d'),
            strtoupper($transaction->type),
            $transaction->category,
            $transaction->description,
            $transaction->type === 'income' ? $transaction->amount : 0,
            $transaction->type === 'expense' ? $transaction->amount : 0,
        ];
    }
}
