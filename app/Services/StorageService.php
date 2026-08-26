<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\StorageVisibility;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    private ?int $tenantId = null;

    private ?Filesystem $disk = null;

    private ?Filesystem $restrictedDisk = null;

    private bool $isLocal = true;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId ?? auth()->user()?->tenant_id;
    }

    /**
     * Get the active storage disk for the current tenant.
     */
    public function disk(): Filesystem
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        $provider = Setting::get('storage_provider', null, $this->tenantId);
        if ($provider === null || $provider === '') {
            $provider = 'local';
        }

        if ($provider === 'local' || empty($provider)) {
            $this->disk = Storage::disk('public');
            $this->isLocal = true;

            return $this->disk;
        }

        $key = Setting::get('storage_s3_key', '', $this->tenantId);
        $secretRaw = Setting::get('storage_s3_secret', '', $this->tenantId);
        $secret = '';
        if ($secretRaw) {
            try {
                $secret = Crypt::decryptString($secretRaw);
            } catch (\Throwable) {
                $secret = '';
            }
        }
        $bucket = Setting::get('storage_s3_bucket', '', $this->tenantId);
        $region = Setting::get('storage_s3_region', 'us-east-1', $this->tenantId);
        $endpoint = Setting::get('storage_s3_endpoint', '', $this->tenantId);
        $url = Setting::get('storage_s3_url', '', $this->tenantId);

        if (empty($key) || empty($secret) || empty($bucket)) {
            $this->disk = Storage::disk('public');
            $this->isLocal = true;

            return $this->disk;
        }

        $isR2 = $provider === 'r2' || ($endpoint && str_contains($endpoint, 'r2.cloudflarestorage.com'));
        $regionForConfig = $isR2 ? 'auto' : ($region ?: 'us-east-1');

        $config = [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $regionForConfig,
            'bucket' => $bucket,
            'throw' => false,
            'report' => false,
        ];

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = str_contains($endpoint, 'r2.cloudflarestorage.com')
                || str_contains($endpoint, 'wasabisys.com')
                || str_contains($endpoint, 'digitaloceanspaces.com');
        }

        if ($url) {
            $config['url'] = rtrim($url, '/');
        }

        $this->disk = Storage::build($config);
        $this->isLocal = false;

        return $this->disk;
    }

    /**
     * Whether the current disk is local (public) or remote (S3/R2).
     */
    public function isLocal(): bool
    {
        $this->disk();

        return $this->isLocal;
    }

    /**
     * Disco de conteúdo restrito.
     *
     * Em armazenamento local vai para storage/app/private, fora do alcance de
     * GET /storage/{path}. Em S3/R2 fica no mesmo bucket — o que protege ali é
     * a URL nunca ser exposta: o acesso passa sempre por PrivateFileController.
     */
    public function restrictedDisk(): Filesystem
    {
        if ($this->restrictedDisk !== null) {
            return $this->restrictedDisk;
        }

        $this->disk();

        $this->restrictedDisk = $this->isLocal ? Storage::disk('local') : $this->disk;

        return $this->restrictedDisk;
    }

    /**
     * Disco correto para um caminho, conforme a visibilidade do prefixo.
     */
    private function diskFor(string $path): Filesystem
    {
        return StorageVisibility::isRestrito($path) ? $this->restrictedDisk() : $this->disk();
    }

    /**
     * Store an uploaded file and return the path.
     */
    public function putFile(string $directory, UploadedFile $file, ?string $name = null): string
    {
        $name = $name ?? $file->hashName();

        return $this->diskFor($directory)->putFileAs($directory, $file, $name);
    }

    /**
     * Store file with putFileAs.
     */
    public function putFileAs(string $directory, UploadedFile $file, string $name): string
    {
        return $this->diskFor($directory)->putFileAs($directory, $file, $name);
    }

    /**
     * Get the public URL for a stored file.
     */
    public function url(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        $this->disk(); // ensure disk is resolved (sets isLocal)

        // Conteúdo restrito nunca recebe URL direta, nem em S3: a rota confere
        // sessão e acesso ao produto antes de entregar o arquivo.
        if (StorageVisibility::isRestrito($path)) {
            return url('/arquivo/' . ltrim($path, '/'));
        }

        if ($this->isLocal) {
            return url('/storage/' . ltrim($path, '/'));
        }

        return $this->disk->url($path);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return $this->diskFor($path)->delete($path);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return $this->diskFor($path)->exists($path);
    }
}
