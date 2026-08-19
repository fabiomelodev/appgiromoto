<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'shifts';

    protected static ?string $title = 'Vagas criadas';

    /** Só leitura: vagas são criadas/editadas pelo próprio app, não pelo painel. */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('venue')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('venue')
                    ->label('Local')
                    ->searchable(),
                TextColumn::make('region')
                    ->label('Região'),
                TextColumn::make('creator_role')
                    ->label('Criada como')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'courier' => 'Motoboy (cobertura)',
                        'business' => 'Estabelecimento',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'courier',
                        'success' => 'business',
                    ]),
                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Horário')
                    ->formatStateUsing(fn ($state, $record) => "{$record->start_time}–{$record->end_time}"),
                TextColumn::make('daily_rate')
                    ->label('Diária')
                    ->money('BRL')
                    ->placeholder('—'),
                TextColumn::make('applications_count')
                    ->label('Interessados')
                    ->counts('applications')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'available' => 'Disponível',
                        'reserved' => 'Reservada',
                        'filled' => 'Preenchida',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'available',
                        'warning' => 'reserved',
                        'primary' => 'filled',
                    ]),
            ])
            ->recordActions([
                Action::make('viewInterested')
                    ->label('')
                    ->tooltip('Ver motoboys interessados')
                    ->icon(Heroicon::OutlinedUsers)
                    ->iconButton()
                    ->visible(fn ($record) => $record->applications_count > 0)
                    ->modalHeading('Motoboys interessados')
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->schema([
                        RepeatableEntry::make('applications')
                            ->label('')
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Nome'),
                                TextEntry::make('user.email')
                                    ->label('E-mail'),
                                TextEntry::make('user.profile.phone')
                                    ->label('Telefone')
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'interested' => 'Interessado',
                                        'accepted' => 'Aceito',
                                        default => $state,
                                    })
                                    ->colors([
                                        'warning' => 'interested',
                                        'success' => 'accepted',
                                    ]),
                                TextEntry::make('created_at')
                                    ->label('Demonstrou interesse em')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
            ]);
    }
}
