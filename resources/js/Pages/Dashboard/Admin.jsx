import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { TrendingUp, Users, CalendarDays, UtensilsCrossed, Star, Activity, ArrowUpRight } from 'lucide-react';

export default function Admin({ stats = {}, recentReservations = [], revenueChart = [], facilityOccupancy = [], recentReviews = [] }) {
    
    const formatPrice = (val) => {
        const n = parseFloat(val);
        if (isNaN(n)) return '-';
        return n.toLocaleString('id-ID');
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('id-ID', {
            month: 'short', day: 'numeric'
        });
    };

    const StatCard = ({ icon: Icon, label, value, subtext, colorClass, delay = 0 }) => (
        <motion.div 
            initial={{ opacity: 0, y: 20 }} 
            animate={{ opacity: 1, y: 0 }} 
            transition={{ delay, duration: 0.5 }}
            className="bg-white p-6 md:p-8 rounded-[2rem] border border-stone-100 shadow-sm relative overflow-hidden group hover:border-emerald-200 hover:shadow-2xl hover:shadow-emerald-900/5 transition-all duration-300 hover:-translate-y-1"
        >
            <div className={`absolute -right-4 -top-4 w-24 h-24 rounded-full opacity-10 transition-transform group-hover:scale-150 ${colorClass.split(' ')[0]}`}></div>
            <div className={`w-12 h-12 rounded-2xl flex items-center justify-center mb-4 ${colorClass}`}>
                <Icon size={24} />
            </div>
            <h4 className="text-slate-500 font-bold text-sm mb-2">{label}</h4>
            <p className="text-3xl font-black text-slate-900 mb-2 tracking-tight">{value}</p>
            {subtext && <p className="text-xs text-slate-400 font-semibold">{subtext}</p>}
        </motion.div>
    );

    return (
        <AppLayout title="Executive Dashboard">
            <Head title="Executive Dashboard — Wawi Kadio" />

            <div className="max-w-7xl mx-auto space-y-8">
                
                {/* Stats Overview */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard 
                        icon={TrendingUp} label="Pendapatan Bulan Ini" 
                        value={`Rp ${formatPrice(stats.revenue_month)}`} 
                        subtext={`Hari ini: Rp ${formatPrice(stats.revenue_today)}`}
                        colorClass="bg-emerald-100 text-emerald-700" 
                        delay={0.1}
                    />
                    <StatCard 
                        icon={CalendarDays} label="Reservasi Bulan Ini" 
                        value={stats.total_reservations} 
                        subtext={`${stats.confirmed_today} Check-in Hari ini`}
                        colorClass="bg-blue-100 text-blue-700" 
                        delay={0.2}
                    />
                    <StatCard 
                        icon={Users} label="Total Pelanggan" 
                        value={stats.total_customers} 
                        subtext="Pelanggan terdaftar"
                        colorClass="bg-indigo-100 text-indigo-700" 
                        delay={0.3}
                    />
                    <StatCard 
                        icon={Star} label="Rata-rata Rating" 
                        value={`${parseFloat(stats.average_rating || 0).toFixed(1)} / 5.0`} 
                        subtext="Dari semua ulasan"
                        colorClass="bg-amber-100 text-amber-700" 
                        delay={0.4}
                    />
                </div>

                <div className="grid lg:grid-cols-3 gap-8">
                    {/* Main Content Area */}
                    <div className="lg:col-span-2 space-y-8">
                        {/* Revenue Chart Placeholder */}
                        <motion.section 
                            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.5 }}
                            className="bg-white rounded-[2rem] border border-stone-100 p-8 shadow-sm hover:shadow-md transition-shadow"
                        >
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                    <Activity size={20} className="text-emerald-500" /> Tren Pendapatan
                                </h3>
                                <Link href={route('admin.reports.index')} className="text-sm font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                    Lihat Detail Laporan <ArrowUpRight size={16} />
                                </Link>
                            </div>
                            
                            <div className="h-64 flex items-end gap-2 px-2 pb-4">
                                {/* Simplified CSS Bar Chart for visual appeal without heavy charting libraries */}
                                {revenueChart.length > 0 ? revenueChart.map((point, idx) => {
                                    const maxVal = Math.max(...revenueChart.map(p => parseFloat(p.total)));
                                    const heightPercent = maxVal > 0 ? (parseFloat(point.total) / maxVal) * 100 : 0;
                                    return (
                                        <div key={idx} className="flex-1 flex flex-col justify-end items-center group relative">
                                            <div 
                                                className="w-full max-w-[2.5rem] bg-emerald-100 rounded-t-xl group-hover:bg-emerald-400 transition-colors relative shadow-sm"
                                                style={{ height: `${heightPercent}%`, minHeight: '6px' }}
                                            >
                                                <div className="absolute -top-12 left-1/2 -translate-x-1/2 bg-slate-900 text-white font-bold text-[10px] py-1.5 px-3 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10 shadow-lg">
                                                    Rp {formatPrice(point.total)}
                                                </div>
                                            </div>
                                            <span className="text-[10px] text-slate-400 mt-2 rotate-45 origin-left h-8 overflow-hidden">{formatDate(point.date)}</span>
                                        </div>
                                    );
                                }) : (
                                    <div className="w-full h-full flex flex-col items-center justify-center text-slate-400">
                                        <Activity size={32} className="mb-2 opacity-50" />
                                        <p className="text-sm">Belum ada data pendapatan bulan ini.</p>
                                    </div>
                                )}
                            </div>
                        </motion.section>

                        {/* Recent Bookings Table */}
                        <motion.section 
                            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.6 }}
                            className="bg-white rounded-[2rem] border border-stone-100 p-8 shadow-sm hover:shadow-md transition-shadow overflow-hidden"
                        >
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-bold text-slate-900">Reservasi Terbaru</h3>
                                <Link href={route('admin.reservations.index')} className="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Semua Data</Link>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm whitespace-nowrap">
                                    <thead>
                                        <tr className="text-slate-400 border-b border-stone-100">
                                            <th className="font-semibold pb-3 pl-2">Pelanggan</th>
                                            <th className="font-semibold pb-3">Fasilitas</th>
                                            <th className="font-semibold pb-3">Tgl Masuk</th>
                                            <th className="font-semibold pb-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100">
                                        {recentReservations.length > 0 ? recentReservations.map(res => (
                                            <tr key={res.id} className="hover:bg-stone-50 transition-colors">
                                                <td className="py-4 pl-2">
                                                    <p className="font-bold text-slate-900">{res.user?.name}</p>
                                                </td>
                                                <td className="py-4 text-slate-600">{res.facility?.name}</td>
                                                <td className="py-4 text-slate-600">{formatDate(res.check_in_date)}</td>
                                                <td className="py-4">
                                                    <span className={`px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider
                                                        ${res.status === 'confirmed' ? 'bg-emerald-100 text-emerald-700' : 
                                                        res.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700'}`}>
                                                        {res.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="4" className="py-8 text-center text-slate-500">Belum ada reservasi baru.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </motion.section>
                    </div>

                    {/* Right Column */}
                    <div className="space-y-8">
                        {/* Occupancy Indicator */}
                        <motion.section 
                            initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.5 }}
                            className="bg-white rounded-[2rem] border border-stone-100 p-8 shadow-sm hover:shadow-md transition-shadow"
                        >
                            <h3 className="text-lg font-bold text-slate-900 mb-6">Okupansi Fasilitas</h3>
                            <div className="space-y-5">
                                {facilityOccupancy.length > 0 ? facilityOccupancy.map(fac => (
                                    <div key={fac.id}>
                                        <div className="flex justify-between items-end mb-2">
                                            <p className="font-bold text-sm text-slate-700">{fac.name}</p>
                                            <p className="text-xs font-bold text-emerald-600">{fac.active_reservations_count} Booking</p>
                                        </div>
                                        <div className="w-full h-2 bg-stone-100 rounded-full overflow-hidden">
                                            <div 
                                                className="h-full bg-emerald-400 rounded-full" 
                                                style={{ width: `${Math.min((fac.active_reservations_count * 10), 100)}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                )) : (
                                    <p className="text-sm text-slate-500 text-center">Data tidak tersedia.</p>
                                )}
                            </div>
                        </motion.section>

                        {/* Recent Reviews */}
                        <motion.section 
                            initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.6 }}
                            className="bg-white rounded-[2rem] border border-stone-100 p-8 shadow-sm hover:shadow-md transition-shadow"
                        >
                            <h3 className="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2">
                                <Star size={20} className="text-amber-400" /> Ulasan Terbaru
                            </h3>
                            <div className="space-y-4">
                                {recentReviews.length > 0 ? recentReviews.map(review => (
                                    <div key={review.id} className="p-4 bg-stone-50 rounded-2xl border border-stone-100">
                                        <div className="flex justify-between items-start mb-2">
                                            <p className="font-bold text-sm text-slate-900">{review.user?.name}</p>
                                            <div className="flex items-center gap-1 text-amber-500">
                                                <Star size={12} className="fill-amber-500" />
                                                <span className="text-xs font-bold">{review.rating}</span>
                                            </div>
                                        </div>
                                        <p className="text-xs text-slate-600 line-clamp-3 leading-relaxed">{review.comment}</p>
                                        <p className="text-[10px] text-slate-400 mt-2 font-medium">{review.reservation?.facility?.name || '-'}</p>
                                    </div>
                                )) : (
                                    <p className="text-sm text-slate-500 text-center py-4">Belum ada ulasan.</p>
                                )}
                            </div>
                        </motion.section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
