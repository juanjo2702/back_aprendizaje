<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('platform_fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 8)->default('BOB');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->boolean('has_open_disputes')->default(false);
            $table->text('dispute_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
