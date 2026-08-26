<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\MemberLessonProgress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmailCampaignRecipientsService
{
    /**
     * Tipos de filtro:
     *   all_customers          → todos que TÊM ACESSO a qualquer produto (product_user) OU compraram
     *   students_of_product    → alunos com acesso a produto(s) específico(s) via product_user
     *   bought_product         → fizeram PEDIDO PAGO de produto(s) específico(s) (orders)
     *   not_bought             → cadastrados mas SEM nenhum pedido pago
     *   not_bought_product     → têm acesso a outros produtos mas NÃO ao produto indicado
     *   never_accessed         → têm acesso mas nunca abriram uma aula (sem lesson_progress)
     *   inactive_days          → não acessam há N dias
     *   course_progress_below  → progresso < X%
     *   course_progress_above  → progresso >= X%
     *   active_subscription    → assinantes ativos
     *   cancelled_subscription → assinantes cancelados
     *   purchased_date_range   → compraram entre datas
     *   all_registered         → todos os usuários cadastrados
     */
    public function getRecipients(?int $tenantId, array $filterConfig): Collection
    {
        $type = $filterConfig['type'] ?? 'all_customers';

        return match ($type) {
            'students_of_product'    => $this->getStudentsOfProduct($tenantId, $filterConfig['product_ids'] ?? []),
            'bought_product'         => $this->getBoughtFromOrders($tenantId, $filterConfig['product_ids'] ?? []),
            'not_bought'             => $this->getNotBoughtRecipients($tenantId),
            'not_bought_product'     => $this->getNotBoughtProductRecipients($tenantId, $filterConfig['product_ids'] ?? []),
            'never_accessed'         => $this->getNeverAccessedRecipients($tenantId, $filterConfig['product_ids'] ?? []),
            'inactive_days'          => $this->getInactiveDaysRecipients($tenantId, (int) ($filterConfig['inactive_days'] ?? 30), $filterConfig['product_ids'] ?? []),
            'course_progress_below'  => $this->getCourseProgressRecipients($tenantId, $filterConfig['product_ids'] ?? [], (float) ($filterConfig['max_progress'] ?? 50), 'below'),
            'course_progress_above'  => $this->getCourseProgressRecipients($tenantId, $filterConfig['product_ids'] ?? [], (float) ($filterConfig['min_progress'] ?? 80), 'above'),
            'active_subscription'    => $this->getSubscriptionRecipients($tenantId, 'active'),
            'cancelled_subscription' => $this->getSubscriptionRecipients($tenantId, 'cancelled'),
            'purchased_date_range'   => $this->getPurchasedDateRangeRecipients($tenantId, $filterConfig['date_from'] ?? null, $filterConfig['date_to'] ?? null, $filterConfig['product_ids'] ?? []),
            'all_registered'         => $this->getAllRegisteredRecipients($tenantId),
            default                  => $this->getAllWithAccessRecipients($tenantId), // all_customers
        };
    }

    // ─── Todos com acesso (product_user) OU que compraram ─────────────────────
    private function getAllWithAccessRecipients(?int $tenantId): Collection
    {
        // Via product_user (inclui manuais + compradores)
        $fromAccess = $this->getStudentsOfProduct($tenantId, []);
        // Via orders (podem ter comprado sem user cadastrado)
        $fromOrders = $this->getBoughtFromOrders($tenantId, []);

        return $this->mergeCollections($fromAccess, $fromOrders);
    }

    // ─── Alunos com acesso a produto(s) via product_user ──────────────────────
    private function getStudentsOfProduct(?int $tenantId, array $productIds = []): Collection
    {
        $query = DB::table('product_user')
            ->join('users', 'users.id', '=', 'product_user.user_id')
            ->join('products', 'products.id', '=', 'product_user.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '');

        if (! empty($productIds)) {
            $query->whereIn('product_user.product_id', $productIds);
        }

        $rows = $query->select('users.id as user_id', 'users.name', 'users.email')->get();

        return $this->buildFromRows($rows);
    }

    // ─── Compradores via orders (pedidos pagos) ────────────────────────────────
    private function getBoughtFromOrders(?int $tenantId, array $productIds = []): Collection
    {
        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        return $this->buildFromOrders($query->with('user:id,name,email')->get());
    }

    // ─── Cadastraram mas NÃO têm nenhum pedido pago ───────────────────────────
    private function getNotBoughtRecipients(?int $tenantId): Collection
    {
        $buyerEmails = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()->flip()->all();

        $users = User::where('role', 'aluno')
            ->whereNotNull('email')->where('email', '!=', '')->get(['id', 'name', 'email']);

        $byEmail = [];
        foreach ($users as $user) {
            $email = strtolower(trim($user->email));
            if (! str_contains($email, '@') || isset($byEmail[$email]) || isset($buyerEmails[$email])) continue;
            $byEmail[$email] = ['email' => $email, 'user_id' => $user->id, 'name' => $user->name ?: $email];
        }

        return collect(array_values($byEmail));
    }

    // ─── Têm acesso a outros produtos mas NÃO ao produto indicado ─────────────
    private function getNotBoughtProductRecipients(?int $tenantId, array $productIds): Collection
    {
        if (empty($productIds)) return collect();

        $excludedUserIds = DB::table('product_user')
            ->whereIn('product_id', $productIds)
            ->pluck('user_id')->unique()->flip()->all();

        return $this->getStudentsOfProduct($tenantId, [])
            ->filter(fn ($r) => $r['user_id'] && ! isset($excludedUserIds[$r['user_id']]));
    }

    // ─── Têm acesso mas nunca abriram aula ────────────────────────────────────
    private function getNeverAccessedRecipients(?int $tenantId, array $productIds = []): Collection
    {
        $students = $this->getStudentsOfProduct($tenantId, $productIds);
        $userIds = $students->pluck('user_id')->filter()->unique()->values()->all();

        $accessedUserIds = MemberLessonProgress::whereIn('user_id', $userIds)
            ->pluck('user_id')->unique()->flip()->all();

        return $students->filter(fn ($r) => $r['user_id'] && ! isset($accessedUserIds[$r['user_id']]));
    }

    // ─── Não acessam há N dias ─────────────────────────────────────────────────
    private function getInactiveDaysRecipients(?int $tenantId, int $days, array $productIds = []): Collection
    {
        $cutoff = now()->subDays($days);
        $students = $this->getStudentsOfProduct($tenantId, $productIds);
        $userIds = $students->pluck('user_id')->filter()->unique()->values()->all();

        $lastAccessByUser = MemberLessonProgress::whereIn('user_id', $userIds)
            ->select('user_id', DB::raw('MAX(updated_at) as last_access'))
            ->groupBy('user_id')
            ->pluck('last_access', 'user_id');

        return $students->filter(function ($r) use ($lastAccessByUser, $cutoff) {
            if (! $r['user_id']) return false;
            $last = $lastAccessByUser[$r['user_id']] ?? null;
            return $last === null || $last < $cutoff;
        });
    }

    // ─── Progresso abaixo/acima de X% ─────────────────────────────────────────
    private function getCourseProgressRecipients(?int $tenantId, array $productIds, float $threshold, string $direction): Collection
    {
        $students = $this->getStudentsOfProduct($tenantId, $productIds);
        $userIds = $students->pluck('user_id')->filter()->unique()->values()->all();

        $totalLessonsByProduct = DB::table('member_lessons')
            ->when(! empty($productIds), fn ($q) => $q->whereIn('product_id', $productIds))
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->groupBy('product_id')->pluck('total', 'product_id');

        $progressData = DB::table('member_lesson_progress')
            ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
            ->whereIn('member_lesson_progress.user_id', $userIds)
            ->when(! empty($productIds), fn ($q) => $q->whereIn('member_lessons.product_id', $productIds))
            ->whereNotNull('member_lesson_progress.completed_at')
            ->select('member_lesson_progress.user_id', 'member_lessons.product_id', DB::raw('COUNT(*) as completed'))
            ->groupBy('member_lesson_progress.user_id', 'member_lessons.product_id')
            ->get()->keyBy(fn ($r) => $r->user_id . '_' . $r->product_id);

        // Produto principal de cada aluno (via product_user)
        $userProductMap = DB::table('product_user')
            ->whereIn('user_id', $userIds)
            ->when(! empty($productIds), fn ($q) => $q->whereIn('product_id', $productIds))
            ->pluck('product_id', 'user_id');

        return $students->filter(function ($r) use ($userProductMap, $totalLessonsByProduct, $progressData, $threshold, $direction) {
            if (! $r['user_id']) return false;
            $productId = $userProductMap[$r['user_id']] ?? null;
            if (! $productId) return false;
            $total = $totalLessonsByProduct[$productId] ?? 0;
            if ($total === 0) return false;
            $key = $r['user_id'] . '_' . $productId;
            $completed = $progressData[$key]->completed ?? 0;
            $pct = ($completed / $total) * 100;
            return $direction === 'below' ? $pct < $threshold : $pct >= $threshold;
        });
    }

    // ─── Assinantes ────────────────────────────────────────────────────────────
    private function getSubscriptionRecipients(?int $tenantId, string $status): Collection
    {
        $rows = DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->where('subscriptions.status', $status)
            ->whereNotNull('users.email')->where('users.email', '!=', '')
            ->select('users.id as user_id', 'users.name', 'users.email')->get();

        return $this->buildFromRows($rows);
    }

    // ─── Compraram em intervalo de datas ──────────────────────────────────────
    private function getPurchasedDateRangeRecipients(?int $tenantId, ?string $dateFrom, ?string $dateTo, array $productIds = []): Collection
    {
        $query = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('email')->where('email', '!=', '');

        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);
        if (! empty($productIds)) $query->whereIn('product_id', $productIds);

        return $this->buildFromOrders($query->with('user:id,name,email')->get());
    }

    // ─── Todos os usuários cadastrados ────────────────────────────────────────
    private function getAllRegisteredRecipients(?int $tenantId): Collection
    {
        $fromAccess = $this->getStudentsOfProduct($tenantId, []);
        $fromOrders = $this->getBoughtFromOrders($tenantId, []);

        $users = User::where('role', 'aluno')
            ->whereNotNull('email')->where('email', '!=', '')->get(['id', 'name', 'email']);

        $extra = collect($users->map(fn ($u) => [
            'email' => strtolower(trim($u->email)),
            'user_id' => $u->id,
            'name' => $u->name ?: $u->email,
        ])->filter(fn ($r) => str_contains($r['email'], '@'))->values()->all());

        return $this->mergeCollections($fromAccess, $fromOrders, $extra);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    private function buildFromRows(\Illuminate\Support\Collection $rows): Collection
    {
        $byEmail = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row->email ?? '')));
            if ($email === '' || ! str_contains($email, '@') || isset($byEmail[$email])) continue;
            $byEmail[$email] = ['email' => $email, 'user_id' => $row->user_id ?? null, 'name' => ($row->name ?? '') ?: $email];
        }
        return collect(array_values($byEmail));
    }

    private function buildFromOrders(\Illuminate\Database\Eloquent\Collection $orders): Collection
    {
        $byEmail = [];
        foreach ($orders as $order) {
            $email = strtolower(trim((string) $order->email));
            if ($email === '' || ! str_contains($email, '@') || isset($byEmail[$email])) continue;
            $byEmail[$email] = ['email' => $email, 'user_id' => $order->user_id, 'name' => ($order->user?->name ?? '') ?: $email];
        }
        return collect(array_values($byEmail));
    }

    private function mergeCollections(Collection ...$collections): Collection
    {
        $byEmail = [];
        foreach ($collections as $col) {
            foreach ($col as $r) {
                $email = strtolower(trim($r['email']));
                if (! isset($byEmail[$email])) $byEmail[$email] = $r;
            }
        }
        return collect(array_values($byEmail));
    }

    /**
     * Próximo lote de destinatários não enviados para a campanha.
     */
    public function getNextRecipientsForCampaign(EmailCampaign $campaign, int $limit = 30): Collection
    {
        $all = $this->getRecipients($campaign->tenant_id, $campaign->filter_config ?? []);
        $sentEmails = $campaign->emailCampaignSends()->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))->flip();
        return $all->filter(fn ($r) => ! $sentEmails->has(strtolower($r['email'])))->take($limit)->values();
    }
}
