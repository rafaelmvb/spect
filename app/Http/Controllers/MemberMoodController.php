<?php

namespace App\Http\Controllers;

use App\Models\DailyMoodCheckin;
use App\Models\UserStreak;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberMoodController extends Controller
{
    /**
     * Registra o humor do dia e atualiza a ofensiva do usuário.
     */
    public function checkin(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'mood' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:500',
        ]);

        $product = $request->attributes->get('member_area_product');
        $productId = $product?->id ?? $slug;
        $userId = auth()->id();
        $today = Carbon::today()->toDateString();

        // Salva ou atualiza o check-in de hoje
        $checkin = DailyMoodCheckin::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId, 'checkin_date' => $today],
            ['mood' => $request->mood, 'note' => $request->note],
        );

        // Atualiza a ofensiva
        $streak = UserStreak::firstOrCreate(
            ['user_id' => $userId, 'product_id' => $productId],
            ['current_streak' => 0, 'longest_streak' => 0],
        );

        $yesterday = Carbon::yesterday()->toDateString();
        $lastDate = $streak->last_checkin_date?->toDateString();

        if ($lastDate === $today) {
            // Já fez check-in hoje, streak não muda
        } elseif ($lastDate === $yesterday) {
            // Continua a sequência
            $streak->current_streak += 1;
        } else {
            // Quebrou a sequência ou primeira vez
            $streak->current_streak = 1;
        }

        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_checkin_date = $today;
        $streak->save();

        return response()->json([
            'ok'             => true,
            'current_streak' => $streak->current_streak,
            'mood'           => $checkin->mood,
        ]);
    }

    /**
     * Retorna os últimos 7 check-ins (para gráfico de humor no painel profissional futuramente).
     */
    public static function recentMoods(int $userId, string $productId, int $days = 7): array
    {
        return DailyMoodCheckin::where('user_id', $userId)
            ->where('product_id', $productId)
            ->orderByDesc('checkin_date')
            ->limit($days)
            ->get(['mood', 'note', 'checkin_date'])
            ->toArray();
    }

    /**
     * Dados de streak e check-in do dia para a home da área de membro.
     */
    public static function homeData(int $userId, string $productId): array
    {
        $today = Carbon::today()->toDateString();

        $streak = UserStreak::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        $todayCheckin = DailyMoodCheckin::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('checkin_date', $today)
            ->first();

        // Verifica se streak quebrou (mais de 1 dia sem check-in)
        $currentStreak = 0;
        if ($streak) {
            $lastDate = $streak->last_checkin_date?->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();
            if ($lastDate === $today || $lastDate === $yesterday) {
                $currentStreak = $streak->current_streak;
            }
            // Se passou mais de 1 dia, streak zerou na prática
        }

        return [
            'current_streak'  => $currentStreak,
            'longest_streak'  => $streak?->longest_streak ?? 0,
            'today_mood'      => $todayCheckin?->mood,
            'checkin_done'    => $todayCheckin !== null,
        ];
    }
}
