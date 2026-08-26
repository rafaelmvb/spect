<?php

namespace App\Http\Controllers;

use App\Models\ClinicalTestSession;
use App\Models\TestResultShare;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestResultShareController extends Controller
{
    /**
     * Gera (ou recupera vigente) um link de compartilhamento para uma sessão concluída.
     */
    public function generate(Request $request, int $sessionId): JsonResponse
    {
        $session = ClinicalTestSession::with('test')->findOrFail($sessionId);

        if ($session->user_id !== auth()->id()) {
            abort(403);
        }

        if ($session->status !== 'completed') {
            return response()->json(['error' => 'Teste ainda não concluído.'], 422);
        }

        // Reusa link vigente (não expirado, criado pelo mesmo usuário)
        $existing = TestResultShare::where('user_id', auth()->id())
            ->where('clinical_test_session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'url'        => url("/resultado/{$existing->token}"),
                'expires_at' => $existing->expires_at->toIso8601String(),
            ]);
        }

        $token = bin2hex(random_bytes(24)); // 48 hex chars

        $share = TestResultShare::create([
            'user_id'                   => auth()->id(),
            'clinical_test_session_id'  => $sessionId,
            'token'                     => $token,
            'expires_at'                => Carbon::now()->addDays(7),
        ]);

        return response()->json([
            'url'        => url("/resultado/{$share->token}"),
            'expires_at' => $share->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Exibe o resultado público via token.
     * Rota pública — sem autenticação.
     */
    public function show(string $token): Response|\Illuminate\Http\RedirectResponse
    {
        $share = TestResultShare::with(['session.test', 'user'])->where('token', $token)->first();

        if (! $share || $share->isExpired()) {
            return Inertia::render('TestResult/Expired');
        }

        // Registra visualização (primeira vez)
        if (! $share->viewed_at) {
            $share->update(['viewed_at' => now()]);
        }

        $session = $share->session;
        $test    = $session->test;

        return Inertia::render('TestResult/Public', [
            'share' => [
                'expires_at'   => $share->expires_at->toIso8601String(),
                'user_name'    => $share->user->name,
                'test_name'    => $test->name,
                'test_category'=> $test->category,
                'result_label' => $session->result_label,
                'result_desc'  => null, // Não expõe descrição clínica no link público por padrão
                'challenge_tags' => $session->challenge_tags ?? [],
                'score'        => $session->score,
                'completed_at' => $session->completed_at?->format('d/m/Y'),
                'lgpd_notice'  => 'Este resultado foi compartilhado voluntariamente pelo participante para fins informativos. Não constitui diagnóstico médico. Dados processados conforme a LGPD (Lei 13.709/2018).',
            ],
        ]);
    }
}
