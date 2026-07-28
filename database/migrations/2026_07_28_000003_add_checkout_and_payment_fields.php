<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('phone_number');
            $table->text('address')->nullable()->after('postal_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_authority', 64)->nullable()->unique()->after('payment_receipt');
            $table->string('payment_reference', 64)->nullable()->after('payment_authority');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
            $table->boolean('inventory_released')->default(false)->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_authority']);
            $table->dropColumn(['payment_authority', 'payment_reference', 'paid_at', 'inventory_released']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'address']);
        });
    }
};
