<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalCommission;
use App\Models\ProfessionalPortfolioItem;
use App\Models\ProfessionalReview;
use App\Models\ProfessionalService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalPanelController extends Controller
{
    private function professional(): Professional
    {
        $pro = auth()->user()->professional;
        abort_if(! $pro, 403, 'Perfil profissional não encontrado.');
        return $pro;
    }

    private function fmtAppointment(Appointment $a): array
    {
        return [
            'id'               => $a->id,
            'client_name'      => $a->client_name,
            'client_email'     => $a->client_email,
            'client_phone'     => $a->client_phone,
            'scheduled_date'   => $a->scheduled_date?->format('Y-m-d'),
            'scheduled_time'   => substr($a->scheduled_time ?? '', 0, 5),
            'duration_minutes' => $a->duration_minutes,
            'status'           => $a->status,
            'status_label'     => Appointment::STATUSES[$a->status] ?? $a->status,
            'notes'            => $a->notes,
        ];
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): Response
    {
        $pro = $this->professional();

        $today    = now()->toDateString();
        $thisMonth = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        $upcoming = Appointment::where('professional_id', $pro->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_date', '>=', $today)
            ->orderBy('scheduled_date')->orderBy('scheduled_time')
            ->limit(10)
            ->get()
            ->map(fn ($a) => $this->fmtAppointment($a));

        $stats = [
            'appointments_month' => Appointment::where('professional_id', $pro->id)
                ->whereBetween('scheduled_date', $thisMonth)->count(),
            'completed_month'    => Appointment::where('professional_id', $pro->id)
                ->whereBetween('scheduled_date', $thisMonth)->where('status', 'completed')->count(),
            'pending'            => Appointment::where('professional_id', $pro->id)
                ->whereIn('status', ['pending', 'confirmed'])->where('scheduled_date', '>=', $today)->count(),
            'avg_rating'         => round((float) ProfessionalReview::where('professional_id', $pro->id)->where('is_visible', true)->avg('rating'), 1),
            'total_reviews'      => ProfessionalReview::where('professional_id', $pro->id)->where('is_visible', true)->count(),
            'services'           => ProfessionalService::where('professional_id', $pro->id)->where('is_active', true)->count(),
        ];

        $recentReviews = ProfessionalReview::where('professional_id', $pro->id)
            ->where('is_visible', true)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'user_name'  => $r->user?->name ?? 'Anônimo',
                'created_at' => $r->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Profissional/Dashboard', [
            'professional'   => $this->fmtProfessional($pro),
            'upcoming'       => $upcoming,
            'stats'          => $stats,
            'recent_reviews' => $recentReviews,
        ]);
    }

    // ─── Perfil ───────────────────────────────────────────────────────────────

    public function perfil(): Response
    {
        $pro = $this->professional();
        return Inertia::render('Profissional/Perfil', [
            'professional' => $this->fmtProfessional($pro),
        ]);
    }

    public function updatePerfil(Request $request): JsonResponse
    {
        $pro = $this->professional();

        $v = $request->validate([
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'website'             => ['nullable', 'url', 'max:255'],
            'bio'                 => ['nullable', 'string', 'max:5000'],
            'experience'          => ['nullable', 'string', 'max:5000'],
            'specialty'           => ['nullable', 'string', 'max:255'],
            'registration_type'   => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'price_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'social_links'        => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.linkedin'  => ['nullable', 'string', 'max:255'],
            'social_links.facebook'  => ['nullable', 'string', 'max:255'],
            'social_links.youtube'   => ['nullable', 'string', 'max:255'],
            'social_links.tiktok'    => ['nullable', 'string', 'max:255'],
            'avatar'              => ['nullable', 'file', 'image', 'max:3072'],
        ]);

        if ($request->hasFile('avatar')) {
            $v['avatar'] = (new StorageService($pro->tenant_id))
                ->putFile('professionals/' . ($pro->tenant_id ?? 'global'), $request->file('avatar'));
        } else {
            unset($v['avatar']);
        }

        $pro->update($v);

        return response()->json(['success' => true, 'professional' => $this->fmtProfessional($pro->fresh())]);
    }

    // ─── Serviços ─────────────────────────────────────────────────────────────

    public function servicos(): Response
    {
        $pro = $this->professional();
        $services = ProfessionalService::where('professional_id', $pro->id)
            ->orderBy('position')->orderBy('name')
            ->get()
            ->map(fn ($s) => $this->fmtService($s));

        return Inertia::render('Profissional/Servicos', [
            'professional' => $this->fmtProfessional($pro),
            'services'     => $services,
        ]);
    }

    public function storeServico(Request $request): JsonResponse
    {
        $pro = $this->professional();
        $v = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'price'            => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $maxPos = ProfessionalService::where('professional_id', $pro->id)->max('position') ?? 0;
        $service = ProfessionalService::create([
            ...$v,
            'professional_id' => $pro->id,
            'tenant_id'       => $pro->tenant_id,
            'duration_minutes'=> $v['duration_minutes'] ?? 60,
            'is_active'       => $v['is_active'] ?? true,
            'position'        => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'service' => $this->fmtService($service)]);
    }

    public function updateServico(Request $request, ProfessionalService $service): JsonResponse
    {
        abort_if($service->professional_id !== $this->professional()->id, 403);
        $v = $request->validate([
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'price'            => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
        $service->update($v);
        return response()->json(['success' => true, 'service' => $this->fmtService($service->fresh())]);
    }

    public function destroyServico(ProfessionalService $service): JsonResponse
    {
        abort_if($service->professional_id !== $this->professional()->id, 403);
        $service->delete();
        return response()->json(['success' => true]);
    }

    // ─── Portfólio ────────────────────────────────────────────────────────────

    public function portfolio(): Response
    {
        $pro = $this->professional();
        $items = ProfessionalPortfolioItem::where('professional_id', $pro->id)
            ->orderBy('position')
            ->get()
            ->map(fn ($i) => $this->fmtPortfolioItem($i));

        return Inertia::render('Profissional/Portfolio', [
            'professional' => $this->fmtProfessional($pro),
            'items'        => $items,
        ]);
    }

    public function storePortfolioItem(Request $request): JsonResponse
    {
        $pro = $this->professional();
        $v = $request->validate([
            'type'        => ['required', 'in:image,video'],
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'video_url'   => ['nullable', 'url', 'max:500', 'regex:#^https://(www\.)?(youtube\.com/watch|youtu\.be/|vimeo\.com/)#i'],
            'image'       => ['nullable', 'file', 'image', 'max:5120'],
        ], [
            'video_url.regex' => 'O link de vídeo deve ser do YouTube ou Vimeo.',
        ]);

        $path     = null;
        $videoUrl = null;

        if ($v['type'] === 'image' && $request->hasFile('image')) {
            $path = (new StorageService($pro->tenant_id))
                ->putFile('professionals/' . ($pro->tenant_id ?? 'global') . '/portfolio', $request->file('image'));
        } elseif ($v['type'] === 'video') {
            $videoUrl = $v['video_url'] ?? null;
        }

        $maxPos = ProfessionalPortfolioItem::where('professional_id', $pro->id)->max('position') ?? 0;

        $item = ProfessionalPortfolioItem::create([
            'professional_id' => $pro->id,
            'type'            => $v['type'],
            'path'            => $path,
            'video_url'       => $videoUrl,
            'title'           => $v['title'] ?? null,
            'description'     => $v['description'] ?? null,
            'position'        => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'item' => $this->fmtPortfolioItem($item->fresh())]);
    }

    public function destroyPortfolioItem(ProfessionalPortfolioItem $item): JsonResponse
    {
        abort_if($item->professional_id !== $this->professional()->id, 403);
        $item->delete();
        return response()->json(['success' => true]);
    }

    // ─── Avaliações ───────────────────────────────────────────────────────────

    public function avaliacoes(): Response
    {
        $pro = $this->professional();

        $reviews = ProfessionalReview::where('professional_id', $pro->id)
            ->where('is_visible', true)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'user_name'  => $r->user?->name ?? 'Anônimo',
                'created_at' => $r->created_at->format('d/m/Y'),
            ]);

        $stats = [
            'avg_rating'    => round((float) ProfessionalReview::where('professional_id', $pro->id)->where('is_visible', true)->avg('rating'), 1),
            'total'         => ProfessionalReview::where('professional_id', $pro->id)->where('is_visible', true)->count(),
            'dist'          => ProfessionalReview::where('professional_id', $pro->id)->where('is_visible', true)
                ->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating'),
        ];

        return Inertia::render('Profissional/Avaliacoes', [
            'professional' => $this->fmtProfessional($pro),
            'reviews'      => $reviews,
            'stats'        => $stats,
        ]);
    }

    // ─── Agenda ───────────────────────────────────────────────────────────────

    public function agenda(Request $request): Response
    {
        $pro   = $this->professional();
        $year  = (int) ($request->query('year',  now()->year));
        $month = (int) ($request->query('month', now()->month));

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $appointments = Appointment::where('professional_id', $pro->id)
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('scheduled_date')->orderBy('scheduled_time')
            ->get()
            ->map(fn ($a) => $this->fmtAppointment($a));

        $stats = [
            'total'     => Appointment::where('professional_id', $pro->id)->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->count(),
            'confirmed' => Appointment::where('professional_id', $pro->id)->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->whereIn('status', ['pending', 'confirmed'])->count(),
            'completed' => Appointment::where('professional_id', $pro->id)->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->where('status', 'completed')->count(),
            'cancelled' => Appointment::where('professional_id', $pro->id)->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Profissional/Agenda', [
            'professional' => $this->fmtProfessional($pro),
            'appointments' => $appointments,
            'stats'        => $stats,
            'year'         => $year,
            'month'        => $month,
        ]);
    }

    public function updateAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->professional_id !== $this->professional()->id, 403);

        $v = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(Appointment::STATUSES))],
            'notes'  => ['nullable', 'string', 'max:2000'],
            'cancelled_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $update = ['status' => $v['status']];
        if (isset($v['notes'])) $update['notes'] = $v['notes'];
        if ($v['status'] === 'cancelled') {
            $update['cancelled_reason'] = $v['cancelled_reason'] ?? null;
            $update['cancelled_at']     = now();
        }

        $appointment->update($update);

        // Vincula o paciente ao profissional após a 1ª consulta concluída
        if ($v['status'] === 'completed' && $appointment->user_id && auth()->user()?->id) {
            $product = \App\Models\Product::where('tenant_id', auth()->user()->tenant_id)
                ->whereHas('users', fn ($q) => $q->where('users.id', $appointment->user_id))
                ->first();

            if ($product) {
                \App\Models\ProfessionalPatientLink::firstOrCreate(
                    [
                        'professional_user_id' => auth()->id(),
                        'patient_user_id'      => $appointment->user_id,
                        'product_id'           => (string) $product->id,
                    ],
                    ['status' => 'active'],
                );
            }
        }

        return response()->json(['success' => true, 'appointment' => $this->fmtAppointment($appointment->fresh())]);
    }

    // ─── Disponibilidade ──────────────────────────────────────────────────────

    public function disponibilidade(): Response
    {
        $pro   = $this->professional();
        $avail = $pro->availabilities()->orderBy('day_of_week')->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'day_of_week'  => $a->day_of_week,
                'start_time'   => substr($a->start_time ?? '', 0, 5),
                'end_time'     => substr($a->end_time ?? '', 0, 5),
                'lunch_start'  => $a->lunch_start ? substr($a->lunch_start, 0, 5) : null,
                'lunch_end'    => $a->lunch_end   ? substr($a->lunch_end,   0, 5) : null,
                'is_active'    => $a->is_active,
            ]);

        return Inertia::render('Profissional/Disponibilidade', [
            'professional' => $this->fmtProfessional($pro),
            'slots'        => $avail,  // corrigido: era 'availability', Vue espera 'slots'
        ]);
    }

    public function saveDisponibilidade(Request $request): JsonResponse
    {
        $pro = $this->professional();

        $request->validate([
            'slots'                 => ['required', 'array'],
            'slots.*.day_of_week'   => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'    => ['required', 'date_format:H:i'],
            'slots.*.end_time'      => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
            'slots.*.lunch_start'   => ['nullable', 'date_format:H:i'],
            'slots.*.lunch_end'     => ['nullable', 'date_format:H:i'],
            'slots.*.is_active'     => ['nullable', 'boolean'],
        ]);

        foreach ($request->slots as $slot) {
            $lunchStart = ($slot['lunch_start'] ?? null) ?: null;
            $lunchEnd   = ($slot['lunch_end']   ?? null) ?: null;

            ProfessionalAvailability::updateOrCreate(
                ['professional_id' => $pro->id, 'day_of_week' => $slot['day_of_week']],
                [
                    'start_time'   => $slot['start_time'],
                    'end_time'     => $slot['end_time'],
                    'lunch_start'  => $lunchStart,
                    'lunch_end'    => $lunchEnd,
                    'slot_duration'=> 30, // mínimo; slots reais vêm da duração do serviço
                    'is_active'    => $slot['is_active'] ?? false,
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    public function financeiro(): Response
    {
        $pro = $this->professional();

        $commissions = ProfessionalCommission::where('professional_id', $pro->id)
            ->with('appointment.user')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'amount_gross'      => $c->amount_gross,
                'commission_percent'=> $c->commission_percent,
                'commission_amount' => $c->commission_amount,
                'status'            => $c->status,
                'client_name'       => $c->appointment?->user?->name ?? '—',
                'appointment_date'  => $c->appointment?->scheduled_at?->format('d/m/Y'),
                'appointment_time'  => $c->appointment?->scheduled_at?->format('H:i'),
            ]);

        $byMonth = ProfessionalCommission::where('professional_id', $pro->id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'total' => (float) $r->total]);

        $stats = [
            'total_earned'      => $commissions->sum('commission_amount'),
            'total_pending'     => $commissions->where('status', ProfessionalCommission::STATUS_PENDING)->sum('commission_amount'),
            'total_approved'    => $commissions->where('status', ProfessionalCommission::STATUS_APPROVED)->sum('commission_amount'),
            'total_paid'        => $commissions->where('status', ProfessionalCommission::STATUS_PAID)->sum('commission_amount'),
            'count_pending'     => $commissions->where('status', ProfessionalCommission::STATUS_PENDING)->count(),
            'total_appointments'=> $commissions->count(),
            'commission_percent'=> $pro->commission_percent,
        ];

        return Inertia::render('Profissional/Financeiro', [
            'commissions' => $commissions,
            'by_month'    => $byMonth,
            'stats'       => $stats,
        ]);
    }

    // ─── Triagens e Testes ────────────────────────────────────────────────────

    public function triagens(Request $request): Response
    {
        $user = auth()->user();

        $tests = \App\Models\ClinicalTest::where('professional_user_id', $user->id)
            ->orderBy('position')
            ->withCount(['sessions', 'questions'])
            ->get()
            ->map(fn ($t) => $this->formatClinicalTest($t));

        $patients = \App\Models\ProfessionalPatientLink::where('professional_user_id', $user->id)
            ->where('status', 'active')
            ->with('patient:id,name,email')
            ->get()
            ->map(fn ($l) => [
                'id'         => $l->patient->id,
                'name'       => $l->patient->name,
                'email'      => $l->patient->email,
                'product_id' => $l->product_id,
            ]);

        return Inertia::render('Profissional/Triagens', [
            'tests'    => $tests,
            'patients' => $patients,
        ]);
    }

    public function storeTest(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'category'          => 'required|string|in:tdah,tea,ah_sd,humor,ansiedade,geral',
            'description'       => 'nullable|string|max:1000',
            'instructions'      => 'nullable|string|max:2000',
            'estimated_minutes' => 'required|integer|min:1|max:120',
            'is_active'         => 'boolean',
        ]);

        $user = auth()->user();
        $max  = \App\Models\ClinicalTest::where('professional_user_id', $user->id)->max('position') ?? 0;

        $test = \App\Models\ClinicalTest::create([
            ...$data,
            'professional_user_id' => $user->id,
            'tenant_id'            => $user->tenant_id,
            'position'             => $max + 1,
            'is_active'            => $data['is_active'] ?? true,
        ]);

        return response()->json($this->formatClinicalTest($test->loadCount(['sessions', 'questions'])));
    }

    public function updateTest(Request $request, int $testId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);

        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'category'          => 'required|string|in:tdah,tea,ah_sd,humor,ansiedade,geral',
            'description'       => 'nullable|string|max:1000',
            'instructions'      => 'nullable|string|max:2000',
            'estimated_minutes' => 'required|integer|min:1|max:120',
            'is_active'         => 'boolean',
        ]);

        $test->update($data);

        return response()->json($this->formatClinicalTest($test->loadCount(['sessions', 'questions'])));
    }

    public function destroyTest(int $testId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);
        $test->delete();

        return response()->json(['ok' => true]);
    }

    public function indexTestQuestions(int $testId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);

        $questions = $test->questions()->with('options')->get()->map(fn ($q) => $this->formatQuestion($q));

        return response()->json($questions);
    }

    public function storeTestQuestion(Request $request, int $testId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);

        $data = $request->validate([
            'text'         => 'required|string|max:1000',
            'type'         => 'required|in:scale,single,multi,boolean,text',
            'scale_min'    => 'nullable|integer',
            'scale_max'    => 'nullable|integer',
            'scale_labels' => 'nullable|array',
            'options'      => 'nullable|array',
            'options.*.text'  => 'required_with:options|string|max:200',
            'options.*.value' => 'required_with:options|numeric',
        ]);

        $max = $test->questions()->max('position') ?? 0;

        $q = $test->questions()->create([
            'text'         => $data['text'],
            'type'         => $data['type'],
            'scale_min'    => $data['scale_min'] ?? null,
            'scale_max'    => $data['scale_max'] ?? null,
            'scale_labels' => $data['scale_labels'] ?? null,
            'position'     => $max + 1,
        ]);

        if (!empty($data['options'])) {
            foreach ($data['options'] as $i => $opt) {
                $q->options()->create(['text' => $opt['text'], 'value' => $opt['value'], 'position' => $i + 1]);
            }
        }

        return response()->json($this->formatQuestion($q->load('options')));
    }

    public function destroyTestQuestion(int $testId, int $questionId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);
        $test->questions()->findOrFail($questionId)->delete();

        return response()->json(['ok' => true]);
    }

    public function storeTestScoringRule(Request $request, int $testId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);

        $data = $request->validate([
            'min_score'          => 'required|integer|min:0',
            'max_score'          => 'required|integer|gte:min_score',
            'result_label'       => 'required|string|max:200',
            'result_description' => 'nullable|string|max:1000',
            'challenge_tags'     => 'nullable|array',
        ]);

        $rule = $test->scoringRules()->create($data);

        return response()->json($rule);
    }

    public function destroyTestScoringRule(int $testId, int $ruleId): \Illuminate\Http\JsonResponse
    {
        $test = \App\Models\ClinicalTest::where('professional_user_id', auth()->id())->findOrFail($testId);
        $test->scoringRules()->findOrFail($ruleId)->delete();

        return response()->json(['ok' => true]);
    }

    public function sendTest(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'test_id'    => 'required|integer',
            'patient_id' => 'required|integer',
        ]);

        $user = auth()->user();

        // Garante que o teste pertence a este profissional
        $test = \App\Models\ClinicalTest::where('professional_user_id', $user->id)
            ->findOrFail($request->test_id);

        // Garante que o paciente está vinculado a este profissional
        $link = \App\Models\ProfessionalPatientLink::where('professional_user_id', $user->id)
            ->where('patient_user_id', $request->patient_id)
            ->where('status', 'active')
            ->first();

        if (! $link) {
            return response()->json(['message' => 'Este paciente não está vinculado a você.'], 422);
        }

        // Cria ou reutiliza a atribuição (permite reenviar sem duplicar)
        \App\Models\ProfessionalTestAssignment::updateOrCreate(
            [
                'professional_user_id' => $user->id,
                'patient_user_id'      => $request->patient_id,
                'clinical_test_id'     => $test->id,
            ],
            [
                'product_id'  => $link->product_id,
                'status'      => 'pending',
                'assigned_at' => now(),
            ]
        );

        return response()->json(['ok' => true, 'message' => "Teste \"{$test->name}\" enviado com sucesso."]);
    }

    private function formatClinicalTest(\App\Models\ClinicalTest $t): array
    {
        return [
            'id'                => $t->id,
            'name'              => $t->name,
            'category'          => $t->category,
            'description'       => $t->description,
            'instructions'      => $t->instructions,
            'estimated_minutes' => $t->estimated_minutes,
            'is_active'         => $t->is_active,
            'position'          => $t->position,
            'sessions_count'    => $t->sessions_count ?? 0,
            'questions_count'   => $t->questions_count ?? 0,
        ];
    }

    private function formatQuestion(\App\Models\ClinicalTestQuestion $q): array
    {
        return [
            'id'           => $q->id,
            'text'         => $q->text,
            'type'         => $q->type,
            'scale_min'    => $q->scale_min,
            'scale_max'    => $q->scale_max,
            'scale_labels' => $q->scale_labels,
            'position'     => $q->position,
            'options'      => $q->options->map(fn ($o) => ['id' => $o->id, 'text' => $o->text, 'value' => $o->value, 'position' => $o->position])->toArray(),
        ];
    }

    // ─── Meus Pacientes ───────────────────────────────────────────────────────

    public function meusPacientes(Request $request): Response
    {
        $pro  = $this->professional();
        $user = auth()->user();

        // Busca vínculos ativos deste profissional
        $links = \App\Models\ProfessionalPatientLink::where('professional_user_id', $user->id)
            ->where('status', 'active')
            ->with('patient')
            ->get();

        $patients = $links->map(function ($link) use ($user) {
            $patient = $link->patient;

            // Últimos 7 humor check-ins do paciente
            $moods = \App\Models\DailyMoodCheckin::where('user_id', $patient->id)
                ->where('product_id', $link->product_id)
                ->orderBy('checkin_date')
                ->limit(30)
                ->get(['mood', 'checkin_date'])
                ->map(fn ($m) => ['date' => $m->checkin_date->format('d/M'), 'mood' => $m->mood])
                ->toArray();

            // Testes concluídos
            $completedTests = \App\Models\ClinicalTestSession::where('user_id', $patient->id)
                ->where('status', 'completed')
                ->with('test:id,name,category')
                ->latest('completed_at')
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'test_name'    => $s->test?->name,
                    'result_label' => $s->result_label,
                    'challenge_tags' => $s->challenge_tags ?? [],
                    'completed_at' => $s->completed_at?->format('d/m/Y'),
                ])
                ->toArray();

            // Nota clínica privada deste profissional sobre este paciente
            $note = \App\Models\ProfessionalClinicalNote::where('professional_user_id', $user->id)
                ->where('patient_user_id', $patient->id)
                ->where('product_id', $link->product_id)
                ->first();

            return [
                'id'             => $patient->id,
                'name'           => $patient->name,
                'email'          => $patient->email,
                'product_id'     => $link->product_id,
                'moods'          => $moods,
                'tests'          => $completedTests,
                'clinical_note'  => $note?->note ?? '',
            ];
        });

        return Inertia::render('Profissional/MeusPacientes', [
            'patients' => $patients,
        ]);
    }

    public function linkPatient(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'patient_email' => 'required|email|exists:users,email',
        ]);

        $patient = \App\Models\User::where('email', $request->patient_email)->firstOrFail();
        $user    = auth()->user();

        // Encontra o produto do tenant ao qual este paciente tem acesso
        $product = \App\Models\Product::where('tenant_id', $user->tenant_id)
            ->whereHas('users', fn ($q) => $q->where('users.id', $patient->id))
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Este paciente não está cadastrado em nenhum produto deste tenant.'], 422);
        }

        \App\Models\ProfessionalPatientLink::firstOrCreate(
            [
                'professional_user_id' => $user->id,
                'patient_user_id'      => $patient->id,
                'product_id'           => (string) $product->id,
            ],
            ['status' => 'active'],
        );

        return response()->json([
            'ok'      => true,
            'patient' => [
                'id'         => $patient->id,
                'name'       => $patient->name,
                'email'      => $patient->email,
                'product_id' => (string) $product->id,
            ],
        ]);
    }

    public function saveNote(Request $request, int $patientId): \Illuminate\Http\JsonResponse
    {
        $request->validate(['note' => 'required|string|max:5000', 'product_id' => 'required|string']);

        $user = auth()->user();

        \App\Models\ProfessionalClinicalNote::updateOrCreate(
            [
                'professional_user_id' => $user->id,
                'patient_user_id'      => $patientId,
                'product_id'           => $request->product_id,
            ],
            ['note' => $request->note],
        );

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function fmtProfessional(Professional $p): array
    {
        return [
            'id'                  => $p->id,
            'name'                => $p->name,
            'email'               => $p->email,
            'phone'               => $p->phone,
            'website'             => $p->website,
            'avatar_url'          => $p->avatar_url,
            'bio'                 => $p->bio,
            'experience'          => $p->experience,
            'social_links'        => $p->social_links ?? [],
            'specialty'           => $p->specialty,
            'registration_type'   => $p->registration_type,
            'registration_number' => $p->registration_number,
            'price_per_hour'      => $p->price_per_hour,
            'is_active'           => $p->is_active,
        ];
    }

    private function fmtService(ProfessionalService $s): array
    {
        return [
            'id'               => $s->id,
            'name'             => $s->name,
            'description'      => $s->description,
            'price'            => $s->price,
            'duration_minutes' => $s->duration_minutes,
            'is_active'        => $s->is_active,
            'position'         => $s->position,
        ];
    }

    private function fmtPortfolioItem(ProfessionalPortfolioItem $i): array
    {
        return [
            'id'          => $i->id,
            'type'        => $i->type,
            'url'         => $i->url,
            'video_url'   => $i->video_url,
            'title'       => $i->title,
            'description' => $i->description,
            'position'    => $i->position,
        ];
    }
}
