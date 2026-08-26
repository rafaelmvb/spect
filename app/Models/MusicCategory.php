<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicCategory extends Model
{
    protected $fillable = ['tenant_id', 'name', 'position'];

    public function tracks(): HasMany
    {
        return $this->hasMany(MusicTrack::class)->orderBy('position');
    }
}
