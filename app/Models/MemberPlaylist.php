<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MemberPlaylist extends Model
{
    protected $fillable = ['user_id', 'product_id', 'name'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(MusicTrack::class, 'member_playlist_tracks', 'playlist_id', 'music_track_id')
            ->withPivot('position')->orderByPivot('position');
    }
}
