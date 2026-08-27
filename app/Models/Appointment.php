<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW   = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING   => 'Pendente',
        self::STATUS_CONFIRMED => 'Confirmado',
        self::STATUS_COMPLETED => 'Realizado',
        self::STATUS_CANCELLED => 'Cancelado',
        self::STATUS_NO_SHOW   => 'Não compareceu',
    ];

    protected $fillable = [
        'tenant_id', 'professional_id', 'user_id',
        'client_name', 'client_email', 'client_phone',
        'scheduled_date', 'scheduled_time', 'duration_minutes',
        'status', 'notes', 'admin_notes',
        'cancelled_reason', 'cancelled_at',
        'meet_space_name',
        'meet_uri',
        'meet_code',
        'meet_created_at',
        'recording_consent_at',
    ];

    protected $casts = [
        'scheduled_date'  => 'date',
        'duration_minutes'=> 'integer',
        'cancelled_at'    => 'datetime',
            'meet_created_at' => 'datetime',
        'recording_consent_at' => 'datetime',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProfessionalReview::class);
    }
}
