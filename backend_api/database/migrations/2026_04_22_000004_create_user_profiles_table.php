<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('mini_bio')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('equipped_avatar_frame_item_id')->nullable()->constrained('user_items')->nullOnDelete();
            $table->foreignId('equipped_profile_title_item_id')->nullable()->constrained('user_items')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
