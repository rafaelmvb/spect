<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalPatientLink extends Model
{
    protected $fillable = [
        'professional_user_id',
        'patient_user_id',
        'product_id',
        'status',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }
}
