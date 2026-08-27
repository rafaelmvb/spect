<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\Appointment;
use App\Models\ClinicalCopilotMessage;
use App\Models\ClinicalTestSession;
use App\Models\DailyMoodCheckin;
use App\Models\ProfessionalClinicalNote;
use App\Models\ProfessionalPatientLink;
use App\Models\ProfessionalTestAssignment;
use App\Models\User;
use RuntimeException;

/**
 * Copiloto Clínico: responde perguntas do profissional sobre um paciente,
 * cruzando prontuário, testes aplicados, humor e transcrições de sessão.
 *
 * O contexto é montado sob as mesmas regras que valem no resto do sistema:
 *
 *  - exige vínculo ATIVO, aceito pelo paciente;
 *  - só entram os testes que ESTE profissional aplicou — o que a pessoa
 *    respondeu por conta própria continua privado dela;
 *  - só entram as notas clínicas deste profissional;
 *  - transcrição só com consentimento de gravação registrado.
 *
 * Escopo, Parte 04 § 8.
 */
class ClinicalCopilotService
{
    /** Quantas trocas anteriores acompanham a pergunta. */
    private const HISTORICO = 8;

    public function __construct(
        private readonly AiService $ai,
        private readonly GoogleMeetService $meet,
    ) {}

    /**
     * @throws RuntimeException quando não há vínculo ativo
     */
    public function responder(User $profissional, User $paciente, string $pergunta): ClinicalCopilotMessage
    {
        $vinculo = $this->vinculoAtivo($profissional, $paciente);

        if (! $this->ai->available()) {
            throw new RuntimeException('A IA ainda não foi configurada nesta instalação.');
        }

        ClinicalCopilotMessage::create([
            'professional_user_id' => $profissional->id,
            'patient_user_id' => $paciente->id,
            'tenant_id' => $profissional->tenant_id,
            'role' => 'user',
            'content' => $pergunta,
        ]);

        $config = $this->config($profissional->tenant_id);

        $mensagens = array_merge(
            [['role' => 'system', 'content' => $this->promptDeSistema($paciente, $vinculo)]],
            $this->historico($profissional, $paciente),
            [['role' => 'user', 'content' => $pergunta]],
        );

        $resposta = $this->ai->complete($mensagens, $config, $profissional->tenant_id, $profissional->id);

        return ClinicalCopilotMessage::create([
            'professional_user_id' => $profissional->id,
            'patient_user_id' => $paciente->id,
            'tenant_id' => $profissional->tenant_id,
            'role' => 'assistant',
            'content' => $resposta,
            'model' => $config->model,
        ]);
    }

