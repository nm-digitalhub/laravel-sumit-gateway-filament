<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Bus;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;
use OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BulkTokenSyncJob;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class TokenResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = OfficeGuyToken::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Token Information')
                    ->schema([
                        Forms\Components\TextInput::make('token')
                            ->label('Token')
                            ->disabled(),
                        Forms\Components\TextInput::make('gateway_id')
                            ->label('Gateway')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Set as Default Token')
                            ->helperText('Mark this token as the default payment method for this customer')
                            ->live()
                            ->afterStateUpdated(function ($state, $record): void {
                                if ($state && $record) {
                                    // Get owner's SUMIT customer ID
                                    $owner = $record->owner;
                                    $client = $owner?->client ?? $owner;
                                    $sumitCustomerId = $client?->sumit_customer_id ?? null;

                                    if ($sumitCustomerId) {
                                        // Sync with SUMIT first
                                        $result = PaymentService::setPaymentMethodForCustomer(
                                            $sumitCustomerId,
                                            $record->token,
                                            $record->metadata ?? []
                                        );

                                        if (! $result['success']) {
                                            Notification::make()
                                                ->title('Failed to update SUMIT')
                                                ->body($result['error'] ?? 'Unknown error')
                                                ->danger()
                                                ->send();

                                            return;
                                        }
                                    }

                                    // Update local database
                                    $record->setAsDefault();
                                }
                            }),
                    ])->columns(3),

                Schemas\Components\Section::make('Card Details')
                    ->description('Card information is read-only and synced from SUMIT')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_four')
                            ->label('Last 4 Digits')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiry_month')
                            ->label('Expiry Month')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiry_year')
                            ->label('Expiry Year')
                            ->disabled(),
                        Forms\Components\TextInput::make('citizen_id')
                            ->label('Citizen ID')
                            ->disabled(),
                    ])->columns(5),

                Schemas\Components\Section::make('Owner Information')
                    ->description('Token owner information (read-only)')
                    ->schema([
                        Forms\Components\TextInput::make('owner_type')
                            ->label('Owner Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('owner_id')
                            ->label('Owner ID')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Admin Notes & Metadata')
                    ->description('Internal notes and additional data (editable by admin)')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->helperText('Internal notes visible only to administrators')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Raw Metadata from SUMIT')
                            ->helperText('Technical data from SUMIT API (view only)')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_four')
                    ->label('Card Number')
                    ->formatStateUsing(fn ($state): string => '**** **** **** ' . $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_month')
                    ->label('Expiry')
                    ->formatStateUsing(
                        fn ($record): string => $record->expiry_month . '/' . substr((string) $record->expiry_year, -2)
                    )
                    ->badge()
                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn ($state): string => class_basename($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Tokens'),
                Tables\Filters\SelectFilter::make('card_type')
                    ->label('Card Type')
                    ->options([
                        'card' => 'Card',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('sync_from_sumit')
                    ->label('Sync from SUMIT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Token from SUMIT')
                    ->modalDescription('This will fetch the latest token data from SUMIT API and update the local record.')
                    ->action(function (\OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken $record): void {
                        $result = \OfficeGuy\LaravelSumitGateway\Services\TokenService::syncTokenFromSumit($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Token synced successfully')
                                ->body('Token data updated from SUMIT')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('test_payment')
                    ->label('Test Payment')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Test Payment (₪1)')
                    ->modalDescription('This will charge ₪1 to verify the token is working. The charge can be cancelled later.')
                    ->action(function ($record): void {
                        $owner = $record->owner;
                        $client = $owner->client ?? $owner;
                        $sumitCustomerId = $client->sumit_customer_id ?? null;

                        if (! $sumitCustomerId) {
                            Notification::make()
                                ->title('Test failed')
                                ->body('SUMIT customer ID not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = \OfficeGuy\LaravelSumitGateway\Services\PaymentService::testPayment(
                            $record->token,
                            $sumitCustomerId
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title('Test payment successful')
                                ->body('Token is valid. Transaction ID: ' . ($result['transaction_id'] ?? 'N/A'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Test payment failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->visible(fn ($record): bool => ! $record->is_default && ! $record->deleted_at)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // Get owner's SUMIT customer ID
                        $owner = $record->owner;
                        $client = $owner?->client ?? $owner;
                        $sumitCustomerId = $client?->sumit_customer_id ?? null;

                        if ($sumitCustomerId) {
                            // Sync with SUMIT first
                            $result = PaymentService::setPaymentMethodForCustomer(
                                $sumitCustomerId,
                                $record->token,
                                $record->metadata ?? []
                            );

                            if (! $result['success']) {
                                Notification::make()
                                    ->title('Failed to update SUMIT')
                                    ->body($result['error'] ?? 'Unknown error')
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        // Update local database
                        $record->setAsDefault();
                        Notification::make()
                            ->title('Token set as default')
                            ->body($sumitCustomerId ? 'Updated in SUMIT and local database' : 'Updated in local database only')
                            ->success()
                            ->send();
                    }),
                Action::make('remove_from_sumit')
                    ->label('Remove from SUMIT')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record): bool => ! $record->deleted_at)
                    ->requiresConfirmation()
                    ->modalHeading('Remove Token from SUMIT')
                    ->modalDescription('This will remove the active payment method from SUMIT. This action cannot be undone!')
                    ->action(function ($record): void {
                        $owner = $record->owner;
                        $client = $owner->client ?? $owner;
                        $sumitCustomerId = $client->sumit_customer_id ?? null;

                        if (! $sumitCustomerId) {
                            Notification::make()
                                ->title('Removal failed')
                                ->body('SUMIT customer ID not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = \OfficeGuy\LaravelSumitGateway\Services\PaymentService::removePaymentMethodForCustomer($sumitCustomerId);

                        if ($result['success']) {
                            $record->delete(); // Soft delete locally
                            Notification::make()
                                ->title('Token removed from SUMIT')
                                ->body('Payment method has been removed')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Removal failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('deactivate')
                    ->label('Deactivate Locally')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn ($record): bool => ! $record->deleted_at)
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate Token')
                    ->modalDescription('This will soft-delete the token locally only. It will still exist in SUMIT.')
                    ->action(function ($record): void {
                        $record->delete();
                        Notification::make()
                            ->title('Token deactivated locally')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Delete Permanently')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Native Filament v5 bulk action with Bus::batch()
                    BulkAction::make('sync_all_from_sumit')
                        ->label('Sync All from SUMIT')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(__('officeguy::messages.bulk_sync_confirm'))
                        ->modalDescription(__('officeguy::messages.bulk_sync_desc'))
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $batch = Bus::batch(
                                $records->map(fn ($record) => new BulkTokenSyncJob($record))
                            )->dispatch();

                            Notification::make()
                                ->title('Bulk sync started')
                                ->body("Batch ID: {$batch->id}. Processing {$records->count()} tokens.")
                                ->info()
                                ->send();
                        }),

                    // Legacy synchronous bulk action (for backwards compatibility)
                    BulkAction::make('sync_all_from_sumit_sync')
                        ->label('Sync All from SUMIT (Sync)')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => ! config('officeguy.bulk_actions.enabled', false) || config('officeguy.bulk_actions.enable_legacy_actions', false))
                        ->modalHeading('Sync Selected Tokens from SUMIT')
                        ->modalDescription('This will fetch the latest data from SUMIT for all selected tokens.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $successCount = 0;
                            $failCount = 0;

                            foreach ($records as $record) {
                                $result = \OfficeGuy\LaravelSumitGateway\Services\TokenService::syncTokenFromSumit($record);
                                if ($result['success']) {
                                    $successCount++;
                                } else {
                                    $failCount++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk sync completed')
                                ->body("Successfully synced: {$successCount}, Failed: {$failCount}")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListTokens::route('/'),
            'view' => Pages\ViewToken::route('/{record}'),
            'edit' => Pages\EditToken::route('/{record}/edit'),
            'add-card' => Pages\AddNewCard::route('/add-card/{ownerType}/{ownerId}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Tokens are created via payment flow, not manually
    }

    public static function canEdit($record): bool
    {
        return true; // Allow editing for admin notes and is_default
    }

    public static function getNavigationBadge(): ?string
    {
        $expiredCount = static::getModel()::query()
            ->get()
            ->filter(fn ($token) => $token->isExpired())
            ->count();

        return $expiredCount > 0 ? (string) $expiredCount : null;
    }
}
