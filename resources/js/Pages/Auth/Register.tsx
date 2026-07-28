import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Register({ phone = '' }: { phone?: string }) {
    const form = useForm({ first_name: '', last_name: '', phone_number: phone, email: '', password: '', password_confirmation: '' });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout>
            <Head title="ثبت‌نام" />
            <div className="auth-heading" dir="rtl">
                <span className="auth-kicker">حساب کاربری ایرگجت</span>
                <h1>ساخت حساب کاربری</h1>
                <p>برای پیگیری سفارش‌ها و نگهداری فاکتورها اطلاعات خود را وارد کنید.</p>
            </div>
            <form className="auth-form" onSubmit={submit} dir="rtl">
                <div className="auth-two"><label>نام<input value={form.data.first_name} onChange={(event) => form.setData('first_name', event.target.value)} autoFocus /></label><label>نام خانوادگی<input value={form.data.last_name} onChange={(event) => form.setData('last_name', event.target.value)} /></label></div>
                {form.errors.first_name && <small className="auth-error">{form.errors.first_name}</small>}
                {form.errors.last_name && <small className="auth-error">{form.errors.last_name}</small>}
                <label>شماره موبایل</label>
                <input type="tel" inputMode="numeric" placeholder="۰۹۱۲۱۲۳۴۵۶۷" value={form.data.phone_number} onChange={(event) => form.setData('phone_number', event.target.value)} />
                {form.errors.phone_number && <small className="auth-error">{form.errors.phone_number}</small>}
                <label>ایمیل</label>
                <input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                {form.errors.email && <small className="auth-error">{form.errors.email}</small>}
                <div className="auth-two"><label>رمز عبور<input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></label><label>تکرار رمز عبور<input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></label></div>
                {form.errors.password && <small className="auth-error">{form.errors.password}</small>}
                <button className="auth-submit" disabled={form.processing}>{form.processing ? 'در حال ثبت‌نام...' : 'ثبت‌نام'}</button>
            </form>
            <p className="auth-back">حساب دارید؟ <Link href="/login">وارد شوید</Link></p>
        </GuestLayout>
    );
}
