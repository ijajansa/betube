<?php

namespace App\Services;

use App\Models\{User, Video, WatchProgress};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FeedService
{
    public function forUser(?User $user, ?string $category = null): LengthAwarePaginator
    {
        $query = Video::publicVisible()->with(['channel', 'category']);
        if ($category) { $query->whereHas('category', fn ($q) => $q->where('slug', $category)); }
        if ($user) {
            $categories = WatchProgress::where('user_id', $user->id)->whereHas('video', fn ($q) => $q->publicVisible())
                ->with('video:id,category_id')->latest('updated_at')->limit(30)->get()->pluck('video.category_id')->filter()->unique()->values()->all();
            if ($categories) {
                $placeholders = implode(',', array_fill(0, count($categories), '?'));
                $query->orderByRaw("CASE WHEN category_id IN ($placeholders) THEN 0 ELSE 1 END", $categories);
            }
        }
        // Give recent uploads a shelf, with popular videos mixed inside it.
        return $query->orderByRaw('CASE WHEN published_at >= ? THEN 0 ELSE 1 END', [now()->subDays(7)])
            ->orderByDesc('view_count')->orderByDesc('published_at')->orderByDesc('id')->paginate(18)->withQueryString();
    }
}
