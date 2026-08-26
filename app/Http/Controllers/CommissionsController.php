<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $status   = $request->query('status', 'all');

        $query = ProfessionalCommission::forTenant($tenantId)
            ->with(['professional:id,name,avatar', 'appointment:id,scheduled_date,scheduled_time,client_name'])
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $commissions = $query->paginate(25)->withQueryString()->through(fn ($c) => $this->fmt($c));

        $stats = [
            'total_gross'   => ProfessionalCommission::forTenant($tenantId)->sum('amount_gross'),
            'total_paid'    => ProfessionalCommission::forTenant($tenantId)->where('status', 'paid')->sum('commission_amount'),
            'total_pending' => ProfessionalCommission::forTenant($tenantId)->where('status', 'pending')->sum('commission_amount'),
            'total_approved'=> ProfessionalCommission::forTenant($tenantId)->where('status', 'approved')->sum('commission_amount'),
            'count_pending' => ProfessionalCommission::forTenant($tenantId)->where('status', 'pending')->count(),
        ];

        // Sumário por profissional
        $byProfessional = ProfessionalCommission::forTenant($tenantId)
            ->selectRaw('professional_id, sum(amount_gross) as gross, sum(commission_amount) as commission, count(*) as total, sum(case when status="paid" then commission_amount else 0 end) as paid, sum(case when status="pending" then commission_amount else 0 end) as pending')
            ->groupBy('professional_id')
            ->with('professional:id,name,avatar')
            ->get()
            ->map(fn ($r) => [
                'professional_id'   => $r->professional_id,
                'professional_name' => $r->professional?->name,
                'gross'             => (float) $r->gross,
                'commission'        => (float) $r->commission,
                'paid'              => (float) $r->paid,
                'pending'           => (float) $r->pending,
                'total'             => (int) $r->total,
            ]);

        return Inertia::render('Financeiro/Comissoes', [
            'commissions'    => $commissions,
            'stats'          => $stats,
            'by_professional'=> $byProfessional,
            'status_filter'  => $status,
        ]);
    }

    public function approve(ProfessionalCommission $commission): JsonResponse
    {
        abort_if($commission->tenant_id !== $this->tenantId(), 403);
        abort_if($commission->status !== 'pending', 422, 'Comissão já processada.');

        $commission->update(['status' => 'approved', 'approved_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markPaid(Request $request, ProfessionalCommission $commission): JsonResponse
    {
        abort_if($commission->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $commission->update([
            'status'        => 'paid',
            'paid_at'       => now(),
            'payment_notes' => $validated['payment_notes'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function approveAll(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $ids      = $request->input('ids', []);

        ProfessionalCommission::forTenant($tenantId)
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function generate(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validate([
            'appointment_id'   => ['required', 'exists:appointments,id'],
        ]);

        $appointment = Appointment::findOrFail($validated['appointment_id']);
        abort_if($appointment->tenant_id !== $tenantId, 403);
        abort_if(! $appointment->service_price, 422, 'O agendamento não possui valor definido.');

        $existing = ProfessionalCommission::where('appointment_id', $appointment->id)->first();
        if ($existing) {
            return response()->json(['success' => true, 'existing' => true, 'commission' => $this->fmt($existing)]);
        }

        $professional     = Professional::findOrFail($appointment->professional_id);
        $percent          = (float) ($professional->commission_percent ?? 80);
        $gross            = (float) $appointment->service_price;
        $commissionAmount = round($gross * $percent / 100, 2);
        $platformFee      = round($gross - $commissionAmount, 2);

        $commission = ProfessionalCommission::create([
            'tenant_id'          => $tenantId,
            'professional_id'    => $professional->id,
            'appointment_id'     => $appointment->id,
            'amount_gross'       => $gross,
            'commission_percent' => $percent,
            'commission_amount'  => $commissionAmount,
            'platform_fee'       => $platformFee,
            'status'             => 'pending',
        ]);

        return response()->json(['success' => true, 'commission' => $this->fmt($commission->load(['professional', 'appointment']))]);
    }

    private function fmt(ProfessionalCommission $c): array
    {
        return [
            'id'                => $c->id,
            'professional_id'   => $c->professional_id,
            'professional_name' => $c->professional?->name,
            'appointment_id'    => $c->appointment_id,
            'appointment_date'  => $c->appointment?->scheduled_date?->format('d/m/Y'),
            'appointment_time'  => $c->appointment?->scheduled_time,
            'client_name'       => $c->appointment?->client_name,
            'amount_gross'      => (float) $c->amount_gross,
            'commission_percent'=> (float) $c->commission_percent,
            'commission_amount' => (float) $c->commission_amount,
            'platform_fee'      => (float) $c->platform_fee,
            'status'            => $c->status,
            'payment_notes'     => $c->payment_notes,
            'approved_at'       => $c->approved_at?->format('d/m/Y H:i'),
            'paid_at'           => $c->paid_at?->format('d/m/Y H:i'),
            'created_at'        => $c->created_at->format('d/m/Y'),
        ];
    }
}
