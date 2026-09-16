<?php

namespace Tests\Feature;

use App\Models\MatchTeam;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Partido $openMatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->organizer()->create();
        $players = User::factory(4)->create();

        $this->openMatch = Partido::factory()->create(['created_by' => $this->organizer->id]);
        foreach ($players as $i => $p) {
            $this->openMatch->entries()->create(['user_id' => $p->id, 'role' => 'going', 'list_order' => $i + 1]);
        }
    }

    public function test_main_pages_render_for_authenticated_user(): void
    {
        $this->actingAs($this->organizer)->get('/dashboard')->assertOk();
        $this->actingAs($this->organizer)->get('/matches')->assertOk();
        $this->actingAs($this->organizer)->get('/matches/create')->assertOk();
        $this->actingAs($this->organizer)->get('/matches/' . $this->openMatch->id)->assertOk();
        $this->actingAs($this->organizer)->get('/matches/' . $this->openMatch->id . '/ratings')->assertOk();
        $this->actingAs($this->organizer)->get('/stats')->assertOk();
        $this->actingAs($this->organizer)->get('/stats/' . $this->organizer->id)->assertOk();
        $this->actingAs($this->organizer)->get('/profile')->assertOk();
    }

    public function test_organizer_can_define_whatsapp_group_and_it_shows_on_match_page(): void
    {
        $this->actingAs($this->organizer)->patch('/profile', [
            'name' => $this->organizer->name,
            'email' => null,
            'phone' => null,
            'whatsapp_group' => 'https://chat.whatsapp.com/AbCd1234',
            'likes_goalie' => 1,
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'overall' => 5, 'goalkeeping' => 8,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->organizer->id,
            'whatsapp_group' => 'https://chat.whatsapp.com/AbCd1234',
        ]);

        $this->assertDatabaseHas('players', [
            'user_id' => $this->organizer->id,
            'likes_goalie' => 1,
            'goalkeeping' => 8,
        ]);

        $this->actingAs($this->organizer)
            ->get('/matches/' . $this->openMatch->id)
            ->assertOk()
            ->assertSee('chat.whatsapp.com/AbCd1234');
    }

    public function test_numeric_widgets_render(): void
    {
        $this->actingAs($this->organizer)
            ->get('/matches/' . $this->openMatch->id)
            ->assertOk()
            ->assertSee('stepper-btn', false);

        $this->actingAs($this->organizer)
            ->get('/matches/' . $this->openMatch->id . '/ratings')
            ->assertOk()
            ->assertSee('scale-opt', false)
            ->assertSee('Arco');

        $this->actingAs($this->organizer)
            ->get('/profile')
            ->assertOk()
            ->assertSee('scale-opt', false)
            ->assertSee('¿Te gusta ir al arco?')
            ->assertSee('Arco');
    }

    public function test_share_message_shows_for_any_user_with_going_list_when_no_teams(): void
    {
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->get('/matches/' . $this->openMatch->id)
            ->assertOk()
            ->assertSee('Compartir este partido')
            ->assertSee('Anotados')
            ->assertSee('wa.me/?text=', false);
    }

    public function test_share_message_lists_players_by_team_when_generated(): void
    {
        $alfa = User::factory()->create(['name' => 'Alfa']);
        $beta = User::factory()->create(['name' => 'Beta']);
        $match = Partido::factory()->create([
            'created_by' => $this->organizer->id,
            'status' => Partido::STATUS_FINISHED,
        ]);
        $match->teams()->create(['user_id' => $alfa->id, 'team' => MatchTeam::TEAM_A]);
        $match->teams()->create(['user_id' => $beta->id, 'team' => MatchTeam::TEAM_B]);

        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->get('/matches/' . $match->id)
            ->assertOk()
            ->assertSee('*Equipo A*')
            ->assertSee('Alfa')
            ->assertSee('*Equipo B*')
            ->assertSee('Beta')
            ->assertDontSee('Anotados');
    }
}
