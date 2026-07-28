<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $guarded = [];

    public static function shippingMethods(): array
    {
        $defaults = [
            ['code' => 'mashhad_courier', 'name' => 'پیک شهری مشهد', 'description' => 'ارسال سریع داخل شهر مشهد', 'cost' => 70000, 'is_active' => true],
            ['code' => 'pickup', 'name' => 'دریافت حضوری', 'description' => 'تحویل از فروشگاه، عبدالمطلب ۳۵', 'cost' => 0, 'is_active' => true],
            ['code' => 'post', 'name' => 'پست پیشتاز', 'description' => 'ارسال به سراسر کشور با کد رهگیری', 'cost' => 95000, 'is_active' => true],
        ];

        $stored = static::where('key', 'shipping_methods')->value('value');

        return $stored ? (json_decode($stored, true) ?: $defaults) : $defaults;
    }
}
