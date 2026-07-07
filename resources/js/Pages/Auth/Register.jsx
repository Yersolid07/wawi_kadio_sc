import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { User, Mail, Lock, Eye, EyeOff, ArrowRight, CheckCircle2 } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { useState } from 'react';

const benefits = [
    'Reservasi kamar & fasilitas eksklusif',
    'Akses katalog menu kuliner premium',
    'Lacak status pesanan & pembayaran',
    'Ulasan tamu tersimpan selamanya',
];

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [showPw, setShowPw] = useState(false);
    const [showPwConf, setShowPwConf] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            title="Buat Akun Gratis"
            subtitle="Mulai petualangan retret alam Anda hari ini."
        >
            <Head title="Daftar — Wawi Kadio" />

            {/* Benefits */}
            <motion.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.15 }}
                className="mb-7 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl"
            >
                <p className="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-3">Keuntungan Akun Gratis</p>
                <ul className="space-y-1.5">
                    {benefits.map(b => (
                        <li key={b} className="flex items-center gap-2.5 text-sm text-emerald-800">
                            <CheckCircle2 size={14} className="text-emerald-500 shrink-0" />
                            {b}
                        </li>
                    ))}
                </ul>
            </motion.div>

            <form onSubmit={submit} className="space-y-5">
                {/* Name */}
                <div>
                    <label htmlFor="name" className="block text-sm font-semibold text-slate-700 mb-2">
                        Nama Lengkap
                    </label>
                    <div className="relative">
                        <User size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="name"
                            type="text"
                            autoComplete="name"
                            autoFocus
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            placeholder="Nama lengkap Anda"
                            required
                            className={`w-full pl-11 pr-4 py-3.5 bg-white border rounded-2xl text-slate-900 placeholder-slate-400 text-sm transition-all outline-none focus:ring-4 ${
                                errors.name
                                    ? 'border-red-400 focus:ring-red-500/10'
                                    : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10'
                            }`}
                        />
                    </div>
                    <InputError message={errors.name} className="mt-2 text-xs" />
                </div>

                {/* Email */}
                <div>
                    <label htmlFor="email" className="block text-sm font-semibold text-slate-700 mb-2">
                        Email
                    </label>
                    <div className="relative">
                        <Mail size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="email"
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            placeholder="nama@email.com"
                            required
                            className={`w-full pl-11 pr-4 py-3.5 bg-white border rounded-2xl text-slate-900 placeholder-slate-400 text-sm transition-all outline-none focus:ring-4 ${
                                errors.email
                                    ? 'border-red-400 focus:ring-red-500/10'
                                    : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10'
                            }`}
                        />
                    </div>
                    <InputError message={errors.email} className="mt-2 text-xs" />
                </div>

                {/* Password */}
                <div>
                    <label htmlFor="password" className="block text-sm font-semibold text-slate-700 mb-2">
                        Password
                    </label>
                    <div className="relative">
                        <Lock size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="password"
                            type={showPw ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            placeholder="Minimal 8 karakter"
                            required
                            className={`w-full pl-11 pr-12 py-3.5 bg-white border rounded-2xl text-slate-900 placeholder-slate-400 text-sm transition-all outline-none focus:ring-4 ${
                                errors.password
                                    ? 'border-red-400 focus:ring-red-500/10'
                                    : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10'
                            }`}
                        />
                        <button type="button" onClick={() => setShowPw(!showPw)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                            {showPw ? <EyeOff size={18} /> : <Eye size={18} />}
                        </button>
                    </div>
                    <InputError message={errors.password} className="mt-2 text-xs" />
                </div>

                {/* Confirm Password */}
                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-semibold text-slate-700 mb-2">
                        Konfirmasi Password
                    </label>
                    <div className="relative">
                        <Lock size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="password_confirmation"
                            type={showPwConf ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={e => setData('password_confirmation', e.target.value)}
                            placeholder="Ulangi password"
                            required
                            className={`w-full pl-11 pr-12 py-3.5 bg-white border rounded-2xl text-slate-900 placeholder-slate-400 text-sm transition-all outline-none focus:ring-4 ${
                                errors.password_confirmation
                                    ? 'border-red-400 focus:ring-red-500/10'
                                    : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10'
                            }`}
                        />
                        <button type="button" onClick={() => setShowPwConf(!showPwConf)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                            {showPwConf ? <EyeOff size={18} /> : <Eye size={18} />}
                        </button>
                    </div>
                    <InputError message={errors.password_confirmation} className="mt-2 text-xs" />
                </div>

                {/* Submit */}
                <motion.button
                    type="submit"
                    disabled={processing}
                    whileHover={{ scale: processing ? 1 : 1.01 }}
                    whileTap={{ scale: processing ? 1 : 0.99 }}
                    className={`w-full flex items-center justify-center gap-2 py-4 rounded-2xl font-bold text-base transition-all mt-2 ${
                        processing
                            ? 'bg-slate-300 text-slate-500 cursor-not-allowed'
                            : 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40'
                    }`}
                >
                    {processing ? (
                        <>
                            <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Mendaftarkan...
                        </>
                    ) : (
                        <>Buat Akun Sekarang <ArrowRight size={20} /></>
                    )}
                </motion.button>
            </form>

            {/* Login link */}
            <div className="mt-6 text-center">
                <p className="text-slate-500 text-sm">
                    Sudah punya akun?{' '}
                    <Link href={route('login')} className="font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                        Masuk di sini →
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
