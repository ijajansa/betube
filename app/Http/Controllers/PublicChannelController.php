<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class PublicChannelController extends Controller
{
    public function show(Request $request, Channel $channel)
    {
        abort_unless($channel->visibility === 'public' || $request->user()?->id === $channel->user_id, 404);
        $tab = in_array($request->query('tab'), ['home', 'videos']) ? $request->query('tab') : 'home';
        $videos = $channel->videos()->publicVisible()->with('channel')->latest('published_at')->paginate(18)->withQueryString();
        return view('channels.show', compact('channel', 'videos', 'tab'));
    }
}
