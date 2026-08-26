<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckpointQuestion extends Model
{
    protected $fillable = [
        'checkpoint_id', 'label', 'type', 'options', 'required', 'position',
    ];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
        'position' => 'integer',
    ];

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CheckpointResponseAnswer::class);
    }
}
