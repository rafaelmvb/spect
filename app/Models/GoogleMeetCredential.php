<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Tokens do Google de um profissional. Gravados cifrados: um dump do banco não
 * pode dar acesso à conta Google de ninguém.
 */
class GoogleMeetCredential extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'google_email',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'last_error_at',
        'last_error',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setTokens(string $accessToken, ?string $refreshToken, ?int $expiresIn): void
    {
        $this->access_token = Crypt::encryptString($accessToken);

        // O Google só devolve refresh_token no primeiro consentimento: não
        // sobrescreve com null numa reautorização.
        if ($refreshToken !== null && $refreshToken !== '') {
            $this->refresh_token = Crypt::encryptString($refreshToken);
        }

        $this->expires_at = $expiresIn ? now()->addSeconds($expiresIn - 60) : null;
        $this->last_error = null;
        $this->last_error_at = null;
        $this->save();
    }

    public function accessTokenPlain(): ?string
    {
        return $this->decifrar($this->access_token);
    }

    public function refreshTokenPlain(): ?string
    {
        return $this->decifrar($this->refresh_token);
    }

    public function expirou(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function registrarFalha(string $mensagem): void
    {
        $this->update([
            'last_error' => mb_substr($mensagem, 0, 255),
            'last_error_at' => now(),
        ]);
    }

    private function decifrar(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            return null;
        }
    }
}
