<?php

namespace App\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Complete seu cadastro — ZunMoto')]
class Onboarding extends Component
{
    /** 'courier' (motoboy) | 'business' (restaurante) */
    public string $role = 'courier';

    public string $name = '';

    public string $birthDate = '';

    public string $phone = '';

    public string $cep = '';

    public string $street = '';

    public string $number = '';

    public string $district = '';

    public string $city = '';

    public bool $cepBusy = false;

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

    /** Fills street / district / city from the CEP (ViaCEP), like the address forms. */
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
                $this->street = $data['logradouro'] ?: $this->street;
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

    public function submit()
    {
        $rules = [
            'role' => ['required', 'in:courier,business'],
            'name' => ['required', 'min:2'],
            'phone' => ['required'],
            'district' => ['required', 'min:2'],
            'city' => ['required', 'min:2'],
        ];
        if ($this->role === 'courier') {
            $rules['birthDate'] = ['required'];
        } else {
            // Only the establishment's address needs street-level precision;
            // the courier's own CEP/bairro/cidade is enough (not used for geocoding).
            $rules['street'] = ['required', 'min:2'];
            $rules['number'] = ['required'];
        }

        $this->validate($rules);

        $phoneDigits = preg_replace('/\D/', '', $this->phone);
        if (strlen($phoneDigits) < 10) {
            $this->addError('phone', 'Telefone inválido.');

            return null;
        }

        $birth = null;
        if ($this->role === 'courier') {
            $birth = $this->parseBrDate($this->birthDate);
            if (! $birth) {
                $this->addError('birthDate', 'Data de nascimento inválida (use DD/MM/AAAA).');

                return null;
            }
            if (Carbon::parse($birth)->isAfter(now()->subYears(18))) {
                $this->addError('birthDate', 'Você precisa ter pelo menos 18 anos para se cadastrar como motoboy.');

                return null;
            }
        }

        $user = Auth::user();
        $user->profile()->update([
            'role' => $this->role,
            'name' => trim($this->name),
            'phone' => $phoneDigits,
            'street' => $this->role === 'business' ? trim($this->street) : null,
            'street_number' => $this->role === 'business' ? trim($this->number) : null,
            'district' => trim($this->district),
            'city' => trim($this->city),
            'birth_date' => $birth,
            'onboarded_at' => now(),
        ]);
        $user->update(['name' => trim($this->name)]);

        return $this->redirect(route('shifts.index'), navigate: true);
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
