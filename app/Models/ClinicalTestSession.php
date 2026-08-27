<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalTestSession extends Model
{
    protected $fillable = [
        'user_id',
        'child_profile_id',
        'respondent_relationship', 'clinical_test_id', 'product_id', 'status',
        'score', 'result_label', 'challenge_tags', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'challenge_tags' => 'array',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ClinicalTest::class, 'clinical_test_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ClinicalTestSessionAnswer::class);
    }
}
