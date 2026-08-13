<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Espelha o UserResource::getEloquentQuery(): administradores não entram na conta.
        $users = fn () => User::where('is_admin', false);

        return [
            Stat::make('Total de usuários', $users()->count())
                ->icon(Heroicon::OutlinedUsers)
                ->color('gray'),
            Stat::make('Cadastrados este mês', $users()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count())
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('warning'),
            Stat::make('Motoboys', $users()->whereHas('profile', fn ($query) => $query->where('role', 'courier'))->count())
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary'),
            Stat::make('Donos de estabelecimento', $users()->whereHas('addresses')->count())
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('success'),
        ];
    }
}
