<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('invoice_token', 64)->nullable()->unique()->after('number');
        });

        DB::table('orders')->whereNull('invoice_token')->orderBy('id')->each(function ($order) {
            DB::table('orders')->where('id', $order->id)->update(['invoice_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_token']);
            $table->dropColumn('invoice_token');
        });
    }
};
