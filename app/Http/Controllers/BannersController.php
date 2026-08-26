<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BannersController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function format(Banner $b): array
    {
        return [
            'id'          => $b->id,
            'title'       => $b->title,
            'subtitle'    => $b->subtitle,
            'image_url'   => $b->image_url,
            'link'        => $b->link,
            'button_text' => $b->button_text,
            'target'      => $b->target,
            'is_active'   => $b->is_active,
            'position'    => $b->position,
            'created_at'  => $b->created_at->format('d/m/Y'),
        ];
    }

    public function index(): Response
    {
        $banners = Banner::forTenant($this->tenantId())
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Banner $b) => $this->format($b));

        return Inertia::render('Banners/Index', [
            'banners' => $banners,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'subtitle'    => ['nullable', 'string', 'max:500'],
            'image'       => ['nullable', 'file', 'image', 'max:5120'],
            'link'        => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'target'      => ['nullable', 'string', 'in:member_area,dashboard,both'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $tenantId = $this->tenantId();
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = (new StorageService($tenantId))
                ->putFile('banners/' . ($tenantId ?? 'global'), $request->file('image'));
        }

        $max = Banner::forTenant($tenantId)->max('position') ?? 0;
        $banner = Banner::create([
            'tenant_id'   => $tenantId,
            'title'       => $validated['title'] ?? null,
            'subtitle'    => $validated['subtitle'] ?? null,
            'image'       => $imagePath,
            'link'        => $validated['link'] ?? null,
            'button_text' => $validated['button_text'] ?? null,
            'target'      => $validated['target'] ?? 'member_area',
            'is_active'   => $validated['is_active'] ?? true,
            'position'    => $max + 1,
        ]);

        return response()->json(['success' => true, 'banner' => $this->format($banner)]);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        abort_if($banner->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'subtitle'    => ['nullable', 'string', 'max:500'],
            'image'       => ['nullable', 'file', 'image', 'max:5120'],
            'link'        => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'target'      => ['nullable', 'string', 'in:member_area,dashboard,both'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = (new StorageService($this->tenantId()))
                ->putFile('banners/' . ($this->tenantId() ?? 'global'), $request->file('image'));
        } else {
            unset($validated['image']);
        }

        $banner->update($validated);

        return response()->json(['success' => true, 'banner' => $this->format($banner->fresh())]);
    }

    public function toggleActive(Banner $banner): JsonResponse
    {
        abort_if($banner->tenant_id !== $this->tenantId(), 403);
        $banner->update(['is_active' => ! $banner->is_active]);
        return response()->json(['success' => true, 'is_active' => $banner->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $tenantId = $this->tenantId();
        foreach ($validated['ids'] as $pos => $id) {
            Banner::where('id', $id)->where('tenant_id', $tenantId)
                ->update(['position' => $pos + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        abort_if($banner->tenant_id !== $this->tenantId(), 403);
        $banner->delete();
        return response()->json(['success' => true]);
    }
}
