<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('generated_text');
            $table->enum('status', ['draft', 'approved', 'scheduled', 'published', 'rejected'])->default('draft');
            $table->unsignedTinyInteger('regeneration_count')->default(0);
            $table->dateTime('publish_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
