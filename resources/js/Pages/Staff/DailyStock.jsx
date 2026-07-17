import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PackageOpen, Save, RefreshCw } from 'lucide-react';
import PrimaryButton from '@/Components/PrimaryButton';
import NumberInput from '@/Components/NumberInput';

export default function DailyStock({ items }) {
    const { data, setData, post, processing, isDirty } = useForm({
        stocks: items.map(item => ({
            id: item.id,
            current_stock: item.current_stock ?? 0,
            daily_stock: item.daily_stock ?? 0,
        }))
    });

    const handleStockChange = (index, value) => {
        const newStocks = [...data.stocks];
        newStocks[index].current_stock = parseInt(value) || 0;
        setData('stocks', newStocks);
    };



    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('staff.daily-stock.update'), {
            preserveScroll: true
        });
    };

    return (
        <AppLayout title="Input Stok Harian">
            <Head title="Stok Harian Cafe — Wawi Kadio" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex justify-between items-center bg-white p-6 rounded-3xl shadow-sm border border-stone-100">
                    <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center shrink-0">
                            <PackageOpen className="text-orange-600" size={24} />
                        </div>
                        <div>
                            <h2 className="text-2xl font-bold text-slate-900">Input Stok Harian</h2>
                            <p className="text-slate-500 text-sm">Update sisa porsi/stok harian untuk Cafe & Resto</p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-3xl shadow-sm border border-stone-200 overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-stone-50 border-b border-stone-200 text-stone-500 text-xs uppercase tracking-wider">
                                <th className="p-4 font-bold">Kategori</th>
                                <th className="p-4 font-bold">Nama Menu</th>
                                <th className="p-4 font-bold text-center">Sisa Stok Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-stone-100">
                            {items.map((item, index) => (
                                <tr key={item.id} className="hover:bg-stone-50/50 transition-colors">
                                    <td className="p-4">
                                        <span className="text-xs font-semibold text-orange-600 bg-orange-50 inline-block px-2 py-0.5 rounded">{item.category}</span>
                                    </td>
                                    <td className="p-4">
                                        <div className="font-bold text-slate-900">{item.name}</div>
                                    </td>

                                    <td className="p-4">
                                        <div className="flex justify-center">
                                            <NumberInput
                                                value={data.stocks[index].current_stock}
                                                onChange={(val) => handleStockChange(index, val)}
                                                min={0}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {items.length === 0 && (
                                <tr>
                                    <td colSpan="3" className="p-8 text-center text-slate-400">
                                        <PackageOpen size={48} className="mx-auto mb-3 opacity-20" />
                                        <p>Belum ada menu yang aktif.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    <div className="p-4 bg-stone-50 border-t border-stone-200 flex justify-end">
                        <PrimaryButton
                            type="submit"
                            disabled={processing || !isDirty}
                            className="bg-orange-600 hover:bg-orange-700 px-8 py-3 text-sm flex items-center gap-2"
                        >
                            <Save size={18} /> Simpan Stok
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
