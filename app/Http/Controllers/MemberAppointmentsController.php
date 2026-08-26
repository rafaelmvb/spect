<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\MemberActivityLog;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Services\MemberAreaResolver;
use App\Services\StorageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberAppointmentsController extends Controller
{
    public function __construct(protected MemberAreaResolver $resolver) {}

    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function index(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;
        $storage  = new StorageService($tenantId);

        $appointments = Appointment::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->with('professional')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_time')
            ->get()
            ->map(fn ($a) => $this->fmtAppointment($a, $storage));

        $professionals = Professional::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn ($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'specialty' => $p->specialty,
                'avatar_url' => $p->avatar ? $storage->url($p->avatar) : null,
            ]);

        return Inertia::render('MemberAreaApp/Agendamentos', [
            'product'       => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'        => $product->member_area_config,
            'appointments'  => $appointments,
            'professionals' => $professionals,
            'base_url'      => $this->baseUrl($product, $request),
            'slug'          => $slug,
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();

        $v = $request->validate([
            'professional_id' => ['required', 'integer'],
            'service_id'      => ['nullable', 'integer'],
            'scheduled_date'  => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time'  => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $professional = Professional::where('id', $v['professional_id'])
            ->where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        // Duração pelo serviço selecionado; fallback para slot_duration da disponibilidade
        $service  = null;
        $duration = 30;
        if (! empty($v['service_id'])) {
            $service  = \App\Models\ProfessionalService::where('id', $v['service_id'])
                ->where('professional_id', $professional->id)
                ->where('is_active', true)
                ->first();
            $duration = $service?->duration_minutes ?? 30;
        }

        // Checar disponibilidade
        $d   = Carbon::parse($v['scheduled_date']);
        $dow = (int) $d->dayOfWeek;
        $avail = ProfessionalAvailability::where('professional_id', $professional->id)
            ->where('day_of_week', $dow)
            ->where('is_active', true)
            ->first();

        if (! $avail) {
            return response()->json(['message' => 'Profissional não disponível nesta data.'], 422);
        }

        // Checar sobreposição com agendamentos existentes considerando durações reais
        $slotMin = $this->timeToMin($v['scheduled_time']);
        $slotEnd = $slotMin + $duration;

        $conflict = Appointment::where('professional_id', $professional->id)
            ->where('scheduled_date', $v['scheduled_date'])
            ->whereIn('status', ['pending', 'confirmed', 'no_show'])
            ->get(['scheduled_time', 'duration_minutes'])
            ->first(function ($a) use ($slotMin, $slotEnd) {
                $aMin = $this->timeToMin(substr($a->scheduled_time, 0, 5));
                $aEnd = $aMin + ($a->duration_minutes ?? 30);
                return $slotMin < $aEnd && $slotEnd > $aMin;
            });

        if ($conflict) {
            return response()->json(['message' => 'Este horário já está ocupado.'], 422);
        }

        $appointment = Appointment::create([
            'tenant_id'        => $product->tenant_id,
            'professional_id'  => $professional->id,
            'user_id'          => $user->id,
            'client_name'      => $user->name,
            'client_email'     => $user->email,
            'client_phone'     => $user->phone ?? '',
            'scheduled_date'   => $v['scheduled_date'],
            'scheduled_time'   => $v['scheduled_time'] . ':00',
            'duration_minutes' => $duration,
            'status'           => 'pending',
            'notes'            => ($service ? "Serviço: {$service->name}. " : '') . ($v['notes'] ?? ''),
        ]);

        $storage = new StorageService($product->tenant_id);
        return response()->json(['success' => true, 'appointment' => $this->fmtAppointment($appointment->load('professional'), $storage)]);
    }

    public function cancel(Request $request, string $slug, Appointment $appointment): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        abort_if($appointment->user_id !== $user->id, 403);
        abort_if($appointment->tenant_id !== $product->tenant_id, 403);

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Não é possível cancelar este agendamento.'], 422);
        }

        $v = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $appointment->update([
            'status'            => 'cancelled',
            'cancelled_reason'  => $v['reason'] ?? null,
            'cancelled_at'      => now(),
        ]);

        try {
            MemberActivityLog::create([
                'tenant_id'  => $product->tenant_id,
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'event'      => 'appointment.cancelled',
                'metadata'   => [
                    'appointment_id'   => $appointment->id,
                    'professional_id'  => $appointment->professional_id,
                    'scheduled_date'   => $appointment->scheduled_date,
                    'scheduled_time'   => $appointment->scheduled_time,
                    'reason'           => $v['reason'] ?? null,
                ],
                'ip'         => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // best-effort — não bloqueia a resposta
        }

        return response()->json(['success' => true, 'status' => 'cancelled']);
    }

    private function fmtAppointment(Appointment $a, StorageService $storage): array
    {
        return [
            'id'               => $a->id,
            'professional_id'  => $a->professional_id,
            'professional_name' => $a->professional?->name,
            'professional_specialty' => $a->professional?->specialty,
            'professional_avatar' => $a->professional?->avatar ? $storage->url($a->professional->avatar) : null,
            'scheduled_date'   => $a->scheduled_date,
            'scheduled_time'   => substr($a->scheduled_time, 0, 5),
            'duration_minutes' => $a->duration_minutes,
            'status'           => $a->status,
            'notes'            => $a->notes,
            'cancelled_reason' => $a->cancelled_reason,
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
