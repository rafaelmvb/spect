<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalTestQuestionOption extends Model
{
    protected $fillable = [
        'clinical_test_question_id', 'text', 'value', 'position',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ClinicalTestQuestion::class, 'clinical_test_question_id');
    }
}
