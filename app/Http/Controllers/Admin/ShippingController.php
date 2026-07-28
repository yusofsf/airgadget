<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'methods' => ['required', 'array', 'size:3'],
            'methods.*.code' => ['required', 'in:mashhad_courier,pickup,post'],
            'methods.*.name' => ['required', 'string', 'max:100'],
            'methods.*.description' => ['nullable', 'string', 'max:500'],
            'methods.*.cost' => ['required', 'numeric', 'min:0'],
            'methods.*.is_active' => ['required', 'boolean'],
        ]);

        StoreSetting::updateOrCreate(
            ['key' => 'shipping_methods'],
            ['value' => json_encode(array_values($validated['methods']), JSON_UNESCAPED_UNICODE)]
        );

        return redirect()->route('admin')->with('status', 'روش‌ها و هزینه‌های ارسال ذخیره شد.');
    }
}
