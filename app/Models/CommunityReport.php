<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReport extends Model
{
    protected $fillable = [
        'post_id', 'reporter_user_id', 'reason', 'notes',
        'status', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public const REASONS = [
        'spam'                   => 'Spam',
        'conteudo_inapropriado'  => 'Conteúdo inapropriado',
        'violencia'              => 'Violência ou ameaça',
        'assedio'                => 'Assédio ou bullying',
        'desinformacao'          => 'Desinformação',
        'outro'                  => 'Outro motivo',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(MemberCommunityPost::class, 'post_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
