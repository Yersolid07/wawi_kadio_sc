import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Save, AlertTriangle, CheckCircle, Wallet } from 'lucide-react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

export default function PosClosing({ initialCash, todaySales, expectedCash, previousClosing, today }) {
    const { data, setData, post, processing, errors } = useForm({
        closing_balance: expectedCash || 0,
        cash_100k: 0,
        cash_50k: 0,
        cash_20k: 0,
        cash_10k: 0,
        cash_5k: 0,
        cash_2k: 0,
        cash_1k: 0,
        coins: 0,
        note: ''
    });

    const [totalPhysicalCash, setTotalPhysicalCash] = useState(0);
    const [difference, setDifference] = useState(0);

    useEffect(() => {
        const total = 
            (parseInt(data.cash_100k) || 0) * 100000 +
            (parseInt(data.cash_50k) || 0) * 50000 +
            (parseInt(data.cash_20k) || 0) * 20000 +
            (parseInt(data.cash_10k) || 0) * 10000 +
            (parseInt(data.cash_5k) || 0) * 5000 +
            (parseInt(data.cash_2k) || 0) * 2000 +
            (parseInt(data.cash_1k) || 0) * 1000 +
            (parseInt(data.coins) || 0);

        setTotalPhysicalCash(total);
        setDifference(total - data.closing_balance);
    }, [
        data.cash_100k, data.cash_50k, data.cash_20k, 
        data.cash_10k, data.cash_5k, data.cash_2k, 
        data.cash_1k, data.coins, data.closing_balance
    ]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (difference !== 0) {
            if (!(await window.customConfirm(`Terdapat selisih kas sebesar Rp ${difference.toLocaleString('id-ID')}. Anda yakin ingin melanjutkan?`))) {
                return;
            }
        }
        post(route('staff.pos-closing.store'));
    };

    const denominations = [
        { key: 'cash_100k', label: 'Rp 100.000' },
        { key: 'cash_50k', label: 'Rp 50.000' },
        { key: 'cash_20k', label: 'Rp 20.000' },
        { key: 'cash_10k', label: 'Rp 10.000' },
        { key: 'cash_5k', label: 'Rp 5.000' },
        { key: 'cash_2k', label: 'Rp 2.000' },
        { key: 'cash_1k', label: 'Rp 1.000' },
    ];

    return (
        <AppLayout title="Tutup Kasir (POS Closing)">
            <Head title="POS Closing — Wawi Kadio" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center gap-4 bg-white p-6 rounded-3xl shadow-sm border border-stone-100">
                    <div className="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center shrink-0">
                        <Wallet className="text-indigo-600" size={24} />
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">Tutup Kasir / Setor Tunai</h2>
                        <p className="text-slate-500 text-sm">Hitung dan cocokkan fisik uang dengan sistem ({today})</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-3xl shadow-sm border border-stone-200 overflow-hidden p-6 space-y-8">
                    
                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-stone-50 rounded-2xl p-5 border border-stone-100">
                            <p className="text-sm font-bold text-slate-500 mb-1">Kas Masuk Sistem (Tunai)</p>
                            <p className="text-2xl font-black text-slate-900">Rp {parseFloat(data.closing_balance).toLocaleString('id-ID')}</p>
                        </div>
                        <div className="bg-emerald-50 rounded-2xl p-5 border border-emerald-100">
                            <p className="text-sm font-bold text-emerald-600 mb-1">Total Uang Fisik</p>
                            <p className="text-2xl font-black text-emerald-700">Rp {totalPhysicalCash.toLocaleString('id-ID')}</p>
                        </div>
                        <div className={`rounded-2xl p-5 border ${difference === 0 ? 'bg-indigo-50 border-indigo-100' : 'bg-rose-50 border-rose-100'}`}>
                            <p className={`text-sm font-bold mb-1 ${difference === 0 ? 'text-indigo-600' : 'text-rose-600'}`}>Selisih (Fisik - Sistem)</p>
                            <div className="flex items-center gap-2">
                                <p className={`text-2xl font-black ${difference === 0 ? 'text-indigo-700' : 'text-rose-700'}`}>
                                    Rp {difference.toLocaleString('id-ID')}
                                </p>
                                {difference === 0 ? <CheckCircle size={20} className="text-indigo-600" /> : <AlertTriangle size={20} className="text-rose-600" />}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="text-lg font-bold text-slate-800 border-b border-stone-100 pb-2">Rincian Lembar Uang Kertas</h3>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {denominations.map((denom) => (
                                <div key={denom.key}>
                                    <InputLabel value={denom.label} />
                                    <div className="relative mt-1">
                                        <TextInput
                                            type="number"
                                            min="0"
                                            className="block w-full pl-3 pr-12 text-center text-lg font-bold bg-stone-50"
                                            value={data[denom.key]}
                                            onChange={(e) => setData(denom.key, e.target.value)}
                                        />
                                        <span className="absolute inset-y-0 right-0 pr-3 flex items-center text-stone-400 font-medium">lbr</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="text-lg font-bold text-slate-800 border-b border-stone-100 pb-2">Total Koin (Rupiah)</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Masukkan Total Nilai Koin Keseluruhan" />
                                <div className="relative mt-1">
                                    <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-stone-500 font-bold">Rp</span>
                                    <TextInput
                                        type="number"
                                        min="0"
                                        className="block w-full pl-10 text-lg font-bold bg-stone-50"
                                        value={data.coins}
                                        onChange={(e) => setData('coins', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="text-lg font-bold text-slate-800 border-b border-stone-100 pb-2">Catatan Laporan</h3>
                        <div>
                            <InputLabel value="Catatan Opsional (Misal: Alasan jika ada selisih, kembalian kurang, dll)" />
                            <textarea
                                className="mt-1 block w-full rounded-xl border-stone-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-stone-50"
                                rows="3"
                                value={data.note}
                                onChange={e => setData('note', e.target.value)}
                            ></textarea>
                        </div>
                    </div>

                    <div className="pt-6 border-t border-stone-100 flex justify-end gap-3">
                        <PrimaryButton type="submit" disabled={processing} className="bg-indigo-600 hover:bg-indigo-700 px-8 py-3 text-sm flex items-center gap-2">
                            <Save size={18} /> Simpan Laporan POS
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
