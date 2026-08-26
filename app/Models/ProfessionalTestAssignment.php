<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalTestAssignment extends Model
{
    protected $fillable = [
        'professional_user_id',
        'patient_user_id',
        'clinical_test_id',
        'product_id',
        'status',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ClinicalTest::class, 'clinical_test_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }
}
