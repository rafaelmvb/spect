<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalTestScoringRule extends Model
{
    protected $fillable = [
        'clinical_test_id', 'min_score', 'max_score', 'result_label', 'result_description', 'challenge_tags',
    ];

    protected $casts = [
        'challenge_tags' => 'array',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ClinicalTest::class, 'clinical_test_id');
    }
}
