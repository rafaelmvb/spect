<?php

namespace App\Http\Controllers;

use App\Events\MemberAreaLoaded;
use App\Models\MemberCommunityPage;
use App\Models\Product;
use App\Services\MemberAreaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaController extends Controller
{
    public function index(MemberAreaResolver $resolver): Response|RedirectResponse
    {
        $user = auth()->user();
        $produtos = $user->products()->orderBy('name')->get();
        event(new MemberAreaLoaded($user, $produtos));

        // Se houver apenas 1 produto area_membros, vai direto para a comunidade (ou área)
        $membrosProducts = $produtos->where('type', Product::TYPE_AREA_MEMBROS);
        if ($membrosProducts->count() === 1) {
            $product = $membrosProducts->first();
            $baseUrl = $resolver->baseUrlForProduct($product);
            $config = $product->member_area_config ?? [];

            if (! empty($config['community_enabled'])) {
                $tenantId = $product->tenant_id;
                $defaultPage = MemberCommunityPage::where('tenant_id', $tenantId)
                    ->where('is_default', true)->first()
                    ?? MemberCommunityPage::where('tenant_id', $tenantId)
                        ->orderBy('position')->first();
                if ($defaultPage) {
                    return redirect(rtrim($baseUrl, '/') . '/comunidade/' . $defaultPage->slug);
                }
            }
            // Sem comunidade: vai para a área diretamente
            return redirect(rtrim($baseUrl, '/'));
        }

        $produtosArray = $produtos->map(function (Product $p) use ($resolver) {
            $item = [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'image_url' => $p->image ? app(\App\Services\StorageService::class)->url($p->image) : null,
            ];
            if ($p->type === Product::TYPE_AREA_MEMBROS && $p->checkout_slug) {
                $item['member_area_url'] = $resolver->baseUrlForProduct($p);
                $item['checkout_slug'] = $p->checkout_slug;
            }
            return $item;
        })->values()->all();

        return Inertia::render('MemberArea/Index', ['produtos' => $produtosArray]);
    }
}
