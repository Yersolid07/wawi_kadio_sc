import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, ShoppingCart, Plus, Minus, Send, UtensilsCrossed, AlertCircle, Info, CreditCard, Banknote } from 'lucide-react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { useState, useMemo, useEffect } from 'react';

export default function Create({ menuItems, reservationId, activeReservations = [], qrCodes = [], isAuthenticated, user, paymentChannels = [] }) {
    // Check URL parameters for QR Code ordering
    const params = new URLSearchParams(window.location.search);
    const locationType = params.get('location_type');
    const qrTableNumber = params.get('table_number');
    const qrFacilityId = params.get('location_id');

    // Load cart from localStorage or start empty
    const [cart, setCart] = useState(() => {
        try {
            const saved = sessionStorage.getItem('wawi_cart');
            return saved ? JSON.parse(saved) : {};
        } catch (e) {
            return {};
        }
    });

    // Determine initial order type and reservation/table based on QR or active reservations
    let initialOrderType = 'dine_in';
    let initialReservationId = '';
    let initialTableNumber = '';

    if (locationType === 'table') {
        initialOrderType = 'dine_in';
        initialTableNumber = qrTableNumber || '';
    } else if (locationType === 'facility') {
        initialOrderType = 'dine_in';
        const facilityQr = qrCodes.find(q => q.location_type === 'facility' && q.location_id === qrFacilityId);
        initialTableNumber = facilityQr ? facilityQr.label : `Fasilitas ID: ${qrFacilityId}`;
    } else if (reservationId) {
        initialOrderType = 'room_service';
        initialReservationId = reservationId;
    } else if (activeReservations.length > 0) {
        initialOrderType = 'room_service';
        initialReservationId = activeReservations[0].id;
    }

    const { data, setData, post, processing, errors } = useForm({
        order_type: initialOrderType,
        table_number: initialTableNumber,
        notes: '',
        reservation_id: initialReservationId,
        items: [],
        customer_name: '',
        customer_phone: '',
        payment_method: '',
        payment_channel: '',
    });

    // Save cart to localStorage whenever it changes
    useEffect(() => {
        sessionStorage.setItem('wawi_cart', JSON.stringify(cart));
        
        // Sync with form data
        const itemsArray = Object.values(cart).map(cartItem => ({
            menu_item_id: cartItem.id,
            quantity: cartItem.quantity
        }));
        setData('items', itemsArray);
    }, [cart]);

    const formatPrice = (price) => parseFloat(price).toLocaleString('id-ID');

    const updateCart = (item, delta) => {
        // Prevent adding out-of-stock items
        if (delta > 0 && item.is_out_of_stock) return;

        setCart(prev => {
            const newCart = { ...prev };
            const currentQty = newCart[item.id]?.quantity || 0;
            const newQty = Math.max(0, currentQty + delta);

            // Respect stock limit
            const maxQty = (item.daily_stock !== null && item.current_stock > 0)
                ? item.current_stock
                : Infinity;
            const clampedQty = Math.min(newQty, maxQty);

            if (clampedQty === 0) {
                delete newCart[item.id];
            } else {
                newCart[item.id] = { ...item, quantity: clampedQty };
            }

            return newCart;
        });
    };

    const cartTotal = useMemo(() => {
        return Object.values(cart).reduce((sum, item) => {
            const priceToUse = item.final_price !== undefined ? item.final_price : item.price;
            return sum + (priceToUse * item.quantity);
        }, 0);
    }, [cart]);

    const submit = (e) => {
        e.preventDefault();
        post(route('customer.orders.store'), {
            onSuccess: () => {
                sessionStorage.removeItem('wawi_cart');
                setCart({});
            }
        });
    };

    return (
        <AppLayout title="Pesan Makanan">
            <Head title="Pesan Makanan — Wawi Kadio" />

            <div className="max-w-7xl mx-auto flex flex-col lg:flex-row gap-8">
                
                {/* Left: Menu Catalog */}
                <div className="flex-1 space-y-8">
                    <div className="flex items-center gap-4">
                        <Link href={route('customer.orders.index')} className="p-2 hover:bg-white rounded-xl transition-colors">
                            <ArrowLeft size={20} className="text-slate-500" />
                        </Link>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Pesan Makanan
                        </h2>
                    </div>

                    {Object.entries(menuItems).map(([category, items]) => (
                        <div key={category} className="space-y-4">
                            <h3 className="text-xl font-bold text-slate-800 capitalize flex items-center gap-2">
                                {category}
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {items.map(item => {
                                    const qty = cart[item.id]?.quantity || 0;
                                    const isOutOfStock = item.is_out_of_stock;
                                    const stockLeft = item.daily_stock !== null ? item.current_stock : null;
                                    const isLowStock = stockLeft !== null && stockLeft > 0 && stockLeft <= 5;
                                    return (
                                        <div key={item.id} className={`bg-white rounded-2xl border border-stone-100 p-4 shadow-sm flex gap-4 relative ${
                                            isOutOfStock ? 'opacity-60' : ''
                                        }`}>
                                            {isOutOfStock && (
                                                <div className="absolute top-3 right-3 bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10">
                                                    Habis
                                                </div>
                                            )}
                                            {isLowStock && !isOutOfStock && (
                                                <div className="absolute top-3 right-3 bg-amber-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-full z-10">
                                                    Sisa {stockLeft}
                                                </div>
                                            )}
                                            <div className="w-24 h-24 rounded-xl bg-stone-100 overflow-hidden shrink-0">
                                                {item.image_url ? (
                                                    <img src={`/storage/${item.image_url}`} alt={item.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-stone-300">
                                                        <UtensilsCrossed size={24} />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 flex flex-col justify-between">
                                                <div>
                                                    <h4 className="font-bold text-slate-900 leading-tight">{item.name}</h4>
                                                    {item.final_price < item.price ? (
                                                        <div className="flex flex-col mt-1">
                                                            <span className="text-xs text-slate-400 line-through">Rp {formatPrice(item.price)}</span>
                                                            <span className="font-bold text-rose-600">Rp {formatPrice(item.final_price)}</span>
                                                        </div>
                                                    ) : (
                                                        <p className="font-bold text-emerald-600 mt-1">Rp {formatPrice(item.price)}</p>
                                                    )}
                                                </div>
                                                <div className="flex justify-end items-center mt-2">
                                                    {isOutOfStock ? (
                                                        <span className="px-4 py-1.5 bg-stone-100 text-stone-400 rounded-lg text-sm font-medium cursor-not-allowed">
                                                            Habis hari ini
                                                        </span>
                                                    ) : qty === 0 ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => updateCart(item, 1)}
                                                            className="px-4 py-1.5 bg-orange-100 text-orange-700 hover:bg-orange-200 rounded-lg text-sm font-bold transition-colors"
                                                        >
                                                            Tambah
                                                        </button>
                                                    ) : (
                                                        <div className="flex items-center gap-3 bg-stone-50 rounded-lg p-1 border border-stone-200">
                                                            <button
                                                                type="button"
                                                                onClick={() => updateCart(item, -1)}
                                                                className="w-7 h-7 flex items-center justify-center bg-white rounded shadow-sm text-rose-600 hover:bg-rose-50"
                                                            >
                                                                <Minus size={14} />
                                                            </button>
                                                            <span className="font-bold w-4 text-center">{qty}</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => updateCart(item, 1)}
                                                                disabled={stockLeft !== null && qty >= stockLeft}
                                                                className="w-7 h-7 flex items-center justify-center bg-white rounded shadow-sm text-emerald-600 hover:bg-emerald-50 disabled:opacity-40 disabled:cursor-not-allowed"
                                                            >
                                                                <Plus size={14} />
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Right: Cart & Checkout */}
                <div className="w-full lg:w-[400px]">
                    <form onSubmit={submit} className="bg-white rounded-[2rem] border border-stone-100 p-6 shadow-sm sticky top-8">
                        <h3 className="text-xl font-bold text-slate-900 flex items-center gap-2 mb-6 border-b border-stone-100 pb-4">
                            <ShoppingCart className="text-emerald-500" /> Detail Pesanan
                        </h3>

                        {Object.keys(cart).length === 0 ? (
                            <div className="py-8 text-center text-slate-400">
                                <ShoppingCart size={40} className="mx-auto mb-3 opacity-20" />
                                <p>Keranjang masih kosong</p>
                            </div>
                        ) : (
                            <div className="space-y-4 max-h-[300px] overflow-y-auto pr-2 mb-6">
                                {Object.values(cart).map(item => (
                                    <div key={item.id} className="flex justify-between items-start text-sm border-b border-stone-50 pb-3">
                                        <div className="flex gap-2 font-medium text-slate-700">
                                            <span className="font-bold text-slate-900 bg-stone-100 px-1.5 rounded">{item.quantity}x</span>
                                            <span>{item.name}</span>
                                        </div>
                                        <span className="font-bold text-slate-900 whitespace-nowrap">
                                            Rp {formatPrice((item.final_price !== undefined ? item.final_price : item.price) * item.quantity)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="space-y-4 border-t border-stone-100 pt-6 mt-4">
                            {!isAuthenticated && (
                                <div className="bg-orange-50 border border-orange-200 p-4 rounded-xl space-y-4 mb-4">
                                    <div className="text-sm font-bold text-orange-800">Data Pemesan (Guest)</div>
                                    <div>
                                        <InputLabel htmlFor="customer_name" value="Nama Lengkap" />
                                        <TextInput
                                            id="customer_name"
                                            className="mt-1 block w-full bg-white"
                                            value={data.customer_name}
                                            onChange={(e) => setData('customer_name', e.target.value)}
                                            required={!isAuthenticated}
                                            placeholder="Contoh: Budi Santoso"
                                        />
                                        <InputError message={errors.customer_name} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="customer_phone" value="Nomor HP/WhatsApp" />
                                        <TextInput
                                            id="customer_phone"
                                            type="tel"
                                            className="mt-1 block w-full bg-white"
                                            value={data.customer_phone}
                                            onChange={(e) => setData('customer_phone', e.target.value)}
                                            required={!isAuthenticated}
                                            placeholder="Contoh: 08123456789"
                                        />
                                        <InputError message={errors.customer_phone} className="mt-2" />
                                    </div>
                                </div>
                            )}

                            <div>
                                <InputLabel htmlFor="order_type" value="Tipe Pesanan" />
                                <select
                                    id="order_type"
                                    className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-emerald-500 rounded-xl"
                                    value={data.order_type}
                                    onChange={(e) => setData('order_type', e.target.value)}
                                    required
                                >
                                    <option value="dine_in">Makan di Resto/Cafe</option>
                                    <option value="takeaway">Bawa Pulang (Takeaway)</option>
                                    {(reservationId || qrFacilityId || activeReservations.length > 0) && (
                                        <option value="room_service">Antar ke Tempat Saya</option>
                                    )}
                                </select>
                            </div>

                            {data.order_type === 'room_service' && activeReservations.length > 0 && (
                                <div>
                                    <InputLabel htmlFor="reservation_id" value="Pilih Tempat Pengantaran" />
                                    <select
                                        id="reservation_id"
                                        className="mt-1 block w-full bg-emerald-50 text-emerald-900 border-emerald-200 focus:border-emerald-500 rounded-xl"
                                        value={data.reservation_id}
                                        onChange={(e) => setData('reservation_id', e.target.value)}
                                        required
                                    >
                                        {activeReservations.map((res) => (
                                            <option key={res.id} value={res.id}>
                                                {res.facility.name} (Kode: {res.unique_code})
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-xs text-slate-500 mt-1">Kami akan mengantarkan pesanan langsung ke fasilitas yang sedang Anda gunakan.</p>
                                </div>
                            )}

                            {data.order_type === 'dine_in' && (
                                <div>
                                    <InputLabel htmlFor="table_number" value="Pilih Lokasi / Meja Anda" />
                                    <select
                                        id="table_number"
                                        className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-emerald-500 rounded-xl"
                                        value={data.table_number}
                                        onChange={(e) => setData('table_number', e.target.value)}
                                        required
                                    >
                                        <option value="">-- Pilih Lokasi --</option>
                                        {qrCodes.map(qr => (
                                            <option key={qr.id} value={qr.location_type === 'table' ? qr.table_number : qr.label}>
                                                {qr.label}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-xs text-slate-500 mt-1">Pilih lokasi meja/gazebo Anda agar kami bisa mengantarkan pesanan dengan tepat.</p>
                                </div>
                            )}

                            <div>
                                <InputLabel htmlFor="notes" value="Catatan Tambahan (Opsional)" />
                                <textarea
                                    id="notes"
                                    rows="2"
                                    className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-emerald-500 rounded-xl resize-none text-sm"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Contoh: Tidak pakai pedas, es dipisah, dll"
                                />
                            </div>

                            {data.order_type !== 'room_service' && (
                                <div className="mt-2">
                                    <InputLabel value="Metode Pembayaran" />
                                    
                                    <div className="grid grid-cols-2 gap-3 mt-2">
                                        <button
                                            type="button"
                                            onClick={() => setData('payment_method', 'tripay')}
                                            className={`p-3 rounded-xl border-2 flex flex-col items-center gap-2 transition-all ${
                                                data.payment_method === 'tripay'
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-stone-200 bg-white text-slate-500 hover:border-emerald-200'
                                            }`}
                                        >
                                            <CreditCard size={24} />
                                            <span className="font-bold text-sm">Bayar Online</span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setData('payment_method', 'cash'); setData('payment_channel', ''); }}
                                            className={`p-3 rounded-xl border-2 flex flex-col items-center gap-2 transition-all ${
                                                data.payment_method === 'cash'
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-stone-200 bg-white text-slate-500 hover:border-emerald-200'
                                            }`}
                                        >
                                            <Banknote size={24} />
                                            <span className="font-bold text-sm">Bayar di Kasir</span>
                                        </button>
                                    </div>

                                    {data.payment_method === 'tripay' && (
                                        paymentChannels && paymentChannels.length > 0 ? (
                                            <div className="mt-4 p-4 bg-stone-50 rounded-xl border border-stone-200">
                                                <p className="text-sm font-bold text-slate-700 mb-3">Pilih Metode Pembayaran Online</p>
                                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                    {paymentChannels.map((channel) => (
                                                        <label 
                                                            key={channel.code}
                                                            className={`flex flex-col items-center justify-center p-2 rounded-lg border cursor-pointer transition-all ${
                                                                data.payment_channel === channel.code 
                                                                ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-500' 
                                                                : 'border-stone-200 bg-white hover:border-emerald-200'
                                                            }`}
                                                        >
                                                            <input 
                                                                type="radio" 
                                                                name="payment_channel" 
                                                                className="sr-only"
                                                                value={channel.code}
                                                                checked={data.payment_channel === channel.code}
                                                                onChange={(e) => setData('payment_channel', e.target.value)}
                                                            />
                                                            <div className="h-10 flex items-center justify-center">
                                                                {channel.icon_url ? (
                                                                    <img src={channel.icon_url} alt={channel.name} className="max-h-full max-w-full object-contain" />
                                                                ) : (
                                                                    <span className="text-xs font-bold text-slate-400">{channel.code}</span>
                                                                )}
                                                            </div>
                                                        </label>
                                                    ))}
                                                </div>
                                                <InputError message={errors.payment_channel} className="mt-2" />
                                            </div>
                                        ) : (
                                            <div className="mt-4 p-4 bg-rose-50 text-rose-700 rounded-xl text-sm flex gap-2">
                                                <AlertCircle size={16} className="shrink-0" />
                                                <span>Pembayaran online sedang tidak tersedia saat ini. Silakan pilih Bayar di Kasir.</span>
                                            </div>
                                        )
                                    )}

                                    <InputError message={errors.payment_method} className="mt-2" />
                                </div>
                            )}
                            
                            {errors.items && (
                                <div className="p-3 bg-rose-50 text-rose-600 rounded-xl text-sm flex gap-2">
                                    <AlertCircle size={16} className="shrink-0 mt-0.5" />
                                    <span>{errors.items || 'Silakan pilih minimal 1 menu untuk dipesan.'}</span>
                                </div>
                            )}

                            <div className="flex justify-between items-center py-4 border-t border-stone-200 mt-6">
                                <span className="font-medium text-slate-500">Total Harga</span>
                                <span className="text-2xl font-black text-emerald-600">Rp {formatPrice(cartTotal)}</span>
                            </div>

                            <PrimaryButton 
                                type="submit" 
                                className="w-full justify-center bg-orange-500 hover:bg-orange-600 text-white rounded-xl py-3.5 text-base shadow-lg shadow-orange-500/30"
                                disabled={processing || cartTotal === 0}
                            >
                                <Send size={18} className="mr-2" /> Pesan Sekarang
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </AppLayout>
    );
}
