<?php

namespace App\Http\Controllers;

use App\Models\MemberActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaLoginController extends Controller
{
    /**
     * Best-effort activity log for proof/compliance. Must never block login.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logMemberActivity(Request $request, Product $product, User $user, string $event, array $metadata = []): void
    {
        try {
            MemberActivityLog::create([
                'tenant_id' => $product->tenant_id ?? $user->tenant_id,
                'user_id' => $user->id,
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

    public function showLoginForm(Request $request, string $slug): Response|RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        if (Auth::check() && $product->hasMemberAreaAccess(Auth::user())) {
            return redirect()->route('member-area-app.show', ['slug' => $slug]);
        }
        $config = $product->member_area_config;
        $loginConfig = $config['login'] ?? [];

        // Branding global salvo em Configurações > Área do Aluno (fallback quando o produto não tem logo/cor própria)
        $rawGlobal = Setting::get('member_area_branding', null, $product->tenant_id);
        $global = is_string($rawGlobal) ? (json_decode($rawGlobal, true) ?? []) : ($rawGlobal ?? []);

        return Inertia::render('MemberAreaApp/Login', [
            'slug' => $slug,
            'product' => [
                'name'  => $global['area_name'] ?? $product->name,
                'logo_light' => $loginConfig['logo'] ?? ($config['logos']['logo_light'] ?? $global['logo_url'] ?? ''),
                'logo_dark'  => $config['logos']['logo_dark'] ?? $global['logo_url'] ?? '',
                'title'    => $loginConfig['title'] ?? ($global['area_name'] ?? 'Área de Membros'),
                'subtitle' => $loginConfig['subtitle'] ?? 'Entre com seu e-mail e senha',
                'background_image' => $loginConfig['background_image'] ?? '',
                'background_color' => $loginConfig['background_color'] ?? $global['bg_color'] ?? '#18181b',
                'primary_color'    => $loginConfig['primary_color'] ?? $global['primary_color'] ?? '#0ea5e9',
                'login_without_password' => false,
                'login_without_password_url' => null,
            ],
        ]);
    }

    public function login(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (Auth::user()->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Sua conta está bloqueada. Entre em contato com o suporte.'])->onlyInput('email');
        }
        if (! $product->hasMemberAreaAccess(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Você não tem acesso a esta área.'])->onlyInput('email');
        }
        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function loginWithoutPassword(Request $request, string $slug): RedirectResponse
    {
        // Login sem senha desabilitado — requer email + senha.
        return back()->withErrors(['email' => 'Esta forma de acesso não está disponível. Use seu e-mail e senha.'])->onlyInput('email');
    }

    public function magicAccess(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->route('member-area.login', ['slug' => $slug])->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();

        $this->logMemberActivity($request, $product, $user, 'member_area.magic_access', [
            'mode' => 'path',
            'path' => '/' . ltrim($request->path(), '/'),
        ]);

        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function magicAccessHost(Request $request): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->to('/login')->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();

        $this->logMemberActivity($request, $product, $user, 'member_area.magic_access', [
            'mode' => 'host',
            'path' => '/' . ltrim($request->path(), '/'),
        ]);

        return redirect()->to('/');
    }
}
