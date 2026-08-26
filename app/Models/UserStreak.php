<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'current_streak',
        'longest_streak',
        'last_checkin_date',
    ];

    protected $casts = [
        'last_checkin_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
