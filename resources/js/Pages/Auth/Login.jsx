import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, ArrowRight, Leaf } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPw, setShowPw] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout
            title="Selamat Datang Kembali"
            subtitle="Masuk untuk menikmati semua layanan premium Wawi Kadio."
        >
            <Head title="Masuk — Wawi Kadio" />

            {status && (
                <motion.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="mb-6 flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-sm font-medium"
                >
                    <Leaf size={16} className="shrink-0" />
                    {status}
                </motion.div>
            )}

            <form onSubmit={submit} className="space-y-5">
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
                            autoFocus
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            placeholder="nama@email.com"
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
                    <div className="flex items-center justify-between mb-2">
                        <label htmlFor="password" className="block text-sm font-semibold text-slate-700">
                            Password
                        </label>
                        {canResetPassword && (
                            <Link href={route('password.request')} className="text-xs text-emerald-600 hover:text-emerald-700 font-medium">
                                Lupa password?
                            </Link>
                        )}
                    </div>
                    <div className="relative">
                        <Lock size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="password"
                            type={showPw ? 'text' : 'password'}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            placeholder="Masukkan password"
                            className={`w-full pl-11 pr-12 py-3.5 bg-white border rounded-2xl text-slate-900 placeholder-slate-400 text-sm transition-all outline-none focus:ring-4 ${
                                errors.password
                                    ? 'border-red-400 focus:ring-red-500/10'
                                    : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/10'
                            }`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPw(!showPw)}
                            className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            {showPw ? <EyeOff size={18} /> : <Eye size={18} />}
                        </button>
                    </div>
                    <InputError message={errors.password} className="mt-2 text-xs" />
                </div>

                {/* Remember Me */}
                <label className="flex items-center gap-3 cursor-pointer group">
                    <div className="relative">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                            className="sr-only peer"
                        />
                        <div className="w-10 h-6 bg-slate-200 peer-checked:bg-emerald-500 rounded-full transition-colors" />
                        <div className="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4" />
                    </div>
                    <span className="text-sm text-slate-600 group-hover:text-slate-800 transition-colors">Ingat saya</span>
                </label>

                {/* Submit */}
                <motion.button
                    type="submit"
                    disabled={processing}
                    whileHover={{ scale: processing ? 1 : 1.01 }}
                    whileTap={{ scale: processing ? 1 : 0.99 }}
                    className={`w-full flex items-center justify-center gap-2 py-4 rounded-2xl font-bold text-base transition-all ${
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
                            Memproses...
                        </>
                    ) : (
                        <>Masuk <ArrowRight size={20} /></>
                    )}
                </motion.button>
            </form>

            {/* Register link */}
            <div className="mt-8 text-center">
                <p className="text-slate-500 text-sm">
                    Belum punya akun?{' '}
                    <Link href={route('register')} className="font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                        Daftar gratis →
                    </Link>
                </p>
            </div>

            {/* Divider + Social Proof */}
            <div className="mt-8 pt-8 border-t border-slate-100">
                <div className="flex items-center justify-center gap-6 text-center">
                    {[
                        { val: '500+', lbl: 'Tamu' },
                        { val: '5.0★', lbl: 'Rating' },
                        { val: '100%', lbl: 'Aman' },
                    ].map(({ val, lbl }) => (
                        <div key={lbl}>
                            <p className="text-lg font-extrabold text-slate-900">{val}</p>
                            <p className="text-xs text-slate-400 font-medium">{lbl}</p>
                        </div>
                    ))}
                </div>
            </div>
        </GuestLayout>
    );
}
