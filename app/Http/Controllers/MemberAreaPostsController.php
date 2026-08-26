<?php

namespace App\Http\Controllers;

use App\Models\MemberAreaPost;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaPostsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function format(MemberAreaPost $p): array
    {
        return [
            'id'           => $p->id,
            'title'        => $p->title,
            'category'     => $p->category,
            'excerpt'      => $p->excerpt,
            'content'      => $p->content,
            'image_url'    => $p->image_url,
            'is_published' => $p->is_published,
            'published_at' => $p->published_at?->format('d/m/Y'),
            'position'     => $p->position,
            'created_at'   => $p->created_at->format('d/m/Y'),
        ];
    }

    public function index(): Response
    {
        $posts = MemberAreaPost::forTenant($this->tenantId())
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MemberAreaPost $p) => $this->format($p));

        return Inertia::render('MemberAreaPosts/Index', [
            'posts' => $posts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:100'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'content'      => ['nullable', 'string'],
            'image'        => ['nullable', 'file', 'image', 'max:5120'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $tenantId = $this->tenantId();
        if ($request->hasFile('image')) {
            $v['image'] = (new StorageService($tenantId))
                ->putFile('member-posts/' . ($tenantId ?? 'global'), $request->file('image'));
        }

        $max = MemberAreaPost::forTenant($tenantId)->max('position') ?? 0;
        $post = MemberAreaPost::create([
            ...$v,
            'tenant_id'    => $tenantId,
            'position'     => $max + 1,
            'published_at' => ($v['is_published'] ?? false) ? now() : null,
        ]);

        return response()->json(['success' => true, 'post' => $this->format($post)]);
    }

    public function update(Request $request, MemberAreaPost $memberAreaPost): JsonResponse
    {
        abort_if($memberAreaPost->tenant_id !== $this->tenantId(), 403);

        $v = $request->validate([
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:100'],
            'excerpt'      => ['nullable', 'string', 'max:500'],
            'content'      => ['nullable', 'string'],
            'image'        => ['nullable', 'file', 'image', 'max:5120'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $v['image'] = (new StorageService($this->tenantId()))
                ->putFile('member-posts/' . ($this->tenantId() ?? 'global'), $request->file('image'));
        } else {
            unset($v['image']);
        }

        if (isset($v['is_published']) && $v['is_published'] && ! $memberAreaPost->published_at) {
            $v['published_at'] = now();
        }

        $memberAreaPost->update($v);

        return response()->json(['success' => true, 'post' => $this->format($memberAreaPost->fresh())]);
    }

    public function destroy(MemberAreaPost $memberAreaPost): JsonResponse
    {
        abort_if($memberAreaPost->tenant_id !== $this->tenantId(), 403);
        $memberAreaPost->delete();
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $v = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $tenantId = $this->tenantId();
        foreach ($v['ids'] as $pos => $id) {
            MemberAreaPost::where('id', $id)->where('tenant_id', $tenantId)
                ->update(['position' => $pos + 1]);
        }
        return response()->json(['success' => true]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov'],
        ]);

        $tenantId = $this->tenantId();
        $storage  = new StorageService($tenantId);
        $path     = $storage->putFile('member-posts/' . ($tenantId ?? 'global'), $request->file('file'));
        $url      = $storage->url($path);

        return response()->json(['url' => $url]);
    }
}
