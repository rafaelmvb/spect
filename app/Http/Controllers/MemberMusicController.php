<?php

namespace App\Http\Controllers;

use App\Models\MusicCategory;
use App\Models\MusicTrack;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberMusicController extends Controller
{
    public function index(Request $request, string $slug, Product $product): Response
    {
        $user = $request->user();

        // Categorias com músicas disponíveis para este produto
        $categories = MusicCategory::where('tenant_id', $product->tenant_id)
            ->orderBy('position')
            ->with(['tracks' => function ($q) use ($product) {
                $q->where('is_active', true)
                  ->where(function ($q2) use ($product) {
                      $q2->where('available_to_all', true)
                         ->orWhereHas('products', fn ($q3) => $q3->where('products.id', $product->id));
                  })
                  ->orderBy('position');
            }])
            ->get()
            ->filter(fn ($cat) => $cat->tracks->isNotEmpty())
            ->map(fn ($cat) => [
                'id'     => $cat->id,
                'name'   => $cat->name,
                'tracks' => $cat->tracks->map(fn ($t) => [
                    'id'        => $t->id,
                    'title'     => $t->title,
                    'file_url'  => $t->file_url,
                ])->values(),
            ])->values();

        return Inertia::render('MemberAreaApp/Musicas', [
            'product'    => ['id' => $product->id, 'name' => $product->name],
            'config'     => $product->member_area_config,
            'slug'       => $slug,
            'categories' => $categories,
        ]);
    }
}
