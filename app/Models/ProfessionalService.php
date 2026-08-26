<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalService extends Model
{
    protected $fillable = [
        'tenant_id', 'professional_id', 'name', 'description',
        'price', 'duration_minutes', 'is_active', 'position',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active'        => 'boolean',
        'position'         => 'integer',
    ];

    public function professional(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
