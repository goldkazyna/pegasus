<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'tour_id',
        'user_id',
        'generated_text',
        'status',
        'regeneration_count',
        'publish_at',
        'published_at',
        'telegram_message_id',
    ];

    protected $casts = [
        'regeneration_count' => 'integer',
        'publish_at' => 'datetime',
        'published_at' => 'datetime',
        'telegram_message_id' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canRegenerate(): bool
    {
        return $this->regeneration_count < config('claude.max_regenerations');
    }
}
