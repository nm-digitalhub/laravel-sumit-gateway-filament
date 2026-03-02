<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyClientPlugin;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class ClientSubscriptionResource extends Resource
{
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = Subscription::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyClientPlugin
    {
        return OfficeGuyClientPlugin::get();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Show only subscriptions for the current authenticated client
        if (auth()->check()) {
            $client = auth()->user()->client;

            if (! $client) {
                // No client found - return empty query
                return parent::getEloquentQuery()->whereRaw('1 = 0');
            }

            // Auto-sync subscriptions and documents from SUMIT before querying
            if ($client->sumit_customer_id) {
                try {
                    // Sync subscriptions
                    \OfficeGuy\LaravelSumitGateway\Services\SubscriptionService::syncFromSumit($client, includeInactive: true);

                    // Sync documents (invoices) for all subscriptions
                    \OfficeGuy\LaravelSumitGateway\Services\DocumentService::syncAllForCustomer(
                        (int) $client->sumit_customer_id,
                        \Carbon\Carbon::now()->subYears(5),
                        \Carbon\Carbon::now()->addYear()
                    );
                } catch (\Exception $e) {
                    // Log error but don't fail the query
                    \Illuminate\Support\Facades\Log::error('Failed to sync from SUMIT', [
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Filter by SUMIT customer ID (not Laravel user_id) because some subscriptions
            // may have user_id=NULL but sumit_customer_id populated from SUMIT sync
            $query->where('sumit_customer_id', $client->getSumitCustomerId());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('פרטי מנוי')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('שם המנוי')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('סכום')
                            ->prefix(fn ($record) => $record?->currency ?? 'ILS')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('מטבע')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('סטטוס')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('מחזור חיוב')
                    ->schema([
                        Forms\Components\TextInput::make('interval_months')
                            ->label('מחזור (חודשים)')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_cycles')
                            ->label('סה"כ מחזורים')
                            ->placeholder('ללא הגבלה')
                            ->disabled(),
                        Forms\Components\TextInput::make('completed_cycles')
                            ->label('מחזורים שהושלמו')
                            ->disabled(),
                        Forms\Components\TextInput::make('recurring_id')
                            ->label('מזהה SUMIT')
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('לוח זמנים')
                    ->schema([
                        Forms\Components\TextInput::make('next_charge_at')
                            ->label('חיוב הבא')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_charged_at')
                            ->label('חיוב אחרון')
                            ->disabled(),
                        Forms\Components\TextInput::make('trial_ends_at')
                            ->label('סיום תקופת ניסיון')
                            ->disabled(),
                        Forms\Components\TextInput::make('expires_at')
                            ->label('תאריך תפוגה')
                            ->disabled(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('שם המנוי')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('סכום')
                    ->money(fn ($record) => $record->currency ?? 'ILS')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'cancelled', 'failed', 'expired' => 'danger',
                        'paused' => 'secondary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'פעיל',
                        'pending' => 'ממתין',
                        'cancelled' => 'מבוטל',
                        'failed' => 'נכשל',
                        'expired' => 'פג תוקף',
                        'paused' => 'מושהה',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('interval_months')
                    ->label('מחזור (חודשים)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_charge_at')
                    ->label('חיוב הבא')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_charged_at')
                    ->label('חיוב אחרון')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_cycles')
                    ->label('מחזורים שולמו')
                    ->formatStateUsing(fn ($state, $record) => $record->documentsMany()->wherePivot('amount', '>', 0)->count())
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('סה"כ חשבוניות')
                    ->formatStateUsing(fn ($state, $record) => $record->documentsMany()->count())
                    ->badge()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_paid')
                    ->label('סה"כ שולם')
                    ->formatStateUsing(
                        // Sum the pivot amounts for this subscription across all documents
                        fn ($state, $record) => $record->documentsMany()
                            ->wherePivot('amount', '>', 0)
                            ->get()
                            ->sum('pivot.amount')
                    )
                    ->money(fn ($record) => $record->currency ?? 'ILS')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'active' => 'פעיל',
                        'pending' => 'ממתין',
                        'cancelled' => 'מבוטל',
                        'failed' => 'נכשל',
                        'expired' => 'פג תוקף',
                        'paused' => 'מושהה',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->label('צפייה'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('אין מנויים')
            ->emptyStateDescription('לא נמצאו מנויים פעילים');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientSubscriptions::route('/'),
            'view' => Pages\ViewClientSubscription::route('/{record}'),
        ];
    }
}
