<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>پیام جدید فرم تماس ایرگجت</title>
</head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:2;color:#17212b">
    <h2>پیام جدید فرم تماس ایرگجت</h2>
    <p><strong>نام:</strong> {{ $senderName }}</p>
    <p><strong>شماره تماس:</strong> <span dir="ltr">{{ $senderPhone }}</span></p>
    <p><strong>متن پیام:</strong></p>
    <div style="white-space:pre-wrap">{{ $contactMessage }}</div>
</body>
</html>
