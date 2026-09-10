<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Category;
use App\Services\SearchService;

class SearchController extends Controller
{
    public function index(SearchRequest $request, SearchService $search)
    {
        $filters = $request->validated();
        return view('search', [
            'filters' => $filters,
            'videos' => $search->videos($filters)->paginate(12, ['*'], 'videos_page')->withQueryString(),
            'channels' => $search->channels($filters)->paginate(8, ['*'], 'channels_page')->withQueryString(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function suggestions(SearchRequest $request, SearchService $search)
    {
        return response()->json(['suggestions' => $search->suggestions($request->input('q', ''))]);
    }
}
