<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoView extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'user_id', 'viewer_hash', 'bucket', 'watched_seconds', 'last_position'];

    protected function casts(): array
    {
        return ['bucket' => 'integer', 'watched_seconds' => 'float', 'last_position' => 'float'];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
