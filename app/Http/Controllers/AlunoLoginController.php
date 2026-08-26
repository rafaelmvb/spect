<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AlunoLoginController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectToComunidade(Auth::user());
        }

        return Inertia::render('AlunoLogin');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if ($user->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Sua conta está bloqueada. Entre em contato com o suporte.'])->onlyInput('email');
        }

        // Admins não são alunos — manda para o painel deles
        if ($user->canAccessPanel()) {
            return redirect('/dashboard');
        }

        return $this->redirectToComunidade($user);
    }

    private function redirectToComunidade($user): RedirectResponse
    {
        // Produto em que o aluno está matriculado
        $product = Product::where('type', Product::TYPE_AREA_MEMBROS)
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->first();

        if ($product) {
            session(['member_area_slug' => $product->checkout_slug]);
            return redirect('/m/comunidade');
        }

        // Sem matrícula: sistema single-tenant, usa qualquer produto ativo como contexto
        $anyProduct = Product::where('type', Product::TYPE_AREA_MEMBROS)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if (! $anyProduct) {
            Auth::logout();
            return redirect('/entrar')->withErrors(['email' => 'Nenhuma área de membros disponível no momento.']);
        }

        session(['member_area_slug' => $anyProduct->checkout_slug]);

        return redirect('/m/loja');
    }
}
