<?php

namespace App\Livewire;

use App\Support\Catalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Complete seu cadastro — ZunMoto')]
class Onboarding extends Component
{
    /** 1 = role + personal data; 2 = vehicle (courier only). */
    public int $step = 1;

    /** 'courier' (motoboy) | 'business' (restaurante) */
    public string $role = 'courier';

    public string $name = '';

    public string $birthDate = '';

    public string $phone = '';

    public string $cep = '';

    public string $district = '';

    public string $city = '';

    public bool $cepBusy = false;

    public string $vehicle = '';

    public function mount(): void
    {
        // Already onboarded (e.g. reached this URL directly): nothing to do here.
        if (Auth::user()->profile?->isOnboarded()) {
            $this->redirect(route('shifts.index'), navigate: true);

            return;
        }

        $profile = Auth::user()->profile;
        $this->name = $profile?->name ?: Auth::user()->name;
        $this->phone = $profile?->phone ?? '';
    }

    public function setRole(string $role): void
    {
        $this->role = $role === 'business' ? 'business' : 'courier';
    }

    /** Fills district / city from the CEP (ViaCEP), like the address forms. */
    public function lookupCep(): void
    {
        $digits = preg_replace('/\D/', '', $this->cep);
        if (strlen($digits) !== 8) {
            return;
        }

        $this->cepBusy = true;
        try {
            $data = Http::timeout(6)->get("https://viacep.com.br/ws/{$digits}/json/")->json();
            if (is_array($data) && empty($data['erro'])) {
                $this->district = $data['bairro'] ?: $this->district;
                $this->city = ($data['localidade'] ?? '')
                    ? trim(($data['localidade'] ?? '').(isset($data['uf']) ? ' - '.$data['uf'] : ''))
                    : $this->city;
            } else {
                $this->dispatch('toast', message: 'CEP não encontrado.');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Falha ao consultar o CEP.');
        } finally {
            $this->cepBusy = false;
        }
    }

    /** Step 1: role + personal data. Business finishes here; courier moves on to pick a vehicle. */
    public function nextStep()
    {
        $rules = [
            'role' => ['required', 'in:courier,business'],
            'name' => ['required', 'min:2'],
            'birthDate' => ['required'],
            'phone' => ['required'],
        ];
        if ($this->role === 'courier') {
            // The establishment's real address is registered later, per venue,
            // in "Meus Endereços" — asking for one here wouldn't be tied to
            // anything. The courier's own CEP/bairro/cidade doubles as the
            // "cidade base" used in Configurações to filter nearby shifts.
            $rules['district'] = ['required', 'min:2'];
            $rules['city'] = ['required', 'min:2'];
        }

        $this->validate($rules);

        $phoneDigits = preg_replace('/\D/', '', $this->phone);
        if (strlen($phoneDigits) < 10) {
            $this->addError('phone', 'Telefone inválido.');

            return null;
        }

        $birth = $this->parseBrDate($this->birthDate);
        if (! $birth) {
            $this->addError('birthDate', 'Data de nascimento inválida (use DD/MM/AAAA).');

            return null;
        }

        // Motoboy: mínimo 16 anos. Restaurante (responsável pelo cadastro): mínimo 18 anos.
        $minAge = $this->role === 'business' ? 18 : 16;
        if (Carbon::parse($birth)->isAfter(now()->subYears($minAge))) {
            $this->addError('birthDate', $this->role === 'business'
                ? 'Você precisa ter pelo menos 18 anos para se cadastrar como estabelecimento.'
                : 'Você precisa ter pelo menos 16 anos para se cadastrar como motoboy.');

            return null;
        }

        $user = Auth::user();
        $user->profile()->update([
            'role' => $this->role,
            'name' => trim($this->name),
            'phone' => $phoneDigits,
            'district' => $this->role === 'courier' ? trim($this->district) : null,
            'city' => $this->role === 'courier' ? trim($this->city) : null,
            'birth_date' => $birth,
        ]);
        $user->update(['name' => trim($this->name)]);

        // Restaurante não escolhe veículo: cadastro termina aqui.
        if ($this->role === 'business') {
            $user->profile()->update(['onboarded_at' => now()]);

            return $this->redirect(route('shifts.index'), navigate: true);
        }

        $this->step = 2;

        return null;
    }

    public function setVehicle(string $vehicle): void
    {
        if ($vehicle === 'moto' && $this->isMinor) {
            return;
        }
        if (in_array($vehicle, Catalog::VEHICLE_OPTIONS, true)) {
            $this->vehicle = $vehicle;
        }
    }

    /** Step 2 (courier only): pick a vehicle and finish onboarding. */
    public function finish()
    {
        $this->validate([
            'vehicle' => ['required', 'in:'.implode(',', Catalog::VEHICLE_OPTIONS)],
        ], [], ['vehicle' => 'veículo']);

        if ($this->vehicle === 'moto' && $this->isMinor) {
            $this->addError('vehicle', 'Você precisa ter 18 anos para escolher moto.');

            return null;
        }

        Auth::user()->profile()->update([
            'vehicle' => $this->vehicle,
            'onboarded_at' => now(),
        ]);

        return $this->redirect(route('shifts.index'), navigate: true);
    }

    /** Whether the birth date entered in step 1 makes the account under 18. */
    #[Computed]
    public function isMinor(): bool
    {
        $birth = $this->parseBrDate($this->birthDate);

        return $birth && Carbon::parse($birth)->isAfter(now()->subYears(18));
    }

    protected function parseBrDate(string $value): ?string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    public function render()
    {
        return view('livewire.onboarding');
    }
}
