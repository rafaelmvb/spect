<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perfil de uma criança sob a conta do responsável.
 *
 * Não é um usuário: não tem login nem e-mail. Os dados são privados por padrão
 * e só o responsável os alcança (escopo, Parte 01, regra de privacidade infantil).
 */
class ChildProfile extends Model
{
    public const VINCULOS = ['mae', 'pai', 'avo', 'responsavel_legal', 'outro'];

    protected $fillable = [
        'guardian_user_id',
        'tenant_id',
        'name',
        'birth_date',
        'relationship',
        'relationship_other',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(ClinicalTestSession::class, 'child_profile_id');
    }

    public function scopeDoResponsavel(Builder $query, int $userId): Builder
    {
        return $query->where('guardian_user_id', $userId);
    }

    public function idade(): ?int
    {
        return $this->birth_date?->age;
    }

    public function vinculoLegivel(): string
    {
        return match ($this->relationship) {
            'mae' => 'Mãe',
            'pai' => 'Pai',
            'avo' => 'Avó ou avô',
            'responsavel_legal' => 'Responsável legal',
            default => $this->relationship_other ?: 'Responsável',
        };
    }

    /**
     * Identificador técnico do perfil para a telemetria.
     *
     * Escopo, Parte 02 § 5: "eventos da criança permanecem segregados dos
     * eventos da conta do responsável" — daí um hash próprio, e não o do adulto.
     */
    public function subjectHash(): string
    {
        return hash_hmac('sha256', 'child:'.$this->id, (string) config('app.key'));
    }
}
