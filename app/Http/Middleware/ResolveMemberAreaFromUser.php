<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveMemberAreaFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/entrar')->with('error', 'Faça login para acessar a área de membros.');
        }

        // Tenta produto em que o aluno está matriculado (cache de sessão primeiro)
        $slug    = session('member_area_slug');
        $product = null;

        if ($slug) {
            $product = Product::where('checkout_slug', $slug)
                ->where('type', Product::TYPE_AREA_MEMBROS)
                ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
                ->first();
        }

        if (! $product) {
            $product = Product::where('type', Product::TYPE_AREA_MEMBROS)
                ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
                ->orderByDesc('created_at')
                ->first();

            if ($product) {
                session(['member_area_slug' => $product->checkout_slug]);
            }
        }

        $isEnrolled = (bool) $product;

        // Sem matrícula: sistema single-tenant, usa qualquer produto ativo como contexto
        if (! $product) {
            $product = Product::where('type', Product::TYPE_AREA_MEMBROS)
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();

            if ($product) {
                session(['member_area_slug' => $product->checkout_slug]);
            }
        }

        if (! $product) {
            Auth::logout();
            return redirect('/entrar')->withErrors(['email' => 'Nenhuma área de membros disponível no momento.']);
        }

        $slug = $product->checkout_slug;
        $request->attributes->set('member_area_product', $product);
        $request->attributes->set('member_area_access_type', 'clean');
        $request->attributes->set('member_area_slug', $slug);
        $request->attributes->set('member_area_enrolled', $isEnrolled);

        // Garante a ordem correta dos parâmetros da rota para o binding posicional do Laravel:
        //   1) slug  — corresponde ao arg string $slug de todos os métodos da área
        //   2) params da URL (module, lesson, post…) — bindings do próprio model binding
        //   3) product — injected last; os métodos leem via $this->getProduct($request)
        // Sem essa reordenação, SubstituteBindings já adiciona {module}/{lesson} antes do
        // middleware rodar, e o slug vai para a posição errada.
        $route = $request->route();
        if ($route) {
            $existing = $route->parameters(); // url-bound params já resolvidos pelo SubstituteBindings
            $urlParams = array_diff_key($existing, ['slug' => null, 'product' => null]);
            $reordered = array_merge(['slug' => $slug], $urlParams, ['product' => $product]);
            try {
                $ref = new \ReflectionProperty($route, 'parameters');
                $ref->setAccessible(true);
                $ref->setValue($route, $reordered);
            } catch (\ReflectionException $e) {
                $route->setParameter('slug', $slug);
                $route->setParameter('product', $product);
            }
        }

        return $next($request);
    }
}
