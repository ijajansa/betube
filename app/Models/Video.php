<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Enums\UploadStatus;
use App\Enums\VideoVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel_id', 'category_id', 'uuid', 'slug', 'title', 'description', 'visibility',
        'processing_status', 'upload_status', 'storage_disk', 'original_path', 'playback_path',
        'thumbnail_path', 'generated_thumbnails', 'duration', 'width', 'height', 'file_size',
        'mime_type', 'published_at', 'scheduled_at', 'age_restricted', 'comments_enabled',
        'embedding_enabled', 'is_blocked', 'processing_error', 'processing_progress',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => VideoVisibility::class,
            'processing_status' => ProcessingStatus::class,
            'upload_status' => UploadStatus::class,
            'generated_thumbnails' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'duration' => 'float',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'dislike_count' => 'integer',
            'comment_count' => 'integer',
            'processing_progress' => 'integer',
            'age_restricted' => 'boolean',
            'comments_enabled' => 'boolean',
            'embedding_enabled' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Video $video) {
            $video->uuid ??= (string) Str::uuid();
            $video->slug ??= Str::slug($video->title) ?: 'video';
        });
        static::created(fn (Video $video) => Channel::whereKey($video->channel_id)->increment('video_count'));
        static::deleted(function (Video $video) {
            if (! $video->isForceDeleting() || $video->getOriginal('deleted_at') === null) {
                Channel::whereKey($video->channel_id)->where('video_count', '>', 0)->decrement('video_count');
            }
        });
        static::restored(fn (Video $video) => Channel::whereKey($video->channel_id)->increment('video_count'));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(VideoVariant::class)->orderBy('height');
    }

    public function views(): HasMany
    {
        return $this->hasMany(VideoView::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(VideoReaction::class);
    }

    public function watchProgress(): HasMany
    {
        return $this->hasMany(WatchProgress::class);
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('visibility', VideoVisibility::Public)
            ->where('processing_status', ProcessingStatus::Ready)
            ->where('upload_status', UploadStatus::Uploaded)
            ->where('is_blocked', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->whereHas('channel', fn (Builder $query) => $query->publicVisible());
    }

    public function isOwnedBy(?User $user): bool
    {
        return $this->channel !== null && $this->channel->isOwnedBy($user);
    }

    public function isPlayableBy(?User $user): bool
    {
        if ($this->trashed() || $this->is_blocked) {
            return false;
        }

        if ($this->isOwnedBy($user)) {
            return $this->upload_status === UploadStatus::Uploaded && filled($this->playback_path);
        }

        return in_array($this->visibility, [VideoVisibility::Public, VideoVisibility::Unlisted], true)
            && $this->processing_status === ProcessingStatus::Ready
            && $this->upload_status === UploadStatus::Uploaded
            && $this->published_at !== null && $this->published_at->isPast()
            && ($this->scheduled_at === null || $this->scheduled_at->isPast())
            && $this->channel?->visibility === 'public'
            && filled($this->playback_path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail_path ? route('media.thumbnail', $this) : asset('images/thumbnail-fallback.svg');
    }

    public function getDurationLabelAttribute(): string
    {
        $seconds = (int) max(0, $this->duration ?? 0);

        return $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
