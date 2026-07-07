import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { FileText, Download, TrendingUp, TrendingDown, CalendarDays, FileDown, FileSpreadsheet, DollarSign, ShoppingBag, Users } from 'lucide-react';
import { useState } from 'react';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, Legend } from 'recharts';

export default function Index({ stats, chartData, filters }) {
    const [periodFrom, setPeriodFrom] = useState(filters.period_from || '');
    const [periodTo, setPeriodTo] = useState(filters.period_to || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(
            route('admin.reports.index'),
            { period_from: periodFrom, period_to: periodTo },
            { preserveState: true }
        );
    };

    const formatPrice = (price) => {
        return parseFloat(price).toLocaleString('id-ID');
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'short', year: 'numeric'
        });
    };

    return (
        <AppLayout title="Laporan & Analitik">
            <Head title="Laporan Komprehensif — Wawi Kadio" />

            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <h2 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
                        <FileText className="text-emerald-500" /> Laporan Komprehensif
                    </h2>
                    
                    <div className="flex gap-2">
                        <a 
                            href={route('admin.reports.pdf', { period_from: periodFrom, period_to: periodTo })}
                            target="_blank"
                            className="flex items-center gap-2 bg-white border border-stone-200 hover:bg-stone-50 px-4 py-2 rounded-xl transition-colors font-semibold text-sm shadow-sm"
                        >
                            <FileDown size={18} className="text-red-500" /> Export PDF (Reservasi)
                        </a>
                        <a 
                            href={route('admin.reports.excel', { period_from: periodFrom, period_to: periodTo })}
                            className="flex items-center gap-2 bg-white border border-stone-200 hover:bg-stone-50 px-4 py-2 rounded-xl transition-colors font-semibold text-sm shadow-sm"
                        >
                            <FileSpreadsheet size={18} className="text-emerald-500" /> Export Excel
                        </a>
                    </div>
                </div>

                {/* Filter */}
                <div className="bg-white rounded-3xl border border-stone-100 p-6 shadow-sm">
                    <form onSubmit={handleFilter} className="flex flex-col md:flex-row gap-4 items-end">
                        <div className="flex-1 w-full">
                            <InputLabel htmlFor="period_from" value="Mulai Tanggal" />
                            <TextInput
                                id="period_from"
                                type="date"
                                className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-indigo-500 rounded-xl shadow-sm"
                                value={periodFrom}
                                onChange={(e) => setPeriodFrom(e.target.value)}
                                required
                            />
                        </div>
                        <div className="flex-1 w-full">
                            <InputLabel htmlFor="period_to" value="Sampai Tanggal" />
                            <TextInput
                                id="period_to"
                                type="date"
                                className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-indigo-500 rounded-xl shadow-sm"
                                value={periodTo}
                                onChange={(e) => setPeriodTo(e.target.value)}
                                required
                            />
                        </div>
                        <div className="w-full md:w-auto">
                            <PrimaryButton type="submit" className="w-full md:w-auto px-8 py-3 bg-slate-900 hover:bg-slate-800 rounded-xl">
                                Terapkan Filter
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-3xl p-6 shadow-md text-white">
                        <div className="flex items-center justify-between mb-4 opacity-80">
                            <span className="font-semibold text-sm">Total Pendapatan Kotor</span>
                            <DollarSign size={20} />
                        </div>
                        <h3 className="text-3xl font-black mb-1">Rp {formatPrice(stats.total_revenue)}</h3>
                        <p className="text-xs opacity-75">Dari Reservasi & Resto</p>
                    </div>

                    <div className="bg-gradient-to-br from-rose-500 to-red-600 rounded-3xl p-6 shadow-md text-white">
                        <div className="flex items-center justify-between mb-4 opacity-80">
                            <span className="font-semibold text-sm">Total Pengeluaran / HPP</span>
                            <TrendingDown size={20} />
                        </div>
                        <h3 className="text-3xl font-black mb-1">Rp {formatPrice(stats.total_expense)}</h3>
                        <p className="text-xs opacity-75">Estimasi Stok Terpakai</p>
                    </div>

                    <div className="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-3xl p-6 shadow-md text-white">
                        <div className="flex items-center justify-between mb-4 opacity-80">
                            <span className="font-semibold text-sm">Laba Bersih (Net Profit)</span>
                            <TrendingUp size={20} />
                        </div>
                        <h3 className="text-3xl font-black mb-1">Rp {formatPrice(stats.net_profit)}</h3>
                        <p className="text-xs opacity-75">Pendapatan - Pengeluaran</p>
                    </div>

                    <div className="bg-white rounded-3xl p-6 shadow-sm border border-stone-200">
                        <div className="flex items-center justify-between mb-4 text-slate-500">
                            <span className="font-semibold text-sm">Volume Aktivitas & Rincian</span>
                            <ShoppingBag size={20} />
                        </div>
                        <div className="space-y-2">
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Total Pengunjung:</span>
                                <span className="font-bold text-slate-900">{stats.total_visitors} Org</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Total Reservasi:</span>
                                <span className="font-bold text-slate-900">{stats.total_reservations}</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Pesanan Makanan:</span>
                                <span className="font-bold text-slate-900">{stats.total_food_orders}</span>
                            </div>
                            <div className="border-t border-stone-100 my-2 pt-2"></div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Fasilitas:</span>
                                <span className="font-bold text-slate-900">Rp {formatPrice(stats.revenue_reservations || 0)}</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Cafe (F&B):</span>
                                <span className="font-bold text-slate-900">Rp {formatPrice(stats.revenue_food_orders || 0)}</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600">Tiket:</span>
                                <span className="font-bold text-slate-900">Rp {formatPrice(stats.revenue_tickets || 0)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Charts */}
                <div className="bg-white rounded-3xl border border-stone-100 p-6 shadow-sm">
                    <h3 className="font-bold text-lg text-slate-900 mb-6">Tren Keuangan (Pemasukan vs Pengeluaran)</h3>
                    <div className="h-[400px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#10b981" stopOpacity={0.3}/>
                                        <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                                    </linearGradient>
                                    <linearGradient id="colorExpense" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#ef4444" stopOpacity={0.3}/>
                                        <stop offset="95%" stopColor="#ef4444" stopOpacity={0}/>
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="date" tick={{fontSize: 12, fill: '#64748b'}} tickLine={false} axisLine={false} />
                                <YAxis tickFormatter={(val) => `Rp ${val / 1000}k`} tick={{fontSize: 12, fill: '#64748b'}} tickLine={false} axisLine={false} />
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                                <Tooltip 
                                    formatter={(value) => `Rp ${formatPrice(value)}`}
                                    contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)'}}
                                />
                                <Legend />
                                <Area type="monotone" dataKey="revenue" name="Pemasukan" stroke="#10b981" strokeWidth={3} fillOpacity={1} fill="url(#colorRevenue)" />
                                <Area type="monotone" dataKey="expense" name="Pengeluaran" stroke="#ef4444" strokeWidth={3} fillOpacity={1} fill="url(#colorExpense)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="bg-white rounded-3xl border border-stone-100 p-6 shadow-sm">
                    <h3 className="font-bold text-lg text-slate-900 mb-6">Tren Laba Bersih (Profit)</h3>
                    <div className="h-[300px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                                <XAxis dataKey="date" tick={{fontSize: 12, fill: '#64748b'}} tickLine={false} axisLine={false} />
                                <YAxis tickFormatter={(val) => `Rp ${val / 1000}k`} tick={{fontSize: 12, fill: '#64748b'}} tickLine={false} axisLine={false} />
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                                <Tooltip 
                                    formatter={(value) => `Rp ${formatPrice(value)}`}
                                    contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)'}}
                                    cursor={{fill: '#f8fafc'}}
                                />
                                <Bar dataKey="profit" name="Laba Bersih" fill="#6366f1" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
