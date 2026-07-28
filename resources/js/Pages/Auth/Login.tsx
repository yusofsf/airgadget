import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

type LoginMode = 'identify' | 'login' | 'setup' | 'unregistered';

export default function Login({
    status,
    mode = 'identify',
    phone = '',
    registered = false,
}: {
    status?: string;
    mode?: LoginMode;
    phone?: string;
    registered?: boolean;
}) {
    const form = useForm({
        phone,
        password: '',
        password_confirmation: '',
        remember: true,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (mode === 'identify') {
            form.post(route('login.identify'));
        } else if (mode === 'setup') {
            form.post(route('admin.setup-password'));
        } else if (mode === 'login') {
            form.post(route('login'));
        }
    };

    const title = mode === 'setup'
        ? 'فعال‌سازی حساب مدیر'
        : mode === 'unregistered'
            ? 'شماره ثبت‌نام نشده است'
        : mode === 'login'
            ? 'خوش آمدید'
            : 'ورود به ایرگجت';

    return (
        <GuestLayout>
            <Head title={title} />

            <div className="auth-heading" dir="rtl">
                <span className="auth-kicker">حساب کاربری ایرگجت</span>
                <h1>{title}</h1>
                <p>
                    {mode === 'setup'
                        ? 'این اولین ورود شماره مدیر است. یک رمز امن برای ورودهای بعدی بسازید.'
                        : mode === 'login'
                            ? 'این شماره موبایل قبلاً ثبت‌نام شده است؛ رمز عبور حساب را وارد کنید.'
                            : mode === 'unregistered'
                                ? 'برای این شماره حسابی پیدا نشد. ابتدا ثبت‌نام کنید.'
                            : 'برای ادامه، شماره موبایل خود را وارد کنید.'}
                </p>
            </div>

            {status && <div className="auth-status">{status}</div>}
            {registered && mode === 'login' && <div className="auth-status">این شماره موبایل قبلاً ثبت‌نام شده است.</div>}

            <form className="auth-form" onSubmit={submit} dir="rtl">
                <label htmlFor="phone">شماره موبایل</label>
                <div className="auth-phone-row">
                    <input
                        id="phone"
                        type="tel"
                        inputMode="numeric"
                        autoComplete="tel"
                        placeholder="۰۹۱۲۱۲۳۴۵۶۷"
                        value={form.data.phone}
                        disabled={mode !== 'identify'}
                        autoFocus={mode === 'identify'}
                        onChange={(event) => form.setData('phone', event.target.value)}
                    />
                    {mode !== 'identify' && <Link href="/login?change=1">تغییر</Link>}
                </div>
                {form.errors.phone && <small className="auth-error">{form.errors.phone}</small>}

                {(mode === 'login' || mode === 'setup') && (
                    <>
                        <label htmlFor="password">{mode === 'setup' ? 'رمز عبور جدید' : 'رمز عبور'}</label>
                        <input
                            id="password"
                            type="password"
                            autoComplete={mode === 'setup' ? 'new-password' : 'current-password'}
                            value={form.data.password}
                            autoFocus
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        {form.errors.password && <small className="auth-error">{form.errors.password}</small>}
                    </>
                )}

                {mode === 'setup' && (
                    <>
                        <label htmlFor="password_confirmation">تکرار رمز عبور</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                    </>
                )}

                {mode === 'login' && (
                    <label className="auth-remember">
                        <input
                            type="checkbox"
                            checked={form.data.remember}
                            onChange={(event) => form.setData('remember', event.target.checked)}
                        />
                        ورود من را به خاطر بسپار
                    </label>
                )}

                {mode !== 'unregistered' && <button className="auth-submit" disabled={form.processing}>
                    {form.processing ? 'لطفاً صبر کنید…' : mode === 'identify' ? 'ادامه' : mode === 'setup' ? 'ساخت حساب مدیر' : 'ورود'}
                </button>}
            </form>

            {mode === 'unregistered' && <Link className="auth-submit auth-register-link" href={`/register?phone=${encodeURIComponent(phone)}`}>ثبت‌نام با این شماره</Link>}

            <p className="auth-back">حساب ندارید؟ <Link href="/register">ثبت‌نام کنید</Link></p>
            <p className="auth-back"><Link href="/">بازگشت به فروشگاه</Link></p>
        </GuestLayout>
    );
}
