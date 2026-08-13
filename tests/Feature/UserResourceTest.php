<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
