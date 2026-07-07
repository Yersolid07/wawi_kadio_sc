import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Search, Check, X, Eye, Clock, Filter, ChevronDown } from 'lucide-react';

const statusConfig = {
    pending: { label: 'Menunggu', color: 'bg-yellow-100 text-yellow-700 border-yellow-200', actions: ['confirmed', 'cancelled'] },
    confirmed: { label: 'Dikonfirmasi', color: 'bg-blue-100 text-blue-700 border-blue-200', actions: ['completed', 'cancelled'] },
    completed: { label: 'Selesai', color: 'bg-green-100 text-green-700 border-green-200', actions: [] },
    cancelled: { label: 'Dibatalkan', color: 'bg-red-100 text-red-700 border-red-200', actions: [] },
};

const paymentStatusConfig = {
    unpaid: { label: 'Belum Bayar', color: 'text-red-600' },
    paid: { label: 'Lunas', color: 'text-green-600' },
    refunded: { label: 'Direfund', color: 'text-gray-600' },
};

function StatusBadge({ status }) {
    const cfg = statusConfig[status] || {};
    return (
        <span className={`px-2.5 py-1 rounded-full text-xs font-medium border ${cfg.color}`}>
            {cfg.label}
        </span>
    );
}

function ReservationRow({ reservation, onStatusChange }) {
    const [statusOpen, setStatusOpen] = useState(false);
    const actions = statusConfig[reservation.status]?.actions || [];

    return (
        <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <td className="px-4 py-3">
                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{reservation.user?.name}</div>
                <div className="text-xs text-gray-400">{reservation.user?.phone}</div>
            </td>
            <td className="px-4 py-3">
                <div className="text-sm text-gray-700 dark:text-gray-300">{reservation.facility?.name}</div>
                <div className="text-xs text-gray-400 capitalize">{reservation.facility?.type}</div>
            </td>
            <td className="px-4 py-3">
                <div className="text-sm text-gray-700 dark:text-gray-300">
                    {new Date(reservation.check_in_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                </div>
                <div className="text-xs text-gray-400">
                    s/d {new Date(reservation.check_out_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}
                </div>
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-col gap-1">
                    <StatusBadge status={reservation.status} />
                    <span className={`text-xs font-medium ${paymentStatusConfig[reservation.payment_status]?.color}`}>
                        {paymentStatusConfig[reservation.payment_status]?.label}
                    </span>
                </div>
            </td>
            <td className="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-200 text-right">
                Rp {Number(reservation.total_amount).toLocaleString('id-ID')}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1 justify-end">
                    <Link
                        href={route('admin.reservations.show', reservation.id)}
                        className="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                    >
                        <Eye size={15} />
                    </Link>
                    {actions.length > 0 && (
                        <div className="relative">
                            <button
                                onClick={() => setStatusOpen(!statusOpen)}
                                className="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors"
                            >
                                Ubah Status <ChevronDown size={12} />
                            </button>
                            {statusOpen && (
                                <div className="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10">
                                    {actions.map(action => (
                                        <button
                                            key={action}
                                            onClick={() => {
                                                onStatusChange(reservation, action);
                                                setStatusOpen(false);
                                            }}
                                            className="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700 capitalize text-gray-700 dark:text-gray-300"
                                        >
                                            {action === 'confirmed' ? '✅ Konfirmasi' :
                                             action === 'completed' ? '🏁 Selesaikan' :
                                             '❌ Batalkan'}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </td>
        </tr>
    );
}

export default function AdminReservationsIndex({ reservations, filters, facilities, stats }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [facilityId, setFacilityId] = useState(filters?.facility_id || '');

    const applyFilter = () => {
        router.get(route('admin.reservations.index'), { search, status, facility_id: facilityId }, { preserveState: true });
    };

    const handleStatusChange = (reservation, newStatus) => {
        if (confirm(`Ubah status reservasi menjadi "${newStatus}"?`)) {
            router.patch(route('admin.reservations.status', reservation.id), { status: newStatus });
        }
    };

    return (
        <AppLayout title="Manajemen Reservasi">
            <Head title="Reservasi — Admin Wawi Kadio" />

            {/* Stats */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                {[
                    { label: 'Total', value: stats?.total, color: 'text-gray-700' },
                    { label: 'Menunggu', value: stats?.pending, color: 'text-yellow-600' },
                    { label: 'Dikonfirmasi', value: stats?.confirmed, color: 'text-blue-600' },
                    { label: 'Check-in Hari Ini', value: stats?.today, color: 'text-green-600' },
                ].map(s => (
                    <div key={s.label} className="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700 text-center">
                        <div className={`text-2xl font-bold ${s.color}`}>{s.value ?? 0}</div>
                        <div className="text-xs text-gray-500 mt-0.5">{s.label}</div>
                    </div>
                ))}
            </div>

            {/* Filters */}
            <div className="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700 mb-5 flex flex-wrap gap-3">
                <div className="flex-1 min-w-[180px] relative">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && applyFilter()}
                        placeholder="Cari nama tamu..."
                        className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none"
                    />
                </div>
                <select value={status} onChange={e => setStatus(e.target.value)}
                    className="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none">
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu</option>
                    <option value="confirmed">Dikonfirmasi</option>
                    <option value="completed">Selesai</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <select value={facilityId} onChange={e => setFacilityId(e.target.value)}
                    className="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none">
                    <option value="">Semua Fasilitas</option>
                    {facilities?.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                </select>
                <button onClick={applyFilter}
                    className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
                    Filter
                </button>
            </div>

            {/* Table */}
            <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tamu</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fasilitas</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                            {reservations.data?.map(res => (
                                <ReservationRow key={res.id} reservation={res} onStatusChange={handleStatusChange} />
                            ))}
                            {!reservations.data?.length && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-gray-400 text-sm">
                                        Tidak ada reservasi ditemukan
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {reservations.last_page > 1 && (
                    <div className="flex justify-center gap-1.5 p-4 border-t border-gray-100 dark:border-gray-700">
                        {reservations.links?.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || '#'}
                                className={`px-3 py-1.5 rounded-lg text-xs transition-colors ${
                                    link.active ? 'bg-green-600 text-white' :
                                    link.url ? 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200' :
                                    'bg-gray-100 text-gray-400 cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                preserveScroll
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
