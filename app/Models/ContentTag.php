<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag de conteúdo com peso, nas três dimensões do escopo (Parte 02 § 2.1):
 * categoria principal, formato/duração e nível/momento.
 */
class ContentTag extends Model
{
    public const DIM_CATEGORIA = 'categoria';

    public const DIM_FORMATO = 'formato';

    public const DIM_NIVEL = 'nivel';

    protected $fillable = [
        'tenant_id',
        'taggable_type',
        'taggable_id',
        'tag',
        'dimension',
        'weight',
    ];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    public function scopeDaDimensao(Builder $query, string $dimensao): Builder
    {
        return $query->where('dimension', $dimensao);
    }

    public function scopeDoConteudo(Builder $query, string $tipo, string $id): Builder
    {
        return $query->where('taggable_type', $tipo)->where('taggable_id', $id);
    }
}
