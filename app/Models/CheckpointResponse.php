<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckpointResponse extends Model
{
    protected $fillable = ['checkpoint_id', 'user_id', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CheckpointResponseAnswer::class);
    }
}
