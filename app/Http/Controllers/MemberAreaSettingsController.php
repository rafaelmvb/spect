<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\StorageService;
use Illuminate\Http\Request;

class MemberAreaSettingsController extends Controller
{
    private const KEY = 'member_area_branding';

    public function data(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        $raw = Setting::get(self::KEY, null, $tenantId);
        $branding = is_string($raw) ? json_decode($raw, true) : ($raw ?? []);

        return response()->json([
            'branding' => (object) ($branding ?: []),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'area_name'           => ['nullable', 'string', 'max:120'],
            'logo_url'            => ['nullable', 'string', 'max:500'],
            'primary_color'       => ['nullable', 'string', 'max:30'],
            'on_primary_color'    => ['nullable', 'string', 'max:30'],
            'bg_color'            => ['nullable', 'string', 'max:30'],
            'surface_color'       => ['nullable', 'string', 'max:30'],
            'surface2_color'      => ['nullable', 'string', 'max:30'],
            'border_color'        => ['nullable', 'string', 'max:30'],
            'text_color'              => ['nullable', 'string', 'max:30'],
            'text2_color'             => ['nullable', 'string', 'max:30'],
            'text_inactive_color'     => ['nullable', 'string', 'max:30'],
            'danger_color'            => ['nullable', 'string', 'max:30'],
            // Componentes estruturais separáveis
            'sidebar_bg_color'        => ['nullable', 'string', 'max:30'],
            'header_bg_color'         => ['nullable', 'string', 'max:30'],
            'nav_bg_color'            => ['nullable', 'string', 'max:30'],
            'nav_active_bg_color'     => ['nullable', 'string', 'max:30'],
            'nav_active_text_color'   => ['nullable', 'string', 'max:30'],
            // Botões – hover
            'button_hover_bg_color'   => ['nullable', 'string', 'max:30'],
            'button_hover_text_color' => ['nullable', 'string', 'max:30'],
            // Navegador
            'favicon_url'             => ['nullable', 'string', 'max:500'],
            'theme_color'             => ['nullable', 'string', 'max:30'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $raw = Setting::get(self::KEY, null, $tenantId);
        $existing = is_string($raw) ? json_decode($raw, true) : ($raw ?? []);
        // null = campo vazio (nullable converte '' → null): limpa o valor salvo
        // valor presente: sobrescreve
        $branding = $existing ?: [];
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $branding[$key] = $value;
            } else {
                unset($branding[$key]);
            }
        }

        Setting::set(self::KEY, json_encode($branding), $tenantId);

        return response()->json(['success' => true, 'branding' => $branding]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $storage = app(StorageService::class);
        $path    = $storage->putFile('member-area-logos', $request->file('logo'));

        // Armazena path relativo para local storage — independe do APP_URL e funciona em qualquer domínio.
        // Para S3/R2 usa a URL do CDN diretamente.
        $url = $storage->isLocal()
            ? '/storage/' . ltrim($path, '/')
            : $storage->url($path);

        return response()->json(['url' => $url]);
    }
}
