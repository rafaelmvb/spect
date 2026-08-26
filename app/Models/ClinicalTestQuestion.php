<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalTestQuestion extends Model
{
    protected $fillable = [
        'clinical_test_id', 'text', 'type', 'scale_min', 'scale_max', 'scale_labels', 'position',
    ];

    protected $casts = [
        'scale_labels' => 'array',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ClinicalTest::class, 'clinical_test_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ClinicalTestQuestionOption::class, 'clinical_test_question_id')->orderBy('position');
    }
}
