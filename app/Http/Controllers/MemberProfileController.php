<?php

namespace App\Http\Controllers;

use App\Models\MemberCommunityGroup;
use App\Models\MemberFriendship;
use App\Models\MemberGroupJoinRequest;
use App\Models\MemberPlaylist;
use App\Models\MusicTrack;
use App\Models\Product;
use App\Models\User;
use App\Services\MemberAreaResolver;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    // ─── Meu perfil / perfil de outro aluno ──────────────────────────────────
    public function show(Request $request, ?string $slug = null): Response
    {
        /** @var Product $product */
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product) abort(404);

        $me = $request->user();

        // userId pode vir da rota {userId} ou não existir (perfil próprio)
        $userId = $request->route('userId');
        $target = $userId ? User::findOrFail((int) $userId) : $me;
        $isOwn = $target->id === $me->id;

        $storage = new StorageService($product->tenant_id);
        $avatarUrl = $target->avatar ? $storage->url($target->avatar) : null;

        // Status de amizade
        $friendship = MemberFriendship::statusBetween($me->id, $target->id);
        $friendshipStatus = $friendship?->status ?? null;
        $friendshipInitiatedByMe = $friendship?->requester_id === $me->id;
        $isFriend = $friendshipStatus === 'accepted';

        // Dados privados só visíveis para o próprio ou para amigos aceitos
        $canSeePrivate = $isOwn || $isFriend;

        // Amigos do target (só visível se for amigo ou próprio perfil)
        $friends = $canSeePrivate
            ? MemberFriendship::where('status', 'accepted')
                ->where(fn ($q) => $q->where('requester_id', $target->id)->orWhere('receiver_id', $target->id))
                ->with(['requester:id,name,avatar', 'receiver:id,name,avatar'])
                ->limit(20)->get()
                ->map(fn ($f) => [
                    'id'   => $f->requester_id === $target->id ? $f->receiver_id : $f->requester_id,
                    'name' => $f->requester_id === $target->id ? $f->receiver->name : $f->requester->name,
                ])
            : collect();

        // Pedidos de amizade pendentes (só meu perfil)
        $pendingRequests = $isOwn
            ? MemberFriendship::where('receiver_id', $me->id)->where('status', 'pending')
                ->with('requester:id,name,avatar')->get()
                ->map(fn ($f) => ['id' => $f->id, 'requester_id' => $f->requester_id, 'name' => $f->requester->name])
            : collect();

        // Playlists (só visível se for amigo ou próprio perfil)
        $playlists = $canSeePrivate
            ? MemberPlaylist::where('user_id', $target->id)
                ->where('product_id', $product->id)
                ->with(['tracks:id,title,file_path'])
                ->get()
                ->map(fn ($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'tracks'=> $p->tracks->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])->values(),
                ])
            : collect();

        // Músicas disponíveis para o produto (para adicionar à playlist)
        $availableTracks = $isOwn ? MusicTrack::where(function ($q) use ($product) {
            $q->where('available_to_all', true)
              ->orWhereHas('products', fn ($q2) => $q2->where('products.id', $product->id));
        })->where('tenant_id', $product->tenant_id)->where('is_active', true)
          ->get(['id', 'title'])->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])
        : collect();

        // Grupos (só visível se for amigo ou próprio perfil)
        $groups = $canSeePrivate
            ? MemberCommunityGroup::where('product_id', $product->id)->get()
                ->map(fn ($g) => [
                    'id'         => $g->id,
                    'name'       => $g->name,
                    'is_private' => $g->is_private,
                    'image_url'  => $g->image_url,
                    'join_status'=> MemberGroupJoinRequest::where('group_id', $g->id)
                        ->where('user_id', $target->id)->value('status'),
                    'is_member'  => \App\Models\MemberCommunityGroupMember::where('group_id', $g->id)
                        ->where('user_id', $target->id)->exists(),
                ])
            : collect();

        return Inertia::render('MemberAreaApp/Profile', [
            'product'           => ['id' => $product->id, 'name' => $product->name],
            'config'            => $product->member_area_config,
            'slug'              => $slug,
            'profile_user'      => [
                'id'         => $target->id,
                'name'       => $target->name,
                'email'      => $isOwn ? $target->email : null,
                'bio'        => $target->bio,
                'location'   => $target->location,
                'gender'     => $target->gender,
                'birth_date' => $target->birth_date ? \Carbon\Carbon::parse($target->birth_date)->format('Y-m-d') : null,
                'age'        => $target->birth_date ? abs((int) \Carbon\Carbon::parse($target->birth_date)->diffInYears(now())) : null,
                'avatar_url' => $avatarUrl,
                'joined_at'  => $target->created_at->format('M Y'),
            ],
            'is_own'            => $isOwn,
            'can_see_private'   => $canSeePrivate,
            'friendship_status' => $friendshipStatus,
            'friendship_initiated_by_me' => $friendshipInitiatedByMe,
            'friends'           => $friends->values(),
            'pending_requests'  => $pendingRequests->values(),
            'playlists'         => $playlists->values(),
            'available_tracks'  => $availableTracks->values(),
            'groups'            => $groups->values(),
            ...$this->pushProps($product),
        ] + $this->gamificationProps($product, $me));
    }

    // ─── Atualizar perfil (bio + avatar) ─────────────────────────────────────
    public function updateProfile(Request $request, ?string $slug = null): JsonResponse
    {
        /** @var Product $product */
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product) abort(404);
        $me = $request->user();
        $validated = $request->validate([
            'bio'        => ['nullable', 'string', 'max:500'],
            'location'   => ['nullable', 'string', 'max:100'],
            'gender'     => ['nullable', 'string', 'in:masculino,feminino,outro,prefiro_nao_dizer'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'avatar'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,bmp', 'max:5120'],
        ]);

        $storage = new StorageService($product->tenant_id);
        if ($request->hasFile('avatar')) {
            $path = $storage->putFile('avatars/' . $me->id, $request->file('avatar'));
            $me->avatar = $path; // raw path; StorageService::url() adiciona /storage/ ao renderizar
        }
        foreach (['bio', 'location', 'gender', 'birth_date'] as $field) {
            if (array_key_exists($field, $validated)) {
                $me->$field = $validated[$field];
            }
        }
        $me->save();

        $avatarUrl = null;
        if ($me->avatar) {
            if ($storage->isLocal()) {
                $avatarUrl = $request->getSchemeAndHttpHost() . '/storage/' . ltrim($me->avatar, '/');
            } else {
                $avatarUrl = $storage->url($me->avatar);
            }
        }

        return response()->json(['success' => true,
            'avatar_url' => $avatarUrl,
            'bio' => $me->bio,
        ]);
    }

    // ─── Amizades ─────────────────────────────────────────────────────────────
    public function sendFriendRequest(Request $request, ?string $slug = null, int $userId = 0): JsonResponse
    {
        $me = $request->user();
        if ($me->id === $userId) return response()->json(['error' => 'Não pode adicionar a si mesmo.'], 422);

        MemberFriendship::firstOrCreate(
            ['requester_id' => $me->id, 'receiver_id' => $userId],
            ['status' => 'pending']
        );

        // Busca o produto via checkout_slug (mesmo padrão do middleware)
        $product = Product::where('checkout_slug', $slug)->orWhere('slug', $slug)->first();
        $this->notifyMember($userId, $product, [
            'type'    => 'friend_request',
            'title'   => 'Pedido de amizade',
            'message' => "{$me->name} enviou um pedido de amizade.",
            'url'     => "/m/{$slug}/perfil/{$me->id}",
        ]);

        return response()->json(['success' => true, 'status' => 'pending']);
    }

    public function acceptFriendRequest(Request $request, ?string $slug = null, int $userId = 0): JsonResponse
    {
        $me = $request->user();
        $f = MemberFriendship::where('requester_id', $userId)->where('receiver_id', $me->id)
            ->where('status', 'pending')->firstOrFail();
        $f->update(['status' => 'accepted']);

        $product = Product::where('checkout_slug', $slug)->orWhere('slug', $slug)->first();
        $this->notifyMember($userId, $product, [
            'type'    => 'friend_accepted',
            'title'   => 'Pedido aceito!',
            'message' => "{$me->name} aceitou seu pedido de amizade.",
            'url'     => "/m/{$slug}/perfil/{$me->id}",
        ]);

        return response()->json(['success' => true, 'status' => 'accepted']);
    }

    public function declineOrUnfriend(Request $request, ?string $slug = null, int $userId = 0): JsonResponse
    {
        $me = $request->user();
        MemberFriendship::where(fn ($q) => $q->where('requester_id', $me->id)->where('receiver_id', $userId))
            ->orWhere(fn ($q) => $q->where('requester_id', $userId)->where('receiver_id', $me->id))
            ->delete();
        return response()->json(['success' => true, 'status' => null]);
    }

    // ─── Playlists ────────────────────────────────────────────────────────────
    public function storePlaylist(Request $request, ?string $slug = null): JsonResponse
    {
        /** @var Product $product */
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product) abort(404);
        $me = $request->user();
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $playlist = MemberPlaylist::create([
            'user_id'    => $me->id,
            'product_id' => $product->id,
            'name'       => $validated['name'],
        ]);
        return response()->json(['id' => $playlist->id, 'name' => $playlist->name, 'tracks' => []]);
    }

    public function destroyPlaylist(Request $request, ?string $slug = null, MemberPlaylist $playlist = null): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) abort(403);
        $playlist->delete();
        return response()->json(['success' => true]);
    }

    public function addTrackToPlaylist(Request $request, ?string $slug = null, MemberPlaylist $playlist = null, int $trackId = 0): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) abort(403);
        $max = $playlist->tracks()->max('member_playlist_tracks.position') ?? 0;
        $playlist->tracks()->syncWithoutDetaching([$trackId => ['position' => $max + 1]]);
        return response()->json(['success' => true]);
    }

    public function removeTrackFromPlaylist(Request $request, ?string $slug = null, MemberPlaylist $playlist = null, int $trackId = 0): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) abort(403);
        $playlist->tracks()->detach($trackId);
        return response()->json(['success' => true]);
    }

    // ─── Grupos ───────────────────────────────────────────────────────────────
    public function requestGroupJoin(Request $request, ?string $slug = null, int $groupId = 0): JsonResponse
    {
        $me = $request->user();
        $group = MemberCommunityGroup::findOrFail($groupId);

        if (! $group->is_private) {
            // Grupo público: entra direto
            \App\Models\MemberCommunityGroupMember::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $me->id],
                ['role' => 'member']
            );
            return response()->json(['success' => true, 'status' => 'member']);
        }

        MemberGroupJoinRequest::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $me->id],
            ['status' => 'pending']
        );
        return response()->json(['success' => true, 'status' => 'pending']);
    }

    private function notifyMember(int $userId, ?Product $product, array $data): void
    {
        if (! $product) {
            \Illuminate\Support\Facades\Log::warning('notifyMember: produto nulo para user '.$userId);
            return;
        }
        try {
            \App\Models\MemberNotification::create([
                'user_id'    => $userId,
                'product_id' => $product->id,
                'type'       => $data['type'],
                'title'      => $data['title'],
                'body'       => $data['message'] ?? $data['body'] ?? '',
                'url'        => $data['url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('notifyMember falhou: '.$e->getMessage());
        }
    }

    private function pushProps(Product $product): array { return []; }
    private function gamificationProps(Product $product, User $user): array { return []; }
}
