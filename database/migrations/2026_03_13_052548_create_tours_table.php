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
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('tour_batches')->cascadeOnDelete();
            $table->string('hotel_name');
            $table->unsignedTinyInteger('stars');
            $table->string('country');
            $table->string('location');
            $table->string('departure_city');
            $table->string('airline');
            $table->dateTime('flight_out');
            $table->dateTime('flight_back');
            $table->string('nights');
            $table->string('room_type');
            $table->string('meal_plan');
            $table->string('guests');
            $table->unsignedBigInteger('price');
            $table->json('amenities')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
