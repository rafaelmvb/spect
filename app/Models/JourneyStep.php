<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JourneyStep extends Model
{
    protected $fillable = [
        'journey_id', 'title', 'description', 'cover',
        'position', 'unlock_type', 'unlock_after_days',
    ];

    protected $casts = [
        'position'          => 'integer',
        'unlock_after_days' => 'integer',
    ];

    protected $appends = ['cover_url'];

    public function getCoverUrlAttribute(): ?string
    {
        if (empty($this->attributes['cover'] ?? null)) return null;
        return app(StorageService::class)->url($this->attributes['cover']);
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(JourneyStepContent::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JourneyStepItem::class)->orderBy('position');
    }

    public function checkpoint(): HasOne
    {
        return $this->hasOne(Checkpoint::class, 'journey_step_id');
    }
}
