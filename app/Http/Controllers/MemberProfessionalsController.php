<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalReview;
use App\Services\MemberAreaResolver;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfessionalsController extends Controller
{
    public function __construct(protected MemberAreaResolver $resolver) {}

    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function index(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $tenantId = $product->tenant_id;
        $storage  = new StorageService($tenantId);

        $professionals = Professional::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->withCount('reviews as reviews_count')
            ->withAvg('reviews as avg_rating', 'rating')
            ->orderBy('position')
            ->get()
            ->map(fn ($p) => $this->fmtProfessional($p, $storage));

        return Inertia::render('MemberAreaApp/Profissionais', [
            'product'       => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'        => $product->member_area_config,
            'professionals' => $professionals,
            'base_url'      => $this->baseUrl($product, $request),
            'slug'          => $slug,
        ]);
    }

    public function show(Request $request, string $slug, int $professionalId): Response
    {
        $product  = $this->getProduct($request);
        $tenantId = $product->tenant_id;
        $storage  = new StorageService($tenantId);
        $user     = $request->user();

        $professional = Professional::where('id', $professionalId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->withCount('reviews as reviews_count')
            ->withAvg('reviews as avg_rating', 'rating')
            ->firstOrFail();

        $reviews = ProfessionalReview::where('professional_id', $professional->id)
            ->where('is_visible', true)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'user_name'  => $r->user?->name,
                'created_at' => $r->created_at->format('d/m/Y'),
            ]);

        // Verificar se o usuário tem consulta concluída sem avaliação
        $pendingReview = Appointment::where('professional_id', $professional->id)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDoesntHave('review', fn ($q) => $q->where('user_id', $user->id))
            ->latest('scheduled_date')
            ->first();

        $services = $professional->services()
            ->where('is_active', true)
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'description'      => $s->description,
                'price'            => $s->price,
                'duration_minutes' => $s->duration_minutes,
            ]);

        return Inertia::render('MemberAreaApp/Profissional', [
            'product'        => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'         => $product->member_area_config,
            'professional'   => $this->fmtProfessional($professional, $storage),
            'reviews'        => $reviews,
            'services'       => $services,
            'pending_review' => $pendingReview ? ['appointment_id' => $pendingReview->id] : null,
            'base_url'       => $this->baseUrl($product, $request),
            'slug'           => $slug,
        ]);
    }

    public function storeReview(Request $request, string $slug, int $professionalId): JsonResponse
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();

        $professional = Professional::where('id', $professionalId)
            ->where('tenant_id', $product->tenant_id)
            ->firstOrFail();

        $v = $request->validate([
            'appointment_id' => ['nullable', 'integer'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'comment'        => ['nullable', 'string', 'max:1000'],
        ]);

        // Verificar ownership do agendamento e duplicidade de avaliação
        if ($v['appointment_id']) {
            $ownsAppointment = Appointment::where('id', $v['appointment_id'])
                ->where('user_id', $user->id)
                ->exists();
            if (! $ownsAppointment) {
                return response()->json(['success' => false, 'message' => 'Agendamento inválido.'], 403);
            }
            if (ProfessionalReview::where('user_id', $user->id)->where('appointment_id', $v['appointment_id'])->exists()) {
                return response()->json(['success' => false, 'message' => 'Você já avaliou esta consulta.'], 422);
            }
        }

        $review = ProfessionalReview::create([
            'tenant_id'       => $product->tenant_id,
            'professional_id' => $professional->id,
            'user_id'         => $user->id,
            'appointment_id'  => $v['appointment_id'] ?? null,
            'rating'          => $v['rating'],
            'comment'         => $v['comment'] ?? null,
            'is_visible'      => true,
        ]);

        return response()->json(['success' => true, 'review' => [
            'id'        => $review->id,
            'rating'    => $review->rating,
            'comment'   => $review->comment,
            'user_name' => $user->name,
            'created_at' => $review->created_at->format('d/m/Y'),
        ]]);
    }

    public function slots(Request $request, string $slug, int $professionalId): JsonResponse
    {
        $product  = $this->getProduct($request);

        $professional = Professional::where('id', $professionalId)
            ->where('tenant_id', $product->tenant_id)
            ->with(['availabilities', 'services'])
            ->firstOrFail();

        $date = $request->query('date');
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['slots' => []]);
        }

        // Duração baseada no serviço selecionado
        $serviceId   = (int) $request->query('service_id', 0);
        $service     = $serviceId
            ? $professional->services->firstWhere('id', $serviceId)
            : null;
        $slotMinutes = $service?->duration_minutes ?? 30;

        $d   = \Carbon\Carbon::parse($date);
        $dow = (int) $d->dayOfWeek;

        $avail = $professional->availabilities->firstWhere('day_of_week', $dow);
        if (! $avail || ! $avail->is_active) return response()->json(['slots' => []]);

        // Agendamentos existentes com suas durações para detecção de sobreposição
        $existingAppts = Appointment::where('professional_id', $professional->id)
            ->where('scheduled_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'no_show'])
            ->get(['scheduled_time', 'duration_minutes']);

        $now     = \Carbon\Carbon::now();
        $isToday = ($date === $now->toDateString());

        // Janela de almoço (minutos desde meia-noite)
        $lunchStart = $avail->lunch_start ? $this->timeToMin($avail->lunch_start) : null;
        $lunchEnd   = $avail->lunch_end   ? $this->timeToMin($avail->lunch_end)   : null;

        $slots   = [];
        $current = \Carbon\Carbon::createFromTimeString($avail->start_time);
        $endTime = \Carbon\Carbon::createFromTimeString($avail->end_time);

        while ($current->copy()->addMinutes($slotMinutes)->lte($endTime)) {
            $slotMin = $this->timeToMin($current->format('H:i'));
            $slotEnd = $slotMin + $slotMinutes;

            // Slot já passou hoje
            if ($isToday && $current->lte($now)) {
                $current->addMinutes($slotMinutes);
                continue;
            }

            // Slot cai dentro ou cruza o almoço
            if ($lunchStart !== null && $slotMin < $lunchEnd && $slotEnd > $lunchStart) {
                $current->addMinutes($slotMinutes);
                continue;
            }

            // Sobreposição com agendamento existente
            $overlap = $existingAppts->first(function ($a) use ($slotMin, $slotEnd) {
                $aMin = $this->timeToMin(substr($a->scheduled_time, 0, 5));
                $aEnd = $aMin + ($a->duration_minutes ?? 30);
                return $slotMin < $aEnd && $slotEnd > $aMin;
            });

            if (! $overlap) {
                $slots[] = $current->format('H:i');
            }

            $current->addMinutes($slotMinutes);
        }

        return response()->json(['slots' => $slots]);
    }

    private function fmtProfessional(Professional $p, StorageService $storage): array
    {
        return [
            'id'             => $p->id,
            'name'           => $p->name,
            'specialty'      => $p->specialty,
            'bio'            => $p->bio,
            'council_type'   => $p->council_type,
            'council_number' => $p->council_number,
            'avatar_url'     => $p->avatar ? $storage->url($p->avatar) : null,
            'avg_rating'     => $p->avg_rating ? round($p->avg_rating, 1) : null,
            'reviews_count'  => $p->reviews_count ?? 0,
            'is_active'      => $p->is_active,
        ];
    }

    private function timeToMin(string $time): int
    {
        [$h, $m] = explode(':', substr($time, 0, 5));
        return (int) $h * 60 + (int) $m;
    }

    private function baseUrl($product, Request $request): string
    {
        return $this->resolver->baseUrlForProduct($product)
            ?? url("/m/{$product->checkout_slug}");
    }
}
