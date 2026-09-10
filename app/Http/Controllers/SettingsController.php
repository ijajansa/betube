<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);
        $request->user()->settings()->updateOrCreate(['user_id' => $request->user()->id], $data);
        return $request->expectsJson() ? response()->json($data) : back()->with('status', 'Appearance saved.');
    }
}
