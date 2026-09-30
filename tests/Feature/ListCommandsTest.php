<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ListCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_list_shows_codes_and_membership_counts(): void
    {
        $default = Group::where('name', 'Ingleball')->firstOrFail();
        $other = Group::factory()->create();

        User::factory()->inGroup($other, isOrganizer: true)->create();
        User::factory()->alsoInGroup($other)->alsoInGroup($default)->create();

        $this->assertSame(0, Artisan::call('groups:list'));
        $output = Artisan::output();

        $this->assertStringContainsString('Ingleball', $output);
        $this->assertStringContainsString($other->name, $output);
        $this->assertStringContainsString($other->join_code, $output);
        $this->assertStringContainsString('Total: 2 grupos · 3 membresías.', $output);
    }

    public function test_users_list_shows_accounts_with_their_groups_and_roles(): void
    {
        $default = Group::where('name', 'Ingleball')->firstOrFail();
        $other = Group::factory()->create();

        User::factory()->inGroup($other, isOrganizer: true)->create([
            'name' => 'Marta Orga',
            'username' => 'marta',
        ]);
        User::factory()->alsoInGroup($other)->create([
            'name' => 'Tini Blas',
            'username' => 'tini',
        ]);

        $this->assertSame(0, Artisan::call('users:list'));
        $output = Artisan::output();

        $this->assertStringContainsString('marta', $output);
        $this->assertStringContainsString($other->name.' (org)', $output);
        $this->assertStringContainsString('tini', $output);
        $this->assertStringContainsString($default->name, $output);
        $this->assertStringContainsString('Total: 2 cuentas.', $output);
    }

    public function test_users_list_flags_managed_accounts(): void
    {
        User::factory()->create(['username' => 'pancho', 'is_managed' => true]);

        $this->assertSame(0, Artisan::call('users:list'));
        $output = Artisan::output();

        $this->assertStringContainsString('pancho', $output);
    }

    public function test_users_list_filters_by_group(): void
    {
        $other = Group::factory()->create();

        User::factory()->inGroup($other)->create(['name' => 'Zoe En Grupo']);
        User::factory()->create(['name' => 'Alone One']);

        $this->assertSame(0, Artisan::call('users:list', ['--group' => $other->name]));
        $output = Artisan::output();

        $this->assertStringContainsString('Zoe En Grupo', $output);
        $this->assertStringNotContainsString('Alone One', $output);
    }

    public function test_users_list_refuses_an_unknown_group(): void
    {
        $this->assertSame(1, Artisan::call('users:list', ['--group' => 'Nadie']));
    }
}
