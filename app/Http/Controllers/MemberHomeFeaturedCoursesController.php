<?php

namespace App\Http\Controllers;

use App\Models\MemberHomeFeaturedCourse;
use App\Models\Product;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberHomeFeaturedCoursesController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    public function index(): Response
    {
        $tenantId = $this->tenantId();

        $featuredIds = MemberHomeFeaturedCourse::forTenant($tenantId)
            ->orderBy('position')
            ->pluck('product_id')
            ->all();

        $storage = new StorageService($tenantId);

        $allProducts = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('checkout_slug')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'image_url' => $p->image ? $storage->url($p->image) : null,
                'featured'  => in_array($p->id, $featuredIds),
            ])
            ->values()->all();

        return Inertia::render('MemberHomeFeatured/Index', [
            'products'     => $allProducts,
            'featured_ids' => $featuredIds,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $v = $request->validate([
            'product_ids'   => ['required', 'array'],
            'product_ids.*' => ['string'],
        ]);

        $tenantId = $this->tenantId();

        MemberHomeFeaturedCourse::forTenant($tenantId)->delete();

        foreach ($v['product_ids'] as $pos => $productId) {
            MemberHomeFeaturedCourse::create([
                'tenant_id'  => $tenantId,
                'product_id' => $productId,
                'position'   => $pos + 1,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
