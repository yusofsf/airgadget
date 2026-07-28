<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'address' => 'array',
        'paid_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'inventory_released' => 'boolean',
        'card_to_card_amount' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
