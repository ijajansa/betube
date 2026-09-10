<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoVariant extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'label', 'path', 'width', 'height', 'file_size', 'mime_type'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'file_size' => 'integer'];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
