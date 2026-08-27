<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * TOTP (RFC 6238) para o acesso ao painel.
 *
 * Implementado aqui em vez de trazer uma dependência: são ~60 linhas de
 * HMAC-SHA1 e base32, e o projeto já carrega vendor versionado — cada pacote
 * novo pesa no repositório.
 *
 * Compatível com Google Authenticator, Authy e 1Password.
 */
class TwoFactorService
{
    private const PERIODO = 30;

    private const DIGITOS = 6;

    /** Aceita o código do passo anterior e do seguinte: relógios desalinham. */
    private const JANELA = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function gerarSegredo(): string
    {
        $bytes = random_bytes(20);
        $segredo = '';
        foreach (str_split($bytes) as $byte) {
            $segredo .= self::BASE32[ord($byte) % 32];
        }

        return $segredo;
    }

    /**
     * URI que o app de autenticação lê no QR Code.
     */
    public function uriDeProvisionamento(User $user, string $segredo): string
    {
        $emissor = rawurlencode((string) config('app.name', 'Spectra'));
        $conta = rawurlencode($user->email);

        return "otpauth://totp/{$emissor}:{$conta}?secret={$segredo}&issuer={$emissor}"
            .'&algorithm=SHA1&digits='.self::DIGITOS.'&period='.self::PERIODO;
    }

    public function codigoValido(string $segredo, string $codigo): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if (strlen($codigo) !== self::DIGITOS) {
            return false;
        }

        $passo = (int) floor(time() / self::PERIODO);

        for ($i = -self::JANELA; $i <= self::JANELA; $i++) {
            // hash_equals: comparar em tempo constante evita vazar o código
            // dígito a dígito pelo tempo de resposta.
            if (hash_equals($this->gerarCodigo($segredo, $passo + $i), $codigo)) {
                return true;
            }
        }

        return false;
    }

    private function gerarCodigo(string $segredo, int $passo): string
    {
        $chave = $this->decodificarBase32($segredo);
        $binario = pack('N*', 0).pack('N*', $passo);
        $hash = hash_hmac('sha1', $binario, $chave, true);

        $deslocamento = ord($hash[19]) & 0xF;
        $trecho = ((ord($hash[$deslocamento]) & 0x7F) << 24)
            | ((ord($hash[$deslocamento + 1]) & 0xFF) << 16)
            | ((ord($hash[$deslocamento + 2]) & 0xFF) << 8)
            | (ord($hash[$deslocamento + 3]) & 0xFF);

        return str_pad((string) ($trecho % (10 ** self::DIGITOS)), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    private function decodificarBase32(string $segredo): string
    {
        $bits = '';
        foreach (str_split(strtoupper($segredo)) as $char) {
            $posicao = strpos(self::BASE32, $char);
            if ($posicao === false) {
                continue;
            }
            $bits .= str_pad(decbin($posicao), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $octeto) {
            if (strlen($octeto) === 8) {
                $bytes .= chr(bindec($octeto));
            }
        }

        return $bytes;
    }

    /**
     * Códigos de recuperação, para quem perde o celular.
     *
     * @return list<string>
     */
    public function gerarCodigosDeRecuperacao(int $quantos = 8): array
    {
        return collect(range(1, $quantos))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    // ------------------------------------------------------- Persistência

    public function iniciarAtivacao(User $user): string
    {
        $segredo = $this->gerarSegredo();

        $user->two_factor_secret = Crypt::encryptString($segredo);
        // Só vale depois de confirmado: sem isso, um erro na configuração
        // trancaria a pessoa para fora do painel.
        $user->two_factor_confirmed_at = null;
        $user->save();

        return $segredo;
    }

    /**
     * @return list<string>|null códigos de recuperação, ou null se o código não confere
     */
    public function confirmarAtivacao(User $user, string $codigo): ?array
    {
        $segredo = $this->segredoDe($user);
        if (! $segredo || ! $this->codigoValido($segredo, $codigo)) {
            return null;
        }

        $codigos = $this->gerarCodigosDeRecuperacao();

        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($codigos));
        $user->two_factor_confirmed_at = now();
        $user->save();

        return $codigos;
    }

    public function desativar(User $user): void
    {
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();
    }

    public function ativo(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $this->segredoDe($user) !== null;
    }

    public function verificarLogin(User $user, string $codigo): bool
    {
        $segredo = $this->segredoDe($user);
        if (! $segredo) {
            return false;
        }

        if ($this->codigoValido($segredo, $codigo)) {
            return true;
        }

        return $this->consumirCodigoDeRecuperacao($user, $codigo);
    }

    /**
     * Código de recuperação vale uma vez só.
     */
    private function consumirCodigoDeRecuperacao(User $user, string $codigo): bool
    {
        $codigos = $this->codigosDeRecuperacaoDe($user);
        $informado = Str::upper(trim($codigo));

        $restantes = array_values(array_filter(
            $codigos,
            fn (string $c) => ! hash_equals(Str::upper($c), $informado)
        ));

        if (count($restantes) === count($codigos)) {
            return false;
        }

        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($restantes));
        $user->save();

        return true;
    }

    public function segredoDe(User $user): ?string
    {
        return $this->decifrar($user->two_factor_secret);
    }

    /**
     * @return list<string>
     */
    public function codigosDeRecuperacaoDe(User $user): array
    {
        $bruto = $this->decifrar($user->two_factor_recovery_codes);

        return $bruto ? (json_decode($bruto, true) ?: []) : [];
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
