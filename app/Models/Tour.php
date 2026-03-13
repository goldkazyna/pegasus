<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tour extends Model
{
    protected $fillable = [
        'batch_id',
        'hotel_name',
        'stars',
        'country',
        'location',
        'departure_city',
        'airline',
        'flight_out',
        'flight_back',
        'nights',
        'room_type',
        'meal_plan',
        'guests',
        'price',
        'amenities',
        'raw_data',
    ];

    protected $casts = [
        'stars' => 'integer',
        'price' => 'integer',
        'flight_out' => 'datetime',
        'flight_back' => 'datetime',
        'amenities' => 'array',
        'raw_data' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TourBatch::class, 'batch_id');
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }
}
