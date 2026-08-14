<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ShiftsRelationManager;
use App\Filament\Resources\Users\Widgets\UserStatsOverview;
use App\Models\Application;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@test.dev',
            'password' => 'secret123',
            'is_admin' => true,
        ]);
    }

    public function test_admin_can_access_user_resource_page(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/users')->assertOk();
    }

    public function test_regular_user_cannot_access_user_resource_page(): void
    {
        $regular = User::create(['name' => 'Maria', 'email' => 'maria'.uniqid().'@test.dev', 'password' => 'secret123']);
        $this->actingAs($regular);

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_listing_shows_regular_users_but_not_admins(): void
    {
        $this->actingAs($this->admin());

        $courier = User::create(['name' => 'João Motoboy', 'email' => 'joao'.uniqid().'@test.dev', 'password' => 'secret123']);
        $otherAdmin = User::create([
            'name' => 'Outro Admin',
            'email' => 'outroadmin'.uniqid().'@test.dev',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('João Motoboy')
            ->assertDontSee('Outro Admin');
    }

    public function test_listing_works_for_users_with_and_without_an_establishment(): void
    {
        $this->actingAs($this->admin());

        $withAddress = User::create(['name' => 'Motoboy Com Loja', 'email' => 'com'.uniqid().'@test.dev', 'password' => 'secret123']);
        $withAddress->addresses()->create([
            'label' => 'Depósito',
            'street' => 'Av Paulista',
            'number' => '100',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
        ]);

        $withoutAddress = User::create(['name' => 'Motoboy Sem Loja', 'email' => 'sem'.uniqid().'@test.dev', 'password' => 'secret123']);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('Motoboy Com Loja')
            ->assertSee('Motoboy Sem Loja');
    }

    public function test_admin_can_view_a_user_with_an_establishment(): void
    {
        $this->actingAs($this->admin());

        $user = User::create(['name' => 'Ana Souza', 'email' => 'ana'.uniqid().'@test.dev', 'password' => 'secret123']);
        $user->profile()->update(['phone' => '11999990000', 'city' => 'São Paulo']);
        $user->addresses()->create([
            'label' => 'Restaurante da Ana',
            'street' => 'Av Paulista',
            'number' => '100',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
        ]);

        $this->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Restaurante da Ana');
    }

    public function test_admin_can_view_a_user_without_an_establishment(): void
    {
        $this->actingAs($this->admin());

        $user = User::create(['name' => 'João Silva', 'email' => 'joaov'.uniqid().'@test.dev', 'password' => 'secret123']);

        $this->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertSee('João Silva');
    }

    public function test_view_page_lists_shifts_created_by_the_user(): void
    {
        $this->actingAs($this->admin());

        $owner = User::create(['name' => 'Dono Teste', 'email' => 'dono'.uniqid().'@test.dev', 'password' => 'secret123']);
        $other = User::create(['name' => 'Outro Dono', 'email' => 'outro'.uniqid().'@test.dev', 'password' => 'secret123']);

        Shift::create([
            'creator_id' => $owner->id,
            'creator_role' => 'business',
            'venue' => 'Hamburgueria da Ana',
            'region' => 'Centro',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'daily_rate' => 150,
            'couriers_needed' => 1,
            'status' => 'available',
            'lat' => 0,
            'lng' => 0,
        ]);
        Shift::create([
            'creator_id' => $other->id,
            'creator_role' => 'business',
            'venue' => 'Pizzaria do Outro',
            'region' => 'Zona Sul',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'daily_rate' => 150,
            'couriers_needed' => 1,
            'status' => 'available',
            'lat' => 0,
            'lng' => 0,
        ]);

        Livewire::test(ShiftsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->assertSee('Hamburgueria da Ana')
            ->assertDontSee('Pizzaria do Outro');
    }

    public function test_view_page_shows_the_number_of_interested_couriers_per_shift(): void
    {
        $this->actingAs($this->admin());

        $owner = User::create(['name' => 'Dono Teste', 'email' => 'dono'.uniqid().'@test.dev', 'password' => 'secret123']);
        $shift = Shift::create([
            'creator_id' => $owner->id,
            'creator_role' => 'business',
            'venue' => 'Hamburgueria da Ana',
            'region' => 'Centro',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'daily_rate' => 150,
            'couriers_needed' => 2,
            'status' => 'available',
            'lat' => 0,
            'lng' => 0,
        ]);

        Application::create(['shift_id' => $shift->id, 'user_id' => User::create(['name' => 'C1', 'email' => 'c1'.uniqid().'@test.dev', 'password' => 'secret123'])->id]);
        Application::create(['shift_id' => $shift->id, 'user_id' => User::create(['name' => 'C2', 'email' => 'c2'.uniqid().'@test.dev', 'password' => 'secret123'])->id]);

        Livewire::test(ShiftsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->assertSee('2');
    }

    public function test_interested_couriers_action_opens_and_lists_them(): void
    {
        $this->actingAs($this->admin());

        $owner = User::create(['name' => 'Dono Teste', 'email' => 'dono'.uniqid().'@test.dev', 'password' => 'secret123']);
        $shift = Shift::create([
            'creator_id' => $owner->id,
            'creator_role' => 'business',
            'venue' => 'Hamburgueria da Ana',
            'region' => 'Centro',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'daily_rate' => 150,
            'couriers_needed' => 2,
            'status' => 'available',
            'lat' => 0,
            'lng' => 0,
        ]);

        $courier = User::create(['name' => 'Motoboy Interessado', 'email' => 'ci'.uniqid().'@test.dev', 'password' => 'secret123']);
        Application::create(['shift_id' => $shift->id, 'user_id' => $courier->id]);

        Livewire::test(ShiftsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->mountTableAction('viewInterested', $shift)
            ->assertMountedActionModalSee('Motoboy Interessado')
            ->assertMountedActionModalSee('Interessado');
    }

    public function test_interested_couriers_action_is_hidden_without_applications(): void
    {
        $this->actingAs($this->admin());

        $owner = User::create(['name' => 'Dono Teste', 'email' => 'dono'.uniqid().'@test.dev', 'password' => 'secret123']);
        $shift = Shift::create([
            'creator_id' => $owner->id,
            'creator_role' => 'business',
            'venue' => 'Hamburgueria da Ana',
            'region' => 'Centro',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'daily_rate' => 150,
            'couriers_needed' => 2,
            'status' => 'available',
            'lat' => 0,
            'lng' => 0,
        ]);

        Livewire::test(ShiftsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->assertTableActionHidden('viewInterested', $shift);
    }

    public function test_stats_widget_shows_correct_counts(): void
    {
        $this->actingAs($this->admin());

        // U1, U2, U3: motoboy, cadastrados este mês. U3 também tem estabelecimento.
        $u1 = User::create(['name' => 'U1', 'email' => 'u1'.uniqid().'@test.dev', 'password' => 'secret123']);
        User::create(['name' => 'U2', 'email' => 'u2'.uniqid().'@test.dev', 'password' => 'secret123']);
        $u3 = User::create(['name' => 'U3', 'email' => 'u3'.uniqid().'@test.dev', 'password' => 'secret123']);
        $u3->addresses()->create(['label' => 'Depósito', 'street' => 'Av Paulista', 'number' => '100', 'district' => 'Bela Vista', 'city' => 'São Paulo']);

        // U4: estabelecimento, cadastrado este mês, também tem endereço.
        $u4 = User::create(['name' => 'U4', 'email' => 'u4'.uniqid().'@test.dev', 'password' => 'secret123']);
        $u4->profile()->update(['role' => 'business']);
        $u4->addresses()->create(['label' => 'Restaurante', 'street' => 'Av Paulista', 'number' => '200', 'district' => 'Centro', 'city' => 'São Paulo']);

        // U5: estabelecimento, mas cadastrado no mês passado (não deve contar em "este mês").
        $u5 = User::create(['name' => 'U5', 'email' => 'u5'.uniqid().'@test.dev', 'password' => 'secret123']);
        $u5->profile()->update(['role' => 'business']);
        DB::table('users')->where('id', $u5->id)->update(['created_at' => now()->subMonthNoOverflow()]);

        // Total: 5 · Este mês: 4 (u1,u2,u3,u4) · Motoboys: 3 (u1,u2,u3) · Estabelecimentos: 2 (u3,u4)
        Livewire::test(UserStatsOverview::class)
            ->assertSee('Total de usuários')
            ->assertSee('5')
            ->assertSee('Cadastrados este mês')
            ->assertSee('4')
            ->assertSee('Motoboys')
            ->assertSee('3')
            ->assertSee('Donos de estabelecimento')
            ->assertSee('2');
    }
}
