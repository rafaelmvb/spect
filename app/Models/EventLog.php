<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento de comportamento. Deliberadamente sem relação com User: o elo é o
 * subject_hash, e reintroduzir a foreign key desfaria a separação exigida
 * pelo escopo (Parte 02 § 5).
 */
class EventLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject_hash',
        'tenant_id',
        'event',
        'subject_type',
        'subject_id',
        'position',
        'duration',
        'value',
        'context',
        'session_token',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
            'value' => 'float',
        ];
    }

    public function scopeDoTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDoEvento(Builder $query, string $evento): Builder
    {
        return $query->where('event', $evento);
    }

    public function scopeDoConteudo(Builder $query, string $tipo, string $id): Builder
    {
        return $query->where('subject_type', $tipo)->where('subject_id', $id);
    }
}
