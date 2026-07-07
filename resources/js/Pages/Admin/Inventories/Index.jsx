import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Package, Plus, Edit, Trash2, ArrowDownCircle, ArrowUpCircle, AlertCircle, History } from 'lucide-react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import DangerButton from '@/Components/DangerButton';

export default function Index({ inventories }) {
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isTransactionOpen, setIsTransactionOpen] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [transactionItem, setTransactionItem] = useState(null);

    const { data: formData, setData: setFormData, post, put, delete: destroy, processing: formProcessing, reset: resetForm, errors: formErrors } = useForm({
        name: '', category: 'Bahan Makanan', unit: '', minimum_stock: 0, price_per_unit: '', notes: ''
    });

    const { data: transData, setData: setTransData, post: postTrans, processing: transProcessing, reset: resetTrans, errors: transErrors } = useForm({
        type: 'in', quantity: '', notes: ''
    });

    const openCreateForm = () => {
        setEditingItem(null);
        resetForm();
        setIsFormOpen(true);
    };

    const openEditForm = (item) => {
        setEditingItem(item);
        setFormData({
            name: item.name,
            category: item.category || 'Bahan Makanan',
            unit: item.unit,
            minimum_stock: item.minimum_stock,
            price_per_unit: item.price_per_unit || '',
            notes: item.notes || ''
        });
        setIsFormOpen(true);
    };

    const openTransaction = (item) => {
        setTransactionItem(item);
        resetTrans();
        setTransData('type', 'in');
        setIsTransactionOpen(true);
    };

    const submitForm = (e) => {
        e.preventDefault();
        if (editingItem) {
            put(route('admin.inventories.update', editingItem.id), {
                onSuccess: () => setIsFormOpen(false)
            });
        } else {
            post(route('admin.inventories.store'), {
                onSuccess: () => setIsFormOpen(false)
            });
        }
    };

    const submitTransaction = (e) => {
        e.preventDefault();
        postTrans(route('admin.inventories.transaction', transactionItem.id), {
            onSuccess: () => setIsTransactionOpen(false)
        });
    };

    const handleDelete = (id) => {
        if(confirm('Hapus bahan baku ini?')) {
            router.delete(route('admin.inventories.destroy', id));
        }
    };

    return (
        <AppLayout title="Inventori Stok">
            <Head title="Manajemen Stok — Wawi Kadio" />

            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex justify-between items-center">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center shrink-0">
                            <Package className="text-indigo-600" size={24} />
                        </div>
                        <div>
                            <h2 className="text-2xl font-bold text-slate-900">Inventori Stok Bahan</h2>
                            <p className="text-slate-500 text-sm">Kelola stok bahan baku restoran dan resort</p>
                        </div>
                    </div>
                    <PrimaryButton onClick={openCreateForm} className="bg-indigo-600 hover:bg-indigo-700 gap-2">
                        <Plus size={16} /> Tambah Bahan Baru
                    </PrimaryButton>
                </div>

                <div className="bg-white rounded-3xl shadow-sm border border-stone-200 overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-stone-50 border-b border-stone-200 text-stone-500 text-xs uppercase tracking-wider">
                                <th className="p-4 font-bold">Nama Bahan</th>
                                <th className="p-4 font-bold">Stok Saat Ini</th>
                                <th className="p-4 font-bold">Batas Minimum</th>
                                <th className="p-4 font-bold">Harga/Unit</th>
                                <th className="p-4 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-stone-100">
                            {inventories.map(item => {
                                const isLowStock = parseFloat(item.current_stock) <= parseFloat(item.minimum_stock);
                                return (
                                    <tr key={item.id} className="hover:bg-stone-50/50 transition-colors">
                                        <td className="p-4">
                                            <div className="font-bold text-slate-900">{item.name}</div>
                                            <div className="text-xs font-semibold text-indigo-600 bg-indigo-50 inline-block px-2 py-0.5 rounded mt-1">{item.category}</div>
                                            {item.notes && <div className="text-xs text-slate-500 truncate max-w-[200px] mt-1">{item.notes}</div>}
                                        </td>
                                        <td className="p-4">
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-bold ${
                                                isLowStock ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'
                                            }`}>
                                                {isLowStock && <AlertCircle size={14} />}
                                                {parseFloat(item.current_stock)} {item.unit}
                                            </span>
                                        </td>
                                        <td className="p-4 text-slate-600">{parseFloat(item.minimum_stock)} {item.unit}</td>
                                        <td className="p-4 text-slate-600">{item.price_per_unit ? `Rp ${parseFloat(item.price_per_unit).toLocaleString('id-ID')}` : '-'}</td>
                                        <td className="p-4 text-right space-x-2">
                                            <button onClick={() => openTransaction(item)} className="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Catat Transaksi Stok">
                                                <History size={18} />
                                            </button>
                                            <button onClick={() => openEditForm(item)} className="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                                                <Edit size={18} />
                                            </button>
                                            <button onClick={() => handleDelete(item.id)} className="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
                                                <Trash2 size={18} />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {inventories.length === 0 && (
                                <tr>
                                    <td colSpan="5" className="p-8 text-center text-slate-400">
                                        <Package size={48} className="mx-auto mb-3 opacity-20" />
                                        <p>Belum ada data stok bahan baku.</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Form Modal */}
                <Modal show={isFormOpen} onClose={() => setIsFormOpen(false)}>
                    <form onSubmit={submitForm} className="p-6 space-y-6">
                        <h2 className="text-lg font-bold text-slate-900">{editingItem ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru'}</h2>
                        
                        <div className="space-y-4">
                            <div>
                                <InputLabel value="Nama Bahan Baku" />
                                <TextInput className="mt-1 block w-full" value={formData.name} onChange={e => setFormData('name', e.target.value)} required />
                            </div>
                            <div>
                                <InputLabel value="Kategori" />
                                <select 
                                    className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-indigo-500 rounded-xl"
                                    value={formData.category}
                                    onChange={e => setFormData('category', e.target.value)}
                                    required
                                >
                                    <option value="Bahan Makanan">Bahan Makanan</option>
                                    <option value="Minuman">Minuman</option>
                                    <option value="Perlengkapan Kamar">Perlengkapan Kamar</option>
                                    <option value="Kebersihan">Kebersihan</option>
                                    <option value="Operasional">Operasional</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Satuan (Unit)" />
                                    <TextInput className="mt-1 block w-full" placeholder="cth: kg, pcs, liter" value={formData.unit} onChange={e => setFormData('unit', e.target.value)} required />
                                </div>
                                <div>
                                    <InputLabel value="Batas Stok Minimum" />
                                    <TextInput type="number" step="0.01" className="mt-1 block w-full" value={formData.minimum_stock} onChange={e => setFormData('minimum_stock', e.target.value)} required />
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Harga per Satuan (Opsional)" />
                                <TextInput type="number" className="mt-1 block w-full" value={formData.price_per_unit} onChange={e => setFormData('price_per_unit', e.target.value)} />
                            </div>
                            <div>
                                <InputLabel value="Catatan / Deskripsi" />
                                <TextInput className="mt-1 block w-full" value={formData.notes} onChange={e => setFormData('notes', e.target.value)} />
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-4">
                            <SecondaryButton onClick={() => setIsFormOpen(false)}>Batal</SecondaryButton>
                            <PrimaryButton type="submit" disabled={formProcessing}>Simpan</PrimaryButton>
                        </div>
                    </form>
                </Modal>

                {/* Transaction Modal */}
                <Modal show={isTransactionOpen} onClose={() => setIsTransactionOpen(false)}>
                    {transactionItem && (
                        <form onSubmit={submitTransaction} className="p-6 space-y-6">
                            <h2 className="text-lg font-bold text-slate-900 border-b border-stone-100 pb-3">
                                Transaksi Stok: {transactionItem.name}
                            </h2>
                            
                            <div className="bg-stone-50 p-4 rounded-xl mb-4">
                                <span className="text-sm text-stone-500">Stok Saat Ini:</span>
                                <span className="ml-2 font-bold text-lg text-slate-900">{parseFloat(transactionItem.current_stock)} {transactionItem.unit}</span>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <InputLabel value="Jenis Transaksi" />
                                    <select 
                                        className="mt-1 block w-full bg-stone-50 border-stone-200 focus:border-indigo-500 rounded-xl"
                                        value={transData.type}
                                        onChange={e => setTransData('type', e.target.value)}
                                        required
                                    >
                                        <option value="in">Stok Masuk (+)</option>
                                        <option value="out">Stok Keluar (-)</option>
                                        <option value="adjustment">Penyesuaian Mutlak (=)</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value={`Jumlah (${transactionItem.unit})`} />
                                    <TextInput type="number" step="0.01" className="mt-1 block w-full text-lg font-bold" value={transData.quantity} onChange={e => setTransData('quantity', e.target.value)} required />
                                    {transErrors.quantity && <p className="text-sm text-rose-500 mt-1">{transErrors.quantity}</p>}
                                </div>
                                <div>
                                    <InputLabel value="Catatan Transaksi" />
                                    <TextInput className="mt-1 block w-full" placeholder="cth: Pembelian dari supplier A" value={transData.notes} onChange={e => setTransData('notes', e.target.value)} />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-4">
                                <SecondaryButton onClick={() => setIsTransactionOpen(false)}>Batal</SecondaryButton>
                                <PrimaryButton type="submit" disabled={transProcessing} className="bg-indigo-600">Simpan Transaksi</PrimaryButton>
                            </div>
                        </form>
                    )}
                </Modal>
            </div>
        </AppLayout>
    );
}
