<?php

namespace App\Http\Controllers;

use App\Models\MusicCategory;
use App\Models\MusicTrack;
use App\Models\Product;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MusicController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    public function index(): Response
    {
        $tenantId = $this->tenantId();

        $categories = MusicCategory::where('tenant_id', $tenantId)
            ->orderBy('position')
            ->with(['tracks' => fn ($q) => $q->with('products:id,name')])
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'position' => $cat->position,
                'tracks' => $cat->tracks->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'file_path' => $t->file_path,
                    'file_url' => $t->file_url,
                    'is_active' => $t->is_active,
                    'available_to_all' => $t->available_to_all,
                    'position' => $t->position,
                    'product_ids' => $t->products->pluck('id')->toArray(),
                ])->values(),
            ])->values();

        $products = Product::where('tenant_id', $tenantId)
            ->where('type', 'area_membros')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Music/Index', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    // --- Categorias ---

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $max = MusicCategory::where('tenant_id', $this->tenantId())->max('position') ?? 0;
        $cat = MusicCategory::create([
            'tenant_id' => $this->tenantId(),
            'name' => $validated['name'],
            'position' => $max + 1,
        ]);
        return response()->json(['id' => $cat->id, 'name' => $cat->name, 'position' => $cat->position, 'tracks' => []]);
    }

    public function updateCategory(Request $request, MusicCategory $category): JsonResponse
    {
        $this->authorizeCategory($category);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $category->update($validated);
        return response()->json(['success' => true, 'name' => $category->name]);
    }

    public function destroyCategory(MusicCategory $category): JsonResponse
    {
        $this->authorizeCategory($category);
        $category->delete();
        return response()->json(['success' => true]);
    }

    // --- Tracks ---

    public function storeTrack(Request $request, MusicCategory $category): JsonResponse
    {
        $this->authorizeCategory($category);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audio' => ['required', 'file', 'mimes:mp3,mpeg,ogg,wav,aac,m4a', 'max:51200'],
            'is_active' => ['boolean'],
            'available_to_all' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string'],
        ]);

        $storage = new StorageService($this->tenantId());
        $path = $storage->putFile('music/' . $this->tenantId(), $request->file('audio'));

        $max = MusicTrack::where('music_category_id', $category->id)->max('position') ?? 0;
        $track = MusicTrack::create([
            'music_category_id' => $category->id,
            'tenant_id' => $this->tenantId(),
            'title' => $validated['title'],
            'file_path' => $path,
            'is_active' => $request->boolean('is_active', true),
            'available_to_all' => $request->boolean('available_to_all', true),
            'position' => $max + 1,
        ]);

        $productIds = $validated['product_ids'] ?? [];
        if (! empty($productIds)) {
            $track->products()->sync($productIds);
        }

        return response()->json([
            'id' => $track->id,
            'title' => $track->title,
            'file_path' => $track->file_path,
            'file_url' => $track->file_url,
            'is_active' => $track->is_active,
            'available_to_all' => $track->available_to_all,
            'position' => $track->position,
            'product_ids' => $productIds,
        ]);
    }

    public function updateTrack(Request $request, MusicTrack $track): JsonResponse
    {
        $this->authorizeTrack($track);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'available_to_all' => ['sometimes', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string'],
        ]);

        $track->update(array_filter($validated, fn ($v, $k) => $k !== 'product_ids', ARRAY_FILTER_USE_BOTH));

        if (array_key_exists('product_ids', $validated)) {
            $track->products()->sync($validated['product_ids'] ?? []);
        }

        return response()->json(['success' => true]);
    }

    public function destroyTrack(MusicTrack $track): JsonResponse
    {
        $this->authorizeTrack($track);
        $track->delete();
        return response()->json(['success' => true]);
    }

    private function authorizeCategory(MusicCategory $category): void
    {
        if ((int) $category->tenant_id !== (int) $this->tenantId()) {
            abort(403);
        }
    }

    private function authorizeTrack(MusicTrack $track): void
    {
        if ((int) $track->tenant_id !== (int) $this->tenantId()) {
            abort(403);
        }
    }
}