    /**
     * @throws RuntimeException
     */
    public function vinculoAtivo(User $profissional, User $paciente): ProfessionalPatientLink
    {
        $vinculo = ProfessionalPatientLink::where('professional_user_id', $profissional->id)
            ->where('patient_user_id', $paciente->id)
            ->ativo()
            ->first();

        if (! $vinculo) {
            throw new RuntimeException('Este paciente não autorizou o seu acesso aos dados dele.');
        }

        return $vinculo;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function historico(User $profissional, User $paciente): array
    {
        return ClinicalCopilotMessage::where('professional_user_id', $profissional->id)
            ->where('patient_user_id', $paciente->id)
            ->latest('id')
            ->limit(self::HISTORICO * 2)
            ->get()
            ->reverse()
            ->map(fn (ClinicalCopilotMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    private function config(?int $tenantId): AiConfig
    {
        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'copiloto_clinico')
            ->where('is_active', true)
            ->first();

        return $config ?? new AiConfig([
            'context' => 'copiloto_clinico',
            'temperature' => 0.3, // baixa: é análise de caso, não redação criativa
            'max_tokens' => 1500,
        ]);
    }

    private function promptDeSistema(User $paciente, ProfessionalPatientLink $vinculo): string
    {
        $dossie = $this->dossieDoPaciente($paciente, $vinculo);

        return <<<PROMPT
        Você é o Copiloto Clínico do Spectra, apoiando um profissional de saúde na análise
        de um caso. Escreva em português do Brasil.

        Regras:
        - Responda apenas com base no dossiê abaixo. Se a informação não estiver lá, diga
          que não consta no material disponível — nunca preencha a lacuna com suposição.
        - Você apoia o raciocínio clínico de quem já é profissional: não emita diagnóstico
          nem prescreva conduta, e não repita avisos de "procure um profissional".
        - Cite a origem do que afirmar: a sessão, o teste ou a nota de onde veio.
        - Seja direto e específico. Nada de generalidades sobre saúde mental.

        === DOSSIÊ DE {$paciente->name} ===
        {$dossie}
        === FIM DO DOSSIÊ ===
        PROMPT;
    }

    /**
     * Reúne o que o profissional já poderia ver na ficha — nada além disso.
     */
    private function dossieDoPaciente(User $paciente, ProfessionalPatientLink $vinculo): string
    {
        $profissionalId = $vinculo->professional_user_id;
        $partes = [];

        $partes[] = $this->blocoTestes($paciente, $profissionalId);
        $partes[] = $this->blocoNotas($paciente, $profissionalId);
        $partes[] = $this->blocoHumor($paciente);
        $partes[] = $this->blocoSessoes($paciente, $profissionalId);

        $preenchidas = array_filter($partes);

        return $preenchidas === []
            ? 'Ainda não há material clínico registrado para este paciente.'
            : implode("\n\n", $preenchidas);
    }

    private function blocoTestes(User $paciente, int $profissionalId): string
    {
        // Só o que este profissional aplicou. Teste que a pessoa fez sozinha
        // no aplicativo é privado dela.
        $atribuidos = ProfessionalTestAssignment::where('professional_user_id', $profissionalId)
            ->where('patient_user_id', $paciente->id)
            ->pluck('clinical_test_id');

        if ($atribuidos->isEmpty()) {
            return '';
        }

        $sessoes = ClinicalTestSession::where('user_id', $paciente->id)
            ->where('status', 'completed')
            ->whereIn('clinical_test_id', $atribuidos)
            ->with('test:id,name,category')
            ->latest('completed_at')
            ->limit(20)
            ->get();

        if ($sessoes->isEmpty()) {
            return '';
        }

        $linhas = $sessoes->map(function (ClinicalTestSession $s) {
            $tags = implode(', ', $s->challenge_tags ?? []);
            $data = $s->completed_at?->format('d/m/Y') ?? 'sem data';

            return "- {$s->test?->name} ({$data}): {$s->result_label}"
                .($s->score !== null ? " | pontuação {$s->score}" : '')
                .($tags !== '' ? " | indicadores: {$tags}" : '');
        })->implode("\n");

        return "TESTES APLICADOS POR VOCÊ\n{$linhas}";
    }

    private function blocoNotas(User $paciente, int $profissionalId): string
    {
        $notas = ProfessionalClinicalNote::where('professional_user_id', $profissionalId)
            ->where('patient_user_id', $paciente->id)
            ->latest('updated_at')
            ->get();

        if ($notas->isEmpty()) {
            return '';
        }

        $linhas = $notas->map(
            fn (ProfessionalClinicalNote $n) => "- [{$n->updated_at?->format('d/m/Y')}] {$n->note}"
        )->implode("\n");

        return "SUAS ANOTAÇÕES PRIVADAS\n{$linhas}";
    }

    private function blocoHumor(User $paciente): string
    {
        $checkins = DailyMoodCheckin::where('user_id', $paciente->id)
            ->latest('checkin_date')
            ->limit(30)
            ->get();

        if ($checkins->isEmpty()) {
            return '';
        }

        $linhas = $checkins->reverse()
            ->map(fn (DailyMoodCheckin $c) => $c->checkin_date->format('d/m').': '.$c->mood)
            ->implode(' | ');

        return "HUMOR REGISTRADO PELO PACIENTE (mais antigo para o mais recente)\n{$linhas}";
    }

    /**
     * Transcrições das consultas, quando houve consentimento de gravação.
     */
    private function blocoSessoes(User $paciente, int $profissionalId): string
    {
        $consultas = Appointment::where('user_id', $paciente->id)
            ->whereHas('professional', fn ($q) => $q->where('user_id', $profissionalId))
            ->where('status', 'completed')
            ->whereNotNull('recording_consent_at')
            ->whereNotNull('meet_space_name')
            ->latest('scheduled_date')
            ->limit(3)
            ->get();

        if ($consultas->isEmpty()) {
            return '';
        }

        $blocos = [];
        foreach ($consultas as $consulta) {
            $resultado = $this->meet->buscarTranscricao($consulta);
            if (($resultado['status'] ?? '') !== 'ok') {
                continue;
            }

            $texto = collect($resultado['entries'] ?? [])
                ->map(fn (array $e) => trim((string) ($e['text'] ?? '')))
                ->filter()
                ->implode(' ');

            if ($texto === '') {
                continue;
            }

            $data = $consulta->scheduled_date?->format('d/m/Y') ?? '';
            // Corta para não estourar o contexto do modelo com sessões longas.
            $blocos[] = "- Sessão de {$data}: ".mb_substr($texto, 0, 4000);
        }

        return $blocos === [] ? '' : "TRANSCRIÇÕES DAS SESSÕES\n".implode("\n\n", $blocos);
    }
}
