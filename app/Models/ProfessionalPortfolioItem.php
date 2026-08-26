<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;

class ProfessionalPortfolioItem extends Model
{
    protected $fillable = [
        'professional_id', 'type', 'path', 'video_url',
        'title', 'description', 'position',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'image' && $this->path) {
            $pro = $this->professional;
            $tenantId = $pro?->tenant_id;
            return app(StorageService::class, ['tenantId' => $tenantId])->url($this->path);
        }
        return $this->video_url;
    }

    public function professional(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
