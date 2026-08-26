<?php

namespace App\Http\Controllers;

use App\Models\AiInsight;
use App\Models\MemberReportRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiReportGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberReportRequestsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    public function index(): Response
    {
        $tenantId   = $this->tenantId();
        $autoApprove = Setting::get('member_reports.auto_approve', false, $tenantId);

        $requests = MemberReportRequest::forTenant($tenantId)
            ->with(['user', 'product'])
            ->orderByRaw("FIELD(status, 'pending', 'approved')")
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (MemberReportRequest $r) => [
                'id'           => $r->id,
                'status'       => $r->status,
                'requested_at' => $r->requested_at?->format('d/m/Y H:i'),
                'approved_at'  => $r->approved_at?->format('d/m/Y H:i'),
                'insight_id'   => $r->insight_id,
                'user'         => [
                    'id'    => $r->user?->id,
                    'name'  => $r->user?->name,
                    'email' => $r->user?->email,
                ],
                'product' => [
                    'id'   => $r->product?->id,
                    'name' => $r->product?->name,
                ],
            ]);

        return Inertia::render('MemberReportRequests/Index', [
            'requests'    => $requests,
            'auto_approve' => (bool) $autoApprove,
        ]);
    }

    public function approve(MemberReportRequest $memberReportRequest, AiReportGeneratorService $generator): JsonResponse
    {
        $tenantId = $this->tenantId();
        abort_if($memberReportRequest->tenant_id !== $tenantId, 403);

        $student   = User::find($memberReportRequest->user_id);
        $insightId = null;

        if ($student) {
            try {
                $product   = $memberReportRequest->product;
                $insight   = $generator->generate($student, $tenantId, auth()->id(), '', $product);
                $insightId = $insight?->id;
            } catch (\Throwable) {
                // geração de relatório falha silenciosamente — aprovação ainda ocorre
            }
        }

        $memberReportRequest->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'insight_id'  => $insightId,
        ]);

        return response()->json([
            'success'     => true,
            'approved_at' => $memberReportRequest->approved_at->format('d/m/Y H:i'),
            'insight_id'  => $insightId,
        ]);
    }

    public function disapprove(MemberReportRequest $memberReportRequest): JsonResponse
    {
        abort_if($memberReportRequest->tenant_id !== $this->tenantId(), 403);
        abort_if($memberReportRequest->status !== 'approved', 422, 'Solicitação não está aprovada.');

        $memberReportRequest->update([
            'status'      => 'pending',
            'approved_at' => null,
            'insight_id'  => null,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(MemberReportRequest $memberReportRequest): JsonResponse
    {
        abort_if($memberReportRequest->tenant_id !== $this->tenantId(), 403);

        if ($memberReportRequest->insight_id) {
            AiInsight::where('id', $memberReportRequest->insight_id)->delete();
        }

        $memberReportRequest->delete();

        return response()->json(['success' => true]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $v = $request->validate([
            'auto_approve' => ['required', 'boolean'],
        ]);

        Setting::set('member_reports.auto_approve', $v['auto_approve'], $this->tenantId());

        return response()->json(['success' => true]);
    }
}
