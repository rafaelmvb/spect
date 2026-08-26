<?php

namespace App\Services;

use App\Models\MessagingCampaign;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Busca destinatários com telefone para campanhas WhatsApp/SMS.
 * Mesma lógica de segmentação do email, mas retorna { phone, user_id, name }.
 */
class MessagingRecipientsService
{
    public function getRecipients(?int $tenantId, array $filterConfig): Collection
    {
        $type = $filterConfig['type'] ?? 'all_customers';

        return match ($type) {
            'students_of_product'    => $this->fromProductUser($tenantId, $filterConfig['product_ids'] ?? []),
            'bought_product'         => $this->fromOrders($tenantId, $filterConfig['product_ids'] ?? []),
            'not_bought'             => $this->notBought($tenantId),
            'not_bought_product'     => $this->notBoughtProduct($tenantId, $filterConfig['product_ids'] ?? []),
            'never_accessed'         => $this->neverAccessed($tenantId, $filterConfig['product_ids'] ?? []),
            'inactive_days'          => $this->inactiveDays($tenantId, (int) ($filterConfig['inactive_days'] ?? 30), $filterConfig['product_ids'] ?? []),
            'all_registered'         => $this->allRegistered($tenantId),
            default                  => $this->allWithAccess($tenantId),
        };
    }

    private function allWithAccess(?int $tenantId): Collection
    {
        return $this->merge(
            $this->fromProductUser($tenantId, []),
            $this->fromOrders($tenantId, [])
        );
    }

    private function fromProductUser(?int $tenantId, array $productIds): Collection
    {
        $query = DB::table('product_user')
            ->join('users', 'users.id', '=', 'product_user.user_id')
            ->join('products', 'products.id', '=', 'product_user.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNotNull('users.id');

        if (! empty($productIds)) {
            $query->whereIn('product_user.product_id', $productIds);
        }

        $userIds = $query->pluck('users.id')->unique()->values()->all();

        // Busca telefone do último pedido de cada usuário
        return $this->phonesFromUserIds($tenantId, $userIds);
    }

    private function fromOrders(?int $tenantId, array $productIds): Collection
    {
        $query = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        return $this->buildFromOrderCollection($query->with('user:id,name')->get());
    }

    private function notBought(?int $tenantId): Collection
    {
        $buyerUserIds = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->pluck('user_id')->unique()->flip()->all();

        $userIds = User::where('role', 'aluno')
            ->whereNotIn('id', array_keys($buyerUserIds))
            ->pluck('id')->values()->all();

        return $this->phonesFromUserIds($tenantId, $userIds);
    }

    private function notBoughtProduct(?int $tenantId, array $productIds): Collection
    {
        if (empty($productIds)) return collect();

        $excludedUserIds = DB::table('product_user')
            ->whereIn('product_id', $productIds)
            ->pluck('user_id')->unique()->flip()->all();

        return $this->fromProductUser($tenantId, [])
            ->filter(fn ($r) => $r['user_id'] && ! isset($excludedUserIds[$r['user_id']]));
    }

    private function neverAccessed(?int $tenantId, array $productIds): Collection
    {
        $students = $this->fromProductUser($tenantId, $productIds);
        $userIds = $students->pluck('user_id')->filter()->values()->all();

        $accessed = DB::table('member_lesson_progress')
            ->whereIn('user_id', $userIds)->pluck('user_id')->unique()->flip()->all();

        return $students->filter(fn ($r) => $r['user_id'] && ! isset($accessed[$r['user_id']]));
    }

    private function inactiveDays(?int $tenantId, int $days, array $productIds): Collection
    {
        $cutoff = now()->subDays($days);
        $students = $this->fromProductUser($tenantId, $productIds);
        $userIds = $students->pluck('user_id')->filter()->values()->all();

        $lastAccess = DB::table('member_lesson_progress')
            ->whereIn('user_id', $userIds)
            ->select('user_id', DB::raw('MAX(updated_at) as last_access'))
            ->groupBy('user_id')->pluck('last_access', 'user_id');

        return $students->filter(function ($r) use ($lastAccess, $cutoff) {
            if (! $r['user_id']) return false;
            $last = $lastAccess[$r['user_id']] ?? null;
            return $last === null || $last < $cutoff;
        });
    }

    private function allRegistered(?int $tenantId): Collection
    {
        return $this->merge(
            $this->fromProductUser($tenantId, []),
            $this->fromOrders($tenantId, [])
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Busca o telefone mais recente de cada user_id via orders */
    private function phonesFromUserIds(?int $tenantId, array $userIds): Collection
    {
        if (empty($userIds)) return collect();

        $rows = Order::where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->with('user:id,name')->latest()->get();

        return $this->buildFromOrderCollection($rows);
    }

    private function buildFromOrderCollection(\Illuminate\Database\Eloquent\Collection $orders): Collection
    {
        $byPhone = [];
        foreach ($orders as $order) {
            $phone = preg_replace('/\D/', '', (string) $order->phone);
            if (strlen($phone) < 8 || isset($byPhone[$phone])) continue;
            $byPhone[$phone] = [
                'phone'   => $phone,
                'user_id' => $order->user_id,
                'name'    => $order->user?->name ?: 'Cliente',
            ];
        }
        return collect(array_values($byPhone));
    }

    private function merge(Collection ...$cols): Collection
    {
        $byPhone = [];
        foreach ($cols as $col) {
            foreach ($col as $r) {
                if (! isset($byPhone[$r['phone']])) $byPhone[$r['phone']] = $r;
            }
        }
        return collect(array_values($byPhone));
    }

    public function getNextBatch(MessagingCampaign $campaign, int $limit = 20): Collection
    {
        $all = $this->getRecipients($campaign->tenant_id, $campaign->filter_config ?? []);
        $sent = $campaign->sends()->pluck('phone')->map(fn ($p) => preg_replace('/\D/', '', $p))->flip();
        return $all->filter(fn ($r) => ! $sent->has($r['phone']))->take($limit)->values();
    }
}
