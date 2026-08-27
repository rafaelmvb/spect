<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensagem de uma conversa do Copiloto Clínico.
 *
 * A conversa pertence ao par (profissional, paciente): outro profissional que
 * atenda a mesma pessoa não vê nada daqui.
 */
class ClinicalCopilotMessage extends Model
{
    protected $fillable = [
        'professional_user_id',
        'patient_user_id',
        'tenant_id',
        'role',
        'content',
        'model',
        'tokens',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function scopeDaConversa(Builder $query, int $professionalUserId, int $patientUserId): Builder
    {
        return $query->where('professional_user_id', $professionalUserId)
            ->where('patient_user_id', $patientUserId);
    }
}
