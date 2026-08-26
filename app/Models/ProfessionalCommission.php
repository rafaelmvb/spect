<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalCommission extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID     = 'paid';

    protected $fillable = [
        'tenant_id', 'professional_id', 'appointment_id',
        'amount_gross', 'commission_percent', 'commission_amount', 'platform_fee',
        'status', 'payment_notes', 'approved_at', 'paid_at',
    ];

    protected $casts = [
        'amount_gross'       => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_amount'  => 'decimal:2',
        'platform_fee'       => 'decimal:2',
        'approved_at'        => 'datetime',
        'paid_at'            => 'datetime',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
