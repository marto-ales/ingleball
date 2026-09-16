<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'speed' => ['required', 'integer', 'between:1,10'],
            'skill' => ['required', 'integer', 'between:1,10'],
            'passing' => ['required', 'integer', 'between:1,10'],
            'shooting' => ['required', 'integer', 'between:1,10'],
            'defense' => ['required', 'integer', 'between:1,10'],
            'overall' => ['required', 'integer', 'between:1,10'],
            'likes_goalie' => ['nullable', 'boolean'],
            'goalkeeping' => ['required', 'integer', 'between:1,10'],
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
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
                'overall' => (int) $data['overall'],
                'likes_goalie' => (bool) ($data['likes_goalie'] ?? false),
                'goalkeeping' => (int) $data['goalkeeping'],
            ],
        );

        return back()->with('status', 'Perfil actualizado.');
    }
}
