<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre profissional e paciente.
 *
 * Nasce sempre em 'pending': o profissional convida, o paciente aceita. Só um
 * vínculo em 'active' dá ao profissional acesso à ficha do paciente, e o
 * paciente pode revogar a qualquer momento.
 */
class ProfessionalPatientLink extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'professional_user_id',
        'patient_user_id',
        'product_id',
        'status',
        'requested_at',
        'responded_at',
    ];

    /**
     * Default no model em vez de na coluna: alterar o default no banco exigiria
     * SQL específico de cada driver e quebraria o SQLite dos testes.
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePendente(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function estaAtivo(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Convida sem sobrescrever uma resposta que o paciente já deu: quem recusou
     * ou revogou não volta a 'pending' sozinho — e quem já aceitou continua ativo.
     */
    public static function convidar(int $professionalUserId, int $patientUserId, string $productId): self
    {
        $existente = static::where('professional_user_id', $professionalUserId)
            ->where('patient_user_id', $patientUserId)
            ->where('product_id', $productId)
            ->first();

        if ($existente) {
            return $existente;
        }

        return static::create([
            'professional_user_id' => $professionalUserId,
            'patient_user_id' => $patientUserId,
            'product_id' => $productId,
            'status' => self::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }
}
