<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->default('qr_manual')->after('status');
            $table->string('provider')->nullable()->after('payment_method');
            $table->foreignId('reviewed_by')->nullable()->after('transaction_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->string('receipt_path')->nullable()->after('review_notes');
            $table->decimal('platform_fee_amount', 10, 2)->default(0)->after('receipt_path');
            $table->decimal('instructor_amount', 10, 2)->default(0)->after('platform_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'payment_method',
                'provider',
                'reviewed_at',
                'review_notes',
                'receipt_path',
                'platform_fee_amount',
                'instructor_amount',
            ]);
        });
    }
};
