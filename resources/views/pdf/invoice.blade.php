<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans; direction: rtl; color: #17212b; font-size: 11px; }
        .header { border-bottom: 3px solid #ee6a42; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { font-size: 25px; font-weight: bold; direction: ltr; text-align: left; color: #17212b; }
        .brand span { color: #ee6a42; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 0 0 7px; color: #26343f; }
        .muted { color: #68757f; }
        .meta { background: #f5f7f8; border-radius: 8px; padding: 12px; margin-bottom: 18px; }
        .meta table, .totals { width: 100%; border-collapse: collapse; }
        .meta td { width: 50%; padding: 5px; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .items th { background: #26343f; color: white; padding: 9px 6px; }
        .items td { border-bottom: 1px solid #e1e6e9; padding: 9px 6px; text-align: center; }
        .items td:first-child, .items th:first-child { text-align: right; }
        .totals { width: 48%; margin-right: auto; }
        .totals td { padding: 7px; border-bottom: 1px solid #e5eaed; }
        .totals td:last-child { text-align: left; font-weight: bold; }
        .grand td { background: #fff0e9; color: #c84e2b; font-size: 13px; border: 0; }
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #dfe5e9; text-align: center; color: #68757f; }
    </style>
</head>
<body>
    @php
        $statusLabels = [
            'pending_payment' => 'در انتظار پرداخت',
            'unpaid' => 'پرداخت‌نشده',
            'pending_review' => 'ثبت شد',
            'processing' => 'تأیید شده',
            'completed' => 'ارسال شد',
            'cancelled' => 'لغو شده',
            'failed' => 'پرداخت ناموفق',
            'refunded' => 'مرجوع شده',
        ];
        $money = fn ($value) => number_format((int) $value).' تومان';
        $persianDateTime = new IntlDateFormatter(
            'fa_IR@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd HH:mm'
        );
    @endphp
    <div class="header">
        <div class="brand">air<span>gadget</span></div>
        <h1>فاکتور فروش سفارش {{ $order->number }}</h1>
        <div class="muted">تاریخ ثبت: {{ $order->created_at ? $persianDateTime->format($order->created_at) : '—' }}</div>
    </div>

    <h2>مشخصات خریدار (گیرنده)</h2>
    <div class="meta">
        <table>
            <tr>
                <td><strong>نام و نام خانوادگی:</strong> {{ data_get($order->address, 'customer_name') }}</td>
                <td><strong>شماره موبایل:</strong> {{ data_get($order->address, 'phone') }}</td>
            </tr>
            <tr>
                <td><strong>کد پستی:</strong> {{ data_get($order->address, 'postal_code') }}</td>
                <td><strong>وضعیت سفارش:</strong> {{ $statusLabels[$order->status] ?? $order->status }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>آدرس مشتری:</strong> {{ data_get($order->address, 'full') }}</td>
            </tr>
            @if($order->payment_reference)
                <tr>
                    <td colspan="2"><strong>شماره پیگیری پرداخت:</strong> {{ $order->payment_reference }}</td>
                </tr>
            @endif
            @if($order->payment_method === 'card_to_card')
                <tr>
                    <td><strong>روش پرداخت:</strong> کارت‌به‌کارت</td>
                    <td><strong>مبلغ اعلام‌شده:</strong> {{ $money($order->card_to_card_amount) }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if($sender)
        <h2>مشخصات فروشنده (فرستنده)</h2>
        <div class="meta">
            <table>
                <tr>
                    <td><strong>نام و نام خانوادگی:</strong> {{ trim($sender->first_name.' '.$sender->last_name) }}</td>
                    <td><strong>شماره موبایل:</strong> {{ $sender->phone_number }}</td>
                </tr>
                <tr>
                    <td><strong>کد پستی:</strong> {{ $sender->postal_code ?: '—' }}</td>
                    <td><strong>نام فروشگاه:</strong> ایرگجت</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>آدرس فرستنده:</strong> {{ $sender->address ?: '—' }}</td>
                </tr>
            </table>
        </div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>عنوان محصول</th>
                <th>شناسه</th>
                <th>تعداد</th>
                <th>مبلغ واحد</th>
                <th>مبلغ کل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $money($item->price) }}</td>
                    <td>{{ $money($item->price * $item->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>جمع محصولات</td><td>{{ $money($order->subtotal) }}</td></tr>
        <tr><td>هزینه ارسال ({{ $shipping['name'] ?? $order->shipping_method }})</td><td>{{ $order->shipping_cost ? $money($order->shipping_cost) : 'رایگان' }}</td></tr>
        <tr class="grand"><td>جمع کل</td><td>{{ $money($order->total) }}</td></tr>
    </table>

    <div class="footer">ایرگجت - خراسان رضوی، مشهد، عبدالمطلب ۳۵ - پشتیبانی 09205850190</div>
</body>
</html>
