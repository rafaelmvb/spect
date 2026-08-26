<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeuroIndicator extends Model
{
    protected $fillable = [
        'neuro_area_id', 'name', 'description', 'scale_min', 'scale_max', 'position', 'is_active',
    ];

    protected $casts = [
        'scale_min' => 'integer',
        'scale_max' => 'integer',
        'is_active' => 'boolean',
        'position'  => 'integer',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(NeuroArea::class, 'neuro_area_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(NeuroUserScore::class);
    }
}
