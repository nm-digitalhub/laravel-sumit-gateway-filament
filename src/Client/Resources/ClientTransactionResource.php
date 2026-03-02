<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyClientPlugin;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class ClientTransactionResource extends Resource
{
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = OfficeGuyTransaction::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyClientPlugin
    {
        return OfficeGuyClientPlugin::get();
    }

    public static function getEloquentQuery(): Builder
    {
        // Filter to only show transactions for the authenticated client's SUMIT customer ID
        $client = auth()->user()?->client;

        if (! $client) {
            // No client found - return empty query
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('customer_id', $client->getSumitCustomerId());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_id')
                            ->label('Payment ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('auth_number')
                            ->label('Authorization Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('Date')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Card Information')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_digits')
                            ->label('Card Number')
                            ->formatStateUsing(fn ($state): string => $state ? '****' . $state : '-')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Installments')
                    ->visible(fn ($record): bool => $record?->payments_count > 1)
                    ->schema([
                        Forms\Components\TextInput::make('payments_count')
                            ->label('Number of Payments')
                            ->disabled(),
                        Forms\Components\TextInput::make('first_payment_amount')
                            ->label('First Payment')
                            ->disabled(),
                        Forms\Components\TextInput::make('non_first_payment_amount')
                            ->label('Other Payments')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_digits')
                    ->label('Card')
                    ->formatStateUsing(fn ($state): string => $state ? '****' . $state : '-'),
                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Installments')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('download_document')
                    ->label('Download Document')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record) => $record->document_id)
                    ->url(fn ($record): string => route('officeguy.document.download', ['document' => $record->document_id]), true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientTransactions::route('/'),
            'view' => Pages\ViewClientTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
