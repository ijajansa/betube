<?php

namespace App\Services;

use App\Models\{Video, Channel};
use Illuminate\Database\Eloquent\Builder;

class SearchService
{
    private function pattern(string $term): string
    {
        // Explicit SQL escape character works consistently on SQLite and MySQL.
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term)).'%';
    }

    public function videos(array $filters): Builder
    {
        $query = Video::publicVisible()->with(['channel', 'category']);
        if ($term = trim($filters['q'] ?? '')) {
            $like = $this->pattern($term);
            $query->where(function (Builder $q) use ($like) {
                $q->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('channel', fn (Builder $c) => $c->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("handle LIKE ? ESCAPE '!'", [$like]))
                    ->orWhereHas('tags', fn (Builder $t) => $t->whereRaw("name LIKE ? ESCAPE '!'", [$like]));
            });
        }
        if (!empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }
        $since = match ($filters['date'] ?? 'any') {
            'today' => now()->startOfDay(), 'week' => now()->subWeek(),
            'month' => now()->subMonth(), 'year' => now()->subYear(), default => null,
        };
        if ($since) { $query->where('published_at', '>=', $since); }
        match ($filters['duration'] ?? 'any') {
            'short' => $query->where('duration', '<', 240),
            'medium' => $query->whereBetween('duration', [240, 1200]),
            'long' => $query->where('duration', '>', 1200), default => null,
        };
        if (($filters['sort'] ?? '') === 'popular') { $query->orderByDesc('view_count'); }
        elseif (($filters['sort'] ?? 'relevance') === 'relevance' && $term) {
            $query->orderByRaw("CASE WHEN title LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$this->pattern($term)]);
        }
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function channels(array $filters): Builder
    {
        $query = Channel::query()->where('visibility', 'public');
        if ($term = trim($filters['q'] ?? '')) {
            $like = $this->pattern($term);
            $query->where(fn (Builder $q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("handle LIKE ? ESCAPE '!'", [$like])->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]));
        }
        // Channel results stay meaningful when video-specific filters are selected.
        if (!empty($filters['category']) || ($filters['date'] ?? 'any') !== 'any' || ($filters['duration'] ?? 'any') !== 'any') {
            $ids = $this->videos(array_diff_key($filters, ['q' => true]))->reorder()->select('channel_id');
            $query->whereIn('id', $ids);
        }
        return $query->orderByDesc('subscriber_count')->orderBy('name')->orderBy('id');
    }

    public function suggestions(string $term): array
    {
        if (mb_strlen(trim($term)) < 2) { return []; }
        return $this->videos(['q' => $term])->limit(5)->get()->map(fn (Video $v) => [
            'label' => $v->title, 'url' => route('videos.show', $v), 'type' => 'Video',
        ])->concat($this->channels(['q' => $term])->limit(3)->get()->map(fn (Channel $c) => [
            'label' => $c->name, 'url' => route('channels.show', $c), 'type' => 'Channel',
        ]))->all();
    }
}
