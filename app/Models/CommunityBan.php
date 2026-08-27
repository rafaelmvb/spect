<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityBan extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'product_id', 'reason', 'banned_by', 'expires_at', 'kind'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public const KIND_BAN = 'ban';

    public const KIND_SUSPENSION = 'suspension';

    /** Advertência é aviso, não restrição: não impede publicar. */
    public const KIND_WARNING = 'warning';

    /**
     * Impedido de publicar agora.
     *
     * Suspensão vencida deixa de valer sozinha — não é preciso rodar nada para
     * devolver o acesso no fim do prazo.
     */
    public static function isBanned(int $tenantId, int $userId, string|int|null $productId = null): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('kind', '!=', self::KIND_WARNING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('product_id')->orWhere('product_id', $productId))
            ->exists();
    }

    public function estaVigente(): bool
    {
        if ($this->kind === self::KIND_WARNING) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function descricao(): string
    {
        return match ($this->kind) {
            self::KIND_WARNING => 'Advertência',
            self::KIND_SUSPENSION => $this->expires_at
                ? 'Suspenso até '.$this->expires_at->format('d/m/Y')
                : 'Suspenso',
            default => 'Banido',
        };
    }
}
