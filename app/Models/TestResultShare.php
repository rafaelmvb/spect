<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResultShare extends Model
{
    protected $fillable = [
        'user_id',
        'clinical_test_session_id',
        'token',
        'expires_at',
        'viewed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'viewed_at'  => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClinicalTestSession::class, 'clinical_test_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
