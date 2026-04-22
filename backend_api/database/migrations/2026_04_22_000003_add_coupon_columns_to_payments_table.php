<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('original_amount', 10, 2)->nullable()->after('amount');
            $table->string('coupon_code')->nullable()->after('transaction_id');
            $table->decimal('coupon_discount_percent', 5, 2)->default(0)->after('coupon_code');
            $table->decimal('coupon_discount_amount', 10, 2)->default(0)->after('coupon_discount_percent');
            $table->foreignId('user_coupon_id')->nullable()->after('coupon_discount_amount')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_coupon_id');
            $table->dropColumn([
                'original_amount',
                'coupon_code',
                'coupon_discount_percent',
                'coupon_discount_amount',
            ]);
        });
    }
};
