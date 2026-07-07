import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload, X } from 'lucide-react';
import { useState, useRef } from 'react';
import { Link } from '@inertiajs/react';

export default function FacilityForm({ facility }) {
    const isEdit = !!facility;
    const [imagePreview, setImagePreview] = useState(facility?.image_url || null);
    const imageRef = useRef(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: facility?.name || '',
        type: facility?.type || 'homestay',
        description: facility?.description || '',
        capacity: facility?.capacity || '',
        price_per_day: facility?.price_per_day || '',
        price_per_hour: facility?.price_per_hour || '',
        image: null,
        is_active: facility?.is_active ?? true,
        _method: isEdit ? 'PUT' : 'POST',
    });

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('image', file);
            const reader = new FileReader();
            reader.onloadend = () => setImagePreview(reader.result);
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            post(route('admin.facilities.update', facility.id), { forceFormData: true });
        } else {
            post(route('admin.facilities.store'), { forceFormData: true });
        }
    };

    const typeOptions = [
        { value: 'homestay', label: '🏠 Homestay / Villa' },
        { value: 'gazebo', label: '🌿 Gazebo' },
        { value: 'pool', label: '🏊 Kolam Renang' },
        { value: 'cafe', label: '☕ Café' },
    ];

    return (
        <AppLayout title={isEdit ? 'Edit Fasilitas' : 'Tambah Fasilitas'}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Fasilitas — Wawi Kadio`} />

            <div className="max-w-2xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link
                        href={route('admin.facilities.index')}
                        className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <div>
                        <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">
                            {isEdit ? 'Edit Fasilitas' : 'Tambah Fasilitas Baru'}
                        </h2>
                        <p className="text-sm text-gray-500">
                            {isEdit ? `Mengedit: ${facility.name}` : 'Isi informasi fasilitas resort'}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} encType="multipart/form-data">
                    <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        {/* Image Upload */}
                        <div
                            className="relative h-48 bg-gray-50 dark:bg-gray-700 cursor-pointer group"
                            onClick={() => imageRef.current?.click()}
                        >
                            {imagePreview ? (
                                <>
                                    <img src={imagePreview} alt="" className="w-full h-full object-cover" />
                                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <div className="text-white text-sm flex items-center gap-2">
                                            <Upload size={16} /> Ganti Gambar
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="flex flex-col items-center justify-center h-full text-gray-400 gap-2">
                                    <Upload size={32} />
                                    <p className="text-sm">Klik untuk upload gambar</p>
                                    <p className="text-xs">JPG, PNG max. 2MB</p>
                                </div>
                            )}
                            <input
                                ref={imageRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={handleImageChange}
                            />
                        </div>
                        {errors.image && <p className="text-xs text-red-500 px-5 py-1">{errors.image}</p>}

                        <div className="p-5 space-y-4">
                            {/* Name */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Nama Fasilitas *
                                </label>
                                <input
                                    value={data.name}
                                    onChange={e => setData('name', e.target.value)}
                                    placeholder="contoh: Villa Wawi 1"
                                    className="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none"
                                />
                                {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
                            </div>

                            {/* Type */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Tipe Fasilitas *
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    {typeOptions.map(opt => (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            onClick={() => setData('type', opt.value)}
                                            className={`px-3 py-2 rounded-lg text-sm font-medium border transition-all text-left ${
                                                data.type === opt.value
                                                    ? 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                    : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-gray-300'
                                            }`}
                                        >
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Description */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Deskripsi
                                </label>
                                <textarea
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    rows={3}
                                    placeholder="Deskripsi fasilitas..."
                                    className="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none resize-none"
                                />
                            </div>

                            {/* Capacity + Pricing */}
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kapasitas</label>
                                    <input
                                        type="number"
                                        value={data.capacity}
                                        onChange={e => setData('capacity', e.target.value)}
                                        min="1"
                                        placeholder="orang"
                                        className="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Harga/Hari (Rp)</label>
                                    <input
                                        type="number"
                                        value={data.price_per_day}
                                        onChange={e => setData('price_per_day', e.target.value)}
                                        min="0"
                                        placeholder="0"
                                        className="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Harga/Jam (Rp)</label>
                                    <input
                                        type="number"
                                        value={data.price_per_hour}
                                        onChange={e => setData('price_per_hour', e.target.value)}
                                        min="0"
                                        placeholder="0"
                                        className="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-green-500 outline-none"
                                    />
                                </div>
                            </div>

                            {/* Status */}
                            <div className="flex items-center justify-between py-3 border-t border-gray-100 dark:border-gray-700">
                                <div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Status Fasilitas</p>
                                    <p className="text-xs text-gray-400">Fasilitas aktif dapat dibooking tamu</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setData('is_active', !data.is_active)}
                                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                        data.is_active ? 'bg-green-500' : 'bg-gray-200'
                                    }`}
                                >
                                    <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                                        data.is_active ? 'translate-x-6' : 'translate-x-1'
                                    }`} />
                                </button>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 px-5 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700">
                            <Link
                                href={route('admin.facilities.index')}
                                className="flex-1 text-center px-4 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                Batal
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white rounded-xl text-sm font-medium transition-colors"
                            >
                                {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Fasilitas'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
