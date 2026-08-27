<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\GoogleMeetCredential;
use App\Services\GoogleMeetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Conexão da conta Google do profissional e sala de vídeo do agendamento.
 */
class GoogleMeetController extends Controller
{
    public function __construct(private readonly GoogleMeetService $meet) {}

    /** Manda o profissional para o consentimento do Google. */
    public function conectar(Request $request): RedirectResponse
    {
        if (! $this->meet->configurado()) {
            return back()->with('error', 'A integração com o Google ainda não foi configurada nesta instalação.');
        }

        $state = Str::random(40);
        $request->session()->put('google_meet_state', $state);

        return redirect()->away($this->meet->urlDeAutorizacao($state));
    }

    /** Volta do Google com o código de autorização. */
    public function callback(Request $request): RedirectResponse
    {
        $esperado = $request->session()->pull('google_meet_state');

        // Sem o state conferido, um terceiro poderia ligar a conta Google dele
        // à sessão de outra pessoa.
        if (! $esperado || $request->query('state') !== $esperado) {
            return redirect('/p/agenda')->with('error', 'Autorização inválida. Tente conectar de novo.');
        }

        if ($request->query('error')) {
            return redirect('/p/agenda')->with('error', 'Você cancelou a autorização do Google.');
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect('/p/agenda')->with('error', 'O Google não devolveu o código de autorização.');
        }

        try {
            $credencial = $this->meet->conectarConta($request->user(), $code);
        } catch (\Throwable $e) {
            report($e);

            return redirect('/p/agenda')->with('error', 'Não foi possível conectar a conta Google.');
        }

        return redirect('/p/agenda')->with(
            'success',
            'Conta Google conectada'.($credencial->google_email ? " ({$credencial->google_email})" : '').'.'
        );
    }

    public function desconectar(Request $request): RedirectResponse
    {
        GoogleMeetCredential::where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Conta Google desconectada. Novas consultas não terão sala de vídeo.');
    }

    /** Cria (ou devolve) a sala do agendamento. */
    public function criarSala(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->doProfissionalOuFalha($request, $appointmentId);

        $atualizado = $this->meet->criarSala($appointment);

        if (! $atualizado?->meet_uri) {
            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível criar a sala. Verifique se a conta Google está conectada em Agenda.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'meet_uri' => $atualizado->meet_uri,
            'meet_code' => $atualizado->meet_code,
        ]);
    }

    public function transcricao(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->doProfissionalOuFalha($request, $appointmentId);

        $resultado = $this->meet->buscarTranscricao($appointment);
        $status = $resultado['status'] === 'ok' ? 200 : 422;

        return response()->json($resultado, $status);
    }

    /**
     * O paciente autoriza a gravação e a transcrição da própria consulta.
     *
     * O $slug vem do ResolveMemberAreaFromUser, que o injeta na primeira posição
     * mesmo nas rotas limpas — omiti-lo faz o id da consulta receber o slug.
     */
    public function registrarConsentimento(Request $request, string $slug, int $appointmentId): JsonResponse
    {
        $appointment = Appointment::where('id', $appointmentId)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if($appointment === null, 404, 'Consulta não encontrada.');

        $validado = $request->validate(['autoriza' => ['required', 'boolean']]);

        $appointment->update([
            'recording_consent_at' => $validado['autoriza'] ? now() : null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => $validado['autoriza']
                ? 'Gravação autorizada. Você pode retirar a autorização quando quiser.'
                : 'Autorização retirada. Esta consulta não será gravada nem transcrita.',
        ]);
    }

    private function doProfissionalOuFalha(Request $request, int $appointmentId): Appointment
    {
        $appointment = Appointment::with('professional')
            ->where('id', $appointmentId)
            ->whereHas('professional', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        abort_if($appointment === null, 404, 'Agendamento não encontrado.');

        return $appointment;
    }
}
