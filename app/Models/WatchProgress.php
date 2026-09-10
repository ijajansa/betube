<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchProgress extends Model
{
    use HasFactory;

    protected $table = 'watch_progress';

    protected $fillable = ['user_id', 'video_id', 'position', 'duration'];

    protected function casts(): array
    {
        return ['position' => 'float', 'duration' => 'float'];
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
