<?php

namespace App\Http\Controllers;

use App\Models\MemberCertificateIssued;
use App\Http\Controllers\MemberMoodController;
use App\Models\MemberComment;
use App\Models\MemberCommunityPost;
use App\Models\MemberCommunityPostComment;
use App\Models\CommunityBan;
use App\Models\CommunityReport;
use App\Models\MemberCommunityPostLike;
use App\Models\Banner;
use App\Models\MemberAreaPost;
use App\Models\MemberHomeFeaturedCourse;
use App\Models\MemberReportRequest;
use App\Models\Setting;
use App\Models\MemberActivityLog;
use App\Models\MemberInternalProduct;
use App\Models\MemberLesson;
use App\Models\MemberLessonLike;
use App\Models\MemberLessonPdfAnnotation;
use App\Models\MemberLessonProgress;
use App\Models\MemberQuizResponse;
use App\Models\MemberModule;
use App\Models\MemberSection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\AiInsight;
use App\Services\AiReportGeneratorService;
use App\Services\GamificationService;
use App\Services\MemberAreaResolver;
use App\Services\MemberCommentService;
use App\Services\MemberProgressService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MemberAreaAppController extends Controller
{
    public function __construct(
        protected MemberProgressService $progressService,
        protected MemberAreaResolver $resolver,
        protected GamificationService $gamificationService
    ) {}

    /**
     * Best-effort activity log for proof/compliance. Must never break student UX.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logMemberActivity(Request $request, Product $product, ?User $user, string $event, array $metadata = []): void
    {
        try {
            MemberActivityLog::create([
                'tenant_id' => $product->tenant_id ?? $user?->tenant_id,
                'user_id' => $user?->id,
                'product_id' => $product->id,
                'event' => $event,
                'metadata' => $metadata,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // ignore (best-effort)
        }
    }

    public function show(Request $request, string $slug): Response|\Illuminate\Http\RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();

        // Garante que a sessão reflete o produto atual para que as clean routes (/m/page) o usem
        session(['member_area_slug' => $product->checkout_slug]);

        $this->logMemberActivity($request, $product, $user, 'member_area.open', [
            'path' => '/' . ltrim($request->path(), '/'),
        ]);

        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $config = $product->member_area_config;
        $sections = $product->memberSections()->with(['modules.lessons', 'modules.relatedProduct'])->orderBy('position')->get();
        $progressPercent = $this->progressService->completionPercent($product, $user);
        $continueWatching = $this->getContinueWatching($product, $user);
        $internalProducts = $product->memberInternalProducts()->with('relatedProduct')->orderBy('position')->get();
        $baseUrl = $this->baseUrlForRequest($product, $request);
        $userProductIds = $user->products()->pluck('products.id')->flip()->all();
        $push = $this->pushProps($product);

        return Inertia::render('MemberAreaApp/Show', [
            'product' => $this->productToArray($product),
            'config' => $config,
            'sections' => $sections->map(fn (MemberSection $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'cover_mode' => $s->cover_mode ?? 'vertical',
                'section_type' => $s->section_type ?? 'courses',
                'modules' => $s->modules->map(fn ($m) => $this->mapModuleForMemberArea($m, $s, $product, $user, $userProductIds, $baseUrl, $accessStartAt, $now))->values()->all(),
            ])->values()->all(),
            'progress_percent' => $progressPercent,
            'continue_watching' => $continueWatching,
            'internal_products' => $internalProducts->map(fn (MemberInternalProduct $ip) => [
                'id' => $ip->related_product_id,
                'name' => $ip->relatedProduct?->name,
                'image_url' => $ip->relatedProduct?->image ? (new StorageService($product->tenant_id))->url($ip->relatedProduct->image) : null,
                'checkout_slug' => $ip->relatedProduct?->checkout_slug,
                'has_access' => $user->products()->where('products.id', $ip->related_product_id)->exists(),
            ])->values()->all(),
            'community_enabled' => (bool) ($config['community_enabled'] ?? false),
            'certificate_enabled' => (bool) (($config['certificate'] ?? [])['enabled'] ?? false),
            'can_issue_certificate' => $this->progressService->canIssueCertificate($product, $user),
            'base_url' => $baseUrl,
            'slug' => $slug,
            'push_enabled' => $push['push_enabled'],
            'vapid_public' => $push['vapid_public'],
            'banners' => $this->homebanners($product->tenant_id),
            'home_posts' => $this->homePosts($product->tenant_id),
            'featured_courses' => $this->homeFeaturedCourses($product->tenant_id),
            'streak' => MemberMoodController::homeData($user->id, (string) $product->id),
        ] + $this->gamificationProps($product, $user));
    }

    private function homeBanners(?int $tenantId): array
    {
        return Banner::forTenant($tenantId)
            ->active()
            ->whereIn('target', ['member_area', 'both'])
            ->orderBy('position')
            ->get()
            ->map(fn (Banner $b) => [
                'id'          => $b->id,
                'title'       => $b->title,
                'subtitle'    => $b->subtitle,
                'image_url'   => $b->image_url,
                'link'        => $b->link,
                'button_text' => $b->button_text,
            ])
            ->values()->all();
    }

    private function homePosts(?int $tenantId): array
    {
        return MemberAreaPost::forTenant($tenantId)
            ->published()
            ->orderBy('position')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn (MemberAreaPost $p) => [
                'id'           => $p->id,
                'title'        => $p->title,
                'category'     => $p->category,
                'excerpt'      => $p->excerpt,
                'image_url'    => $p->image_url,
                'published_at' => $p->published_at?->format('d/m/Y'),
            ])
            ->values()->all();
    }

    private function homeFeaturedCourses(?int $tenantId): array
    {
        $storage = new StorageService($tenantId);
        return MemberHomeFeaturedCourse::forTenant($tenantId)
            ->with('product')
            ->orderBy('position')
            ->get()
            ->filter(fn ($fc) => $fc->product && $fc->product->is_active)
            ->map(fn ($fc) => [
                'id'           => $fc->product->id,
                'name'         => $fc->product->name,
                'description'  => $fc->product->description,
                'image_url'    => $fc->product->image ? $storage->url($fc->product->image) : null,
                'checkout_slug'=> $fc->product->checkout_slug,
                'checkout_url' => '/c/' . $fc->product->checkout_slug,
            ])
            ->values()->all();
    }

    public function resultados(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $baseUrl  = $this->baseUrlForRequest($product, $request);
        $tenantId = $product->tenant_id;

        // Todos os produtos que o aluno tem acesso neste tenant
        $userProducts = $user->products()
            ->where('products.tenant_id', $tenantId)
            ->where('products.type', 'area_membros')
            ->where('products.is_active', true)
            ->get();

        // Solicitações já feitas pelo aluno neste tenant
        $existingRequests = MemberReportRequest::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('product_id');

        $courses = $userProducts->map(function ($p) use ($user, $existingRequests) {
            $progressPercent = $this->progressService->completionPercent($p, $user);
            $completed       = $progressPercent >= 100;
            $req             = $existingRequests->get($p->id);

            // Calcula % de quizzes respondidos pelo aluno neste produto
            $lessonsWithQuiz = MemberLesson::where('product_id', $p->id)
                ->get()
                ->filter(fn ($l) => ! empty($l->content_files['questions'] ?? []));
            $totalQuiz    = $lessonsWithQuiz->count();
            $answeredQuiz = $totalQuiz > 0
                ? MemberQuizResponse::where('user_id', $user->id)
                    ->whereIn('lesson_id', $lessonsWithQuiz->pluck('id'))
                    ->count()
                : 0;
            $quizPercent = $totalQuiz > 0 ? (int) round($answeredQuiz / $totalQuiz * 100) : 100;
            $canRequest  = $quizPercent >= 60;

            return [
                'id'               => $p->id,
                'name'             => $p->name,
                'progress_percent' => $progressPercent,
                'completed'        => $completed,
                'quiz_percent'     => $quizPercent,
                'can_request'      => $canRequest,
                'request_status'   => $req?->status,
                'request_id'       => $req?->id,
                'approved_at'      => $req?->approved_at?->format('d/m/Y H:i'),
                'insight_id'       => $req?->insight_id,
            ];
        });

        return Inertia::render('MemberAreaApp/Resultados', [
            'courses'  => $courses,
            'product'  => $this->productToArray($product),
            'config'   => $product->member_area_config,
            'base_url' => $baseUrl,
            'slug'     => $slug,
        ]);
    }

    public function solicitarRelatorio(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $request->validate(['product_id' => ['required', 'string']]);

        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;
        $productId = $request->input('product_id');

        // Verifica se o aluno tem acesso ao produto solicitado
        $hasAccess = $user->products()->where('products.id', $productId)->exists();
        abort_if(! $hasAccess, 403, 'Sem acesso a esta trilha.');

        // Já existe solicitação ativa?
        $existing = MemberReportRequest::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_if($existing !== null, 422, 'Já existe uma solicitação para esta trilha.');

        // Gate: mínimo 60% dos quizzes respondidos
        $lessonsWithQuiz = MemberLesson::where('product_id', $productId)
            ->get()
            ->filter(fn ($l) => ! empty($l->content_files['questions'] ?? []));
        $totalQuiz    = $lessonsWithQuiz->count();
        $answeredQuiz = $totalQuiz > 0
            ? MemberQuizResponse::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonsWithQuiz->pluck('id'))
                ->count()
            : 0;
        $quizPercent = $totalQuiz > 0 ? (int) round($answeredQuiz / $totalQuiz * 100) : 100;

        if ($quizPercent < 60) {
            return response()->json([
                'message'      => "Responda pelo menos 60% dos quizzes para solicitar o relatório. Você completou {$quizPercent}%.",
                'quiz_percent' => $quizPercent,
            ], 422);
        }

        $autoApprove = (bool) Setting::get('member_reports.auto_approve', false, $tenantId);

        $insightId = null;
        if ($autoApprove) {
            try {
                $generator = app(\App\Services\AiReportGeneratorService::class);
                $insight   = $generator->generate($user, $tenantId, null, '', $product);
                $insightId = $insight?->id;
            } catch (\Throwable) {
                // geração falha silenciosamente — aprovação ainda ocorre
            }
        }

        $req = MemberReportRequest::create([
            'tenant_id'    => $tenantId,
            'user_id'      => $user->id,
            'product_id'   => $productId,
            'status'       => $autoApprove ? 'approved' : 'pending',
            'requested_at' => now(),
            'approved_at'  => $autoApprove ? now() : null,
            'insight_id'   => $insightId,
        ]);

        return response()->json([
            'success'      => true,
            'status'       => $req->status,
            'approved_at'  => $req->approved_at?->format('d/m/Y H:i'),
            'insight_id'   => $insightId,
        ]);
    }

    public function trocarProduto(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $request->validate(['product_id' => ['required', 'string']]);

        $user      = $request->user();
        $product   = $this->getProduct($request);
        $tenantId  = $product->tenant_id;
        $productId = $request->input('product_id');

        $target = Product::where('id', $productId)
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->where('is_active', true)
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        abort_if(! $target, 403, 'Sem acesso a este produto.');

        session(['member_area_slug' => $target->checkout_slug]);

        return response()->json(['ok' => true, 'redirect' => '/m/modulos']);
    }

    public function showPost(Request $request, string $slug, MemberAreaPost $memberAreaPost): Response
    {
        $product = $this->getProduct($request);
        $user = $request->user();

        abort_if(
            $memberAreaPost->tenant_id !== $product->tenant_id || ! $memberAreaPost->is_published,
            404
        );

        $baseUrl = $this->baseUrlForRequest($product, $request);

        return Inertia::render('MemberAreaApp/Post', [
            'post'     => [
                'id'           => $memberAreaPost->id,
                'title'        => $memberAreaPost->title,
                'category'     => $memberAreaPost->category,
                'excerpt'      => $memberAreaPost->excerpt,
                'content'      => $memberAreaPost->content,
                'image_url'    => $memberAreaPost->image_url,
                'published_at' => $memberAreaPost->published_at?->format('d \d\e F \d\e Y'),
            ],
            'product'  => ['name' => $product->name],
            'base_url' => $baseUrl,
            'slug'     => $slug,
            'config'   => $product->member_area_config,
        ]);
    }

    public function modulos(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;
        $baseUrl  = $this->baseUrlForRequest($product, $request);
        $storage  = new StorageService($tenantId);
        $isAdmin  = $user->canAccessPanel() && (int) $user->tenant_id === (int) $tenantId;

        $coursesQuery = $isAdmin
            ? Product::where('products.tenant_id', $tenantId)
                     ->where('products.type', Product::TYPE_AREA_MEMBROS)
                     ->where('products.is_active', true)
            : $user->products()
                   ->where('products.tenant_id', $tenantId)
                   ->where('products.type', Product::TYPE_AREA_MEMBROS)
                   ->where('products.is_active', true);

        $courses = $coursesQuery->get()->map(function (Product $p) use ($user, $storage) {
            return [
                'id'               => $p->id,
                'name'             => $p->name,
                'description'      => $p->description,
                'image_url'        => $p->image ? $storage->url($p->image) : null,
                'progress_percent' => $this->progressService->completionPercent($p, $user),
            ];
        });

        return Inertia::render('MemberAreaApp/Trilha', [
            'courses'  => $courses,
            'product'  => $this->productToArray($product),
            'config'   => $product->member_area_config,
            'base_url' => $baseUrl,
            'slug'     => $slug,
        ]);
    }

    public function modulosCurso(Request $request, string $slug, string $courseId): Response
    {
        $contextProduct = $this->getProduct($request);
        $user           = $request->user();
        $tenantId       = $contextProduct->tenant_id;
        $isAdmin        = $user->canAccessPanel() && (int) $user->tenant_id === (int) $tenantId;

        if ($isAdmin) {
            $product = Product::where('id', $courseId)
                ->where('tenant_id', $tenantId)
                ->where('type', Product::TYPE_AREA_MEMBROS)
                ->firstOrFail();
        } else {
            $product = $user->products()
                ->where('products.id', $courseId)
                ->where('products.tenant_id', $tenantId)
                ->where('products.type', Product::TYPE_AREA_MEMBROS)
                ->where('products.is_active', true)
                ->firstOrFail();
        }

        $userHasAccess = $product->hasMemberAreaAccess($user);
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now           = now();
        $sections      = $product->memberSections()->with(['modules.lessons'])->orderBy('position')->get();

        return Inertia::render('MemberAreaApp/Modulos', [
            'product'      => $this->productToArray($product),
            'config'       => $product->member_area_config,
            'user_has_access' => $userHasAccess,
            'checkout_url' => route('checkout.show', ['slug' => $product->checkout_slug]),
            'sections' => $sections->map(fn (MemberSection $s) => [
                'id'         => $s->id,
                'title'      => $s->title,
                'cover_mode' => $s->cover_mode ?? 'vertical',
                'modules' => $s->modules->map(function (MemberModule $m) use ($accessStartAt, $now, $user, $userHasAccess) {
                    $effective       = ($m->source_member_module_id)
                        ? $this->resolveContentModuleForWrapper($m)
                        : $m;
                    $isFree          = (bool) ($m->is_free ?? false);
                    $isPurchaseLocked = ! $isFree && ! $userHasAccess;

                    return [
                        'id'                  => $m->id,
                        'title'               => $m->title,
                        'thumbnail'           => $m->thumbnail,
                        'show_title_on_cover' => $m->show_title_on_cover ?? true,
                        'is_free'             => $isFree,
                        'is_purchase_locked'  => $isPurchaseLocked,
                        ...($isPurchaseLocked
                            ? ['is_locked' => false, 'available_at' => null, 'lock_message' => null]
                            : $this->moduleLockPayload($effective, $accessStartAt, $now)
                        ),
                        'lessons' => $isPurchaseLocked ? [] : $effective->lessons->map(fn (MemberLesson $l) => [
                            'id'               => $l->id,
                            'title'            => $l->title,
                            'type'             => $l->type,
                            'duration_seconds' => $l->duration_seconds,
                            'is_completed'     => $this->isLessonCompleted($user->id, $l->id),
                            ...$this->lessonLockPayload($l, $effective, $accessStartAt, $now),
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ])->values()->all(),
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug'     => $slug,
            'progress_percent' => $userHasAccess ? $this->progressService->completionPercent($product, $user) : 0,
            'course_lesson_progress' => $userHasAccess ? [
                'completed' => $this->progressService->completedLessonsCount($product, $user),
                'total'     => $this->progressService->totalLessonsCount($product),
            ] : ['completed' => 0, 'total' => 0],
            'mega_report' => $userHasAccess ? $this->getProductMegaReport($product, $user) : null,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    private function getProductMegaReport(Product $product, User $user): ?array
    {
        $report = AiInsight::where('user_id', $user->id)
            ->where('type', 'admin_report')
            ->where('metadata->product_id', (string) $product->id)
            ->latest()
            ->first();

        if (! $report) {
            return null;
        }

        return [
            'id'           => $report->id,
            'content'      => $report->content,
            'generated_at' => $report->created_at->format('d/m/Y \à\s H:i'),
        ];
    }

    public function generateProductMegaReport(
        Request $request,
        string $slug,
        string $courseId,
        AiReportGeneratorService $generator,
    ): JsonResponse {
        $contextProduct = $this->getProduct($request);
        $user           = $request->user();
        $tenantId       = $contextProduct->tenant_id;

        $product = Product::where('id', $courseId)
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->firstOrFail();

        $pct = $this->progressService->completionPercent($product, $user);
        if ($pct < 100) {
            return response()->json(['error' => 'Complete todos os quizzes antes de gerar o relatório.'], 422);
        }

        try {
            $insight = $generator->generate($user, $tenantId, null, '', $product);

            if (! $insight) {
                return response()->json(['error' => 'IA não disponível no momento. Tente novamente mais tarde.'], 503);
            }

            // Associa o relatório ao produto para recuperação futura
            $insight->update([
                'metadata' => array_merge((array) ($insight->metadata ?? []), ['product_id' => (string) $product->id]),
            ]);

            return response()->json([
                'success'      => true,
                'redirect_url' => "{$this->baseUrlForRequest($product, $request)}/modulos/{$product->id}/mega-relatorio",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()], 500);
        }
    }

    public function moduleContent(Request $request, string $slug, MemberModule $module): Response|RedirectResponse
    {
        $product = $this->getProduct($request);
        if ($module->product_id !== $product->id) {
            abort(404);
        }
        $user = $request->user();
        $userHasAccess = $product->hasMemberAreaAccess($user);
        if (! $userHasAccess && ! ($module->is_free ?? false)) {
            return redirect()->route('checkout.show', ['slug' => $product->checkout_slug])
                ->with('error', 'Este módulo requer a compra do produto.');
        }
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        if ($module->source_member_module_id) {
            $redirect = $this->assertEmbeddedProductLinkAccess($module, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $module->load('section');
        $effectiveModule = $this->resolveContentModuleForWrapper($module);
        $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
        if (($moduleLock['is_locked'] ?? false) === true) {
            return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $slug])
                ->with('error', $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
        }
        $lessons = $effectiveModule->lessons->map(fn (MemberLesson $l) => [
            'id' => $l->id,
            'title' => $l->title,
            'type' => $l->type,
            'position' => $l->position,
            'duration_seconds' => $l->duration_seconds,
            'is_completed' => $this->isLessonCompleted($user->id, $l->id),
            ...$this->lessonLockPayload($l, $effectiveModule, $accessStartAt, $now),
        ])->values()->all();

        $lessonId = $request->query('aula');
        // Sem ?aula= na URL: mostra lista de aulas (não abre nenhuma automaticamente)
        $currentLesson = $lessonId
            ? $effectiveModule->lessons->firstWhere('id', (int) $lessonId)
            : null;
        if ($currentLesson) {
            $lock = $this->lessonLockPayload($currentLesson, $effectiveModule, $accessStartAt, $now);
            if (($lock['is_locked'] ?? false) === true) {
                $firstUnlocked = $effectiveModule->lessons->first(function (MemberLesson $l) use ($effectiveModule, $accessStartAt, $now) {
                    return ($this->lessonLockPayload($l, $effectiveModule, $accessStartAt, $now)['is_locked'] ?? false) !== true;
                });
                if ($firstUnlocked) {
                    return redirect()->route($this->memberAreaModuleRouteName($request), ['slug' => $slug, 'module' => $module->id, 'aula' => $firstUnlocked->id])
                        ->with('error', $lock['lock_message'] ?? 'Aula ainda não liberada.');
                }
                $request->session()->flash('error', $lock['lock_message'] ?? 'Aulas ainda não liberadas.');
                $currentLesson = null;
            }
        }

        $currentLessonData = null;
        if ($currentLesson) {
            $this->progressService->ensureLessonStarted($currentLesson, $user);
            $currentLessonData = [
                'id' => $currentLesson->id,
                'title' => $currentLesson->title,
                'type' => $currentLesson->type,
                'content_url' => $currentLesson->content_url,
                'content_files' => $currentLesson->content_files,
                'link_title' => $currentLesson->link_title,
                'content_text' => \App\Support\HtmlSanitizer::sanitize($currentLesson->content_text),
                'duration_seconds' => $currentLesson->duration_seconds,
                'is_completed' => $this->isLessonCompleted($user->id, $currentLesson->id),
                'quiz_response' => $currentLesson->type === 'quiz' ? $this->getQuizResponse($user->id, $currentLesson->id) : null,
                'module' => ['id' => $module->id, 'title' => $module->title],
                'section' => $module->section ? ['id' => $module->section->id, 'title' => $module->section->title] : null,
                'watermark_enabled' => (bool) ($currentLesson->watermark_enabled ?? false),
            ];
            if ($currentLessonData['watermark_enabled']) {
                $currentLessonData['student'] = $this->getStudentWatermarkData($user, $product);
            }
            if ($currentLesson->type === MemberLesson::TYPE_PDF_READER) {
                $currentLessonData = array_merge($currentLessonData, $this->pdfReaderLessonExtras($currentLesson, $user));
            }
        }

        $progressPercent = $this->progressService->completionPercent($product, $user);

        $sections = $product->memberSections()->with('modules')->orderBy('position')->get();
        $sectionsPayload = $sections->map(fn (MemberSection $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'cover_mode' => $s->cover_mode ?? 'vertical',
            'modules' => $s->modules->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail' => $m->thumbnail,
                'show_title_on_cover' => $m->show_title_on_cover ?? true,
                ...$this->moduleLockPayload($m, $accessStartAt, $now),
            ])->values()->all(),
        ])->values()->all();

        $config = $product->member_area_config;
        $commentsEnabled = (bool) ($config['comments_enabled'] ?? true);
        $commentsRequireApproval = (bool) ($config['comments_require_approval'] ?? false);
        $lessonComments = [];
        if ($commentsEnabled && $currentLesson) {
            $lessonComments = MemberComment::forProduct($product->id)
                ->where('member_lesson_id', $currentLesson->id)
                ->status(MemberComment::STATUS_APPROVED)
                ->with('user:id,name,avatar')
                ->latest()
                ->get()
                ->map(fn (MemberComment $c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'user' => $c->user ? [
                        'id' => $c->user->id,
                        'name' => $c->user->name,
                        'avatar_url' => $c->user->avatar ? (new StorageService($product->tenant_id))->url($c->user->avatar) : null,
                    ] : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('MemberAreaApp/ModuleContent', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'section' => $module->section ? ['id' => $module->section->id, 'title' => $module->section->title] : null,
            ],
            'lessons' => $lessons,
            'current_lesson' => $currentLessonData,
            'progress_percent' => $progressPercent,
            'course_lesson_progress' => [
                'completed' => $this->progressService->completedLessonsCount($product, $user),
                'total' => $this->progressService->totalLessonsCount($product),
            ],
            'sections' => $sectionsPayload,
            'comments_enabled' => $commentsEnabled,
            'comments_require_approval' => $commentsRequireApproval,
            'lesson_comments' => $lessonComments,
            ...$this->pushProps($product),
        ]);
    }

    public function lesson(Request $request, string $slug, MemberLesson $lesson): Response|RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $lesson->load('module');
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $effectiveModule = $wrapper !== null
            ? $this->resolveContentModuleForWrapper($wrapper)
            : $lesson->module;
        if ($effectiveModule) {
            $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
            if (($moduleLock['is_locked'] ?? false) === true) {
                return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $slug])
                    ->with('error', $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
            }
        }
        $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
        if (($lessonLock['is_locked'] ?? false) === true) {
            $moduleRouteId = $wrapper?->id ?? $lesson->module?->id;
            if ($moduleRouteId) {
                return redirect()->route($this->memberAreaModuleRouteName($request), ['slug' => $slug, 'module' => $moduleRouteId])
                    ->with('error', $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
            }
            return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $slug])
                ->with('error', $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
        }
        $this->progressService->ensureLessonStarted($lesson, $user);

        $this->logMemberActivity($request, $product, $user, 'member_area.lesson_view', [
            'lesson_id' => $lesson->id,
            'lesson_product_id' => $lesson->product_id,
            'module_id' => $lesson->module?->id,
            'embedded' => $wrapper !== null,
        ]);

        $sectionPayload = null;
        if ($wrapper !== null) {
            $wrapper->loadMissing('section');
            $sectionPayload = $wrapper->section ? ['id' => $wrapper->section->id, 'title' => $wrapper->section->title] : null;
        } elseif ($lesson->module && $lesson->module->section) {
            $sectionPayload = ['id' => $lesson->module->section->id, 'title' => $lesson->module->section->title];
        }

        $lessonPayload = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'type' => $lesson->type,
            'content_url' => $lesson->content_url,
            'content_files' => $lesson->content_files,
            'link_title' => $lesson->link_title,
            'content_text' => \App\Support\HtmlSanitizer::sanitize($lesson->content_text),
            'duration_seconds' => $lesson->duration_seconds,
            'is_completed' => $this->isLessonCompleted($user->id, $lesson->id),
            'quiz_response' => $lesson->type === 'quiz' ? $this->getQuizResponse($user->id, $lesson->id) : null,
            'module' => $wrapper !== null
                ? ['id' => $wrapper->id, 'title' => $wrapper->title]
                : ($lesson->module ? ['id' => $lesson->module->id, 'title' => $lesson->module->title] : null),
            'section' => $sectionPayload,
            'watermark_enabled' => (bool) ($lesson->watermark_enabled ?? false),
        ];
        if ($lessonPayload['watermark_enabled']) {
            $lessonPayload['student'] = $this->getStudentWatermarkData($user, $product);
        }
        if ($lesson->type === MemberLesson::TYPE_PDF_READER) {
            $lessonPayload = array_merge($lessonPayload, $this->pdfReaderLessonExtras($lesson, $user));
        }
        $config = $product->member_area_config;
        $commentsEnabled = (bool) ($config['comments_enabled'] ?? true);
        $commentsRequireApproval = (bool) ($config['comments_require_approval'] ?? false);
        $lessonComments = [];
        if ($commentsEnabled) {
            $lessonComments = MemberComment::forProduct($product->id)
                ->where('member_lesson_id', $lesson->id)
                ->status(MemberComment::STATUS_APPROVED)
                ->with('user:id,name,avatar')
                ->latest()
                ->get()
                ->map(fn (MemberComment $c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'user' => $c->user ? [
                        'id' => $c->user->id,
                        'name' => $c->user->name,
                        'avatar_url' => $c->user->avatar ? (new StorageService($product->tenant_id))->url($c->user->avatar) : null,
                    ] : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('MemberAreaApp/Lesson', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'lesson' => $lessonPayload,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            'comments_enabled' => $commentsEnabled,
            'comments_require_approval' => $commentsRequireApproval,
            'lesson_comments' => $lessonComments,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    /**
     * Proxy para PDFs de apresentação (mesma origem): o pdf.js usa fetch; URLs no R2 sem CORS falham no browser.
     */
    public function presentationPdf(Request $request, string $slug, MemberLesson $lesson, int $fileIndex): SymfonyResponse
    {
        $this->assertLessonViewableForPdf($request, $lesson);
        if (! in_array($lesson->type, [MemberLesson::TYPE_PDF_PRESENTATION, MemberLesson::TYPE_PDF_READER], true)) {
            abort(404);
        }
        $urls = $this->pdfPresentationSourceUrls($lesson);
        if ($fileIndex < 0 || $fileIndex >= count($urls)) {
            abort(404);
        }
        $url = $urls[$fileIndex];
        if (! \App\Support\SafeRemoteUrl::isAllowedHttpUrl($url)) {
            abort(403, 'URL do arquivo não permitida.');
        }

        $this->progressService->ensureLessonStarted($lesson, $request->user());

        $remote = Http::timeout(120)->connectTimeout(30)->get($url);
        if (! $remote->successful()) {
            abort(502, 'Não foi possível obter o arquivo.');
        }

        $path = parse_url($url, PHP_URL_PATH);
        $filename = $path ? basename($path) : 'apresentacao.pdf';
        if ($filename === '' || $filename === '/') {
            $filename = 'apresentacao.pdf';
        }

        return response($remote->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=120',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET — marcações do usuário no leitor PDF (por arquivo).
     */
    public function getLessonPdfAnnotations(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $rows = MemberLessonPdfAnnotation::query()
            ->where('user_id', $user->id)
            ->where('member_lesson_id', $lesson->id)
            ->get(['file_index', 'payload']);

        $byFile = [];
        foreach ($rows as $row) {
            $byFile[(string) $row->file_index] = is_array($row->payload) ? $row->payload : [];
        }

        return response()->json(['annotations_by_file' => $byFile]);
    }

    /**
     * PUT — salva marcações de um arquivo PDF (lista de highlights).
     *
     * @param  array<string, mixed>  $payload
     */
    public function putLessonPdfAnnotations(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $validated = $request->validate([
            'file_index' => ['required', 'integer', 'min:0'],
            'highlights' => ['required', 'array', 'max:500'],
            'highlights.*.id' => ['required', 'string', 'max:64'],
            'highlights.*.page' => ['required', 'integer', 'min:1'],
            'highlights.*.color' => ['required', 'string', 'in:yellow,green,pink'],
            'highlights.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.width' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.height' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        MemberLessonPdfAnnotation::updateOrCreate(
            [
                'user_id' => $user->id,
                'member_lesson_id' => $lesson->id,
                'file_index' => $validated['file_index'],
            ],
            [
                'payload' => $validated['highlights'],
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * POST — alterna curtida na aula (somente tipo pdf_reader).
     */
    public function toggleLessonLike(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $liked = false;
        $count = (int) ($lesson->likes_count ?? 0);

        DB::transaction(function () use ($lesson, $user, &$liked, &$count): void {
            $lessonRow = MemberLesson::query()->whereKey($lesson->id)->lockForUpdate()->first();
            if (! $lessonRow) {
                return;
            }
            $existing = MemberLessonLike::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->first();
            if ($existing) {
                $existing->delete();
                $lessonRow->decrement('likes_count');
                $liked = false;
            } else {
                MemberLessonLike::create([
                    'user_id' => $user->id,
                    'member_lesson_id' => $lesson->id,
                ]);
                $lessonRow->increment('likes_count');
                $liked = true;
            }
            $count = (int) $lessonRow->fresh()->likes_count;
        });

        return response()->json([
            'liked' => $liked,
            'likes_count' => $count,
        ]);
    }

    /**
     * @return array{likes_count: int, user_liked: bool}
     */
    private function pdfReaderLessonExtras(MemberLesson $lesson, User $user): array
    {
        return [
            'likes_count' => (int) ($lesson->likes_count ?? 0),
            'user_liked' => MemberLessonLike::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->exists(),
        ];
    }

    public function completeLesson(Request $request, string $slug, MemberLesson $lesson): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }
        $product = $this->getProduct($request);
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $this->progressService->markLessonCompleted($lesson->id, $user);

        $this->logMemberActivity($request, $product, $user, 'member_area.lesson_complete', [
            'lesson_id' => $lesson->id,
            'lesson_product_id' => $lesson->product_id,
            'embedded' => $wrapper !== null,
        ]);

        $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $user);
        if ($newlyUnlocked !== []) {
            $request->session()->flash('newly_unlocked_achievements', $newlyUnlocked);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }
        $percent = $this->progressService->completionPercent($product, $user);

        return response()->json(['success' => true, 'progress_percent' => $percent, 'newly_unlocked_achievements' => $newlyUnlocked]);
    }

    public function submitQuiz(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }
        $product = $this->getProduct($request);
        if ((string) $lesson->product_id !== (string) $product->id) {
            abort(404);
        }
        $validated = $request->validate([
            'responses'               => ['required', 'array', 'min:1'],
            'responses.*.question_id' => ['required', 'string'],
            'responses.*.type'        => ['nullable', 'string', 'in:scale,boolean,single,multi,text'],
            'responses.*.value'       => ['required'],
            'responses.*.comment'     => ['nullable', 'string', 'max:1000'],
        ]);

        MemberQuizResponse::updateOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id],
            ['product_id' => $product->id, 'responses' => $validated['responses']]
        );

        $this->progressService->markLessonCompleted($lesson->id, $user);
        $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $user);
        $percent = $this->progressService->completionPercent($product, $user);

        return response()->json(['success' => true, 'progress_percent' => $percent, 'newly_unlocked_achievements' => $newlyUnlocked]);
    }

    public function quizReport(Request $request, string $productId, string $lessonId): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'infoprodutor', 'team'])) {
            abort(403);
        }
        $lesson = MemberLesson::findOrFail($lessonId);
        $responses = MemberQuizResponse::where('lesson_id', $lessonId)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'user' => ['name' => $r->user->name, 'email' => $r->user->email],
                'responses' => $r->responses,
                'created_at' => $r->created_at->format('d/m/Y H:i'),
            ]);

        $questions = collect($lesson->content_files['questions'] ?? []);
        $stats = $questions->map(function ($q) use ($responses) {
            $values = $responses->flatMap(fn ($r) => collect($r['responses'])->where('question_id', $q['id'])->pluck('value'));
            return [
                'question_id' => $q['id'],
                'text' => $q['text'],
                'scale_max' => $q['scale_max'] ?? 5,
                'average' => $values->count() ? round($values->avg(), 2) : null,
                'count' => $values->count(),
                'distribution' => $values->countBy()->sortKeys()->all(),
            ];
        });

        return response()->json([
            'lesson_title' => $lesson->title,
            'total_responses' => $responses->count(),
            'stats' => $stats->values(),
            'responses' => $responses->values(),
        ]);
    }

    public function storeLessonComment(Request $request, string $slug, MemberLesson $lesson): JsonResponse|RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $config = $product->member_area_config;
        if (isset($config['comments_enabled']) && $config['comments_enabled'] === false) {
            abort(403, 'Comentários desativados para este produto.');
        }
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);
        $requireApproval = (bool) ($config['comments_require_approval'] ?? false);
        $initialStatus = $requireApproval ? MemberComment::STATUS_PENDING : MemberComment::STATUS_APPROVED;
        $commentService = app(MemberCommentService::class);
        $commentService->create(
            $product,
            $request->user(),
            $validated['content'],
            $lesson->id,
            null,
            $initialStatus
        );
        $message = $requireApproval ? 'Comentário enviado e aguardando aprovação.' : 'Comentário publicado.';
        if (! $requireApproval) {
            $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $request->user());
            if ($newlyUnlocked !== []) {
                $request->session()->flash('newly_unlocked_achievements', $newlyUnlocked);
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function memberGroups(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user    = $request->user();
        $groups = \App\Models\MemberCommunityGroup::where('product_id', $product->id)
            ->withCount('members')->orderBy('position')->get()
            ->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'description' => $g->description,
                'image_url' => $g->image_url, 'is_private' => $g->is_private,
                'members_count' => $g->members_count,
                'is_member'   => \App\Models\MemberCommunityGroupMember::where('group_id', $g->id)->where('user_id', $user->id)->exists(),
                'join_status' => \App\Models\MemberGroupJoinRequest::where('group_id', $g->id)->where('user_id', $user->id)->value('status'),
            ]);
        return Inertia::render('MemberAreaApp/Grupos', [
            'product' => $this->productToArray($product),
            'config'  => $product->member_area_config,
            'slug'    => $slug,
            'groups'  => $groups,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    public function memberEvents(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user    = $request->user();
        $events = \App\Models\MemberCommunityEvent::where('product_id', $product->id)
            ->orderBy('starts_at')->get()
            ->map(fn ($e) => [
                'id' => $e->id, 'title' => $e->title, 'description' => $e->description,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at'   => $e->ends_at?->toIso8601String(),
                'location'  => $e->location, 'link' => $e->link,
                'image_url' => $e->image_url, 'is_online' => $e->is_online,
                'rsvp_count'=> $e->rsvp_count,
                'past'      => $e->starts_at?->isPast() ?? false,
            ]);
        return Inertia::render('MemberAreaApp/Eventos', [
            'product' => $this->productToArray($product),
            'config'  => $product->member_area_config,
            'slug'    => $slug,
            'events'  => $events,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    public function loja(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $ownedIds = $user->products()->pluck('products.id')->toArray();
        $storage  = new StorageService($product->tenant_id);

        $catalogProducts = \App\Models\Product::where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->whereNotNull('checkout_slug')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'description'  => $p->description,
                'image'        => $p->image ? $storage->url($p->image) : null,
                'price'        => $p->price ? number_format((float) $p->price, 2, ',', '.') : null,
                'currency'     => $p->currency ?? 'BRL',
                'checkout_url' => '/c/' . $p->checkout_slug,
                'is_owned'     => in_array($p->id, $ownedIds, true),
                'is_current'   => $p->id === $product->id,
                'area_url'     => '/m/modulos/' . $p->id,
            ])
            ->values()
            ->all();

        return Inertia::render('MemberAreaApp/Loja', [
            'product'          => $this->productToArray($product),
            'config'           => $product->member_area_config,
            'catalog_products' => $catalogProducts,
            'items'            => [],
            'categories'       => [],
            'base_url'         => $this->baseUrlForRequest($product, $request),
            'slug'             => $slug,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    public function comunidade(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $config = $product->member_area_config;
        $isInstructor = $user->canAccessPanel() && $user->tenant_id === $product->tenant_id;
        $isEnrolled   = (bool) $request->attributes->get('member_area_enrolled');
        $isBanned     = ! $isInstructor && CommunityBan::isBanned($product->tenant_id, $user->id, $product->id);

        $posts = MemberCommunityPost::where('tenant_id', $product->tenant_id)
            ->where('is_hidden', false)
            ->with(['user:id,name,email,avatar', 'likes', 'comments.user:id,name,avatar'])
            ->latest()
            ->paginate(20);

        $canDeleteAny = $isInstructor;
        $usersCanDeleteOwn = (bool) ($config['community_users_can_delete_own_posts'] ?? true);

        $posts->getCollection()->transform(function (MemberCommunityPost $post) use ($user, $product) {
            $comments = $post->comments->map(fn (MemberCommunityPostComment $c) => [
                'id'         => $c->id,
                'content'    => $c->content,
                'created_at' => $c->created_at->format('d/m/Y H:i'),
                'user'       => $c->user ? [
                    'id'         => $c->user->id,
                    'name'       => $c->user->name,
                    'avatar_url' => $c->user->avatar ? (new StorageService($product->tenant_id))->url($c->user->avatar) : null,
                ] : null,
            ])->values()->all();

            return array_merge($post->toArray(), [
                'user' => $post->user ? [
                    'id'         => $post->user->id,
                    'name'       => $post->user->name,
                    'avatar_url' => $post->user->avatar ? (new StorageService($product->tenant_id))->url($post->user->avatar) : null,
                ] : null,
                'likes_count'    => $post->likes->count(),
                'user_has_liked' => $post->likes->contains('user_id', $user->id),
                'comments'       => $comments,
                'video_url'      => $post->video_url,
            ]);
        });

        $authorIds = $posts->getCollection()->pluck('user_id')->filter()->unique()->values()->toArray();
        $friendshipMap = [];
        if (! empty($authorIds)) {
            \App\Models\MemberFriendship::where(function ($q) use ($user, $authorIds) {
                $q->where('requester_id', $user->id)->whereIn('receiver_id', $authorIds);
            })->orWhere(function ($q) use ($user, $authorIds) {
                $q->where('receiver_id', $user->id)->whereIn('requester_id', $authorIds);
            })->get()->each(function ($f) use ($user, &$friendshipMap) {
                $otherId = $f->requester_id === $user->id ? $f->receiver_id : $f->requester_id;
                $friendshipMap[$otherId] = $f->status;
            });
        }

        return Inertia::render('MemberAreaApp/ComunidadePage', [
            'product'                              => $this->productToArray($product),
            'config'                               => $config,
            'auth_user_id'                         => $user->id,
            'can_delete_any_post'                  => $canDeleteAny,
            'community_users_can_delete_own_posts' => $usersCanDeleteOwn,
            'friendship_map'                       => $friendshipMap,
            'pages'                                => [],
            'is_enrolled'                          => $isEnrolled,
            'page'                                 => [
                'id'                => null,
                'title'             => 'Comunidade',
                'icon'              => null,
                'slug'              => null,
                'banner_url'        => null,
                'is_public_posting' => true,
                'can_post'          => $isEnrolled && ! $isBanned,
                'is_banned'         => $isBanned,
            ],
            'posts'    => $posts,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug'     => $slug,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    public function storeCommunityPost(Request $request, string $slug): RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $isInstructor = $user->canAccessPanel() && $user->tenant_id === $product->tenant_id;
        if (! $isInstructor && CommunityBan::isBanned($product->tenant_id, $user->id, $product->id)) {
            abort(403, 'Você foi suspenso da comunidade e não pode publicar.');
        }
        $validated = $request->validate([
            'content'   => ['nullable', 'string', 'max:5000'],
            'image'     => ['nullable', 'file', 'image', 'max:10240'],
            'video'     => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],
            'video_url' => ['nullable', 'string', 'max:500'],
        ]);
        if (empty($validated['content']) && ! $request->hasFile('image') && ! $request->hasFile('video') && empty($validated['video_url'])) {
            return back()->withErrors(['content' => 'Escreva algo ou adicione uma imagem/vídeo.']);
        }
        $storage = new StorageService($product->tenant_id);
        $imagePath = null;
        $videoUrl  = $validated['video_url'] ?? null;

        if ($request->hasFile('image')) {
            $imagePath = $storage->putFile('member-area-posts/'.$product->id, $request->file('image'));
        }
        if ($request->hasFile('video')) {
            $videoPath = $storage->putFile('member-area-posts/'.$product->id, $request->file('video'));
            $videoUrl  = $storage->url($videoPath);
        }

        MemberCommunityPost::create([
            'tenant_id'  => $product->tenant_id,
            'product_id' => $product->id,
            'user_id'    => $user->id,
            'content'    => $validated['content'] ?? '',
            'image'      => $imagePath,
            'video_url'  => $videoUrl,
        ]);

        return back()->with('success', 'Post publicado.');
    }

    public function destroyCommunityPost(Request $request, string $slug, MemberCommunityPost $post): RedirectResponse
    {
        $product = $this->getProduct($request);
        abort_if((int) $post->tenant_id !== (int) $product->tenant_id, 404);
        $user = $request->user();
        $config = $product->member_area_config;
        $canDeleteAny = $user->canAccessPanel() && (int) $user->tenant_id === (int) $product->tenant_id;
        $usersCanDeleteOwn = (bool) ($config['community_users_can_delete_own_posts'] ?? true);
        if (! $canDeleteAny && ! ($post->user_id === $user->id && $usersCanDeleteOwn)) {
            abort(403, 'Você não pode excluir esta postagem.');
        }
        $postId = $post->id;
        $post->delete();

        try {
            MemberActivityLog::create([
                'tenant_id'  => $product->tenant_id,
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'event'      => 'community.post.deleted',
                'metadata'   => ['post_id' => $postId],
                'ip'         => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable) {}

        return back()->with('success', 'Postagem excluída.');
    }

    public function likeCommunityPost(Request $request, string $slug, MemberCommunityPost $post): JsonResponse
    {
        $product = $this->getProduct($request);
        abort_if((int) $post->tenant_id !== (int) $product->tenant_id, 404);
        $user = $request->user();
        MemberCommunityPostLike::firstOrCreate(
            ['member_community_post_id' => $post->id, 'user_id' => $user->id]
        );

        if ($post->user_id && $post->user_id !== $user->id) {
            $this->notifyMemberQuietly($post->user_id, $product, [
                'type'  => 'post_like',
                'title' => 'Nova curtida',
                'body'  => "{$user->name} curtiu seu post.",
                'url'   => null,
            ]);
        }

        return response()->json(['likes_count' => $post->likes()->count(), 'user_has_liked' => true]);
    }

    public function unlikeCommunityPost(Request $request, string $slug, MemberCommunityPost $post): JsonResponse
    {
        $product = $this->getProduct($request);
        abort_if((int) $post->tenant_id !== (int) $product->tenant_id, 404);
        MemberCommunityPostLike::where('member_community_post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['likes_count' => $post->likes()->count(), 'user_has_liked' => false]);
    }

    public function storeCommunityPostComment(Request $request, string $slug, MemberCommunityPost $post): JsonResponse|RedirectResponse
    {
        $product = $this->getProduct($request);
        abort_if((int) $post->tenant_id !== (int) $product->tenant_id, 404);
        $validated = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $commenter = $request->user();
        $comment = MemberCommunityPostComment::create([
            'member_community_post_id' => $post->id,
            'user_id'                  => $commenter->id,
            'content'                  => $validated['content'],
        ]);
        $comment->load('user:id,name,avatar');

        if ($post->user_id && $post->user_id !== $commenter->id) {
            $this->notifyMemberQuietly($post->user_id, $product, [
                'type'  => 'post_comment',
                'title' => 'Novo comentário',
                'body'  => "{$commenter->name} comentou: \"{$validated['content']}\"",
                'url'   => null,
            ]);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'comment' => [
                    'id'         => $comment->id,
                    'content'    => $comment->content,
                    'created_at' => $comment->created_at->format('d/m/Y H:i'),
                    'user'       => $comment->user ? [
                        'id'         => $comment->user->id,
                        'name'       => $comment->user->name,
                        'avatar_url' => $comment->user->avatar ? (new StorageService($product->tenant_id))->url($comment->user->avatar) : null,
                    ] : null,
                ],
            ]);
        }

        return back()->with('success', 'Comentário adicionado.');
    }

    public function reportCommunityPost(Request $request, string $slug, MemberCommunityPost $post): JsonResponse
    {
        $product = $this->getProduct($request);
        abort_if((int) $post->tenant_id !== (int) $product->tenant_id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(CommunityReport::REASONS))],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $reporter = $request->user();
        if ($post->user_id === $reporter->id) {
            return response()->json(['message' => 'Você não pode denunciar seu próprio post.'], 422);
        }

        $existing = CommunityReport::where('post_id', $post->id)->where('reporter_user_id', $reporter->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Você já denunciou este post.'], 422);
        }

        CommunityReport::create([
            'post_id'          => $post->id,
            'reporter_user_id' => $reporter->id,
            'reason'           => $validated['reason'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Denúncia registrada. Nossa equipe irá analisar.']);
    }

    public function certificado(Request $request, string $slug): Response|RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $config = $product->member_area_config;
        $certConfig = $config['certificate'] ?? [];

        if (empty($certConfig['enabled'])) {
            return redirect()->route('member-area-app.show', $slug)
                ->with('error', 'O certificado não está habilitado para esta trilha.');
        }

        $progressPercent = $this->progressService->completionPercent($product, $user);
        $requiredPercent = (int) ($certConfig['completion_percent'] ?? 100);
        $issued = MemberCertificateIssued::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($progressPercent >= $requiredPercent && ! $issued) {
            $issued = $this->progressService->issueCertificate($product, $user);
        }

        $newlyUnlocked = [];
        if ($issued !== null) {
            $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $user);
        }

        $certificateAvailable = $issued !== null;
        $certTitle = ! empty($certConfig['title']) ? $certConfig['title'] : $product->name;

        $certificatePayload = [
            'title' => $certTitle,
            'issued_at' => $issued ? $issued->issued_at->format('d/m/Y') : null,
            'issued_at_full' => $issued ? $issued->issued_at->format('d/m/Y H:i') : null,
            'completion_percent' => $issued ? $issued->completion_percent : $progressPercent,
            'signature_text' => $certConfig['signature_text'] ?? '',
            'duration_text' => $certConfig['duration_text'] ?? '',
            'font_family' => $certConfig['font_family'] ?? 'sans-serif',
            'platform_name' => ! empty($certConfig['platform_name']) ? $certConfig['platform_name'] : config('app.name'),
            'primary_color' => $certConfig['primary_color'] ?? null,
            'background_image_url' => $certConfig['background_image_url'] ?? null,
            'background_overlay_enabled' => (bool) ($certConfig['background_overlay_enabled'] ?? false),
            'background_overlay_color' => $certConfig['background_overlay_color'] ?? '#000000',
            'background_overlay_opacity' => isset($certConfig['background_overlay_opacity']) ? (float) $certConfig['background_overlay_opacity'] : 50,
            'text_color' => $certConfig['text_color'] ?? null,
            'title_color' => $certConfig['title_color'] ?? null,
            'signature_font_family' => $certConfig['signature_font_family'] ?? 'Dancing Script',
            'print_format' => $certConfig['print_format'] ?? 'A4',
        ];

        return Inertia::render('MemberAreaApp/Certificado', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'recipient_name' => $user->name,
            'certificate_available' => $certificateAvailable,
            'progress_percent' => $progressPercent,
            'completion_required_percent' => $requiredPercent,
            'certificate' => $certificatePayload,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            'newly_unlocked_achievements' => $newlyUnlocked,
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $user));
    }

    public function pushSubscribe(Request $request, string $slug): JsonResponse
    {
        $product = $this->getProduct($request);
        $config = $product->member_area_config;
        $pwa = $config['pwa'] ?? [];
        if (! ((bool) ($pwa['push_enabled'] ?? false))) {
            return response()->json(['message' => 'Notificações push não estão habilitadas para esta área.'], 403);
        }
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
        ]);
        $keys = $validated['keys'];
        $keys['auth'] = $this->normalizeBase64KeyForPush((string) ($keys['auth'] ?? ''));
        $keys['p256dh'] = $this->normalizeBase64KeyForPush((string) ($keys['p256dh'] ?? ''));
        $subscription = \App\Models\MemberPushSubscription::updateOrCreate(
            [
                'endpoint' => $validated['endpoint'],
            ],
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'keys' => $keys,
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'success' => true,
            'subscribed' => true,
            'subscription_id' => $subscription->id,
            'updated_at' => $subscription->updated_at?->toISOString(),
        ]);
    }

    private function normalizeBase64KeyForPush(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return $key;
        }
        if (str_contains($key, '+') || str_contains($key, '/')) {
            return strtr($key, ['+' => '-', '/' => '_']);
        }

        return $key;
    }

    private function notifyMemberQuietly(int $userId, Product $product, array $data): void
    {
        try {
            \App\Models\MemberNotification::create([
                'user_id'    => $userId,
                'product_id' => $product->id,
                'type'       => $data['type'],
                'title'      => $data['title'],
                'body'       => $data['body'],
                'url'        => $data['url'] ?? null,
            ]);
        } catch (\Throwable) {}
    }

    // Rotas de stories na área de membros
    public function storiesForProduct(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();

        $userProductIds = $user->products()->pluck('products.id')->toArray();

        $stories = \App\Models\MemberCommunityStory::where('product_id', $product->id)
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($userProductIds) {
                $q->where('visibility', 'all')
                  ->orWhere(function ($q2) use ($userProductIds) {
                      $q2->where('visibility', 'specific')
                         ->whereJsonContains('visible_product_ids', ...$userProductIds);
                  });
            })
            ->withCount(['likes', 'views'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'content'     => $s->content,
                'image_url'   => $s->image_url,
                'video_url'   => $s->video_file_url ?? $s->video_url,
                'is_video'    => (bool) ($s->video_file_url ?? $s->video_url),
                'bg_color'    => $s->bg_color,
                'expires_at'  => $s->expires_at->toIso8601String(),
                'likes_count' => $s->likes_count,
                'views_count' => $s->views_count,
                'user_liked'  => $s->likes()->where('user_id', $user->id)->exists(),
            ]);

        return response()->json(['stories' => $stories]);
    }

    public function likeStory(Request $request, string $slug, int $storyId): \Illuminate\Http\JsonResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $story = \App\Models\MemberCommunityStory::findOrFail($storyId);
        \App\Models\MemberCommunityStoryLike::firstOrCreate(['story_id' => $storyId, 'user_id' => $user->id]);
        return response()->json(['success' => true, 'likes_count' => $story->likes()->count(), 'user_liked' => true]);
    }

    public function viewStory(Request $request, string $slug, int $storyId): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        \App\Models\MemberCommunityStoryView::firstOrCreate(['story_id' => $storyId, 'user_id' => $user->id]);
        return response()->json(['success' => true]);
    }

    private function getProduct(Request $request): Product
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product) {
            abort(404);
        }

        return $product;
    }

    /** @return array{push_enabled: bool, vapid_public: string|null} */
    private function pushProps(Product $product): array
    {
        $config = $product->member_area_config;
        $pwa = $config['pwa'] ?? [];
        $pushEnabled = (bool) ($pwa['push_enabled'] ?? false);

        return [
            'push_enabled' => $pushEnabled,
            'vapid_public' => $pushEnabled ? ($pwa['vapid_public'] ?? null) : null,
        ];
    }

    /** @return array{gamification_achievements: array} */
    private function gamificationProps(Product $product, User $user): array
    {
        $config = $product->member_area_config;
        $gamification = $config['gamification'] ?? [];
        if (empty($gamification['enabled'])) {
            return ['gamification_achievements' => []];
        }

        return [
            'gamification_achievements' => $this->gamificationService->getAchievementsForUser($product, $user),
        ];
    }

    private function productToArray(Product $product): array
    {
        $config = $product->member_area_config;
        $logos = $config['logos'] ?? [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'checkout_slug' => $product->checkout_slug,
            'logo_light' => $logos['logo_light'] ?? '',
            'logo_dark' => $logos['logo_dark'] ?? '',
        ];
    }

    /**
     * Retorna lista de "continuar assistindo": um item por seção (o último progresso de cada seção).
     */
    private function getContinueWatching(Product $product, $user): array
    {
        $lessonIds = $this->progressService->lessonIdsForMemberAreaHost($product);
        if ($lessonIds === []) {
            return [];
        }

        $progresses = MemberLessonProgress::query()
            ->forUser($user->id)
            ->whereNull('completed_at')
            ->whereIn('member_lesson_id', $lessonIds)
            ->with('lesson.module')
            ->latest('updated_at')
            ->get();

        $wrappers = MemberModule::query()
            ->where('product_id', $product->id)
            ->whereNotNull('source_member_module_id')
            ->get()
            ->keyBy('source_member_module_id');

        $bySection = [];
        foreach ($progresses as $p) {
            if (! $p->lesson) {
                continue;
            }
            $lesson = $p->lesson;
            $wrapper = $wrappers->get($lesson->member_module_id);
            $sectionId = $wrapper?->member_section_id ?? $lesson->module?->member_section_id;
            if ($sectionId === null) {
                continue;
            }
            if (isset($bySection[$sectionId])) {
                continue;
            }
            $bySection[$sectionId] = $p;
        }

        $items = [];
        foreach ($bySection as $p) {
            $lesson = $p->lesson;
            $wrapper = $wrappers->get($lesson->member_module_id);
            $moduleForMeta = $wrapper ?? $lesson->module;
            $moduleThumbnail = null;
            if ($moduleForMeta && $moduleForMeta->thumbnail) {
                $moduleThumbnail = str_starts_with($moduleForMeta->thumbnail, 'http') ? $moduleForMeta->thumbnail : (new StorageService($product->tenant_id))->url($moduleForMeta->thumbnail);
            }
            $items[] = [
                'lesson_id' => $lesson->id,
                'module_id' => $wrapper?->id ?? $lesson->module?->id,
                'title' => $lesson->title,
                'module_title' => $moduleForMeta?->title,
                'module_thumbnail' => $moduleThumbnail,
            ];
        }

        return $items;
    }

    private function memberAreaModuleRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return str_ends_with($name, '.host') ? 'member-area-app.module.host' : 'member-area-app.module';
    }

    private function memberAreaModulosRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return str_ends_with($name, '.host') ? 'member-area-app.modulos.host' : 'member-area-app.modulos';
    }

    /**
     * Abertura de "outros produtos" mantendo o contexto da área atual.
     *
     * - Se o produto relacionado é "Link": redireciona para o endpoint deliverable.
     * - Se for área de membros: redireciona para o primeiro módulo embutido (wrapper) dentro do produto host.
     */
    public function openRelatedProduct(Request $request): RedirectResponse
    {
        $relatedProduct = (string) $request->route('relatedProduct', '');
        if ($relatedProduct === '') {
            abort(404);
        }

        $host = $this->getProduct($request);
        $user = $request->user();

        $slug = (string) ($request->attributes->get('member_area_slug') ?? $request->route('slug') ?? '');
        $slug = $slug !== '' ? $slug : (string) ($host->checkout_slug ?? '');

        if (! $user instanceof User) {
            // In host-based member areas, GET /login is handled by the platform login controller.
            $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
            return $isHost
                ? redirect()->to('/login')->with('error', 'Faça login para acessar a área de membros.')
                : redirect()->route('member-area.login', ['slug' => $slug])->with('error', 'Faça login para acessar a área de membros.');
        }

        $related = Product::query()->whereKey($relatedProduct)->first();
        if (! $related) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Produto relacionado não encontrado ou indisponível.');
        }

        if ($related->type === Product::TYPE_LINK) {
            return redirect()->route($this->memberAreaProductsDeliverableRouteName($request), $this->memberAreaProductsRouteParams($request, $slug, $relatedProduct));
        }

        $wrapper = MemberModule::query()
            ->where('product_id', $host->id)
            ->where('related_product_id', $related->id)
            ->whereNotNull('source_member_module_id')
            ->orderBy('position')
            ->first();

        if (! $wrapper) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Este produto ainda não foi embutido nesta área. No Member Builder, adicione/importa os módulos do produto para esta seção.');
        }

        $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->route(
            $this->memberAreaModuleRouteName($request),
            $this->memberAreaModuleRouteParams($request, $slug, (string) $wrapper->id)
        );
    }

    /**
     * Endpoint dedicado para abrir o deliverable de produtos do tipo "Link" a partir da área de membros.
     * Deve ser usado com target=_blank no front.
     */
    public function openRelatedProductDeliverable(Request $request): RedirectResponse
    {
        $relatedProduct = (string) $request->route('relatedProduct', '');
        if ($relatedProduct === '') {
            abort(404);
        }

        $host = $this->getProduct($request);
        $user = $request->user();

        $slug = (string) ($request->attributes->get('member_area_slug') ?? $request->route('slug') ?? '');
        $slug = $slug !== '' ? $slug : (string) ($host->checkout_slug ?? '');

        if (! $user instanceof User) {
            $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
            return $isHost
                ? redirect()->to('/login')->with('error', 'Faça login para acessar a área de membros.')
                : redirect()->route('member-area.login', ['slug' => $slug])->with('error', 'Faça login para acessar a área de membros.');
        }

        // Normaliza chaves numéricas: em SQLite products.id pode ser inteiro enquanto a rota envia string,
        // e whereKey/where em FK podem falhar de forma inconsistente entre colunas inteiras vs modelo keyType string.
        $relatedIdCandidates = array_values(array_unique(array_filter(
            [(string) $relatedProduct, is_numeric($relatedProduct) ? (int) $relatedProduct : null],
            static fn ($v) => $v !== null && $v !== ''
        )));
        // 1) Tenta resolver via card/wrapper do próprio host (fonte mais confiável do contexto "paid/free")
        $anyWrapperOrCard = MemberModule::query()
            ->with('relatedProduct')
            ->where('product_id', $host->getKey())
            ->whereIn('related_product_id', $relatedIdCandidates)
            ->orderBy('position')
            ->first();

        $related = $anyWrapperOrCard?->relatedProduct;

        // 2) Fallback: resolver pelo id diretamente (não depende de ter card importado)
        if (! $related) {
            $related = Product::query()->whereIn('id', $relatedIdCandidates)->first();
        }

        if (! $related) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Produto relacionado não encontrado ou indisponível.');
        }

        // Gate de acesso: se existir card/wrapper no host, respeita paid/free e acesso do usuário.
        if ($anyWrapperOrCard) {
            $redirect = $this->assertEmbeddedProductLinkAccess($anyWrapperOrCard, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        // Abrir deliverable (produto tipo Link)
        if ($related->type === Product::TYPE_LINK) {
            $link = $this->resolveDeliverableLinkForLinkProduct($related);
            if ($link !== '') {
                return str_starts_with($link, 'http://') || str_starts_with($link, 'https://')
                    ? redirect()->away($link)
                    : redirect('/' . ltrim($link, '/'));
            }
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Este produto está como tipo Link, mas o link de entrega não foi configurado.');
        }

        return redirect()->route(
            $this->memberAreaProductsOpenRouteName($request),
            $this->memberAreaProductsRouteParams($request, $slug, $relatedProduct)
        );
    }

    private function memberAreaProductsOpenRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        return str_ends_with($name, '.host') ? 'member-area-app.products.open.host' : 'member-area-app.products.open';
    }

    private function memberAreaProductsDeliverableRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        return str_ends_with($name, '.host') ? 'member-area-app.products.deliverable.host' : 'member-area-app.products.deliverable';
    }

    /**
     * @return array<string, mixed>
     */
    private function memberAreaProductsRouteParams(Request $request, string $slug, string $productId): array
    {
        $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
        return $isHost ? ['relatedProduct' => $productId] : ['slug' => $slug, 'relatedProduct' => $productId];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberAreaModuleRouteParams(Request $request, string $slug, string $moduleId): array
    {
        $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
        return $isHost ? ['module' => $moduleId] : ['slug' => $slug, 'module' => $moduleId];
    }

    /**
     * Lê deliverable_link a partir de products.checkout_config (merge com default).
     * Prioriza o array já cast no modelo; só consulta a tabela bruta se o link continuar vazio
     * (ex.: id string vs inteiro no SQLite, ou relação carregada sem o JSON).
     */
    private function resolveDeliverableLinkForLinkProduct(Product $related): string
    {
        $fromModel = $related->checkout_config;
        $stored = is_array($fromModel) ? $fromModel : [];
        $merged = array_replace_recursive(Product::defaultCheckoutConfig(), $stored);
        $link = trim((string) ($merged['deliverable_link'] ?? ''));
        if ($link !== '') {
            return $link;
        }

        $ids = array_values(array_unique(array_filter(
            [(string) $related->getKey(), is_numeric($related->getKey()) ? (int) $related->getKey() : null],
            static fn ($v) => $v !== null && $v !== ''
        )));
        $row = DB::table('products')->whereIn('id', $ids)->first();
        $stored = [];
        if ($row && isset($row->checkout_config) && $row->checkout_config !== null) {
            $raw = $row->checkout_config;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $stored = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $stored = $raw;
            } elseif (is_object($raw)) {
                $decoded = json_decode(json_encode($raw), true);
                $stored = is_array($decoded) ? $decoded : [];
            }
        }
        $merged = array_replace_recursive(Product::defaultCheckoutConfig(), $stored);

        return trim((string) ($merged['deliverable_link'] ?? ''));
    }

    private function assertEmbeddedProductLinkAccess(MemberModule $wrapper, User $user): ?RedirectResponse
    {
        if (! $wrapper->related_product_id) {
            return null;
        }
        $relatedFkIds = array_values(array_unique(array_filter(
            [(string) $wrapper->related_product_id, is_numeric($wrapper->related_product_id) ? (int) $wrapper->related_product_id : null],
            static fn ($v) => $v !== null && $v !== ''
        )));
        $hasAccess = $user->products()->whereIn('products.id', $relatedFkIds)->exists();
        if (($wrapper->access_type ?? 'paid') === 'paid' && ! $hasAccess) {
            $related = Product::query()->whereIn('id', $relatedFkIds)->first();
            if ($related?->checkout_slug) {
                return redirect()->route('checkout.show', ['slug' => $related->checkout_slug])
                    ->with('error', 'Você não tem acesso a este conteúdo.');
            }
            abort(403);
        }

        // If the embedded product is a "Link" deliverable, open the deliverable link instead of
        // trying to render it as member-area content (which would 404).
        $related = Product::query()->whereIn('id', $relatedFkIds)->first();
        if ($related?->type === Product::TYPE_LINK) {
            $link = $this->resolveDeliverableLinkForLinkProduct($related);
            if ($link !== '') {
                return str_starts_with($link, 'http://') || str_starts_with($link, 'https://')
                    ? redirect()->away($link)
                    : redirect('/' . ltrim($link, '/'));
            }
        }

        return null;
    }

    /**
     * Módulo de origem (com aulas) quando o registro é um wrapper de embed; senão o próprio módulo.
     */
    private function resolveContentModuleForWrapper(MemberModule $wrapper): MemberModule
    {
        if (! $wrapper->source_member_module_id) {
            if (! $wrapper->relationLoaded('lessons')) {
                $wrapper->load(['lessons' => fn ($q) => $q->orderBy('position')]);
            }

            return $wrapper;
        }
        $source = MemberModule::query()
            ->whereKey($wrapper->source_member_module_id)
            ->where('product_id', $wrapper->related_product_id)
            ->with(['lessons' => fn ($q) => $q->orderBy('position')])
            ->first();
        if (! $source) {
            abort(404);
        }

        return $source;
    }

    private function findWrapperForEmbeddedLesson(MemberLesson $lesson, Product $host): ?MemberModule
    {
        if ((string) $lesson->product_id === (string) $host->id) {
            return null;
        }

        return MemberModule::query()
            ->where('product_id', $host->id)
            ->where('source_member_module_id', $lesson->member_module_id)
            ->where('related_product_id', $lesson->product_id)
            ->first();
    }

    private function mapModuleForMemberArea(MemberModule $m, MemberSection $s, Product $product, $user, array $userProductIds, string $baseUrl, Carbon $accessStartAt, Carbon $now): array
    {
        $sectionType = $s->section_type ?? 'courses';

        if ($sectionType === 'courses') {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail' => $m->thumbnail,
                'show_title_on_cover' => $m->show_title_on_cover ?? true,
                ...$this->moduleLockPayload($m, $accessStartAt, $now),
                'lessons' => $m->lessons->map(fn (MemberLesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'type' => $l->type,
                    'duration_seconds' => $l->duration_seconds,
                    'is_completed' => $this->progressService->completedLessonsCount($product, $user) > 0
                        ? $this->isLessonCompleted($user->id, $l->id)
                        : false,
                    ...$this->lessonLockPayload($l, $m, $accessStartAt, $now),
                ])->values()->all(),
            ];
        }

        if ($sectionType === 'products') {
            $related = $m->relatedProduct;
            $hasAccess = $m->related_product_id ? isset($userProductIds[$m->related_product_id]) : false;
            $embed = $m->source_member_module_id
                && $related
                && $related->type === Product::TYPE_AREA_MEMBROS;
            $accessType = $m->access_type ?? 'paid';
            $isFree = $accessType === 'free';
            $canOpenEmbed = $embed && ($isFree || $hasAccess);
            $deliverableLink = null;
            if ($related && $related->type === Product::TYPE_LINK && ($isFree || $hasAccess)) {
                $raw = $this->resolveDeliverableLinkForLinkProduct($related);
                $deliverableLink = $raw !== '' ? $raw : null;
            }

            return [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail' => $m->thumbnail,
                'show_title_on_cover' => $m->show_title_on_cover ?? true,
                'related_product_id' => $m->related_product_id,
                'source_member_module_id' => $m->source_member_module_id,
                'access_type' => $accessType,
                'embed' => $canOpenEmbed,
                'related_product' => $related ? [
                    'id' => $related->id,
                    'name' => $related->name,
                    'type' => $related->type,
                    'deliverable_link' => $deliverableLink,
                    'image_url' => $related->image ? (new StorageService($product->tenant_id))->url($related->image) : null,
                    'checkout_slug' => $related->checkout_slug,
                    'checkout_url' => url('/c/'.$related->checkout_slug),
                    'member_area_slug' => $related->checkout_slug,
                ] : null,
                'has_access' => $hasAccess,
            ];
        }

        // external_links
        return [
            'id' => $m->id,
            'title' => $m->title,
            'thumbnail' => $m->thumbnail,
            'show_title_on_cover' => $m->show_title_on_cover ?? true,
            'external_url' => $m->external_url,
        ];
    }

    private function userAccessStartAt(Product $product, User $user): Carbon
    {
        if ($user->canAccessPanel() && $user->tenant_id === $product->tenant_id) {
            return now()->subYears(20);
        }
        $createdAt = DB::table('product_user')
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->value('created_at');
        if ($createdAt) {
            return Carbon::parse($createdAt);
        }
        return now();
    }

    private function scheduleMeta(?int $afterDays, mixed $atDate, Carbon $accessStartAt): array
    {
        if ($atDate instanceof Carbon) {
            return ['available_at' => $atDate->copy()->startOfDay(), 'mode' => 'date'];
        }
        if (is_string($atDate) && $atDate !== '') {
            return ['available_at' => Carbon::createFromFormat('Y-m-d', $atDate)->startOfDay(), 'mode' => 'date'];
        }
        if (is_int($afterDays) && $afterDays > 0) {
            return ['available_at' => $accessStartAt->copy()->addDays($afterDays), 'mode' => 'days'];
        }
        return ['available_at' => null, 'mode' => null];
    }

    private function lockPayload(?Carbon $availableAt, Carbon $now, ?string $mode): array
    {
        if (! $availableAt) {
            return ['is_locked' => false, 'available_at' => null, 'lock_message' => null];
        }
        if ($availableAt->lessThanOrEqualTo($now)) {
            return ['is_locked' => false, 'available_at' => $availableAt->toIso8601String(), 'lock_message' => null];
        }
        $message = null;
        if ($mode === 'date') {
            $message = 'Disponível em '.$availableAt->format('d/m/Y');
        } elseif ($mode === 'days') {
            // Carbon 3: diffInDays() retorna float (interpolação entre dias); exibir número inteiro.
            $days = max(1, (int) round($now->diffInDays($availableAt, true)));
            $message = $days === 1
                ? 'Disponível em 1 dia'
                : 'Disponível em '.$days.' dias';
        } else {
            $message = 'Disponível em '.$availableAt->format('d/m/Y H:i');
        }
        return ['is_locked' => true, 'available_at' => $availableAt->toIso8601String(), 'lock_message' => $message];
    }

    private function moduleLockPayload(MemberModule $module, Carbon $accessStartAt, Carbon $now): array
    {
        $meta = $this->scheduleMeta($module->release_after_days, $module->release_at_date, $accessStartAt);
        return $this->lockPayload($meta['available_at'], $now, $meta['mode']);
    }

    private function lessonLockPayload(MemberLesson $lesson, ?MemberModule $module, Carbon $accessStartAt, Carbon $now): array
    {
        $lessonMeta = $this->scheduleMeta($lesson->release_after_days, $lesson->release_at_date, $accessStartAt);
        $moduleMeta = $module ? $this->scheduleMeta($module->release_after_days, $module->release_at_date, $accessStartAt) : ['available_at' => null, 'mode' => null];

        $lessonAt = $lessonMeta['available_at'];
        $moduleAt = $moduleMeta['available_at'];
        $availableAt = null;
        $mode = null;
        if ($lessonAt && $moduleAt) {
            if ($lessonAt->greaterThanOrEqualTo($moduleAt)) {
                $availableAt = $lessonAt;
                $mode = $lessonMeta['mode'];
            } else {
                $availableAt = $moduleAt;
                $mode = $moduleMeta['mode'];
            }
        } else {
            if ($lessonAt) {
                $availableAt = $lessonAt;
                $mode = $lessonMeta['mode'];
            } elseif ($moduleAt) {
                $availableAt = $moduleAt;
                $mode = $moduleMeta['mode'];
            }
        }
        return $this->lockPayload($availableAt, $now, $mode);
    }

    private function isLessonCompleted(int $userId, int $lessonId): bool
    {
        return \App\Models\MemberLessonProgress::where('user_id', $userId)
            ->where('member_lesson_id', $lessonId)
            ->whereNotNull('completed_at')
            ->exists();
    }

    private function getQuizResponse(int $userId, int $lessonId): ?array
    {
        $row = MemberQuizResponse::where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->value('responses');
        return $row ?: null;
    }

    /**
     * Dados do aluno para marca d'água (nome, email, cpf se houver no pedido).
     */
    private function getStudentWatermarkData(User $user, Product $product): array
    {
        $cpf = Order::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->latest()
            ->value('cpf');
        if (is_string($cpf) && trim($cpf) !== '') {
            return ['name' => $user->name ?? '', 'email' => $user->email ?? '', 'cpf' => trim($cpf)];
        }

        return ['name' => $user->name ?? '', 'email' => $user->email ?? '', 'cpf' => null];
    }

    public function manifest(Request $request, ?string $slug = null): \Illuminate\Http\JsonResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404);
        }
        $slug = $slug ?? $request->route('slug') ?? $request->attributes->get('member_area_slug');
        $baseUrl = rtrim($this->baseUrlForRequest($product, $request), '/');
        $config  = $product->member_area_config;
        $pwa     = $config['pwa'] ?? [];
        $logos   = $config['logos'] ?? [];

        // Lê branding global (configurações → Área do Aluno) e usa como override com prioridade
        $rawBranding    = \App\Models\Setting::get('member_area_branding', null, $product->tenant_id);
        $globalBranding = is_string($rawBranding) ? (json_decode($rawBranding, true) ?: []) : ($rawBranding ?? []);

        $name       = ($globalBranding['area_name'] ?? null) ?: ($pwa['name'] ?: $product->name);
        $shortName  = $pwa['short_name'] ?: $name;
        $themeColor = ($globalBranding['theme_color'] ?? null)
                   ?: ($globalBranding['primary_color'] ?? null)
                   ?: ($pwa['theme_color'] ?? '#0ea5e9');

        $icons = [];
        $faviconUrl = ($globalBranding['favicon_url'] ?? null) ?: ($logos['favicon'] ?? $pwa['favicon'] ?? null);
        if ($faviconUrl) {
            $iconUrl = str_starts_with($faviconUrl, 'http') ? $faviconUrl : (str_starts_with($faviconUrl, '/') ? $request->getSchemeAndHttpHost().$faviconUrl : $request->getSchemeAndHttpHost().'/'.ltrim($faviconUrl, '/'));
            $icons[] = ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'];
            $icons[] = ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
        }
        if (isset($pwa['icons']) && is_array($pwa['icons'])) {
            foreach ($pwa['icons'] as $icon) {
                $src = $icon['src'] ?? null;
                if ($src && ! str_starts_with($src, 'http')) {
                    $src = $request->getSchemeAndHttpHost().(str_starts_with($src, '/') ? $src : '/'.ltrim($src, '/'));
                }
                if ($src) {
                    $icons[] = [
                        'src' => $src,
                        'sizes' => $icon['sizes'] ?? '192x192',
                        'type' => $icon['type'] ?? 'image/png',
                        'purpose' => $icon['purpose'] ?? 'any maskable',
                    ];
                }
            }
        }
        if (empty($icons)) {
            $icons[] = [
                'src' => $request->getSchemeAndHttpHost().'/images/gateways/pix.svg',
                'sizes' => '192x192',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ];
        }

        // `id` único para o Android tratar como app separado do painel (mesmo origin); evita "já está instalado"
        $manifestId = $slug ? '/m/'.$slug : ($baseUrl ? parse_url($baseUrl, PHP_URL_PATH) : '/m/member-area');

        $manifest = [
            'id' => $manifestId,
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => $baseUrl,
            'scope' => $baseUrl.'/',
            'display' => 'standalone',
            'background_color' => $config['theme']['background'] ?? '#18181b',
            'theme_color' => $themeColor,
            'icons' => $icons,
        ];

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    private function baseUrlForRequest(Product $product, Request $request): string
    {
        $accessType = $request->attributes->get('member_area_access_type');
        if (in_array($accessType, ['subdomain', 'custom'], true)) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        // Clean routes e rotas de produto (path /m/{slug}) usam a mesma base /m
        return rtrim($request->getSchemeAndHttpHost(), '/').'/m';
    }

    private function assertLessonViewableForPdf(Request $request, MemberLesson $lesson): void
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $lesson->loadMissing('module');
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                abort(403, 'Sem acesso a este conteúdo.');
            }
        }
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $effectiveModule = $wrapper !== null
            ? $this->resolveContentModuleForWrapper($wrapper)
            : $lesson->module;
        if ($effectiveModule) {
            $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
            if (($moduleLock['is_locked'] ?? false) === true) {
                abort(403, $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
            }
        }
        $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
        if (($lessonLock['is_locked'] ?? false) === true) {
            abort(403, $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
        }
    }

    /**
     * Mesma ordem que `normalizePdfFiles` no frontend (MemberAreaApp).
     *
     * @return list<string>
     */
    private function pdfPresentationSourceUrls(MemberLesson $lesson): array
    {
        $urls = [];
        $files = $lesson->content_files;
        if (is_array($files)) {
            foreach ($files as $it) {
                if (is_string($it)) {
                    $u = trim($it);
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                } elseif (is_array($it)) {
                    $u = trim((string) ($it['url'] ?? ''));
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                }
            }
        }
        if ($urls === [] && $lesson->content_url) {
            $u = trim((string) $lesson->content_url);
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        return $urls;
    }
}
