<?php

namespace App\Http\Controllers;

use App\Models\{Category, Video};
use App\Services\FeedService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, FeedService $feed)
    {
        $request->validate(['category' => ['nullable', 'string', 'max:100']]);
        return view('home', ['videos' => $feed->forUser($request->user(), $request->input('category')), 'categories' => Category::orderBy('name')->get()]);
    }

    public function history(Request $request)
    {
        $videos = Video::with(['channel', 'category'])->whereHas('watchProgress', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where(fn ($q) => $q->publicVisible()->orWhereHas('channel', fn ($c) => $c->where('user_id', $request->user()->id)))
            ->orderByDesc(\App\Models\WatchProgress::select('updated_at')->whereColumn('video_id', 'videos.id')->where('user_id', $request->user()->id)->limit(1))
            ->paginate(18);
        return view('history', compact('videos'));
    }
}
