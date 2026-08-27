<?php

namespace App\Http\Controllers;

use App\Models\GoogleMeetCredential;
use App\Models\Setting;
use App\Services\GoogleMeetService;
use App\Support\SecretSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuração da teleconsulta no painel: credenciais do Google Cloud e status
 * de quem já conectou a conta.
 */
class TeleconsultaSettingsController extends Controller
{
    public function __construct(private readonly GoogleMeetService $meet) {}

    public function mostrar(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $conectados = GoogleMeetCredential::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->get()
            ->map(fn (GoogleMeetCredential $c) => [
                'profissional' => $c->user?->name,
                'conta_google' => $c->google_email,
                'com_erro' => $c->last_error !== null,
                'erro' => $c->last_error,
            ]);

        return response()->json([
            'ok' => true,
            'configurado' => $this->meet->configurado(),
            'client_id' => (string) (Setting::get('google_meet_client_id', '', $tenantId) ?? ''),
            // Nunca devolve o segredo: só diz se existe.
            'client_secret_definido' => SecretSetting::isSet('google_meet_client_secret', $tenantId),
            'redirect' => (string) (Setting::get('google_meet_redirect', '', $tenantId) ?? ''),
            'redirect_sugerido' => url('/p/meet/callback'),
            'profissionais_conectados' => $conectados,
            'origem_env' => config('services.google_meet.client_id') !== '',
        ]);
    }

    public function salvar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'url', 'max:255'],
        ], [
            'redirect.url' => 'O URI de redirecionamento precisa ser um endereço completo, começando com https://.',
        ]);

        $tenantId = $request->user()->tenant_id;

        Setting::set('google_meet_client_id', trim($validado['client_id'] ?? ''), $tenantId);
        Setting::set('google_meet_redirect', trim($validado['redirect'] ?? ''), $tenantId);

        // Campo em branco mantém o segredo atual — é como o formulário se
        // comporta em toda a tela de configurações.
        if (! empty($validado['client_secret'])) {
            SecretSetting::set('google_meet_client_secret', trim($validado['client_secret']), $tenantId);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Credenciais salvas. Agora cada profissional conecta a conta Google em Agenda.',
            'configurado' => $this->meet->configurado(),
        ]);
    }

    /** Remove as credenciais e desconecta todo mundo. */
    public function remover(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        Setting::set('google_meet_client_id', '', $tenantId);
        Setting::set('google_meet_redirect', '', $tenantId);
        SecretSetting::set('google_meet_client_secret', null, $tenantId);

        $desconectados = GoogleMeetCredential::where('tenant_id', $tenantId)->delete();

        return response()->json([
            'ok' => true,
            'message' => $desconectados > 0
                ? "Credenciais removidas e {$desconectados} conta(s) desconectada(s). Novas consultas não terão sala de vídeo."
                : 'Credenciais removidas.',
        ]);
    }
}
