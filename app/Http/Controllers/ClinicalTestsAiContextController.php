<?php

namespace App\Http\Controllers;

use App\Models\ClinicalTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicalTestsAiContextController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    public function update(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        abort_if((int) $clinicalTest->tenant_id !== (int) $this->tenantId(), 403);

        $validated = $request->validate([
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);

        $context                 = $clinicalTest->ai_context ?? [];
        $context['instructions'] = $validated['instructions'] ?? '';

        $clinicalTest->update(['ai_context' => $context]);

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        abort_if((int) $clinicalTest->tenant_id !== (int) $this->tenantId(), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt,png,jpg,jpeg,gif,webp'],
        ]);

        $file    = $request->file('file');
        $fileId  = (string) Str::uuid();
        $ext     = $file->getClientOriginalExtension();
        $mime    = $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');
        $path    = "clinical-test-ai-context/{$clinicalTest->id}/{$fileId}.{$ext}";

        Storage::disk('public')->putFileAs(
            "clinical-test-ai-context/{$clinicalTest->id}",
            $file,
            "{$fileId}.{$ext}"
        );

        $context        = $clinicalTest->ai_context ?? [];
        $files          = $context['files'] ?? [];
        $files[]        = [
            'id'   => $fileId,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
            'type' => $isImage ? 'image' : 'document',
            'mime' => $mime,
            'size' => $file->getSize(),
        ];
        $context['files'] = $files;

        $clinicalTest->update(['ai_context' => $context]);

        return response()->json(['file' => end($files)]);
    }

    public function destroyFile(ClinicalTest $clinicalTest, string $fileId): JsonResponse
    {
        abort_if((int) $clinicalTest->tenant_id !== (int) $this->tenantId(), 403);

        $context = $clinicalTest->ai_context ?? [];
        $files   = $context['files'] ?? [];

        $target = collect($files)->firstWhere('id', $fileId);
        if ($target) {
            Storage::disk('public')->delete($target['path']);
        }

        $context['files'] = collect($files)->filter(fn ($f) => $f['id'] !== $fileId)->values()->all();
        $clinicalTest->update(['ai_context' => $context]);

        return response()->json(['ok' => true]);
    }
}
