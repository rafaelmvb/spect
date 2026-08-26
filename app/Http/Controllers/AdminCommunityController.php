<?php

namespace App\Http\Controllers;

use App\Models\CommunityBan;
use App\Models\CommunityReport;
use App\Models\MemberCommunityEvent;
use App\Models\MemberCommunityGroup;
use App\Models\MemberCommunityGroupMember;
use App\Models\MemberCommunityPage;
use App\Models\MemberCommunityPost;
use App\Models\MemberCommunityStory;
use App\Models\Product;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCommunityController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    // ─── Página principal ──────────────────────────────────────────────────────
    public function index(): Response
    {
        $tenantId = $this->tenantId();
        $products = Product::forTenant($tenantId)->where('type', 'area_membros')->get(['id', 'name']);

        $posts = MemberCommunityPost::query()
            ->join('member_community_pages', 'member_community_pages.id', '=', 'member_community_posts.member_community_page_id')
            ->where('member_community_pages.tenant_id', $tenantId)
            ->with(['user:id,name', 'page:id,title,slug'])
            ->orderByDesc('member_community_posts.created_at')
            ->select('member_community_posts.*')
            ->paginate(30);

        $events = MemberCommunityEvent::query()
            ->join('products', 'products.id', '=', 'member_community_events.product_id')
            ->where('products.tenant_id', $tenantId)
            ->orderBy('member_community_events.starts_at')
            ->select('member_community_events.*')
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'description' => $e->description,
                'starts_at'   => $e->starts_at?->toIso8601String(),
                'ends_at'     => $e->ends_at?->toIso8601String(),
                'location'    => $e->location,
                'link'        => $e->link,
                'image_url'   => $e->image_url,
                'is_online'   => $e->is_online,
                'rsvp_count'  => $e->rsvp_count,
                'product_id'  => $e->product_id,
            ]);

        $groups = MemberCommunityGroup::query()
            ->join('products', 'products.id', '=', 'member_community_groups.product_id')
            ->where('products.tenant_id', $tenantId)
            ->orderBy('member_community_groups.position')
            ->select('member_community_groups.*')
            ->withCount('members')
            ->get()
            ->map(fn ($g) => [
                'id'           => $g->id,
                'name'         => $g->name,
                'description'  => $g->description,
                'image_url'    => $g->image_url,
                'is_private'   => $g->is_private,
                'product_id'   => $g->product_id,
                'members_count'=> $g->members_count,
            ]);

        $pages = MemberCommunityPage::query()
            ->where('tenant_id', $tenantId)
            ->select('id', 'title', 'slug', 'product_id')
            ->orderBy('position')
            ->get();

        $stories = MemberCommunityStory::query()
            ->join('products', 'products.id', '=', 'member_community_stories.product_id')
            ->where('products.tenant_id', $tenantId)
            ->orderByDesc('member_community_stories.created_at')
            ->select('member_community_stories.*')
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'content'    => $s->content,
                'image_url'  => $s->image_url,
                'video_url'  => $s->video_url,
                'bg_color'   => $s->bg_color,
                'expires_at' => $s->expires_at->toIso8601String(),
                'is_expired' => $s->isExpired(),
                'product_id' => $s->product_id,
                'created_at' => $s->created_at->format('d/m/Y H:i'),
            ]);

        $reports = CommunityReport::query()
            ->join('member_community_posts', 'member_community_posts.id', '=', 'community_reports.post_id')
            ->join('member_community_pages', 'member_community_pages.id', '=', 'member_community_posts.member_community_page_id')
            ->where('member_community_pages.tenant_id', $tenantId)
            ->with([
                'reporter:id,name,email',
                'post:id,content,user_id,is_hidden,member_community_page_id',
                'post.user:id,name',
                'resolver:id,name',
            ])
            ->select('community_reports.*')
            ->orderByRaw("FIELD(community_reports.status, 'pending', 'resolved', 'dismissed')")
            ->orderByDesc('community_reports.created_at')
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'reason'      => $r->reason,
                'reason_label'=> CommunityReport::REASONS[$r->reason] ?? $r->reason,
                'notes'       => $r->notes,
                'status'      => $r->status,
                'created_at'  => $r->created_at->format('d/m/Y H:i'),
                'resolved_at' => $r->resolved_at?->format('d/m/Y H:i'),
                'reporter'    => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name, 'email' => $r->reporter->email] : null,
                'resolver'    => $r->resolver ? ['id' => $r->resolver->id, 'name' => $r->resolver->name] : null,
                'post'        => $r->post ? [
                    'id'        => $r->post->id,
                    'content'   => $r->post->content,
                    'is_hidden' => $r->post->is_hidden,
                    'author'    => $r->post->user ? ['id' => $r->post->user->id, 'name' => $r->post->user->name] : null,
                ] : null,
            ]);

        return Inertia::render('AdminCommunity/Index', [
            'products' => $products,
            'posts'    => $posts,
            'events'   => $events,
            'groups'   => $groups,
            'pages'    => $pages,
            'stories'  => $stories,
            'reports'  => $reports,
        ]);
    }

    // ─── Posts ─────────────────────────────────────────────────────────────────
    public function storePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_id'                => ['required', 'integer'],
            'content'                => ['nullable', 'string', 'max:5000'],
            'image'                  => ['nullable', 'file', 'image', 'max:10240'],
            'video'                  => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],
            'video_url'              => ['nullable', 'string', 'max:500'],
            'visible_to_product_ids' => ['nullable', 'array'],
            'visible_to_product_ids.*' => ['integer'],
        ]);

        $page = MemberCommunityPage::findOrFail($validated['page_id']);
        abort_if($page->tenant_id !== $this->tenantId(), 403);

        $storage   = new StorageService($this->tenantId());
        $imagePath = null;
        $videoUrl  = $validated['video_url'] ?? null;

        if ($request->hasFile('image')) {
            $imagePath = $storage->putFile('member-area-posts/' . $page->product_id, $request->file('image'));
        }
        if ($request->hasFile('video')) {
            $videoPath = $storage->putFile('member-area-posts/' . $page->product_id, $request->file('video'));
            $videoUrl  = $storage->url($videoPath);
        }

        $visibleToProductIds = ! empty($validated['visible_to_product_ids'])
            ? array_values(array_map('intval', $validated['visible_to_product_ids']))
            : null;

        $post = MemberCommunityPost::create([
            'member_community_page_id' => $page->id,
            'user_id'                  => auth()->id(),
            'content'                  => $validated['content'] ?? '',
            'image'                    => $imagePath,
            'video_url'                => $videoUrl,
            'visible_to_product_ids'   => $visibleToProductIds,
        ]);

        return response()->json(['success' => true, 'post' => ['id' => $post->id, 'content' => $post->content]]);
    }

    // ─── Ban / Unban de comunidade ─────────────────────────────────────────────

    public function banUser(Request $request, User $user): JsonResponse
    {
        $tenantId = $this->tenantId();

        // Garante que o aluno pertence ao mesmo tenant
        abort_if($user->tenant_id !== $tenantId, 403);
        abort_if($user->canAccessPanel(), 422, 'Não é possível banir membros da equipe.');

        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);

        // Valida que o produto pertence ao tenant, se informado
        if (! empty($validated['product_id'])) {
            $product = Product::where('id', $validated['product_id'])->where('tenant_id', $tenantId)->firstOrFail();
        }

        CommunityBan::updateOrCreate(
            [
                'tenant_id'  => $tenantId,
                'user_id'    => $user->id,
                'product_id' => $validated['product_id'] ?? null,
            ],
            [
                'reason'    => $validated['reason'] ?? null,
                'banned_by' => auth()->id(),
            ]
        );

        return response()->json(['success' => true, 'banned' => true]);
    }

    public function unbanUser(Request $request, User $user): JsonResponse
    {
        $tenantId = $this->tenantId();
        abort_if($user->tenant_id !== $tenantId, 403);

        $validated = $request->validate([
            'product_id' => ['nullable', 'integer'],
        ]);

        CommunityBan::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('product_id', $validated['product_id'] ?? null)
            ->delete();

        return response()->json(['success' => true, 'banned' => false]);
    }

    public function communityBans(): JsonResponse
    {
        $tenantId = $this->tenantId();

        $bans = CommunityBan::where('tenant_id', $tenantId)
            ->with(['user:id,name,email', 'product:id,name', 'bannedByUser:id,name'])
            ->latest()
            ->get()
            ->map(fn ($b) => [
                'id'           => $b->id,
                'user'         => ['id' => $b->user?->id, 'name' => $b->user?->name, 'email' => $b->user?->email],
                'product'      => $b->product ? ['id' => $b->product->id, 'name' => $b->product->name] : null,
                'reason'       => $b->reason,
                'banned_by'    => $b->bannedByUser?->name,
                'created_at'   => $b->created_at->format('d/m/Y H:i'),
            ]);

        return response()->json(['bans' => $bans]);
    }

    public function updatePost(Request $request, MemberCommunityPost $post): JsonResponse
    {
        $this->authorizeProduct($post->page->product_id);
        $validated = $request->validate(['content' => ['required', 'string', 'max:5000']]);
        $post->update(['content' => $validated['content']]);
        return response()->json(['success' => true, 'content' => $post->content]);
    }

    public function toggleHidePost(MemberCommunityPost $post): JsonResponse
    {
        $this->authorizeProduct($post->page->product_id);
        $post->update(['is_hidden' => ! $post->is_hidden]);
        return response()->json(['success' => true, 'is_hidden' => $post->is_hidden]);
    }

    public function destroyPost(MemberCommunityPost $post): JsonResponse
    {
        $page = $post->page;
        $this->authorizeProduct($page->product_id);
        $post->delete();
        return response()->json(['success' => true]);
    }

    // ─── Eventos ───────────────────────────────────────────────────────────────
    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'            => ['required', 'string'],
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'starts_at'             => ['required', 'date'],
            'ends_at'               => ['nullable', 'date'],
            'location'              => ['nullable', 'string', 'max:500'],
            'link'                  => ['nullable', 'string', 'max:500'],
            'is_online'             => ['boolean'],
            'image'                 => ['nullable', 'file', 'image', 'max:5120'],
            'visibility'            => ['nullable', 'string', 'in:all,specific'],
            'visible_product_ids'   => ['nullable', 'array'],
            'visible_product_ids.*' => ['nullable', 'string'],
            'is_paid'               => ['boolean'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'payment_link'          => ['nullable', 'string', 'max:500'],
        ]);

        $this->authorizeProduct($validated['product_id']);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $storage = new StorageService($this->tenantId());
            $imagePath = $storage->putFile('community-events/' . $validated['product_id'], $request->file('image'));
        }

        $event = MemberCommunityEvent::create([
            ...$validated,
            'image'      => $imagePath,
            'created_by' => auth()->id(),
            'visibility' => $validated['visibility'] ?? 'all',
            'is_paid'    => $validated['is_paid'] ?? false,
        ]);

        return response()->json(['success' => true, 'event' => [
            'id'                  => $event->id,
            'title'               => $event->title,
            'starts_at'           => $event->starts_at->toIso8601String(),
            'ends_at'             => $event->ends_at?->toIso8601String(),
            'location'            => $event->location,
            'link'                => $event->link,
            'is_online'           => $event->is_online,
            'is_paid'             => $event->is_paid,
            'price'               => $event->price,
            'payment_link'        => $event->payment_link,
            'visibility'          => $event->visibility,
            'visible_product_ids' => $event->visible_product_ids ?? [],
            'image_url'           => $event->image_url,
            'product_id'          => $event->product_id,
            'rsvp_count'          => 0,
        ]]);
    }

    public function updateEvent(Request $request, MemberCommunityEvent $event): JsonResponse
    {
        $this->authorizeProduct($event->product_id);
        $validated = $request->validate([
            'title'                 => ['sometimes', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'starts_at'             => ['sometimes', 'date'],
            'ends_at'               => ['nullable', 'date'],
            'location'              => ['nullable', 'string', 'max:500'],
            'link'                  => ['nullable', 'string', 'max:500'],
            'is_online'             => ['boolean'],
            'visibility'            => ['nullable', 'string', 'in:all,specific'],
            'visible_product_ids'   => ['nullable', 'array'],
            'visible_product_ids.*' => ['nullable', 'string'],
            'is_paid'               => ['boolean'],
            'price'                 => ['nullable', 'numeric', 'min:0'],
            'payment_link'          => ['nullable', 'string', 'max:500'],
        ]);
        $event->update($validated);
        return response()->json(['success' => true]);
    }

    public function destroyEvent(MemberCommunityEvent $event): JsonResponse
    {
        $this->authorizeProduct($event->product_id);
        $event->delete();
        return response()->json(['success' => true]);
    }

    // ─── Grupos ────────────────────────────────────────────────────────────────
    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'  => ['required', 'string'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_private'  => ['boolean'],
            'image'       => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $this->authorizeProduct($validated['product_id']);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $storage = new StorageService($this->tenantId());
            $imagePath = $storage->putFile('community-groups/' . $validated['product_id'], $request->file('image'));
        }

        $max = MemberCommunityGroup::where('product_id', $validated['product_id'])->max('position') ?? 0;
        $group = MemberCommunityGroup::create([
            ...$validated,
            'image'    => $imagePath,
            'position' => $max + 1,
        ]);

        return response()->json(['success' => true, 'group' => [
            'id'           => $group->id,
            'name'         => $group->name,
            'description'  => $group->description,
            'image_url'    => $group->image_url,
            'is_private'   => $group->is_private,
            'product_id'   => $group->product_id,
            'members_count'=> 0,
        ]]);
    }

    public function updateGroup(Request $request, MemberCommunityGroup $group): JsonResponse
    {
        $this->authorizeProduct($group->product_id);
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_private'  => ['boolean'],
        ]);
        $group->update($validated);
        return response()->json(['success' => true]);
    }

    public function destroyGroup(MemberCommunityGroup $group): JsonResponse
    {
        $this->authorizeProduct($group->product_id);
        $group->delete();
        return response()->json(['success' => true]);
    }

    public function addGroupMember(Request $request, MemberCommunityGroup $group): JsonResponse
    {
        $this->authorizeProduct($group->product_id);
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        MemberCommunityGroupMember::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $validated['user_id']],
            ['role' => 'member']
        );
        return response()->json(['success' => true]);
    }

    public function removeGroupMember(MemberCommunityGroup $group, User $user): JsonResponse
    {
        $this->authorizeProduct($group->product_id);
        MemberCommunityGroupMember::where('group_id', $group->id)->where('user_id', $user->id)->delete();
        return response()->json(['success' => true]);
    }

    public function groupMembers(MemberCommunityGroup $group): JsonResponse
    {
        $this->authorizeProduct($group->product_id);
        $members = $group->members()->with('user:id,name,email')->get()
            ->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role]);
        return response()->json(['members' => $members]);
    }

    // ─── Stories ───────────────────────────────────────────────────────────────
    public function storeStory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'          => ['required', 'string'],
            'content'             => ['nullable', 'string', 'max:300'],
            'video_url'           => ['nullable', 'string', 'max:500'],
            'video'               => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:204800'], // 200MB
            'bg_color'            => ['nullable', 'string', 'max:20'],
            'duration_hours'      => ['nullable', 'integer', 'min:1', 'max:72'],
            'image'               => ['nullable', 'file', 'image', 'max:5120'],
            'visibility'          => ['nullable', 'string', 'in:all,specific'],
            'visible_product_ids' => ['nullable', 'array'],
            'visible_product_ids.*' => ['nullable', 'string'],
        ]);

        $this->authorizeProduct($validated['product_id']);

        if (empty($validated['content']) && empty($validated['video_url']) && ! $request->hasFile('image') && ! $request->hasFile('video')) {
            return response()->json(['message' => 'Adicione texto, imagem ou vídeo.'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $storage = new StorageService($this->tenantId());
            $imagePath = $storage->putFile('community-stories/' . $validated['product_id'], $request->file('image'));
        }

        $videoFilePath = null;
        if ($request->hasFile('video')) {
            $storage = new StorageService($this->tenantId());
            $videoFilePath = $storage->putFile('community-stories/' . $validated['product_id'], $request->file('video'));
        }

        $hours = (int) ($validated['duration_hours'] ?? 24);
        $story = MemberCommunityStory::create([
            'product_id'          => $validated['product_id'],
            'created_by'          => auth()->id(),
            'content'             => $validated['content'] ?? null,
            'image'               => $imagePath,
            'video_url'           => $validated['video_url'] ?? null,
            'video_file'          => $videoFilePath,
            'bg_color'            => $validated['bg_color'] ?? '#1e1e2e',
            'expires_at'          => now()->addHours($hours),
            'visibility'          => $validated['visibility'] ?? 'all',
            'visible_product_ids' => $validated['visible_product_ids'] ?? [],
        ]);

        return response()->json(['success' => true, 'story' => [
            'id'         => $story->id,
            'content'    => $story->content,
            'image_url'  => $story->image_url,
            'video_url'  => $story->video_url,
            'bg_color'   => $story->bg_color,
            'expires_at' => $story->expires_at->toIso8601String(),
            'is_expired' => false,
            'product_id' => $story->product_id,
            'created_at' => $story->created_at->format('d/m/Y H:i'),
        ]]);
    }

    public function destroyStory(MemberCommunityStory $story): JsonResponse
    {
        $this->authorizeProduct($story->product_id);
        $story->delete();
        return response()->json(['success' => true]);
    }

    // ─── Denúncias ─────────────────────────────────────────────────────────────
    public function resolveReport(CommunityReport $report): JsonResponse
    {
        $this->authorizeReportAccess($report);
        $report->update([
            'status'      => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        // Oculta o post automaticamente
        $report->post?->update(['is_hidden' => true]);
        return response()->json(['success' => true, 'status' => 'resolved', 'post_hidden' => true]);
    }

    public function dismissReport(CommunityReport $report): JsonResponse
    {
        $this->authorizeReportAccess($report);
        $report->update([
            'status'      => 'dismissed',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        return response()->json(['success' => true, 'status' => 'dismissed']);
    }

    // ─── Helper ────────────────────────────────────────────────────────────────
    private function authorizeProduct(string $productId): void
    {
        $product = Product::findOrFail($productId);
        if ((int) $product->tenant_id !== (int) $this->tenantId()) abort(403);
    }

    private function authorizeReportAccess(CommunityReport $report): void
    {
        $post = $report->post()->with('page.product')->first();
        if (! $post || (int) $post->page->product->tenant_id !== (int) $this->tenantId()) abort(403);
    }
}
