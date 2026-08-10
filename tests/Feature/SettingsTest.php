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

    public function test_switch_role_updates_profile(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->assertSame('courier', $user->profile->role);

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
}
