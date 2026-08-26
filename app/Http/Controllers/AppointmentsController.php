<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function format(Appointment $a): array
    {
        return [
            'id'               => $a->id,
            'professional_id'  => $a->professional_id,
            'professional_name'=> $a->professional?->name,
            'user_id'          => $a->user_id,
            'client_name'      => $a->client_name,
            'client_email'     => $a->client_email,
            'client_phone'     => $a->client_phone,
            'scheduled_date'   => $a->scheduled_date?->format('Y-m-d'),
            'scheduled_time'   => $a->scheduled_time,
            'duration_minutes' => $a->duration_minutes,
            'status'           => $a->status,
            'status_label'     => Appointment::STATUSES[$a->status] ?? $a->status,
            'notes'            => $a->notes,
            'admin_notes'      => $a->admin_notes,
            'cancelled_reason' => $a->cancelled_reason,
            'cancelled_at'     => $a->cancelled_at?->format('d/m/Y H:i'),
        ];
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();

        // Mês/ano para o calendário
        $year  = (int) ($request->query('year',  now()->year));
        $month = (int) ($request->query('month', now()->month));

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // Appointments do mês (para o calendário)
        $monthAppointments = Appointment::forTenant($tenantId)
            ->with('professional:id,name')
            ->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->when($request->query('professional_id'), fn ($q, $id) => $q->where('professional_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get()
            ->map(fn ($a) => $this->format($a));

        // Stats do mês
        $stats = [
            'total'     => Appointment::forTenant($tenantId)->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->count(),
            'confirmed' => Appointment::forTenant($tenantId)->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->where('status', 'confirmed')->count(),
            'completed' => Appointment::forTenant($tenantId)->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->where('status', 'completed')->count(),
            'cancelled' => Appointment::forTenant($tenantId)->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->where('status', 'cancelled')->count(),
        ];

        $professionals = Professional::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'specialty']);

        return Inertia::render('Appointments/Index', [
            'appointments'  => $monthAppointments,
            'stats'         => $stats,
            'professionals' => $professionals,
            'year'          => $year,
            'month'         => $month,
            'filters'       => [
                'professional_id' => $request->query('professional_id'),
                'status'          => $request->query('status'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'professional_id'  => ['required', 'integer', 'exists:professionals,id'],
            'client_name'      => ['required', 'string', 'max:255'],
            'client_email'     => ['nullable', 'email', 'max:255'],
            'client_phone'     => ['nullable', 'string', 'max:30'],
            'scheduled_date'   => ['required', 'date'],
            'scheduled_time'   => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'status'           => ['nullable', 'string', 'in:' . implode(',', array_keys(Appointment::STATUSES))],
        ]);

        // Verifica que o profissional pertence ao tenant
        $pro = Professional::findOrFail($v['professional_id']);
        abort_if($pro->tenant_id !== $this->tenantId(), 403);

        $appointment = Appointment::create([
            ...$v,
            'tenant_id'        => $this->tenantId(),
            'duration_minutes' => $v['duration_minutes'] ?? 60,
            'status'           => $v['status'] ?? Appointment::STATUS_PENDING,
        ]);

        $appointment->load('professional:id,name');

        return response()->json(['success' => true, 'appointment' => $this->format($appointment)]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->tenant_id !== $this->tenantId(), 403);

        $v = $request->validate([
            'professional_id'  => ['sometimes', 'integer', 'exists:professionals,id'],
            'client_name'      => ['sometimes', 'required', 'string', 'max:255'],
            'client_email'     => ['nullable', 'email', 'max:255'],
            'client_phone'     => ['nullable', 'string', 'max:30'],
            'scheduled_date'   => ['sometimes', 'date'],
            'scheduled_time'   => ['sometimes', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'admin_notes'      => ['nullable', 'string', 'max:2000'],
            'status'           => ['nullable', 'string', 'in:' . implode(',', array_keys(Appointment::STATUSES))],
        ]);

        $appointment->update($v);
        $appointment->load('professional:id,name');

        return response()->json(['success' => true, 'appointment' => $this->format($appointment->fresh())]);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->tenant_id !== $this->tenantId(), 403);
        abort_if(in_array($appointment->status, ['completed', 'cancelled']), 422, 'Não é possível cancelar.');

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $appointment->update([
            'status'           => Appointment::STATUS_CANCELLED,
            'cancelled_reason' => $request->reason,
            'cancelled_at'     => now(),
        ]);

        return response()->json(['success' => true, 'appointment' => $this->format($appointment->fresh())]);
    }

    public function complete(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->tenant_id !== $this->tenantId(), 403);
        $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
        return response()->json(['success' => true, 'appointment' => $this->format($appointment->fresh())]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->tenant_id !== $this->tenantId(), 403);
        $appointment->delete();
        return response()->json(['success' => true]);
    }

    // ─── Disponibilidade ──────────────────────────────────────────────────────

    public function availability(Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);
        $avail = $professional->availabilities()->orderBy('day_of_week')->get();
        return response()->json(['availability' => $avail->map(fn ($a) => [
            'id'             => $a->id,
            'day_of_week'    => $a->day_of_week,
            'start_time'     => $a->start_time,
            'end_time'       => $a->end_time,
            'slot_duration'  => $a->slot_duration,
            'is_active'      => $a->is_active,
        ])]);
    }

    public function saveAvailability(Request $request, Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);

        $request->validate([
            'slots'               => ['required', 'array'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'  => ['required', 'date_format:H:i'],
            'slots.*.end_time'    => ['required', 'date_format:H:i'],
            'slots.*.slot_duration'=>['nullable', 'integer', 'min:15', 'max:480'],
            'slots.*.is_active'   => ['nullable', 'boolean'],
        ]);

        foreach ($request->slots as $slot) {
            ProfessionalAvailability::updateOrCreate(
                ['professional_id' => $professional->id, 'day_of_week' => $slot['day_of_week']],
                [
                    'start_time'    => $slot['start_time'],
                    'end_time'      => $slot['end_time'],
                    'slot_duration' => $slot['slot_duration'] ?? 60,
                    'is_active'     => $slot['is_active'] ?? true,
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // Retorna os slots disponíveis de um profissional numa data específica
    public function slots(Request $request, Professional $professional): JsonResponse
    {
        abort_if($professional->tenant_id !== $this->tenantId(), 403);
        $request->validate(['date' => ['required', 'date']]);

        $date = Carbon::parse($request->date);
        $dow  = $date->dayOfWeek; // 0=Dom

        $avail = ProfessionalAvailability::where('professional_id', $professional->id)
            ->where('day_of_week', $dow)
            ->where('is_active', true)
            ->first();

        if (!$avail) {
            return response()->json(['slots' => []]);
        }

        // Gera slots baseados no horário
        $start    = Carbon::parse($avail->start_time);
        $end      = Carbon::parse($avail->end_time);
        $duration = $avail->slot_duration;
        $slots    = [];

        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slots[] = $cursor->format('H:i');
            $cursor->addMinutes($duration);
        }

        // Remove slots já ocupados (pending, confirmed, no_show)
        $booked = Appointment::where('professional_id', $professional->id)
            ->where('scheduled_date', $date->toDateString())
            ->whereIn('status', ['pending', 'confirmed', 'no_show'])
            ->pluck('scheduled_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->values()
            ->toArray();

        $slots = array_values(array_filter($slots, fn ($s) => !in_array($s, $booked, true)));

        return response()->json(['slots' => $slots]);
    }
}
