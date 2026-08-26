<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalTestSessionAnswer extends Model
{
    protected $fillable = [
        'clinical_test_session_id', 'clinical_test_question_id', 'answer',
    ];

    protected $casts = [
        'answer' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClinicalTestSession::class, 'clinical_test_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ClinicalTestQuestion::class, 'clinical_test_question_id');
    }
}
