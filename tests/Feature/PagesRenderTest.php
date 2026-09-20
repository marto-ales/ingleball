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
        foreach ($players as $p) {
            $this->openMatch->entries()->create(['user_id' => $p->id, 'role' => 'going']);
        }
    }

    public function test_main_pages_render_for_authenticated_user(): void
    {
        $this->actingAs($this->organizer)->get('/dashboard')->assertOk();
        $this->actingAs($this->organizer)->get('/matches')->assertOk();
        $this->actingAs($this->organizer)->get('/matches/create')->assertOk();
        $this->actingAs($this->organizer)->get('/matches/'.$this->openMatch->id)->assertOk();
        $this->actingAs($this->organizer)->get('/matches/'.$this->openMatch->id.'/ratings')->assertOk();
        $this->actingAs($this->organizer)->get('/stats')->assertOk();
        $this->actingAs($this->organizer)->get('/stats/'.$this->organizer->id)->assertOk();
        $this->actingAs($this->organizer)->get('/profile')->assertOk();
    }

    public function test_dashboard_prompts_users_without_email(): void
    {
        $user = User::factory()->create(['email' => null]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Completá tu correo');

        $user->update(['email' => 'fulano@example.com']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Completá tu correo');
    }

    public function test_organizer_can_define_whatsapp_group_and_it_shows_on_match_page(): void
    {
        $this->actingAs($this->organizer)->patch('/profile', [
            'name' => $this->organizer->name,
            'email' => null,
            'phone' => null,
            'whatsapp_group' => 'https://chat.whatsapp.com/AbCd1234',
            'likes_goalie' => 1,
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 8,
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
            ->get('/matches/'.$this->openMatch->id)
            ->assertOk()
            ->assertSee('chat.whatsapp.com/AbCd1234');
    }

    public function test_numeric_widgets_render(): void
    {
        $this->actingAs($this->organizer)
            ->get('/matches/'.$this->openMatch->id)
            ->assertOk()
            ->assertSee('stepper-btn', false);

        $this->actingAs($this->organizer)
            ->get('/matches/'.$this->openMatch->id.'/ratings')
            ->assertOk()
            ->assertSee('type="range"', false)
            ->assertSee('Calificación general')
            ->assertSee('current-name', false)
            ->assertSee('pick-rated', false);

        $this->actingAs($this->organizer)
            ->get('/profile')
            ->assertOk()
            ->assertSee('type="range"', false)
            ->assertSee('data-live="1"', false)
            ->assertSee('¿Te gusta ir al arco?')
            ->assertSee('Arco');

        $this->actingAs($this->organizer)
            ->get('/stats/'.$this->organizer->id)
            ->assertOk()
            ->assertSee('class="radar"', false)
            ->assertSee('Rendimiento reciente');
    }

    public function test_share_message_shows_for_any_user_with_going_list_when_no_teams(): void
    {
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->get('/matches/'.$this->openMatch->id)
            ->assertOk()
            ->assertSee('Compartir este partido')
            ->assertSee('Anotados')
            ->assertSee('wa.me/?text=', false);
    }

    public function test_share_message_includes_substitutes(): void
    {
        $sub = User::factory()->create(['name' => 'Substituto']);
        $match = Partido::factory()->create(['created_by' => $this->organizer->id]);
        $match->entries()->create(['user_id' => $sub->id, 'role' => 'substitute']);

        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->get('/matches/'.$match->id)
            ->assertOk()
            ->assertSee('Compartir este partido')
            ->assertSee('*Suplentes*')
            ->assertSee('Substituto');
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
            ->get('/matches/'.$match->id)
            ->assertOk()
            ->assertSee('*Equipo A*')
            ->assertSee('Alfa')
            ->assertSee('*Equipo B*')
            ->assertSee('Beta')
            ->assertDontSee('Anotados');
    }

    public function test_share_message_includes_cost_per_person(): void
    {
        // 4v4 → 8 jugadores → 10000 / 8 = 1250 (independiente de los anotados)
        $match = Partido::factory()->create(['created_by' => $this->organizer->id, 'field_value' => 10000, 'size' => 4]);

        $this->actingAs($this->organizer)
            ->get('/matches/'.$match->id)
            ->assertOk()
            ->assertSee('Valor: $1.250 por persona', false);
    }

    public function test_index_lists_cost_per_person(): void
    {
        $match = Partido::factory()->create(['created_by' => $this->organizer->id, 'field_value' => 10000, 'size' => 4]);

        $this->actingAs($this->organizer)
            ->get('/matches')
            ->assertOk()
            ->assertSee('Valor: $1.250 por persona', false);
    }

    public function test_dashboard_lists_cost_per_person(): void
    {
        $match = Partido::factory()->create(['created_by' => $this->organizer->id, 'field_value' => 10000, 'size' => 4]);

        $this->actingAs($this->organizer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Valor: $1.250 por persona', false);
    }

    public function test_dashboard_prompts_self_evaluation_when_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Completá tu autoevaluación')
            ->assertSee(route('profile.edit'));
    }

    public function test_dashboard_hides_prompt_after_self_evaluation(): void
    {
        $user = User::factory()->create();
        $user->player()->create([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
            'self_eval_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Completá tu autoevaluación');
    }
}
