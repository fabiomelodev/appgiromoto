<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::create([
            'name' => 'Carlos',
            'email' => 'carlos'.uniqid().'@test.dev',
            'password' => 'secret123',
        ]);
    }

    /** An adult courier with complete data, as if it had already been a motoboy before. */
    protected function completeCourier(): User
    {
        $user = $this->user();
        $user->profile()->update([
            'birth_date' => now()->subYears(20)->toDateString(),
            'district' => 'Centro',
            'city' => 'São Paulo',
            'vehicle' => 'bike',
        ]);

        return $user;
    }

    public function test_switch_role_to_business_updates_profile_when_adult(): void
    {
        $user = $this->completeCourier();
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('switchRole', 'business');

        $this->assertSame('business', $user->profile->fresh()->role);
    }

    public function test_switch_role_ignores_invalid_value(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('switchRole', 'admin');

        $this->assertSame('courier', $user->profile->fresh()->role);
    }

    public function test_switch_role_to_business_blocks_minor(): void
    {
        $user = $this->user();
        $user->profile()->update(['birth_date' => now()->subYears(17)->toDateString()]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('switchRole', 'business');

        $this->assertSame('courier', $user->profile->fresh()->role, 'menor de 18 não pode virar estabelecimento');
    }

    public function test_switch_role_to_courier_redirects_when_data_is_missing(): void
    {
        $user = $this->user();
        $user->profile()->update(['role' => 'business', 'birth_date' => now()->subYears(30)->toDateString()]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('switchRole', 'courier')
            ->assertRedirect(route('switch-to-courier'));

        $this->assertSame('business', $user->profile->fresh()->role, 'só troca depois de completar cidade base + veículo');
    }

    public function test_switch_role_to_courier_switches_directly_when_data_already_present(): void
    {
        $user = $this->user();
        $user->profile()->update([
            'role' => 'business',
            'birth_date' => now()->subYears(30)->toDateString(),
            'district' => 'Centro',
            'city' => 'São Paulo',
            'vehicle' => 'bike',
        ]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('switchRole', 'courier');

        $this->assertSame('courier', $user->profile->fresh()->role);
    }
}
