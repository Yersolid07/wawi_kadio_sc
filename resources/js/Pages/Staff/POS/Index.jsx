import { useState, useMemo, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Search, ShoppingCart, Plus, Minus, X, CreditCard, Banknote, Receipt, ArrowLeft, Loader2, UtensilsCrossed, Users, Maximize } from 'lucide-react';
import TextInput from '@/Components/TextInput';

export default function POSIndex({ menuItems, activeOrders = [], user }) {
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState('Semua');
    const [cart, setCart] = useState([]);
    
    // POS Order State
    const [selectedOrderId, setSelectedOrderId] = useState(null); // ID of existing order if we are editing/paying an active one
    const [orderType, setOrderType] = useState('dine_in');
    const [tableNumber, setTableNumber] = useState('');
    const [guestName, setGuestName] = useState('Walk-in Customer');
    const [notes, setNotes] = useState('');
    
    // Payment State
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [amountPaid, setAmountPaid] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    
    // Print Modal State
    const [printUrl, setPrintUrl] = useState(null);

    // Layout State
    const [layoutDirection, setLayoutDirection] = useState('horizontal'); // 'horizontal' or 'vertical'

    // Categories
    const categories = ['Semua', ...Object.keys(menuItems)];

    // Filter Items
    const filteredItems = useMemo(() => {
        let items = [];
        if (activeCategory === 'Semua') {
            Object.values(menuItems).forEach(catItems => {
                items = [...items, ...catItems];
            });
        } else {
            items = menuItems[activeCategory] || [];
        }

        if (search) {
            items = items.filter(item => item.name.toLowerCase().includes(search.toLowerCase()));
        }
        return items;
    }, [menuItems, activeCategory, search]);

    const addToCart = (item) => {
        setCart(prev => {
            const existing = prev.find(i => i.id === item.id && !i.is_existing);
            if (existing) {
                return prev.map(i => (i.id === item.id && !i.is_existing) ? { ...i, quantity: i.quantity + 1 } : i);
            }
            return [...prev, { ...item, quantity: 1, itemNotes: '', is_existing: false }];
        });
    };

    const updateQuantity = (id, delta, isExisting) => {
        if (isExisting) return; // Cannot modify existing items quantity from POS (already sent to kitchen)
        setCart(prev => prev.map(item => {
            if (item.id === id && item.is_existing === isExisting) {
                const newQty = item.quantity + delta;
                return newQty > 0 ? { ...item, quantity: newQty } : item;
            }
            return item;
        }));
    };

    const updateItemNotes = (id, notes, isExisting) => {
        if (isExisting) return; // Cannot modify notes for existing items
        setCart(prev => prev.map(item => (item.id === id && item.is_existing === isExisting) ? { ...item, itemNotes: notes } : item));
    };

    const removeFromCart = (id, isExisting) => {
        if (isExisting) return; // Cannot remove existing items
        setCart(prev => prev.filter(item => !(item.id === id && item.is_existing === isExisting)));
    };

    const loadActiveOrder = (order) => {
        setSelectedOrderId(order.id);
        setOrderType(order.order_type);
        setTableNumber(order.table_number || (order.reservation ? order.reservation.facility.name : ''));
        setGuestName(order.guest_name || (order.user ? order.user.name : 'Tamu QR'));
        setNotes(order.notes || '');
        
        // Load items into cart and mark as existing
        const existingItems = order.items.map(item => ({
            id: item.menu_item.id,
            name: item.menu_item.name,
            price: item.price,
            quantity: item.quantity,
            itemNotes: item.notes || '',
            is_existing: true // Flag to prevent modifying/removing
        }));
        
        setCart(existingItems);
    };

    const clearActiveOrder = () => {
        setSelectedOrderId(null);
        setOrderType('dine_in');
        setTableNumber('');
        setGuestName('Walk-in Customer');
        setNotes('');
        setCart([]);
    };

    const totalAmount = cart.reduce((sum, item) => sum + (parseFloat(item.final_price !== undefined ? item.final_price : item.price) * item.quantity), 0);
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    const formatPrice = (price) => parseFloat(price).toLocaleString('id-ID');

    const handleCheckout = (e) => {
        e.preventDefault();
        
        if (orderType === 'dine_in' && !tableNumber) {
            alert('Silakan isi Nomor Meja untuk pesanan Dine In.');
            return;
        }

        if (paymentMethod === 'cash') {
            const paid = parseFloat(amountPaid.replace(/\D/g, '')) || 0;
            if (paid < totalAmount) {
                alert('Jumlah bayar kurang dari total tagihan!');
                return;
            }
        }

        setIsProcessing(true);

        const payload = {
            order_id: selectedOrderId, // Pass if editing an active order
            order_type: orderType,
            table_number: tableNumber,
            guest_name: guestName,
            notes: notes,
            payment_method: paymentMethod,
            amount_paid: paymentMethod === 'cash' ? (parseFloat(amountPaid.replace(/\D/g, '')) || 0) : totalAmount,
            items: cart.map(item => ({
                menu_item_id: item.id,
                quantity: item.quantity,
                notes: item.itemNotes,
                is_existing: item.is_existing || false
            }))
        };

        router.post(route('staff.pos.store'), payload, {
            onSuccess: (page) => {
                setShowPaymentModal(false);
                clearActiveOrder();
                setAmountPaid('');
                setIsProcessing(false);
                
                if (page.props.flash.print_order_id) {
                    let url = route('staff.pos.print', page.props.flash.print_order_id);
                    if (page.props.flash.change_amount !== undefined) {
                        url += '?change_amount=' + page.props.flash.change_amount;
                    }
                    setPrintUrl(url);
                }
            },
            onError: () => setIsProcessing(false)
        });
    };

    const quickCashOptions = [50000, 100000, 150000, 200000];

    const toggleFullScreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                alert(`Error attempting to enable fullscreen mode: ${err.message} (${err.name})`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    };

    return (
        <div className={`min-h-screen bg-stone-100 flex font-sans ${layoutDirection === 'horizontal' ? 'flex-col md:flex-row' : 'flex-col'}`}>
            <Head title="Point of Sale (POS)" />

            {/* Left Side: Active Orders & Catalog */}
            <div className={`flex flex-col bg-stone-50 ${layoutDirection === 'horizontal' ? 'flex-1 h-screen overflow-hidden' : 'h-[60vh] overflow-hidden border-b border-stone-200'}`}>
                {/* Header */}
                <header className="bg-white border-b border-stone-200 p-4 flex items-center justify-between shrink-0 shadow-sm z-10">
                    <div className="flex items-center gap-4">
                        <a href={route('dashboard')} className="p-2 text-stone-500 hover:text-emerald-600 bg-stone-100 rounded-xl transition-colors">
                            <ArrowLeft size={20} />
                        </a>
                        <div>
                            <h1 className="text-xl font-black text-slate-900 flex items-center gap-2">
                                <UtensilsCrossed className="text-emerald-500" size={24} /> 
                                WAWI POS
                            </h1>
                            <p className="text-xs font-bold text-stone-500 uppercase tracking-widest">Kasir: {user.name}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <button 
                            onClick={() => setLayoutDirection(prev => prev === 'horizontal' ? 'vertical' : 'horizontal')}
                            className="hidden md:flex p-2 text-stone-500 hover:text-emerald-600 bg-stone-100 hover:bg-emerald-50 rounded-xl transition-colors" title="Toggle Layout"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" y1="12" x2="21" y2="12"/></svg>
                        </button>
                        <button onClick={toggleFullScreen} className="p-2 text-stone-500 hover:text-emerald-600 bg-stone-100 hover:bg-emerald-50 rounded-xl transition-colors" title="Toggle Fullscreen">
                            <Maximize size={20} />
                        </button>
                        <div className="relative w-48 md:w-80">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400" size={20} />
                            <TextInput
                                type="text"
                                placeholder="Cari menu..."
                                className="w-full pl-10 bg-stone-100 border-transparent focus:bg-white focus:border-emerald-500 focus:ring-emerald-500 rounded-xl"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>
                </header>

                {/* Active Orders Horizontal Scroll (If any) */}
                {activeOrders && activeOrders.length > 0 && (
                    <div className="bg-white border-b border-stone-200 p-4 shrink-0 overflow-x-auto hide-scrollbar">
                        <div className="flex items-center gap-2 mb-2">
                            <Users size={16} className="text-orange-500" />
                            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Pesanan Aktif (Meja/QR)</span>
                        </div>
                        <div className="flex gap-3">
                            {activeOrders.map(order => (
                                <button
                                    key={order.id}
                                    onClick={() => loadActiveOrder(order)}
                                    className={`px-4 py-3 rounded-2xl border-2 text-left flex flex-col min-w-[160px] transition-all ${
                                        selectedOrderId === order.id 
                                            ? 'border-emerald-500 bg-emerald-50 shadow-md shadow-emerald-500/20' 
                                            : 'border-stone-200 bg-white hover:border-emerald-300'
                                    }`}
                                >
                                    <span className="font-black text-slate-900 truncate w-full">
                                        {order.order_type === 'dine_in' ? `Meja ${order.table_number}` : (order.reservation ? order.reservation.facility.name : 'Takeaway')}
                                    </span>
                                    <span className="text-xs font-bold text-stone-500 truncate w-full mb-1">
                                        {order.guest_name || order.user?.name || 'Tamu'}
                                    </span>
                                    <span className="text-sm font-black text-emerald-600">Rp {formatPrice(order.total_amount)}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Categories */}
                <div className="bg-white border-b border-stone-200 p-4 shrink-0 overflow-x-auto hide-scrollbar">
                    <div className="flex gap-2">
                        {categories.map(cat => (
                            <button
                                key={cat}
                                onClick={() => setActiveCategory(cat)}
                                className={`px-6 py-2 rounded-xl font-bold text-sm whitespace-nowrap transition-all ${
                                    activeCategory === cat 
                                        ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' 
                                        : 'bg-stone-100 text-stone-600 hover:bg-stone-200'
                                }`}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Product Grid */}
                <div className="flex-1 overflow-y-auto p-4 md:p-6 bg-stone-50">
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        {filteredItems.map(item => (
                            <div 
                                key={item.id} 
                                onClick={() => addToCart(item)}
                                className="bg-white rounded-2xl border border-stone-200 overflow-hidden cursor-pointer hover:border-emerald-500 hover:shadow-lg transition-all group flex flex-col h-full active:scale-95"
                            >
                                <div className="h-28 bg-stone-100 relative overflow-hidden">
                                    {item.image_url ? (
                                        <img src={`/storage/${item.image_url}`} alt={item.name} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center text-stone-400">
                                            <UtensilsCrossed size={32} />
                                        </div>
                                    )}
                                    <div className="absolute top-2 right-2">
                                        <span className="bg-white/90 backdrop-blur-sm text-emerald-700 text-[10px] font-black px-2 py-1 rounded-lg shadow-sm">
                                            {item.category}
                                        </span>
                                    </div>
                                </div>
                                <div className="p-3 flex flex-col justify-between flex-1">
                                    <h3 className="font-bold text-slate-900 leading-tight mb-1 text-sm line-clamp-2">{item.name}</h3>
                                    {item.final_price < item.price ? (
                                        <div className="flex flex-col">
                                            <span className="text-xs text-slate-400 line-through">Rp {formatPrice(item.price)}</span>
                                            <span className="font-black text-rose-600 text-sm">Rp {formatPrice(item.final_price)}</span>
                                        </div>
                                    ) : (
                                        <div className="font-black text-emerald-600 text-sm">Rp {formatPrice(item.price)}</div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    {filteredItems.length === 0 && (
                        <div className="text-center py-20 text-stone-400">
                            <Search size={48} className="mx-auto mb-4 opacity-50" />
                            <p className="font-bold text-lg">Menu tidak ditemukan</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Right Side: Cart */}
            <div className={`bg-white flex flex-col border-stone-200 shadow-2xl z-20 shrink-0 ${
                layoutDirection === 'horizontal' 
                    ? 'w-full md:w-[400px] lg:w-[450px] h-screen border-l' 
                    : 'w-full h-[40vh] border-t'
            }`}>
                <div className="p-4 border-b border-stone-200 bg-stone-50 flex justify-between items-center shrink-0">
                    <h2 className="font-black text-slate-900 text-xl flex items-center gap-2">
                        <ShoppingCart className="text-emerald-500" /> 
                        {selectedOrderId ? 'Tagihan Meja' : 'Pesanan Baru'}
                    </h2>
                    <div className="flex gap-2">
                        {selectedOrderId && (
                            <button onClick={clearActiveOrder} className="bg-rose-100 text-rose-700 font-bold px-3 py-1 rounded-full text-xs hover:bg-rose-200 transition-colors">
                                Batal / Reset
                            </button>
                        )}
                        <span className="bg-emerald-100 text-emerald-700 font-bold px-3 py-1 rounded-full text-sm">
                            {totalItems} item
                        </span>
                    </div>
                </div>

                {/* Cart Items */}
                <div className="flex-1 overflow-y-auto p-4 space-y-3">
                    {cart.map((item, index) => (
                        <div key={`${item.id}-${index}`} className={`rounded-xl p-3 border flex gap-3 ${item.is_existing ? 'bg-orange-50/50 border-orange-200' : 'bg-stone-50 border-stone-200'}`}>
                            <div className="flex-1">
                                <div className="flex justify-between items-start mb-1">
                                    <div className="flex items-center gap-2">
                                        <h4 className="font-bold text-slate-900 leading-tight">{item.name}</h4>
                                        {item.is_existing && (
                                            <span className="bg-orange-100 text-orange-700 text-[10px] font-black px-2 py-0.5 rounded uppercase">Sudah Dipesan</span>
                                        )}
                                    </div>
                                    {!item.is_existing && (
                                        <button onClick={() => removeFromCart(item.id, item.is_existing)} className="text-stone-400 hover:text-rose-500 transition-colors p-1">
                                            <X size={16} />
                                        </button>
                                    )}
                                </div>
                                {item.final_price < item.price && !item.is_existing ? (
                                    <div className="flex flex-col mb-2">
                                        <span className="text-xs text-slate-400 line-through">Rp {formatPrice(item.price)}</span>
                                        <span className="text-rose-600 font-bold text-sm">Rp {formatPrice(item.final_price)}</span>
                                    </div>
                                ) : (
                                    <div className="text-emerald-600 font-bold text-sm mb-2">Rp {formatPrice(item.is_existing ? item.price : (item.final_price !== undefined ? item.final_price : item.price))}</div>
                                )}
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3 bg-white border border-stone-200 rounded-lg p-1">
                                        <button 
                                            onClick={() => updateQuantity(item.id, -1, item.is_existing)} 
                                            disabled={item.is_existing}
                                            className="w-7 h-7 rounded bg-stone-100 hover:bg-stone-200 disabled:opacity-50 flex items-center justify-center text-stone-600"
                                        >
                                            <Minus size={14} />
                                        </button>
                                        <span className="font-bold w-6 text-center">{item.quantity}</span>
                                        <button 
                                            onClick={() => updateQuantity(item.id, 1, item.is_existing)} 
                                            disabled={item.is_existing}
                                            className="w-7 h-7 rounded bg-emerald-100 hover:bg-emerald-200 disabled:opacity-50 flex items-center justify-center text-emerald-700"
                                        >
                                            <Plus size={14} />
                                        </button>
                                    </div>
                                    <div className="font-black text-slate-900">
                                        Rp {formatPrice((item.is_existing ? item.price : (item.final_price !== undefined ? item.final_price : item.price)) * item.quantity)}
                                    </div>
                                </div>
                                {!item.is_existing && (
                                    <input
                                        type="text"
                                        placeholder="Catatan (opsional)..."
                                        className="w-full mt-2 text-xs bg-white border-stone-200 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                                        value={item.itemNotes}
                                        onChange={(e) => updateItemNotes(item.id, e.target.value, item.is_existing)}
                                    />
                                )}
                                {item.is_existing && item.itemNotes && (
                                    <p className="text-xs font-bold text-orange-600 italic mt-2">Catatan: {item.itemNotes}</p>
                                )}
                            </div>
                        </div>
                    ))}
                    {cart.length === 0 && (
                        <div className="h-full flex flex-col items-center justify-center text-stone-400 space-y-4">
                            <ShoppingCart size={64} className="opacity-30" />
                            <p className="font-bold text-lg">Keranjang Kosong</p>
                            <p className="text-sm text-center px-8">Klik menu di sebelah kiri untuk menambah pesanan ke keranjang.</p>
                        </div>
                    )}
                </div>

                {/* Checkout Summary */}
                <div className="border-t border-stone-200 bg-stone-50 p-4 shrink-0 space-y-4 shadow-[0_-10px_20px_rgba(0,0,0,0.02)]">
                    <div className="grid grid-cols-2 gap-3 mb-2">
                        <div>
                            <label className="text-[10px] font-bold text-stone-500 uppercase tracking-widest">Tipe Pesanan</label>
                            <select 
                                className="w-full mt-1 rounded-xl border-stone-200 text-sm font-bold bg-white focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-stone-100"
                                value={orderType}
                                onChange={(e) => setOrderType(e.target.value)}
                                disabled={selectedOrderId !== null}
                            >
                                <option value="dine_in">Makan di Tempat</option>
                                <option value="takeaway">Bungkus / Takeaway</option>
                                <option value="room_service">Layanan Kamar</option>
                            </select>
                        </div>
                        {(orderType === 'dine_in' || orderType === 'room_service') && (
                            <div>
                                <label className="text-[10px] font-bold text-stone-500 uppercase tracking-widest">
                                    {orderType === 'dine_in' ? 'No. Meja' : 'Kamar/Fasilitas'} <span className="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    className="w-full mt-1 rounded-xl border-stone-200 text-sm font-bold bg-white focus:ring-emerald-500 focus:border-emerald-500 placeholder:font-normal disabled:bg-stone-100 disabled:text-stone-500"
                                    placeholder={orderType === 'dine_in' ? 'Meja 12' : 'Gazebo A'}
                                    value={tableNumber}
                                    onChange={(e) => setTableNumber(e.target.value)}
                                    disabled={selectedOrderId !== null}
                                />
                            </div>
                        )}
                    </div>
                    
                    <div>
                        <label className="text-[10px] font-bold text-stone-500 uppercase tracking-widest">Nama Tamu</label>
                        <input 
                            type="text" 
                            className="w-full mt-1 rounded-xl border-stone-200 text-sm font-bold bg-white focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-stone-100 disabled:text-stone-500"
                            value={guestName}
                            onChange={(e) => setGuestName(e.target.value)}
                            disabled={selectedOrderId !== null}
                        />
                    </div>

                    <div className="flex justify-between items-end pt-2 border-t border-stone-200">
                        <span className="text-slate-500 font-bold uppercase tracking-wider text-sm">Total Tagihan</span>
                        <span className="text-3xl font-black text-emerald-600 leading-none">Rp {formatPrice(totalAmount)}</span>
                    </div>

                    <button
                        onClick={() => setShowPaymentModal(true)}
                        disabled={cart.length === 0}
                        className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:bg-stone-300 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg transition-all shadow-lg hover:shadow-xl active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        {selectedOrderId ? 'BAYAR TAGIHAN' : 'BAYAR SEKARANG'}
                    </button>
                </div>
            </div>

            {/* Payment Modal */}
            {showPaymentModal && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden flex flex-col">
                        <div className="p-6 bg-slate-900 text-white flex justify-between items-center">
                            <h3 className="text-xl font-black">Proses Pembayaran</h3>
                            <button onClick={() => setShowPaymentModal(false)} className="text-slate-400 hover:text-white transition-colors">
                                <X size={24} />
                            </button>
                        </div>
                        
                        <div className="p-6 md:p-8 space-y-6">
                            <div className="text-center p-4 bg-emerald-50 rounded-2xl border border-emerald-100">
                                <p className="text-emerald-700 font-bold uppercase text-sm mb-1">Total Tagihan</p>
                                <p className="text-4xl font-black text-emerald-600">Rp {formatPrice(totalAmount)}</p>
                            </div>

                            <div>
                                <p className="font-bold text-slate-900 mb-3">Pilih Metode Pembayaran:</p>
                                <div className="grid grid-cols-2 gap-4">
                                    <button 
                                        onClick={() => { setPaymentMethod('cash'); setAmountPaid(''); }}
                                        className={`flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all ${
                                            paymentMethod === 'cash' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-stone-200 hover:border-stone-300 text-stone-500'
                                        }`}
                                    >
                                        <Banknote size={32} className="mb-2" />
                                        <span className="font-bold">Tunai (Cash)</span>
                                    </button>
                                    <button 
                                        onClick={() => setPaymentMethod('qris')}
                                        className={`flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all ${
                                            paymentMethod === 'qris' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-stone-200 hover:border-stone-300 text-stone-500'
                                        }`}
                                    >
                                        <CreditCard size={32} className="mb-2" />
                                        <span className="font-bold">QRIS / Transfer</span>
                                    </button>
                                </div>
                            </div>

                            {paymentMethod === 'cash' && (
                                <div className="space-y-4 animate-in fade-in slide-in-from-top-4">
                                    <div>
                                        <label className="font-bold text-slate-900 block mb-2">Jumlah Uang Diterima:</label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-bold text-stone-400">Rp</span>
                                            <input 
                                                type="text" 
                                                value={amountPaid}
                                                onChange={(e) => {
                                                    const val = e.target.value.replace(/\D/g, '');
                                                    setAmountPaid(val ? formatPrice(val) : '');
                                                }}
                                                className="w-full pl-12 pr-4 py-4 text-2xl font-black rounded-2xl border-stone-300 focus:border-emerald-500 focus:ring-emerald-500"
                                                placeholder="0"
                                            />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-4 gap-2">
                                        <button onClick={() => setAmountPaid(formatPrice(totalAmount))} className="p-2 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-stone-700 text-sm">Uang Pas</button>
                                        {quickCashOptions.map(opt => (
                                            opt >= totalAmount && (
                                                <button key={opt} onClick={() => setAmountPaid(formatPrice(opt))} className="p-2 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-stone-700 text-sm">
                                                    {opt / 1000}k
                                                </button>
                                            )
                                        ))}
                                    </div>

                                    {amountPaid && (parseFloat(amountPaid.replace(/\D/g, '')) >= totalAmount) && (
                                        <div className="p-4 bg-orange-50 rounded-2xl border border-orange-100 flex justify-between items-center">
                                            <span className="font-bold text-orange-800 uppercase text-sm">Kembalian:</span>
                                            <span className="font-black text-2xl text-orange-600">
                                                Rp {formatPrice(parseFloat(amountPaid.replace(/\D/g, '')) - totalAmount)}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}

                            {paymentMethod === 'qris' && (
                                <div className="text-center p-6 bg-blue-50 rounded-2xl border border-blue-100 animate-in fade-in slide-in-from-top-4">
                                    <div className="w-16 h-16 bg-blue-100 text-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                        <Receipt size={32} />
                                    </div>
                                    <p className="font-bold text-blue-900 mb-1">Pembayaran Non-Tunai</p>
                                    <p className="text-sm text-blue-700">Pastikan pelanggan memindai QRIS Statis atau EDC Kasir dan dana telah masuk sebelum menyelesaikan pesanan.</p>
                                </div>
                            )}

                            <button
                                onClick={handleCheckout}
                                disabled={isProcessing || (paymentMethod === 'cash' && (!amountPaid || parseFloat(amountPaid.replace(/\D/g, '')) < totalAmount))}
                                className="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:bg-stone-300 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg transition-all shadow-lg flex items-center justify-center gap-2"
                            >
                                {isProcessing ? (
                                    <><Loader2 className="animate-spin" /> MENGDIPROSES...</>
                                ) : (
                                    <>SELESAIKAN PESANAN & CETAK STRUK</>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Print Modal (Bypass Pop-up Blocker) */}
            {printUrl && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
                    <div className="bg-white rounded-[2rem] w-full max-w-md shadow-2xl overflow-hidden flex flex-col h-[80vh]">
                        <div className="p-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                            <h3 className="text-lg font-black flex items-center gap-2"><Receipt size={20} /> Cetak Struk</h3>
                            <button onClick={() => setPrintUrl(null)} className="text-slate-400 hover:text-white transition-colors">
                                <X size={24} />
                            </button>
                        </div>
                        <div className="flex-1 w-full bg-stone-100 overflow-hidden relative">
                            <iframe 
                                src={printUrl} 
                                className="w-full h-full border-0 absolute inset-0" 
                                title="Struk Pembayaran"
                                onLoad={(e) => {
                                    try {
                                        e.target.contentWindow.print();
                                    } catch (err) {}
                                }}
                            ></iframe>
                        </div>
                        <div className="p-4 bg-white border-t border-stone-200 shrink-0">
                            <button
                                onClick={() => setPrintUrl(null)}
                                className="w-full py-3 bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-xl font-bold transition-all"
                            >
                                Tutup & Lanjut Pesanan Baru
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
