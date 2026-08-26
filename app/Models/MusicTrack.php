<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MusicTrack extends Model
{
    protected $fillable = [
        'music_category_id', 'tenant_id', 'title',
        'file_path', 'is_active', 'available_to_all', 'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'available_to_all' => 'boolean',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->file_path)) return null;
        return app(StorageService::class)->url($this->file_path);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MusicCategory::class, 'music_category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'music_track_product', 'music_track_id', 'product_id');
    }
}
