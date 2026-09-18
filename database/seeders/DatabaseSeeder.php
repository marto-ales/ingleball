<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Partido;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organizers = [
            User::factory()->organizer()->create([
                'name' => 'Marta', 'username' => 'marta',
                'email' => 'marta@ingleball.local', 'phone' => '+5491155550001',
            ]),
            User::factory()->organizer()->create([
                'name' => 'Leo', 'username' => 'leo',
                'email' => 'leo@ingleball.local', 'phone' => '+5491155550002',
            ]),
        ];

        $players = User::factory(12)->create();
        $all = collect($organizers)->concat($players);

        // Self-assessments for every registered player.
        foreach ($all as $user) {
            $user->player()->create([
                'speed' => fake()->numberBetween(4, 9),
                'skill' => fake()->numberBetween(4, 9),
                'passing' => fake()->numberBetween(4, 9),
                'shooting' => fake()->numberBetween(4, 9),
                'defense' => fake()->numberBetween(4, 9),
                'overall' => fake()->numberBetween(5, 9),
                'likes_goalie' => fake()->boolean(30),
                'goalkeeping' => fake()->numberBetween(3, 9),
            ]);
        }

        // Peer ratings (accumulated) among registered players.
        foreach ($players as $rater) {
            foreach ($players->where('id', '!=', $rater->id)->random(3) as $rated) {
                Rating::create([
                    'rater_user_id' => $rater->id,
                    'rated_user_id' => $rated->id,
                    'speed' => fake()->numberBetween(4, 9),
                    'skill' => fake()->numberBetween(4, 9),
                    'passing' => fake()->numberBetween(4, 9),
                    'shooting' => fake()->numberBetween(4, 9),
                    'defense' => fake()->numberBetween(4, 9),
                    'overall' => fake()->numberBetween(5, 9),
                    'goalkeeping' => fake()->numberBetween(3, 9),
                ]);
            }
        }

        // Upcoming open match, 5v5 (9 registered + 1 guest = 10 going).
        $saturday = Partido::factory()->create([
            'created_by' => $organizers[0]->id,
            'title' => 'Fútbol de los sábados',
            'played_at' => now()->addDays(6)->setTime(18, 0),
        ]);
        foreach ($all->random(9) as $user) {
            $saturday->entries()->create(['user_id' => $user->id, 'role' => 'going']);
        }
        $guest = Guest::create(['name' => 'Cheto (invitado)', 'phone' => '+5491155550099', 'overall' => 7]);
        $saturday->entries()->create(['guest_id' => $guest->id, 'role' => 'going']);

        // Managed player: sits in the list but has no password (cannot log in).
        $managed = User::factory()->create([
            'name' => 'Pancho (sin cuenta)',
            'username' => 'pancho',
            'phone' => '+5491155550088',
            'is_managed' => true,
        ]);
        $managed->player()->create([
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 5, 'overall' => 6,
        ]);
        $saturday->entries()->create(['user_id' => $managed->id, 'role' => 'going']);

        // Upcoming open match, 4v4.
        $four = Partido::factory()->create([
            'created_by' => $organizers[1]->id,
            'title' => 'Fútbol 4x4',
            'played_at' => now()->addDays(13)->setTime(20, 0),
        ]);
        foreach ($all->random(8) as $user) {
            $four->entries()->create(['user_id' => $user->id, 'role' => 'going']);
        }

        // A finished match with result, goals and MVP (for stats/leaderboard).
        $past = Partido::factory()->create([
            'created_by' => $organizers[0]->id,
            'title' => 'Amistoso pasado',
            'played_at' => now()->subWeek()->setTime(18, 0),
            'status' => 'finished',
            'locked_at' => now()->subWeek(),
        ]);
        $five = $players->random(5)->values();
        foreach ($five as $user) {
            $past->entries()->create(['user_id' => $user->id, 'role' => 'going']);
        }
        $past->result()->create(['winner' => 'A', 'diff' => 2, 'mvp_user_id' => $five->first()->id]);
        $past->goals()->create(['scorer_user_id' => $five->first()->id]);
        $past->goals()->create(['scorer_user_id' => $five->get(1)->id, 'assister_user_id' => $five->get(2)->id]);
    }
}
