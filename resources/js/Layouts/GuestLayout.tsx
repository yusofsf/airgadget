import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="auth-shell">
            <div className="auth-visual" dir="rtl">
                <Link href="/" className="logo">air<span>gadget</span><b>●</b></Link>
                <div>
                    <span>خرید مطمئن، انتخاب هوشمند</span>
                    <h2>دنیای گجت‌ها،<br />یک قدم نزدیک‌تر.</h2>
                    <p>لوازم جانبی اصل با ارسال سریع و پشتیبانی واقعی.</p>
                </div>
            </div>
            <main className="auth-main">
                <div className="auth-card">{children}</div>
            </main>
        </div>
    );
}
