<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'handle', 'slug', 'description', 'avatar_path', 'banner_path', 'country', 'visibility'];

    protected function casts(): array
    {
        return ['subscriber_count' => 'integer', 'video_count' => 'integer', 'total_views' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->user();
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : asset('images/avatar-fallback.svg');
    }

    public function getBannerUrlAttribute(): string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : asset('images/banner-fallback.svg');
    }
}
