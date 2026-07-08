import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { ChefHat, CheckCircle2, Clock, PlayCircle, Maximize, ArrowLeft, Flame, AlertCircle } from 'lucide-react';

// Custom relative time formatter
const getRelativeTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return `${diffInSeconds} detik yang lalu`;
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} menit yang lalu`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} jam yang lalu`;
    return `${Math.floor(diffInSeconds / 86400)} hari yang lalu`;
};

// Calculate if order is delayed (e.g. > 15 minutes)
const getDelayStatus = (dateString) => {
    const date = new Date(dateString);
    const diffInMinutes = Math.floor((new Date() - date) / 60000);
    
    if (diffInMinutes > 20) return 'critical';
    if (diffInMinutes > 10) return 'warning';
    return 'normal';
};

export default function KDS({ orders }) {
    const [currentTime, setCurrentTime] = useState(new Date());
    const [layoutDirection, setLayoutDirection] = useState('horizontal');

    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000);
        
        // Auto-refresh KDS every 10 seconds
        const poller = setInterval(() => {
            router.reload({ only: ['orders'], preserveScroll: true, preserveState: true });
        }, 10000);

        return () => {
            clearInterval(timer);
            clearInterval(poller);
        };
    }, []);

    const updateStatus = (orderId, newStatus) => {
        router.patch(route('staff.food-orders.status', orderId), { status: newStatus }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const pendingOrders = orders.filter(o => o.status === 'pending');
    const preparingOrders = orders.filter(o => o.status === 'preparing');
    const readyOrders = orders.filter(o => o.status === 'ready');

    const toggleFullScreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    };

    const updateTimer = (orderId, minutesToAdd) => {
        const order = orders.find(o => o.id === orderId);
        if (!order) return;
        
        let newDate;
        if (order.estimated_ready_at) {
            newDate = new Date(order.estimated_ready_at);
        } else {
            // Default 15 minutes from now if empty
            newDate = new Date();
            newDate.setMinutes(newDate.getMinutes() + 15);
        }
        
        newDate.setMinutes(newDate.getMinutes() + minutesToAdd);
        
        // Convert to ISO string for backend
        const isoString = newDate.toISOString();
        
        router.patch(route('staff.food-orders.timer', orderId), { estimated_ready_at: isoString }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const OrderCard = ({ order, status, nextAction, nextStatus, nextIcon: Icon, nextColor, bgColor }) => {
        const delayStatus = status !== 'ready' ? getDelayStatus(order.created_at) : 'normal';
        
        // Calculate remaining time for timer
        let timerDisplay = null;
        let isOverdue = false;
        
        if (status === 'preparing') {
            if (order.estimated_ready_at) {
                const estDate = new Date(order.estimated_ready_at);
                const diffSec = Math.floor((estDate - currentTime) / 1000);
                
                if (diffSec < 0) {
                    isOverdue = true;
                    const absSec = Math.abs(diffSec);
                    const min = Math.floor(absSec / 60);
                    const sec = absSec % 60;
                    timerDisplay = `TERLAMBAT ${min}:${sec.toString().padStart(2, '0')}`;
                } else {
                    const min = Math.floor(diffSec / 60);
                    const sec = diffSec % 60;
                    timerDisplay = `${min}:${sec.toString().padStart(2, '0')}`;
                }
            } else {
                timerDisplay = "Set Waktu";
            }
        }
        
        return (
            <div className={`rounded-2xl border-2 shadow-2xl overflow-hidden flex flex-col transition-all duration-300 ${
                delayStatus === 'critical' ? 'border-rose-500 bg-rose-950/40 animate-pulse' : 
                delayStatus === 'warning' ? 'border-amber-500 bg-amber-950/40' : 
                `border-stone-800 ${bgColor}`
            }`}>
                {/* Header */}
                <div className={`px-5 py-3 border-b-2 flex justify-between items-center ${
                    delayStatus === 'critical' ? 'border-rose-500/50 bg-rose-600 text-white' :
                    delayStatus === 'warning' ? 'border-amber-500/50 bg-amber-600 text-white' :
                    status === 'pending' ? 'border-orange-500/30 bg-orange-500/20 text-orange-400' :
                    status === 'preparing' ? 'border-sky-500/30 bg-sky-500/20 text-sky-400' :
                    'border-emerald-500/30 bg-emerald-500/20 text-emerald-400'
                }`}>
                    <div className="flex items-center gap-2">
                        {delayStatus === 'critical' && <Flame className="animate-bounce" />}
                        <span className="text-xl font-black tracking-widest">#{order.id.slice(0,4).toUpperCase()}</span>
                    </div>
                    <span className="text-sm font-bold flex items-center gap-1 bg-black/30 px-3 py-1 rounded-full">
                        <Clock size={16} /> 
                        {getRelativeTime(order.created_at)}
                    </span>
                </div>
                
                {/* Body */}
                <div className="p-5 flex-1 flex flex-col">
                    <div className="mb-4 flex items-center justify-between border-b border-white/10 pb-4">
                        <div>
                            <div className="text-stone-400 text-xs mb-1 uppercase tracking-widest font-black">Tujuan</div>
                            <div className="text-white font-black text-2xl leading-none">
                                {order.order_type === 'room_service' && order.reservation ? order.reservation.facility.name :
                                 order.order_type === 'dine_in' ? (order.table_number ? `MEJA ${order.table_number}` : 'DINE IN') : 
                                 'TAKEAWAY'}
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-stone-400 text-xs mb-1 uppercase tracking-widest font-black">Tamu</div>
                            <div className="text-stone-300 font-bold text-lg leading-none">{order.guest_name || order.user?.name || 'Walk-in'}</div>
                        </div>
                    </div>

                    <div className="space-y-3 flex-1">
                        {order.items.map(item => (
                            <div key={item.id} className="flex gap-4 items-start">
                                <span className="font-black text-3xl w-10 text-center text-stone-500 leading-none shrink-0">{item.quantity}<span className="text-lg">x</span></span>
                                <div className="flex-1">
                                    <div className="font-black text-2xl text-white leading-tight mb-1">{item.menu_item.name}</div>
                                    {item.notes && (
                                        <div className="bg-yellow-400 text-black px-3 py-2 rounded-lg font-black text-lg flex items-start gap-2 shadow-[0_0_15px_rgba(250,204,21,0.3)] animate-in slide-in-from-left-2">
                                            <AlertCircle size={20} className="shrink-0 mt-0.5" />
                                            <span className="leading-tight uppercase">{item.notes}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    
                    {order.notes && (
                        <div className="mt-5 p-4 bg-rose-500/20 border-2 border-rose-500 rounded-xl text-rose-200 font-bold text-lg flex items-start gap-3">
                            <AlertCircle size={24} className="text-rose-400 shrink-0" />
                            <span className="uppercase">Catatan Nota: {order.notes}</span>
                        </div>
                    )}

                    {status === 'preparing' && (
                        <div className="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
                            <div className="flex gap-2">
                                <button onClick={() => updateTimer(order.id, -1)} className="w-10 h-10 rounded-xl bg-stone-800 text-stone-300 font-black hover:bg-stone-700 flex items-center justify-center">-1m</button>
                                <button onClick={() => updateTimer(order.id, 1)} className="w-10 h-10 rounded-xl bg-stone-800 text-stone-300 font-black hover:bg-stone-700 flex items-center justify-center">+1m</button>
                                {!order.estimated_ready_at && (
                                    <button onClick={() => updateTimer(order.id, 15)} className="px-3 h-10 rounded-xl bg-stone-800 text-stone-300 font-black hover:bg-stone-700 flex items-center justify-center">15m</button>
                                )}
                            </div>
                            <div className={`font-mono font-black text-2xl tracking-tighter ${isOverdue ? 'text-rose-500 animate-pulse' : 'text-sky-400'}`}>
                                {timerDisplay}
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer Action */}
                <button 
                    onClick={() => updateStatus(order.id, nextStatus)}
                    className={`w-full py-5 font-black text-2xl flex items-center justify-center gap-3 transition-all uppercase tracking-wider ${nextColor}`}
                >
                    <Icon size={28} /> {nextAction}
                </button>
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-stone-950 font-sans text-stone-200 overflow-hidden flex flex-col">
            <Head title="Kitchen Display System - POS" />

            {/* Top Bar */}
            <header className="bg-stone-900 border-b border-stone-800 px-6 py-4 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-6">
                    <a href={route('dashboard')} className="w-12 h-12 bg-stone-800 hover:bg-stone-700 rounded-full flex items-center justify-center text-stone-300 transition-colors">
                        <ArrowLeft size={24} />
                    </a>
                    <div className="flex items-center gap-3 text-emerald-500">
                        <ChefHat size={40} />
                        <div>
                            <h1 className="text-3xl font-black uppercase tracking-widest text-white leading-none">WAWI KDS</h1>
                            <p className="text-emerald-500/70 font-bold text-sm tracking-widest">KITCHEN DISPLAY SYSTEM</p>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-6">
                    <div className="flex gap-4">
                        <div className="text-center">
                            <div className="text-3xl font-black text-orange-500 leading-none">{pendingOrders.length}</div>
                            <div className="text-[10px] font-bold text-stone-500 uppercase tracking-widest">Baru</div>
                        </div>
                        <div className="text-center">
                            <div className="text-3xl font-black text-sky-500 leading-none">{preparingOrders.length}</div>
                            <div className="text-[10px] font-bold text-stone-500 uppercase tracking-widest">Masak</div>
                        </div>
                    </div>
                    <div className="h-10 w-px bg-stone-700"></div>
                    <div className="text-4xl font-black font-mono text-stone-200 tracking-tighter">
                        {currentTime.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                    </div>
                    <button 
                        onClick={() => setLayoutDirection(prev => prev === 'horizontal' ? 'vertical' : 'horizontal')}
                        className="w-12 h-12 bg-stone-800 hover:bg-stone-700 rounded-full flex items-center justify-center text-stone-400 hover:text-white transition-colors" title="Toggle Layout"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" y1="12" x2="21" y2="12"/></svg>
                    </button>
                    <button onClick={toggleFullScreen} className="w-12 h-12 bg-stone-800 hover:bg-stone-700 rounded-full flex items-center justify-center text-stone-400 hover:text-white transition-colors" title="Layar Penuh">
                        <Maximize size={24} />
                    </button>
                </div>
            </header>

            {/* Kanban Board */}
            <main className={`p-6 flex-1 flex gap-6 ${
                layoutDirection === 'horizontal' 
                    ? 'flex-col lg:flex-row h-auto lg:h-[calc(100vh-88px)] overflow-y-auto lg:overflow-hidden' 
                    : 'flex-col h-auto overflow-y-auto'
            }`}>
                
                {/* Pending Column */}
                <div className={`flex-1 flex flex-col bg-stone-900/40 rounded-[2rem] border border-stone-800/50 overflow-hidden shrink-0 ${
                    layoutDirection === 'horizontal' ? 'h-[500px] lg:h-full' : 'h-[600px]'
                }`}>
                    <div className="bg-orange-500/10 px-6 py-5 border-b-2 border-orange-500 flex justify-between items-center shrink-0">
                        <h2 className="font-black text-2xl text-orange-400 uppercase tracking-widest flex items-center gap-3">
                            <Clock size={28} /> PESANAN MASUK
                        </h2>
                        <span className="bg-orange-500 text-white font-black px-4 py-1 rounded-xl text-xl shadow-[0_0_15px_rgba(249,115,22,0.5)]">{pendingOrders.length}</span>
                    </div>
                    <div className="flex-1 p-6 overflow-y-auto space-y-6 no-scrollbar relative">
                        {pendingOrders.map(order => (
                            <OrderCard 
                                key={order.id} 
                                order={order} 
                                status="pending"
                                nextAction="MULAI MASAK"
                                nextStatus="preparing"
                                nextIcon={PlayCircle}
                                nextColor="bg-orange-500 hover:bg-orange-400 text-white shadow-[0_0_20px_rgba(249,115,22,0.4)]"
                                bgColor="bg-stone-900"
                            />
                        ))}
                        {pendingOrders.length === 0 && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-stone-600">
                                <Clock size={80} className="mb-6 opacity-20" />
                                <div className="font-black text-2xl uppercase tracking-widest opacity-40">Tidak Ada Pesanan</div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Preparing Column */}
                <div className={`flex-1 flex flex-col bg-stone-900/40 rounded-[2rem] border border-stone-800/50 overflow-hidden shrink-0 ${
                    layoutDirection === 'horizontal' ? 'h-[500px] lg:h-full' : 'h-[600px]'
                }`}>
                    <div className="bg-sky-500/10 px-6 py-5 border-b-2 border-sky-500 flex justify-between items-center shrink-0">
                        <h2 className="font-black text-2xl text-sky-400 uppercase tracking-widest flex items-center gap-3">
                            <Flame size={28} /> SEDANG DIMASAK
                        </h2>
                        <span className="bg-sky-500 text-white font-black px-4 py-1 rounded-xl text-xl shadow-[0_0_15px_rgba(14,165,233,0.5)]">{preparingOrders.length}</span>
                    </div>
                    <div className="flex-1 p-6 overflow-y-auto space-y-6 no-scrollbar relative">
                        {preparingOrders.map(order => (
                            <OrderCard 
                                key={order.id} 
                                order={order} 
                                status="preparing"
                                nextAction="SELESAI (SIAP SAJI)"
                                nextStatus="ready"
                                nextIcon={CheckCircle2}
                                nextColor="bg-sky-500 hover:bg-sky-400 text-white shadow-[0_0_20px_rgba(14,165,233,0.4)]"
                                bgColor="bg-stone-900"
                            />
                        ))}
                        {preparingOrders.length === 0 && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-stone-600">
                                <Flame size={80} className="mb-6 opacity-20" />
                                <div className="font-black text-2xl uppercase tracking-widest opacity-40">Dapur Kosong</div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Ready Column */}
                <div className={`flex-1 flex flex-col bg-stone-900/40 rounded-[2rem] border border-stone-800/50 overflow-hidden shrink-0 ${
                    layoutDirection === 'horizontal' ? 'h-[500px] lg:h-full' : 'h-[600px]'
                }`}>
                    <div className="bg-emerald-500/10 px-6 py-5 border-b-2 border-emerald-500 flex justify-between items-center shrink-0">
                        <h2 className="font-black text-2xl text-emerald-400 uppercase tracking-widest flex items-center gap-3">
                            <CheckCircle2 size={28} /> SIAP DIANTAR
                        </h2>
                        <span className="bg-emerald-500 text-white font-black px-4 py-1 rounded-xl text-xl shadow-[0_0_15px_rgba(16,185,129,0.5)]">{readyOrders.length}</span>
                    </div>
                    <div className="flex-1 p-6 overflow-y-auto space-y-6 no-scrollbar relative">
                        {readyOrders.map(order => (
                            <OrderCard 
                                key={order.id} 
                                order={order} 
                                status="ready"
                                nextAction="DISERAHKAN KE TAMU"
                                nextStatus="delivered"
                                nextIcon={CheckCircle2}
                                nextColor="bg-stone-800 hover:bg-stone-700 text-stone-300"
                                bgColor="bg-stone-900/50"
                            />
                        ))}
                        {readyOrders.length === 0 && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center text-stone-600">
                                <CheckCircle2 size={80} className="mb-6 opacity-20" />
                                <div className="font-black text-2xl uppercase tracking-widest opacity-40">Semua Terantar</div>
                            </div>
                        )}
                    </div>
                </div>

            </main>
        </div>
    );
}
