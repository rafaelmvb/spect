<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberFriendship extends Model
{
    protected $fillable = ['requester_id', 'receiver_id', 'status'];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function receiver(): BelongsTo  { return $this->belongsTo(User::class, 'receiver_id'); }

    public static function statusBetween(int $a, int $b): ?self
    {
        return static::where(fn ($q) => $q->where('requester_id', $a)->where('receiver_id', $b))
            ->orWhere(fn ($q) => $q->where('requester_id', $b)->where('receiver_id', $a))
            ->first();
    }
}
