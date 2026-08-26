<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'team_role_id',
    ];

    /**
     * Campos de privilégio: nunca podem entrar por mass assignment, senão um
     * request conseguiria se promover a admin ou trocar de tenant.
     *
     * Como $fillable acima está preenchido, estes campos ficam fora dele e são
     * descartados em silêncio por create()/fill()/update(). Para gravá-los use
     * createWithRole()/firstOrCreateWithRole() ou atribuição direta ($u->role = ...).
     */
    protected $guarded = [
        'role',
        'tenant_id',
        'blocked_at',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_ALUNO = 'aluno';
    public const ROLE_TEAM = 'team';
    public const ROLE_PROFISSIONAL = 'profissional';

    /**
     * Cria um usuário atribuindo role/tenant_id fora do mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithRole(array $attributes, string $role, ?int $tenantId = null): self
    {
        $user = new static;
        $user->fill($attributes);
        $user->role = $role;
        $user->tenant_id = $tenantId;
        $user->save();

        return $user;
    }

    /**
     * Equivalente a firstOrCreate(), mas gravando role/tenant_id no registro novo.
     * Mantém wasRecentlyCreated para quem depende disso após a chamada.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public static function firstOrCreateWithRole(array $match, array $attributes, string $role, ?int $tenantId = null): self
    {
        $existing = static::where($match)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return static::createWithRole(array_merge($match, $attributes), $role, $tenantId);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Corrida entre dois checkouts com o mesmo e-mail: o outro request ganhou.
            return static::where($match)->firstOrFail();
        }
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAluno(): bool
    {
        return $this->role === self::ROLE_ALUNO;
    }

    public function isTeam(): bool
    {
        return $this->role === self::ROLE_TEAM;
    }

    public function isProfissional(): bool
    {
        return $this->role === self::ROLE_PROFISSIONAL;
    }

    public function canAccessPanel(): bool
    {
        return $this->isAdmin() || $this->isTeam() || $this->isProfissional();
    }

    public function professional(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Professional::class);
    }

    public function activityLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MemberActivityLog::class);
    }

    public function teamRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TeamRole::class, 'team_role_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_user')->withTimestamps();
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function savedPaymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SavedPaymentMethod::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function block(): void
    {
        // Atribuição direta: blocked_at está em $guarded e update() o descartaria.
        $this->blocked_at = now();
        $this->save();
    }

    public function unblock(): void
    {
        $this->blocked_at = null;
        $this->save();
    }

    public function neuroScores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NeuroUserScore::class);
    }
}
