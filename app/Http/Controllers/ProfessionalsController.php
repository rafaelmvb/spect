<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function format(Professional $p): array
    {
        return [
            'id'                  => $p->id,
            'name'                => $p->name,
            'email'               => $p->email,
            'phone'               => $p->phone,
            'avatar_url'          => $p->avatar_url,
            'bio'                 => $p->bio,
            'specialty'           => $p->specialty,
            'registration_type'   => $p->registration_type,
            'registration_number' => $p->registration_number,
            'price_per_hour'      => $p->price_per_hour,
            'is_active'           => $p->is_active,
            'status'              => $p->status ?? 'approved',
            'rejection_reason'    => $p->rejection_reason,
            'approved_at'         => $p->approved_at?->format('d/m/Y'),
            'rejected_at'         => $p->rejected_at?->format('d/m/Y'),
            'created_at'          => $p->created_at->format('d/m/Y'),
        ];
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $search = trim((string) ($request->query('q') ?? ''));

        $query = Professional::forTenant($tenantId)->orderBy('name');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('specialty', 'like', $like);
            });
        }

        $professionals = $query->paginate(20)->withQueryString()
            ->through(fn (Professional $p) => $this->format($p));

        $stats = [
            'total'   => Professional::forTenant($tenantId)->count(),
            'ativos'  => Professional::forTenant($tenantId)->where('is_active', true)->count(),
            'inativos'=> Professional::forTenant($tenantId)->where('is_active', false)->count(),
        ];

        return Inertia::render('Professionals/Index', [
            'professionals' => $professionals,
            'stats'         => $stats,
            'q'             => $search !== '' ? $search : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'bio'                 => ['nullable', 'string', 'max:3000'],
            'specialty'           => ['nullable', 'string', 'max:255'],
            'registration_type'   => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'price_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['nullable', 'boolean'],
            'avatar'              => ['nullable', 'file', 'image', 'max:3072'],
        ]);

        $tenantId = $this->tenantId();
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = (new StorageService($tenantId))
                ->putFile('professionals/' . ($tenantId ?? 'global'), $request->file('avatar'));
        }

        $professional = Professional::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'avatar'    => $avatarPath,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'professional' => $this->format($professional)]);
    }

    public function update(Request $request, Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'bio'                 => ['nullable', 'string', 'max:3000'],
            'specialty'           => ['nullable', 'string', 'max:255'],
            'registration_type'   => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'price_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['nullable', 'boolean'],
            'avatar'              => ['nullable', 'file', 'image', 'max:3072'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = (new StorageService($this->tenantId()))
                ->putFile('professionals/' . ($this->tenantId() ?? 'global'), $request->file('avatar'));
        } else {
            unset($validated['avatar']);
        }

        $professional->update($validated);

        return response()->json(['success' => true, 'professional' => $this->format($professional->fresh())]);
    }

    public function toggleActive(Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);
        $professional->update(['is_active' => ! $professional->is_active]);
        return response()->json(['success' => true, 'is_active' => $professional->is_active]);
    }

    public function destroy(Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);
        $professional->delete();
        return response()->json(['success' => true]);
    }

    public function approve(Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);

        $professional->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return response()->json(['success' => true, 'status' => 'approved']);
    }

    public function reject(Request $request, Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $professional->update([
            'status'           => 'rejected',
            'rejected_at'      => now(),
            'approved_at'      => null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'status' => 'rejected']);
    }

    public function createAccess(Request $request, Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);

        if ($professional->user_id) {
            $user = User::find($professional->user_id);
            return response()->json([
                'success'  => true,
                'existing' => true,
                'email'    => $user?->email,
                'message'  => 'Este profissional já possui acesso criado.',
            ]);
        }

        $email = $professional->email;
        abort_if(! $email, 422, 'O profissional precisa ter um e-mail cadastrado para criar o acesso.');

        $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        $rawPassword  = $request->filled('password') ? $request->input('password') : Str::random(12);

        $user = User::createWithRole([
            'name'      => $professional->name,
            'email'     => $email,
            'password'  => Hash::make($rawPassword),
        ], User::ROLE_PROFISSIONAL, $professional->tenant_id);

        $professional->update(['user_id' => $user->id]);

        return response()->json([
            'success'       => true,
            'existing'      => false,
            'email'         => $email,
            'temp_password' => $rawPassword,
            'message'       => 'Acesso criado com sucesso.',
        ]);
    }
}
