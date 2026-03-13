<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'telegram_id',
        'name',
        'is_admin',
        'is_active',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tourBatches(): HasMany
    {
        return $this->hasMany(TourBatch::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
