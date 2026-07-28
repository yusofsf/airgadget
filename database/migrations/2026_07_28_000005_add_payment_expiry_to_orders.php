<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_expires_at')->nullable()->index()->after('paid_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending_payment','pending_review','processing','completed','cancelled','failed','refunded','unpaid') NOT NULL DEFAULT 'pending_payment'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('orders')->where('status', 'unpaid')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending_payment','pending_review','processing','completed','cancelled','failed','refunded') NOT NULL DEFAULT 'pending_payment'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn('payment_expires_at');
        });
    }
};
