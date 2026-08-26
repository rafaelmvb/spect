<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'email', 'phone', 'website', 'avatar',
        'bio', 'experience', 'social_links', 'specialty',
        'registration_type', 'registration_number',
        'price_per_hour', 'is_active', 'position',
        'status', 'approved_at', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'price_per_hour' => 'decimal:2',
        'social_links'   => 'array',
        'position'       => 'integer',
        'approved_at'    => 'datetime',
        'rejected_at'    => 'datetime',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->attributes['avatar'] ?? null)) return null;
        return app(StorageService::class)->url($this->attributes['avatar']);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function availabilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfessionalAvailability::class)->orderBy('day_of_week');
    }

    public function appointments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfessionalReview::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfessionalService::class)->orderBy('position');
    }

    public function portfolioItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfessionalPortfolioItem::class)->orderBy('position');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
