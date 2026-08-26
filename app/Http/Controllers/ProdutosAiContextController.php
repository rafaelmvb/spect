<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProdutosAiContextController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    public function update(Request $request, Product $produto): JsonResponse
    {
        abort_if($produto->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'instructions'              => ['nullable', 'string', 'max:10000'],
            'jornadas'                  => ['nullable', 'array'],
            'jornadas.*.journey_id'     => ['required', 'integer'],
            'jornadas.*.is_free'        => ['required', 'boolean'],
        ]);

        $tenantId = $this->tenantId();
        $rawJornadas = $validated['jornadas'] ?? [];

        if (!empty($rawJornadas)) {
            $incomingIds   = collect($rawJornadas)->pluck('journey_id')->unique()->values();
            $existingIds   = \DB::table('journeys')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $incomingIds)
                ->pluck('id')
                ->flip();
            $rawJornadas = collect($rawJornadas)
                ->filter(fn ($j) => isset($existingIds[$j['journey_id']]))
                ->values()
                ->all();
        }

        $context = $produto->ai_context ?? [];
        $context['instructions'] = $validated['instructions'] ?? '';
        $context['jornadas']     = $rawJornadas;

        $produto->update(['ai_context' => $context]);

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request, Product $produto): JsonResponse
    {
        abort_if($produto->tenant_id !== $this->tenantId(), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt,png,jpg,jpeg,gif,webp'],
        ]);

        $file     = $request->file('file');
        $fileId   = (string) Str::uuid();
        $ext      = $file->getClientOriginalExtension();
        $mime     = $file->getMimeType();
        $size     = $file->getSize();
        $name     = $file->getClientOriginalName();
        $isImage  = str_starts_with($mime, 'image/');
        $type     = $isImage ? 'image' : 'document';
        $path     = "product-ai-context/{$produto->id}/{$fileId}.{$ext}";

        Storage::disk('public')->putFileAs(
            "product-ai-context/{$produto->id}",
            $file,
            "{$fileId}.{$ext}"
        );

        $context  = $produto->ai_context ?? [];
        $files    = $context['files'] ?? [];
        $files[]  = [
            'id'   => $fileId,
            'name' => $name,
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
            'type' => $type,
            'mime' => $mime,
            'size' => $size,
        ];
        $context['files'] = $files;

        $produto->update(['ai_context' => $context]);

        return response()->json(['file' => end($files)]);
    }

    public function destroyFile(Product $produto, string $fileId): JsonResponse
    {
        abort_if($produto->tenant_id !== $this->tenantId(), 403);

        $context = $produto->ai_context ?? [];
        $files   = $context['files'] ?? [];

        $target = collect($files)->firstWhere('id', $fileId);
        if ($target) {
            Storage::disk('public')->delete($target['path']);
        }

        $context['files'] = collect($files)->filter(fn ($f) => $f['id'] !== $fileId)->values()->all();
        $produto->update(['ai_context' => $context]);

        return response()->json(['ok' => true]);
    }
}
