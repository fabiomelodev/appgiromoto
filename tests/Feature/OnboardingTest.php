<?php

namespace Tests\Feature;

use App\Livewire\Onboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** A user pending onboarding, as if just created via email/password signup. */
    protected function pendingUser(): User
    {
        $user = User::create([
            'name' => 'João Silva',
            'email' => 'joao'.uniqid().'@test.dev',
            'password' => 'secret123',
        ]);
        $user->profile()->update(['onboarded_at' => null]);

        return $user;
    }

    public function test_pending_user_is_redirected_to_onboarding(): void
    {
        $this->actingAs($this->pendingUser());

        $this->get(route('shifts.index'))->assertRedirect(route('onboarding'));
    }

    public function test_onboarded_user_is_not_redirected(): void
    {
        $user = User::create(['name' => 'Ana', 'email' => 'ana'.uniqid().'@test.dev', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->get(route('shifts.index'))->assertOk();
    }

    public function test_completing_as_courier_sets_role_and_birth_date(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'João Silva')
            ->set('birthDate', '10/05/1990')
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('shifts.index'));

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('courier', $profile->role);
        $this->assertSame('1990-05-10', $profile->birth_date->toDateString());
        $this->assertNull($profile->street, 'motoboy não precisa de rua/número, só CEP/bairro/cidade');
        $this->assertNull($profile->street_number);
        $this->assertSame('Centro', $profile->district);
        $this->assertTrue($profile->isOnboarded());
    }

    public function test_completing_as_business_does_not_require_birth_date(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'business')
            ->set('name', 'Restaurante da Ana')
            ->set('phone', '(11) 99999-0000')
            ->set('street', 'Av Paulista')
            ->set('number', '100')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('shifts.index'));

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('business', $profile->role);
        $this->assertNull($profile->birth_date);
        $this->assertSame('Av Paulista', $profile->street, 'restaurante precisa de rua/número (endereço do estabelecimento)');
        $this->assertSame('100', $profile->street_number);
        $this->assertTrue($profile->isOnboarded());
    }

    public function test_business_requires_street_and_number(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'business')
            ->set('name', 'Restaurante da Ana')
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('submit')
            ->assertHasErrors(['street', 'number']);

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }

    public function test_courier_under_18_is_rejected(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'Jovem Demais')
            ->set('birthDate', now()->subYears(17)->format('d/m/Y'))
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('submit')
            ->assertHasErrors('birthDate');

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }
}
