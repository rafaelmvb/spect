<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\ChildProfile;
use App\Models\ClinicalTestSession;
use App\Models\DailyMoodCheckin;
use App\Models\MemberLessonProgress;
use App\Models\ProfessionalPatientLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Central de Privacidade do aluno.
 *
 * Escopo, Parte 01 § 3.3: exportar cópia dos dados e solicitar a exclusão
 * definitiva da conta, do histórico e dos relatórios.
 */
class PrivacidadeController extends Controller
{
    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function index(Request $request, string $slug): \Inertia\Response
    {
        $user = $request->user();
        $product = $this->getProduct($request);

        return \Inertia\Inertia::render('MemberAreaApp/Privacidade', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'config' => $product->member_area_config,
            'slug' => $slug,
            'resumo' => $this->resumoDosDados($user),
            'profissionais' => ProfessionalPatientLink::where('patient_user_id', $user->id)
                ->ativo()
                ->with('professional:id,name')
                ->get()
                ->map(fn (ProfessionalPatientLink $l) => [
                    'id' => $l->id,
                    'nome' => $l->professional?->name,
                    'desde' => $l->responded_at?->format('d/m/Y'),
                ]),
        ]);
    }

    /**
     * Baixa tudo que a conta guarda, em JSON.
     */
    public function exportar(Request $request, string $slug): StreamedResponse
    {
        $user = $request->user();

        $dados = [
            'gerado_em' => now()->toIso8601String(),
            'conta' => [
                'nome' => $user->name,
                'email' => $user->email,
                'criada_em' => $user->created_at?->toIso8601String(),
            ],
            'perfis_infantis' => ChildProfile::doResponsavel((int) $user->id)->get()
                ->map(fn (ChildProfile $p) => [
                    'nome' => $p->name,
                    'nascimento' => $p->birth_date?->format('Y-m-d'),
                    'vinculo' => $p->vinculoLegivel(),
                ]),
            'testes_respondidos' => ClinicalTestSession::where('user_id', $user->id)
                ->with('test:id,name')
                ->get()
                ->map(fn (ClinicalTestSession $s) => [
                    'teste' => $s->test?->name,
                    'para' => $s->child_profile_id ? 'perfil infantil' : 'você',
                    'status' => $s->status,
                    'pontuacao' => $s->score,
                    'resultado' => $s->result_label,
                    'concluido_em' => $s->completed_at?->toIso8601String(),
                ]),
            'humor' => DailyMoodCheckin::where('user_id', $user->id)->get()
                ->map(fn ($m) => ['data' => $m->checkin_date?->format('Y-m-d'), 'humor' => $m->mood]),
            'progresso_nas_aulas' => MemberLessonProgress::where('user_id', $user->id)->get()
                ->map(fn ($p) => [
                    'aula_id' => $p->member_lesson_id,
                    'percentual' => $p->progress_percent,
                    'concluida_em' => $p->completed_at?->toIso8601String(),
                ]),
            'conversas_com_a_ia' => AiChatMessage::where('user_id', $user->id)->get()
                ->map(fn ($m) => [
                    'quem' => $m->role === 'user' ? 'você' : 'mentor',
                    'mensagem' => $m->content,
                    'em' => $m->created_at?->toIso8601String(),
                ]),
            'profissionais_autorizados' => ProfessionalPatientLink::where('patient_user_id', $user->id)
                ->with('professional:id,name')
                ->get()
                ->map(fn (ProfessionalPatientLink $l) => [
                    'profissional' => $l->professional?->name,
                    'situacao' => $l->status,
                    'respondido_em' => $l->responded_at?->toIso8601String(),
                ]),
        ];

        $nome = 'meus-dados-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $nome,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Exclusão definitiva. Exige a senha: apagar a conta por engano não tem volta.
     */
    public function excluirConta(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'senha' => ['required', 'string'],
            'confirmacao' => ['required', 'string', 'in:EXCLUIR'],
        ], [
            'confirmacao.in' => 'Escreva EXCLUIR para confirmar.',
        ]);

        if (! Hash::check($request->input('senha'), $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Senha incorreta.'], 422);
        }

        // Admin não se exclui por aqui: derrubaria o acesso ao painel do tenant.
        if ($user->canAccessPanel()) {
            return response()->json([
                'ok' => false,
                'message' => 'Contas com acesso ao painel não podem ser excluídas por aqui. Fale com o suporte.',
            ], 422);
        }

        $id = (int) $user->id;

        DB::transaction(function () use ($id) {
            // O que não some por cascade da foreign key.
            ClinicalTestSession::where('user_id', $id)->delete();
            DailyMoodCheckin::where('user_id', $id)->delete();
            MemberLessonProgress::where('user_id', $id)->delete();
            AiChatMessage::where('user_id', $id)->delete();
            ProfessionalPatientLink::where('patient_user_id', $id)->delete();
            ChildProfile::where('guardian_user_id', $id)->delete();

            User::where('id', $id)->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'message' => 'Sua conta e seu histórico foram excluídos.',
            'redirect' => '/entrar',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function resumoDosDados(User $user): array
    {
        return [
            'testes' => ClinicalTestSession::where('user_id', $user->id)->count(),
            'humor' => DailyMoodCheckin::where('user_id', $user->id)->count(),
            'aulas' => MemberLessonProgress::where('user_id', $user->id)->count(),
            'conversas' => AiChatMessage::where('user_id', $user->id)->count(),
            'perfis_infantis' => ChildProfile::doResponsavel((int) $user->id)->count(),
        ];
    }
}
