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

    public function test_courier_step_one_saves_data_and_advances_to_vehicle_step(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'João Silva')
            ->set('birthDate', '10/05/1990')
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('courier', $profile->role);
        $this->assertSame('1990-05-10', $profile->birth_date->toDateString());
        $this->assertNull($profile->street, 'motoboy não precisa de rua/número, só CEP/bairro/cidade');
        $this->assertNull($profile->street_number);
        $this->assertSame('Centro', $profile->district);
        $this->assertFalse($profile->isOnboarded(), 'ainda falta escolher o veículo');
    }

    public function test_courier_finishes_onboarding_by_choosing_a_vehicle(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'João Silva')
            ->set('birthDate', '10/05/1990')
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->call('setVehicle', 'bike-eletrica')
            ->assertSet('vehicle', 'bike-eletrica')
            ->call('finish')
            ->assertHasNoErrors()
            ->assertRedirect(route('shifts.index'));

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('bike-eletrica', $profile->vehicle);
        $this->assertTrue($profile->isOnboarded());
    }

    public function test_vehicle_is_required_to_finish(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'João Silva')
            ->set('birthDate', '10/05/1990')
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->call('finish')
            ->assertHasErrors('vehicle');

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }

    public function test_minor_courier_cannot_select_moto_but_can_pick_other_vehicles(): void
    {
        $this->actingAs($this->pendingUser());

        $component = Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'Jovem Demais')
            ->set('birthDate', now()->subYears(17)->format('d/m/Y'))
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->assertHasNoErrors('birthDate')
            ->assertSet('step', 2);

        // Trying to pick "moto" is silently ignored while under 18.
        $component->call('setVehicle', 'moto')->assertSet('vehicle', '');

        $component->call('setVehicle', 'bike')
            ->assertSet('vehicle', 'bike')
            ->call('finish')
            ->assertHasNoErrors()
            ->assertRedirect(route('shifts.index'));

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('bike', $profile->vehicle);
        $this->assertTrue($profile->isOnboarded());
    }

    public function test_minor_courier_is_rejected_if_moto_is_forced_on_finish(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'Jovem Demais')
            ->set('birthDate', now()->subYears(17)->format('d/m/Y'))
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->set('vehicle', 'moto') // bypasses the UI guard in setVehicle()
            ->call('finish')
            ->assertHasErrors('vehicle');

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }

    public function test_courier_under_16_is_rejected(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'courier')
            ->set('name', 'Muito Jovem')
            ->set('birthDate', now()->subYears(15)->format('d/m/Y'))
            ->set('phone', '(11) 99999-0000')
            ->set('district', 'Centro')
            ->set('city', 'São Paulo')
            ->call('nextStep')
            ->assertHasErrors('birthDate')
            ->assertSet('step', 1, 'não avança de etapa sem atingir a idade mínima');

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }

    public function test_completing_as_business_requires_18_and_does_not_ask_for_address_or_vehicle(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'business')
            ->set('name', 'Restaurante da Ana')
            ->set('birthDate', '10/05/1990')
            ->set('phone', '(11) 99999-0000')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertRedirect(route('shifts.index'));

        $profile = auth()->user()->profile->fresh();
        $this->assertSame('business', $profile->role);
        $this->assertSame('1990-05-10', $profile->birth_date->toDateString());
        $this->assertNull($profile->district, 'endereço do estabelecimento é cadastrado depois, em Meus Endereços');
        $this->assertNull($profile->city);
        $this->assertTrue($profile->isOnboarded());
    }

    public function test_business_under_18_is_rejected_even_though_courier_minimum_is_16(): void
    {
        $this->actingAs($this->pendingUser());

        Livewire::test(Onboarding::class)
            ->call('setRole', 'business')
            ->set('name', 'Restaurante do Jovem')
            ->set('birthDate', now()->subYears(17)->format('d/m/Y'))
            ->set('phone', '(11) 99999-0000')
            ->call('nextStep')
            ->assertHasErrors('birthDate');

        $this->assertFalse(auth()->user()->profile->fresh()->isOnboarded());
    }
}
