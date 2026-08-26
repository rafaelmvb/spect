<?php

namespace App\Http\Controllers;

use App\Models\AiConfig;
use App\Models\AiInsight;
use App\Models\CheckpointResponse;
use App\Models\MemberActivityLog;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberQuizResponse;
use App\Models\NeuroUserScore;
use App\Models\Product;
use App\Models\User;
use App\Models\UserJourneyUnlock;
use App\Services\AccessEmailService;
use App\Services\AiReportGeneratorService;
use App\Services\AiService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AlunosController extends Controller
{
    private const FILTER_OPTIONS = ['todos', 'novos_30'];

    private function tenantProductIds(?int $tenantId): array
    {
        if (auth()->user()?->isTeam()) {
            return app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
        }

        return Product::forTenant($tenantId)->pluck('id')->toArray();
    }

    private function baseAlunosQuery(?int $tenantId)
    {
        $query = User::where('role', User::ROLE_ALUNO);

        if (auth()->user()?->isTeam()) {
            $allowedIds = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            return $query->whereHas('products', fn ($q) => $q->whereIn('products.id', $allowedIds));
        }

        return $query->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }

    private function canAccessAluno(User $aluno, ?int $tenantId): bool
    {
        if (auth()->user()?->isTeam()) {
            return $aluno->products()->forTenant($tenantId)->exists();
        }
        return $aluno->tenant_id === $tenantId || $aluno->tenant_id === null;
    }

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $filter = $request->query('filter', 'todos');
        if (! in_array($filter, self::FILTER_OPTIONS, true)) {
            $filter = 'todos';
        }

        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : '';
        $search = $search !== '' ? $search : null;

        $productIdsFilter = $request->query('product_ids');
        $productIdsFilter = is_array($productIdsFilter)
            ? $productIdsFilter
            : (is_string($productIdsFilter) ? array_filter(explode(',', $productIdsFilter)) : []);

        $tenantProductIds = $this->tenantProductIds($tenantId);
        $baseAlunosQuery = $this->baseAlunosQuery($tenantId);

        if ($filter === 'novos_30') {
            $baseAlunosQuery->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('product_user')
                    ->join('products', 'products.id', '=', 'product_user.product_id')
                    ->whereColumn('product_user.user_id', 'users.id')
                    ->where('product_user.created_at', '>=', now()->subDays(30));
                if ($tenantId === null) {
                    $q->whereNull('products.tenant_id');
                } else {
                    $q->where('products.tenant_id', $tenantId);
                }
            });
        }

        if (! empty($productIdsFilter)) {
            $validProductIds = array_intersect($productIdsFilter, $tenantProductIds);
            if (! empty($validProductIds)) {
                $baseAlunosQuery->whereHas('products', fn ($q) => $q->whereIn('products.id', $validProductIds));
            }
        }

        if ($search !== null) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $baseAlunosQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like);
            });
        }

        $alunos = (clone $baseAlunosQuery)
            ->with(['products' => fn ($q) => $q->forTenant($tenantId)->select('products.id', 'products.name')])
            ->withCount(['products as products_count' => function ($q) use ($tenantId) {
                if ($tenantId === null) {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', $tenantId);
                }
            }])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'products_count' => $u->products_count,
                'products' => $u->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
                'blocked_at' => $u->blocked_at?->toIso8601String(),
                'is_blocked' => $u->isBlocked(),
            ]);

        $produtos = Product::forTenant($tenantId)->withCount('users')->orderBy('name')->get();

        $totalAlunos = (clone $this->baseAlunosQuery($tenantId))->count();

        $totalInscricoes = empty($tenantProductIds)
            ? 0
            : DB::table('product_user')->whereIn('product_id', $tenantProductIds)->count();

        $produtosAtivos = Product::forTenant($tenantId)->whereHas('users')->count();

        $alunosNovos30dias = User::where('role', User::ROLE_ALUNO)
            ->whereExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('product_user')
                    ->join('products', 'products.id', '=', 'product_user.product_id')
                    ->whereColumn('product_user.user_id', 'users.id')
                    ->where('product_user.created_at', '>=', now()->subDays(30));
                if ($tenantId === null) {
                    $q->whereNull('products.tenant_id');
                } else {
                    $q->where('products.tenant_id', $tenantId);
                }
            })
            ->count();

        $stats = [
            'total_alunos' => $totalAlunos,
            'total_inscricoes' => $totalInscricoes,
            'produtos_ativos' => $produtosAtivos,
            'alunos_novos_30dias' => $alunosNovos30dias,
        ];

        $view = $request->routeIs('alunos.root') ? 'Alunos/Standalone' : 'Alunos/Index';

        return Inertia::render($view, [
            'alunos' => $alunos,
            'produtos' => $produtos,
            'stats' => $stats,
            'filter' => $filter,
            'product_ids_filter' => $productIdsFilter,
            'q' => $search,
        ]);
    }

    public function show(User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }
        $aluno->load(['products' => fn ($q) => $q->forTenant($tenantId)->select('products.id', 'products.name')]);
        return response()->json([
            'id' => $aluno->id,
            'name' => $aluno->name,
            'email' => $aluno->email,
            'products' => $aluno->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'blocked_at' => $aluno->blocked_at?->toIso8601String(),
            'is_blocked' => $aluno->isBlocked(),
        ]);
    }

    public function store(Request $request, AccessEmailService $accessEmailService): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
            'send_access_email' => ['nullable', 'boolean'],
        ]);
        $productIds = $validated['product_ids'] ?? [];
        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = array_values(array_intersect($productIds, $tenantProductIds));
        $sendAccessEmail = (bool) ($validated['send_access_email'] ?? true);

        $user = User::createWithRole([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ], User::ROLE_ALUNO, $tenantId);

        foreach ($productIds as $pid) {
            $user->products()->syncWithoutDetaching([$pid]);
        }

        $emailsSent = 0;
        if ($sendAccessEmail && ! empty($productIds)) {
            $products = Product::whereIn('id', $productIds)->get();
            foreach ($products as $product) {
                if ($accessEmailService->sendForUserProduct($user, $product)) {
                    $emailsSent++;
                }
            }
        }

        $message = 'Aluno cadastrado com sucesso.';
        if ($sendAccessEmail && $emailsSent > 0) {
            $message .= " E-mail de acesso enviado para {$emailsSent} produto(s).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'aluno' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'products_count' => count($productIds)],
        ]);
    }

    public function toggleBlock(User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }

        if ($aluno->isBlocked()) {
            $aluno->unblock();
            $message = 'Conta desbloqueada com sucesso.';
        } else {
            $aluno->block();
            $message = 'Conta bloqueada com sucesso.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_blocked' => $aluno->isBlocked(),
            'blocked_at' => $aluno->blocked_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request, User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $aluno->id],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $aluno->name = $validated['name'];
        $aluno->email = $validated['email'];
        if (! empty($validated['password'])) {
            $aluno->password = Hash::make($validated['password']);
        }
        $aluno->save();

        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = $validated['product_ids'] ?? [];
        $productIds = array_values(array_intersect($productIds, $tenantProductIds));
        $currentIds = $aluno->products()->forTenant($tenantId)->pluck('products.id')->toArray();
        $aluno->products()->detach($currentIds);
        $aluno->products()->attach($productIds);

        return response()->json([
            'success' => true,
            'message' => 'Aluno atualizado com sucesso.',
            'aluno' => [
                'id' => $aluno->id,
                'name' => $aluno->name,
                'email' => $aluno->email,
                'products_count' => count($productIds),
                'products' => Product::forTenant($tenantId)->whereIn('id', $productIds)->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ],
        ]);
    }

    public function destroy(User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }
        $aluno->products()->detach();
        $aluno->delete();
        return response()->json(['success' => true, 'message' => 'Aluno excluído com sucesso.']);
    }

    public function downloadImportExample(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'alunos_exemplo_' . date('Y-m-d') . '.csv';
        $content = "nome;email;senha\nJoão Silva;joao@exemplo.com;senha123\nMaria Santos;maria@exemplo.com;\nPedro Oliveira;pedro@exemplo.com;minhasenha456";

        return response()->streamDownload(function () use ($content) {
            echo "\xEF\xBB\xBF";
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, AccessEmailService $accessEmailService): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
            'send_access_email' => ['nullable', 'boolean'],
        ]);

        $productIds = $request->input('product_ids', []);
        $tenantProductIds = $this->tenantProductIds($tenantId);
        $productIds = array_values(array_intersect((array) $productIds, $tenantProductIds));
        if (empty($productIds)) {
            return response()->json(['success' => false, 'message' => 'Selecione ao menos um produto para dar acesso.'], 422);
        }
        $sendAccessEmail = (bool) ($request->input('send_access_email', true));

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return response()->json(['success' => false, 'message' => 'Arquivo vazio.'], 422);
        }

        $rows = [];
        foreach ($lines as $i => $line) {
            $cols = str_getcsv($line, $this->detectDelimiter($line));
            if (! empty(array_filter(array_map('trim', $cols)))) {
                $rows[] = array_map('trim', $cols);
            }
        }
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Nenhuma linha válida no arquivo.'], 422);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim($h)), $rows[0]);
        $nameCol = $this->findColumn($header, ['nome', 'name', 'nome_completo']);
        $emailCol = $this->findColumn($header, ['email', 'e-mail', 'mail']);
        $passCol = $this->findColumn($header, ['senha', 'password', 'senha_acesso']);

        $hasHeader = $emailCol !== null || $nameCol !== null || $passCol !== null;
        if ($emailCol === null) {
            if (count($rows[0] ?? []) >= 2 && filter_var(trim($rows[0][1] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $emailCol = 1;
                $nameCol = 0;
                $hasHeader = false;
            } else {
                return response()->json(['success' => false, 'message' => 'Coluna "email" ou "e-mail" não encontrada. Use cabeçalho: nome;email;senha'], 422);
            }
        }

        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
        if (empty($dataRows)) {
            return response()->json(['success' => false, 'message' => 'Nenhum dado para importar.'], 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $emailsSent = 0;

        foreach ($dataRows as $idx => $row) {
            $email = isset($emailCol) && isset($row[$emailCol]) ? $row[$emailCol] : ($row[1] ?? $row[0] ?? '');
            $email = trim($email);
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Linha " . ($idx + 2) . ": e-mail inválido ou vazio.";
                $skipped++;
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $errors[] = "Linha " . ($idx + 2) . ": e-mail {$email} já cadastrado.";
                $skipped++;
                continue;
            }

            $name = isset($nameCol) && isset($row[$nameCol]) ? $row[$nameCol] : explode('@', $email)[0];
            $name = trim($name) ?: 'Aluno';
            $password = (isset($passCol) && isset($row[$passCol]) && strlen(trim($row[$passCol] ?? '')) >= 6)
                ? trim($row[$passCol])
                : Str::random(12);

            try {
                $user = User::createWithRole([
                    'name' => mb_substr($name, 0, 255),
                    'email' => $email,
                    'password' => Hash::make($password),
                ], User::ROLE_ALUNO, $tenantId);

                foreach ($productIds as $pid) {
                    $user->products()->syncWithoutDetaching([$pid]);
                }

                if ($sendAccessEmail && ! empty($productIds)) {
                    $products = Product::whereIn('id', $productIds)->get();
                    foreach ($products as $product) {
                        if ($accessEmailService->sendForUserProduct($user, $product)) {
                            $emailsSent++;
                        }
                    }
                }
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Linha " . ($idx + 2) . ": " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "{$created} aluno(s) importado(s) com sucesso.";
        if ($skipped > 0) {
            $message .= " {$skipped} linha(s) ignorada(s).";
        }
        if ($sendAccessEmail && $emailsSent > 0) {
            $message .= " E-mail de acesso enviado.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    private function detectDelimiter(string $line): string
    {
        return str_contains($line, ';') ? ';' : ',';
    }

    private function findColumn(array $header, array $names): ?int
    {
        foreach ($names as $n) {
            $i = array_search($n, $header, true);
            if ($i !== false) {
                return (int) $i;
            }
        }
        return null;
    }

    public function removeProduct(User $aluno, Product $produto): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }
        $aluno->products()->detach($produto->id);
        $remaining = $aluno->products()->where(fn ($q) => $q->forTenant($tenantId))->count();
        return response()->json([
            'success' => true,
            'message' => 'Acesso ao produto removido.',
            'products_count' => $remaining,
        ]);
    }

    public function addProduct(User $aluno, Product $produto): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }
        $aluno->products()->syncWithoutDetaching([$produto->id]);
        $products = $aluno->products()->where('products.tenant_id', $tenantId)->get(['products.id', 'products.name']);
        return response()->json([
            'success'  => true,
            'message'  => 'Acesso à trilha concedido.',
            'products' => $products,
        ]);
    }

    public function quizResponses(User $aluno): \Illuminate\Http\JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO) {
            abort(404);
        }

        $responses = MemberQuizResponse::where('user_id', $aluno->id)
            ->with('lesson:id,title,content_files,product_id')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($r) => $r->lesson && $r->lesson->product)
            ->filter(fn ($r) => \App\Models\Product::where('id', $r->lesson->product_id)->where('tenant_id', $tenantId)->exists())
            ->map(function ($r) {
                $questions = $r->lesson->content_files['questions'] ?? [];
                $questionsById = collect($questions)->keyBy('id');
                return [
                    'id' => $r->id,
                    'lesson_id' => $r->lesson_id,
                    'lesson_title' => $r->lesson->title,
                    'product_id' => $r->lesson->product_id,
                    'responded_at' => $r->created_at->format('d/m/Y H:i'),
                    'responses' => collect($r->responses)->map(fn ($resp) => [
                        'question_id' => $resp['question_id'],
                        'question_text' => $questionsById[$resp['question_id']]['text'] ?? '—',
                        'value' => $resp['value'],
                        'comment' => $resp['comment'] ?? null,
                    ])->values(),
                ];
            })->values();

        return response()->json(['quiz_responses' => $responses]);
    }

    public function showPage(User $aluno): Response
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }

        $tenantProductIds = $this->tenantProductIds($tenantId);

        // Produtos com progresso
        $products = $aluno->products()->forTenant($tenantId)
            ->select('products.id', 'products.name', 'products.image', 'product_user.created_at as enrolled_at')
            ->get()
            ->map(function ($p) use ($aluno) {
                $totalLessons     = MemberLesson::where('product_id', $p->id)->count();
                $completedLessons = MemberLessonProgress::where('user_id', $aluno->id)
                    ->where('product_id', $p->id)
                    ->whereNotNull('completed_at')
                    ->count();
                return [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'image'            => $p->image,
                    'enrolled_at'      => $p->enrolled_at ? \Carbon\Carbon::parse($p->enrolled_at)->format('d/m/Y') : null,
                    'total_lessons'    => $totalLessons,
                    'completed_lessons'=> $completedLessons,
                    'progress_percent' => $totalLessons > 0 ? (int) round($completedLessons / $totalLessons * 100) : 0,
                ];
            });

        // Quiz responses com detalhes completos
        $quizResponses = MemberQuizResponse::where('user_id', $aluno->id)
            ->with('lesson:id,title,content_files,product_id')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($r) => $r->lesson && in_array($r->lesson->product_id, $tenantProductIds, false))
            ->map(function ($r) {
                $questions    = $r->lesson->content_files['questions'] ?? [];
                $questionsById = collect($questions)->keyBy('id');
                return [
                    'id'           => $r->id,
                    'lesson_title' => $r->lesson->title,
                    'product_id'   => $r->lesson->product_id,
                    'responded_at' => $r->created_at->format('d/m/Y H:i'),
                    'responses'    => collect($r->responses)->map(fn ($resp) => [
                        'question_text' => $questionsById[$resp['question_id']]['text'] ?? '—',
                        'value'         => $resp['value'],
                        'comment'       => $resp['comment'] ?? null,
                    ])->values(),
                ];
            })->values();

        // Checkpoints respondidos
        $checkpoints = CheckpointResponse::where('user_id', $aluno->id)
            ->with(['checkpoint:id,title', 'answers.question:id,label'])
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get()
            ->map(fn ($cr) => [
                'id'               => $cr->id,
                'checkpoint_title' => $cr->checkpoint?->title ?? '—',
                'completed_at'     => $cr->completed_at?->format('d/m/Y H:i'),
                'answers'          => $cr->answers->map(fn ($a) => [
                    'question' => $a->question?->label ?? '—',
                    'value'    => $a->value,
                ])->values(),
            ]);

        // Atividade recente
        $activity = MemberActivityLog::where('user_id', $aluno->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'event'      => $a->event,
                'product_id' => $a->product_id,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ]);

        // Scores NeuroMap
        $neuroScores = NeuroUserScore::where('user_id', $aluno->id)
            ->with('indicator:id,name')
            ->orderByDesc('scored_at')
            ->get()
            ->groupBy('neuro_indicator_id')
            ->map(fn ($g) => [
                'indicator' => $g->first()->indicator?->name ?? '—',
                'value'     => (float) $g->first()->value,
                'scored_at' => $g->first()->scored_at?->format('d/m/Y'),
            ])->values();

        // Insights de IA gerados
        $insights = AiInsight::where('user_id', $aluno->id)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'id'         => $i->id,
                'type'       => $i->type,
                'title'      => $i->title,
                'content'    => $i->content,
                'created_at' => $i->created_at->format('d/m/Y H:i'),
            ]);

        // Stats gerais
        $totalCompleted = MemberLessonProgress::where('user_id', $aluno->id)
            ->whereIn('product_id', $tenantProductIds)
            ->whereNotNull('completed_at')
            ->count();
        $daysActive = MemberActivityLog::where('user_id', $aluno->id)
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as days')
            ->value('days') ?? 0;

        $aiService = new AiService($tenantId);

        // Jornadas atribuídas ao aluno
        $journeys = UserJourneyUnlock::where('user_id', $aluno->id)
            ->where('tenant_id', $tenantId)
            ->with('journey:id,name,slug,description,cover')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($u) => [
                'id'           => $u->journey_id,
                'name'         => $u->journey?->name ?? '—',
                'slug'         => $u->journey?->slug ?? '',
                'cover'        => $u->journey?->cover_url ?? null,
                'is_free'      => $u->is_free,
                'unlocked_at'  => $u->created_at->format('d/m/Y H:i'),
            ]);

        $hasReport = $insights->where('type', 'admin_report')->isNotEmpty();

        return Inertia::render('Alunos/Show', [
            'aluno' => [
                'id'         => $aluno->id,
                'name'       => $aluno->name,
                'email'      => $aluno->email,
                'avatar'     => $aluno->avatar,
                'blocked_at' => $aluno->blocked_at?->toIso8601String(),
                'is_blocked' => $aluno->isBlocked(),
                'created_at' => $aluno->created_at->format('d/m/Y'),
            ],
            'products'      => $products,
            'quiz_responses'=> $quizResponses,
            'checkpoints'   => $checkpoints,
            'activity'      => $activity,
            'neuro_scores'  => $neuroScores,
            'insights'      => $insights,
            'journeys'      => $journeys,
            'has_report'    => $hasReport,
            'ai_available'  => $aiService->available(),
            'stats' => [
                'lessons_completed' => $totalCompleted,
                'quizzes_done'      => $quizResponses->count(),
                'checkpoints_done'  => $checkpoints->count(),
                'days_active'       => (int) $daysActive,
            ],
        ]);
    }

    public function generateAiReport(Request $request, User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }

        $instructions = trim($request->input('instructions', ''));

        try {
            $insight = app(\App\Services\AiReportGeneratorService::class)
                ->generate($aluno, $tenantId, auth()->id(), $instructions);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()], 500);
        }

        if ($insight === null) {
            return response()->json(['error' => 'Nenhuma chave de IA configurada. Configure em Configurações → IA.'], 422);
        }

        return response()->json([
            'success' => true,
            'insight' => [
                'id'         => $insight->id,
                'title'      => $insight->title,
                'content'    => $insight->content,
                'created_at' => $insight->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    // ─── Edição do relatório ────────────────────────────────────────────────────

    public function updateInsight(Request $request, User $aluno, AiInsight $insight): \Illuminate\Http\JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        abort_if($aluno->tenant_id !== $tenantId, 403);
        abort_if($insight->user_id !== $aluno->id, 403);
        abort_if($insight->tenant_id !== $tenantId, 403);

        $v = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
        ]);

        $insight->update(['content' => $v['content']]);

        return response()->json([
            'success' => true,
            'insight' => [
                'id'         => $insight->id,
                'title'      => $insight->title,
                'content'    => $insight->content,
                'created_at' => $insight->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    // ─── Download / impressão do relatório ─────────────────────────────────────

    public function printReport(User $aluno, AiInsight $insight): \Illuminate\Http\Response
    {
        $tenantId = auth()->user()->tenant_id;
        abort_if($aluno->tenant_id !== $tenantId, 403);
        abort_if($insight->user_id !== $aluno->id, 403);
        abort_if($insight->tenant_id !== $tenantId, 403);

        $products = $aluno->products()->forTenant($tenantId)->get(['products.id', 'products.name'])->toArray();

        return response()->view('relatorio-print', [
            'aluno'    => $aluno,
            'insight'  => $insight,
            'products' => $products,
        ]);
    }

    public function atribuirJornada(Request $request, User $aluno): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($aluno->role !== User::ROLE_ALUNO || ! $this->canAccessAluno($aluno, $tenantId)) {
            abort(404);
        }

        $aiService = new AiService($tenantId);
        if (! $aiService->available()) {
            return response()->json(['error' => 'Nenhuma chave de IA configurada.'], 422);
        }

        // Busca o relatório mais recente do aluno neste tenant
        $insight = AiInsight::where('user_id', $aluno->id)
            ->where('tenant_id', $tenantId)
            ->where('type', 'admin_report')
            ->latest()
            ->first();

        if (! $insight) {
            return response()->json(['error' => 'Aluno ainda não tem relatório gerado.'], 422);
        }

        // Usa o produto passado ou o primeiro produto do aluno no tenant
        $productId = $request->input('product_id');
        $product = $productId
            ? Product::where('id', $productId)->where('tenant_id', $tenantId)->first()
            : $aluno->products()->forTenant($tenantId)->first();

        if (! $product) {
            return response()->json(['error' => 'Nenhum produto encontrado para este aluno.'], 422);
        }

        $result = app(AiReportGeneratorService::class)
            ->triggerJourneyForInsight($insight, $aluno, $tenantId, $product);

        if ($result === null) {
            return response()->json(['error' => 'Não foi possível atribuir uma jornada. Verifique se há jornadas ativas e se a IA está configurada.'], 422);
        }

        $unlock = UserJourneyUnlock::where('user_id', $aluno->id)
            ->where('tenant_id', $tenantId)
            ->with('journey:id,name')
            ->latest()
            ->first();

        return response()->json([
            'success'  => true,
            'new'      => $result,
            'journey'  => $unlock?->journey ? ['id' => $unlock->journey->id, 'name' => $unlock->journey->name] : null,
        ]);
    }

    public function printStudentReport(\Illuminate\Http\Request $request): \Illuminate\Http\Response
    {
        $insightParam = $request->route('insight');
        $insight = $insightParam instanceof AiInsight
            ? $insightParam
            : AiInsight::findOrFail($insightParam);

        $user = auth()->user();
        abort_if($insight->user_id !== $user->id, 403);

        $products = $user->products()->get(['products.id', 'products.name'])->toArray();

        return response()->view('relatorio-print', [
            'aluno'    => $user,
            'insight'  => $insight,
            'products' => $products,
        ]);
    }
}
