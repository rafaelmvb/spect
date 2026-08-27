<?php

namespace App\Http\Controllers;

use App\Models\ClinicalCopilotMessage;
use App\Models\User;
use App\Services\ClinicalCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Copiloto Clínico no painel do profissional.
 */
class ClinicalCopilotController extends Controller
{
    public function __construct(private readonly ClinicalCopilotService $copiloto) {}

    /** Conversa já havida sobre este paciente. */
    public function historico(Request $request, int $patientId): JsonResponse
    {
        $profissional = $request->user();
        $paciente = $this->pacienteAutorizadoOuFalha($request, $patientId);

        $mensagens = ClinicalCopilotMessage::daConversa((int) $profissional->id, (int) $paciente->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ClinicalCopilotMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'em' => $m->created_at?->format('d/m/Y H:i'),
            ]);

        return response()->json([
            'ok' => true,
            'paciente' => ['id' => $paciente->id, 'nome' => $paciente->name],
            'mensagens' => $mensagens,
        ]);
    }

    public function perguntar(Request $request, int $patientId): JsonResponse
    {
        $validado = $request->validate([
            'pergunta' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $paciente = $this->pacienteAutorizadoOuFalha($request, $patientId);

        try {
            $resposta = $this->copiloto->responder($request->user(), $paciente, $validado['pergunta']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'resposta' => [
                'id' => $resposta->id,
                'role' => 'assistant',
                'content' => $resposta->content,
                'em' => $resposta->created_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /** Apaga a conversa deste profissional sobre este paciente. */
    public function limpar(Request $request, int $patientId): JsonResponse
    {
        $paciente = $this->pacienteAutorizadoOuFalha($request, $patientId);

        ClinicalCopilotMessage::daConversa((int) $request->user()->id, (int) $paciente->id)->delete();

        return response()->json(['ok' => true, 'message' => 'Conversa apagada.']);
    }

    /**
     * O vínculo ativo é a autorização do paciente: sem ele, nem o histórico abre.
     */
    private function pacienteAutorizadoOuFalha(Request $request, int $patientId): User
    {
        $paciente = User::find($patientId);
        abort_if($paciente === null, 404, 'Paciente não encontrado.');

        try {
            $this->copiloto->vinculoAtivo($request->user(), $paciente);
        } catch (\RuntimeException) {
            abort(403, 'Este paciente não autorizou o seu acesso aos dados dele.');
        }

        return $paciente;
    }
}
