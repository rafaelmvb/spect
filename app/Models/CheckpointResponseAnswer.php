<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckpointResponseAnswer extends Model
{
    protected $fillable = ['checkpoint_response_id', 'checkpoint_question_id', 'value'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(CheckpointResponse::class, 'checkpoint_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CheckpointQuestion::class, 'checkpoint_question_id');
    }
}
