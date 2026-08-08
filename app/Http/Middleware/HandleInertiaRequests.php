<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'seo' => [
                'canonical' => $request->attributes->get('seo.canonical'),
                'robots' => $request->attributes->get('seo.robots', 'noindex,follow'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'favoriteProductIds' => fn () => $request->user() && Schema::hasTable('favorite_product_user')
                ? $request->user()->favoriteProducts()->pluck('products.id')
                : [],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'createdProductId' => fn () => $request->session()->get('created_product_id'),
            ],
            'shippingMethods' => fn () => StoreSetting::shippingMethods(),
            'cardToCard' => [
                'number' => config('services.card_to_card.card_number'),
                'holder' => config('services.card_to_card.holder'),
            ],
        ];
    }
}
