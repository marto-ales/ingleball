<?php

namespace App\Http\Controllers;

use App\Support\Themes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->load('player');

        return view('profile.edit', [
            'user' => $user,
            'profile' => $user->player,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_group' => ['nullable', 'string', 'max:255'],
            'speed' => ['required', 'integer', 'between:0,10'],
            'skill' => ['required', 'integer', 'between:0,10'],
            'passing' => ['required', 'integer', 'between:0,10'],
            'shooting' => ['required', 'integer', 'between:0,10'],
            'defense' => ['required', 'integer', 'between:0,10'],
            'likes_goalie' => ['nullable', 'boolean'],
            'goalkeeping' => ['required', 'integer', 'between:0,10'],
            'theme' => ['nullable', Rule::in(array_keys(Themes::all()))],
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'theme' => $data['theme'] ?? Themes::DEFAULT,
        ];

        if ($user->is_organizer) {
            $update['whatsapp_group'] = $data['whatsapp_group'] ?? null;
        }

        $user->update($update);

        $user->player()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'speed' => (int) $data['speed'],
                'skill' => (int) $data['skill'],
                'passing' => (int) $data['passing'],
                'shooting' => (int) $data['shooting'],
                'defense' => (int) $data['defense'],
                'likes_goalie' => (bool) ($data['likes_goalie'] ?? false),
                'goalkeeping' => (int) $data['goalkeeping'],
                'self_eval_completed_at' => now(),
            ],
        );

        return back()->with('status', 'Perfil actualizado.');
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(array_keys(Themes::all()))],
        ]);

        $request->user()->update(['theme' => $validated['theme']]);

        return response()->json(['ok' => true]);
    }
}
