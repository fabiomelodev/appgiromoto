<div class="flex min-h-dvh flex-col px-6 py-10">
    <div class="flex flex-1 flex-col justify-center">
        <div class="mb-8 flex flex-col items-center text-center">
            <x-logo :size="64" :withText="false" />
            <h1 class="mt-4 font-display text-2xl font-bold tracking-tight">Complete seu cadastro</h1>
            <p class="mt-2 max-w-xs text-sm text-muted-foreground">
                Só mais um passo para você começar a usar o ZunMoto.
            </p>
        </div>

        <form wire:submit="submit" class="space-y-3 rounded-2xl border border-border bg-card p-5">
            <div>
                <label class="mb-2 block text-xs font-medium text-muted-foreground">Você é...</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="setRole('courier')"
                        class="flex items-center gap-2 rounded-xl border p-3 text-left transition {{ $role === 'courier' ? 'border-primary bg-accent text-foreground shadow-sm glow-orange' : 'border-border bg-surface text-muted-foreground hover:text-foreground' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $role === 'courier' ? 'bg-primary text-primary-foreground' : 'bg-surface-elevated' }}">
                            <x-ui.icon name="bike" class="h-5 w-5" />
                        </span>
                        <span class="text-sm font-semibold">Motoboy</span>
                    </button>
                    <button type="button" wire:click="setRole('business')"
                        class="flex items-center gap-2 rounded-xl border p-3 text-left transition {{ $role === 'business' ? 'border-primary bg-accent text-foreground shadow-sm glow-orange' : 'border-border bg-surface text-muted-foreground hover:text-foreground' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $role === 'business' ? 'bg-primary text-primary-foreground' : 'bg-surface-elevated' }}">
                            <x-ui.icon name="store" class="h-5 w-5" />
                        </span>
                        <span class="text-sm font-semibold">Restaurante</span>
                    </button>
                </div>
                @error('role') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-[11px] text-muted-foreground">Depois você pode alternar entre os dois perfis em Configurações.</p>
            </div>

            <x-ui.field :label="$role === 'business' ? 'Nome do estabelecimento' : 'Nome completo'">
                <x-ui.input wire:model="name" :placeholder="$role === 'business' ? 'Restaurante da Ana' : 'João da Silva'" />
                @error('name') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
            </x-ui.field>

            @if ($role === 'courier')
                <x-ui.field label="Data de nascimento">
                    <x-ui.input wire:model="birthDate" inputmode="numeric" placeholder="DD/MM/AAAA"
                        x-on:input="$el.value = window.maskDate($el.value)" />
                    @error('birthDate') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                </x-ui.field>
            @endif

            <x-ui.field label="Telefone / WhatsApp">
                <x-ui.input wire:model="phone" inputmode="tel" placeholder="(11) 9 9999-0000"
                    x-on:input="$el.value = window.maskPhone($el.value)" />
                @error('phone') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
            </x-ui.field>

            <x-ui.field :label="$role === 'business' ? 'CEP do estabelecimento' : 'CEP'">
                <div class="relative">
                    <x-ui.input wire:model="cep" inputmode="numeric" maxlength="9" placeholder="00000-000"
                        x-on:input="$el.value = window.maskCep($el.value)"
                        x-on:blur="$wire.set('cep', $el.value).then(() => $wire.lookupCep())" />
                    <span wire:loading wire:target="lookupCep" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" /><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    </span>
                </div>
                @error('cep') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
            </x-ui.field>

            @if ($role === 'business')
                <div class="grid grid-cols-[1fr_90px] gap-2">
                    <x-ui.field label="Rua">
                        <x-ui.input wire:model="street" placeholder="Av. Paulista" />
                        @error('street') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                    </x-ui.field>
                    <x-ui.field label="Número">
                        <x-ui.input wire:model="number" placeholder="100" />
                        @error('number') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                    </x-ui.field>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                <x-ui.field label="Bairro">
                    <x-ui.input wire:model="district" placeholder="Centro" />
                    @error('district') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                </x-ui.field>
                <x-ui.field label="Cidade">
                    <x-ui.input wire:model="city" placeholder="São Paulo - SP" />
                    @error('city') <p class="mt-1 text-[11px] font-medium text-destructive">{{ $message }}</p> @enderror
                </x-ui.field>
            </div>

            <x-ui.button type="submit" size="lg" class="w-full glow-orange" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Continuar</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    Aguarde…
                </span>
            </x-ui.button>
        </form>
    </div>
</div>
