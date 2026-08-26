<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberQuizResponse extends Model
{
    protected $fillable = ['lesson_id', 'user_id', 'product_id', 'responses'];

    protected $casts = ['responses' => 'array'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(MemberLesson::class, 'lesson_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
