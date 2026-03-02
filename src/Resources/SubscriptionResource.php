<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use App\Models\Client;
use App\Models\User;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Bus;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource\Pages;
use OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BulkSubscriptionCancelJob;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

class SubscriptionResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = Subscription::class;

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
                Schemas\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Subscription Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('Currency')
                            ->formatStateUsing(fn ($state): string => match (strtoupper((string) $state)) {
                                '', '0', 'ILS' => '₪ ILS',
                                'USD' => '$ USD',
                                'EUR' => '€ EUR',
                                'GBP' => '£ GBP',
                                default => strtoupper((string) $state),
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Billing Cycle')
                    ->schema([
                        Forms\Components\TextInput::make('interval_months')
                            ->label('Interval (Months)')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_cycles')
                            ->label('Total Cycles')
                            ->formatStateUsing(fn ($state) => $state ?? 'Unlimited')
                            ->disabled(),
                        Forms\Components\TextInput::make('completed_cycles')
                            ->label('Completed Cycles')
                            ->formatStateUsing(function ($record) {
                                $meta = $record?->metadata ?? [];
                                $completed = $record?->completed_cycles ?? ($meta['cycles_completed'] ?? 0);

                                if ((int) $completed === 0 && $record?->id) {
                                    return \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::query()
                                        ->where('subscription_id', $record->id)
                                        ->count();
                                }

                                return $completed;
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('recurring_id')
                            ->label('SUMIT Recurring ID')
                            ->formatStateUsing(fn ($record) => $record?->metadata['sumit_item_id'] ?? $record?->recurring_id)
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('next_charge_at')
                            ->label('Next Charge')
                            ->formatStateUsing(fn ($record) => $record?->next_charge_at
                                ?? optional($record?->metadata)['date_next']
                                ?? null)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_charged_at')
                            ->label('Last Charged')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->last_charged_at ?? ($meta['date_last'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->expires_at ?? ($meta['date_end'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('Subscriber Information')
                    ->schema([
                        Forms\Components\TextInput::make('subscriber_type')
                            ->label('Subscriber Type')
                            ->formatStateUsing(fn ($state): string => match ($state) {
                                'App\\Models\\User', 'App\Models\\User' => 'User',
                                'App\\Models\\Client', 'App\Models\\Client' => 'Client',
                                default => ($state ? class_basename($state) : '-'),
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('subscriber_id')
                            ->label('Subscriber ID')
                            ->formatStateUsing(function ($record) {
                                $subscriber = $record?->subscriber;
                                if ($subscriber?->name) {
                                    return $subscriber->name;
                                }
                                if ($subscriber?->email) {
                                    return $subscriber->email;
                                }

                                return $record?->subscriber_id;
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method_token')
                            ->label('Payment Token ID')
                            ->formatStateUsing(fn ($state) => $state ?: '—')
                            ->disabled(),
                    ])->columns(3),

                Schemas\Components\Section::make('Cancellation')
                    ->schema([
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At')
                            ->disabled(),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2)
                    ->visible(fn ($record): bool => $record?->cancelled_at !== null),

                Schemas\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->disabled(),
                    ])->collapsed(),

                Schemas\Components\Section::make('SUMIT Integration')
                    ->schema([
                        Forms\Components\TextInput::make('sumit_customer_id')
                            ->label('לקוח SUMIT')
                            ->formatStateUsing(fn ($record) => $record?->metadata['sumit_customer_id'] ?? $record?->subscriber_id)
                            ->disabled(),
                        Forms\Components\TextInput::make('recurring_id')
                            ->label('Recurring Item ID')
                            ->formatStateUsing(fn ($record) => $record?->metadata['sumit_recurring_item_id']
                                ?? $record?->metadata['sumit_item_id']
                                ?? $record?->recurring_id)
                            ->disabled(),
                        Forms\Components\TextInput::make('sumit_document_id')
                            ->label('Last Document ID')
                            ->formatStateUsing(function ($record) {
                                if ($record?->metadata['sumit_document_id'] ?? false) {
                                    return $record->metadata['sumit_document_id'];
                                }

                                // Fallback: latest document linked to this subscription
                                $doc = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::query()
                                    ->where('subscription_id', $record?->id)
                                    ->latest('document_id')
                                    ->value('document_id');

                                return $doc ?: null;
                            })
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('sumit_last_sync')
                            ->label('SUMIT Last Sync')
                            ->formatStateUsing(fn ($record) => $record?->metadata['sumit_last_sync'] ?? $record?->updated_at)
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Lifecycle')
                    ->schema([
                        Forms\Components\DateTimePicker::make('current_period_start')
                            ->label('Period Start')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->current_period_start ?? ($meta['date_start'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('current_period_end')
                            ->label('Period End')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->current_period_end ?? ($meta['date_end'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends')
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('next_charge_at')
                            ->label('Next Charge')
                            ->formatStateUsing(fn ($record) => $record?->next_charge_at
                                ?? optional($record?->metadata)['date_next']
                                ?? null)
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_charged_at')
                            ->label('Last Charged')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->last_charged_at ?? ($meta['date_last'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('next_billing_date')
                            ->label('Next Billing Date')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->next_billing_date
                                    ?? ($meta['date_next'] ?? null)
                                    ?? $record?->next_charge_at;

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_billing_date')
                            ->label('Last Billing Date')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->last_billing_date
                                    ?? ($meta['date_last'] ?? null)
                                    ?? $record?->last_charged_at;

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->formatStateUsing(function ($record): ?\Carbon\Carbon {
                                $meta = $record?->metadata ?? [];
                                $date = $record?->expires_at ?? ($meta['date_end'] ?? null);

                                return $date ? Carbon::parse($date) : null;
                            })
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At')
                            ->timezone('Asia/Jerusalem')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('מזהה')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('שם מנוי')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriber_id')
                    ->label('לקוח/משתמש')
                    ->state(fn ($record) => $record->subscriber?->id ?? $record->subscriber_id)
                    ->formatStateUsing(fn ($record) => $record->subscriber?->name ?? $record->subscriber?->email ?? $record->subscriber_id)
                    ->url(function ($record): ?string {
                        $subscriber = $record->subscriber;

                        if (! $subscriber) {
                            return null;
                        }

                        if ($subscriber instanceof Client) {
                            return route('filament.admin.resources.clients.view', ['record' => $subscriber->id]);
                        }

                        if ($subscriber instanceof User) {
                            return route('filament.admin.resources.users.view', ['record' => $subscriber->id]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending', 'paused' => 'warning',
                        'cancelled', 'failed', 'expired' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'paused' => 'heroicon-o-pause-circle',
                        'cancelled' => 'heroicon-o-x-circle',
                        'failed' => 'heroicon-o-exclamation-circle',
                        'expired' => 'heroicon-o-calendar',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('סכום')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sumit_customer_id')
                    ->label('לקוח SUMIT')
                    ->state(fn ($record) => $record->metadata['sumit_customer_id'] ?? $record->subscriber_id)
                    ->toggleable()
                    ->copyable()
                    ->badge(),
                Tables\Columns\TextColumn::make('sumit_recurring_item_id')
                    ->label('פריט מחזורי')
                    ->state(fn ($record) => $record->metadata['sumit_recurring_item_id'] ?? $record->recurring_id)
                    ->toggleable()
                    ->copyable()
                    ->badge(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('הזמנה')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                Tables\Columns\TextColumn::make('interval_months')
                    ->label('מרווח חיוב')
                    ->formatStateUsing(fn ($record) => $record->getIntervalDescription())
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_cycles')
                    ->label('מחזורים')
                    ->formatStateUsing(function ($record): string {
                        $completed = $record->completed_cycles;

                        if ((int) $completed === 0 && $record?->id) {
                            $completed = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::query()
                                ->where('subscription_id', $record->id)
                                ->count();
                        }

                        return $completed . '/' . ($record->total_cycles ?? '∞');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_charge_at')
                    ->label('חיוב הבא')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_charged_at')
                    ->label('חיוב אחרון')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('סיום מחזור')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->state(fn ($record) => $record->expires_at ?? $record->current_period_end ?? null)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_period_start')
                    ->label('תחילת מחזור')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sumit_last_sync')
                    ->label('סנכרון SUMIT')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->state(fn ($record) => $record->metadata['sumit_last_sync'] ?? $record->updated_at)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sumit_document_id')
                    ->label('מסמך SUMIT')
                    ->state(fn ($record) => $record->metadata['sumit_document_id'] ?? null)
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recurring_id')
                    ->label('מזהה מחזורי')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'ממתין',
                        'active' => 'פעיל',
                        'paused' => 'מושהה',
                        'cancelled' => 'בוטל',
                        'expired' => 'פג תוקף',
                        'failed' => 'נכשל',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->label('מטבע')
                    ->options([
                        'ILS' => '₪ ILS',
                        'USD' => '$ USD',
                        'EUR' => '€ EUR',
                        'GBP' => '£ GBP',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('due_for_charge')
                    ->label('Due for Charge')
                    ->query(fn ($query) => $query->due()),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->label('סכום מינימלי'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->label('סכום מקסימלי'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when(
                            $data['amount_from'],
                            fn ($query, $amount) => $query->where('amount', '>=', $amount),
                        )
                        ->when(
                            $data['amount_to'],
                            fn ($query, $amount) => $query->where('amount', '<=', $amount),
                        )),
            ])

            /* 👇👇👇  ❗️כאן הבלוק שתיקנתי ❗️👇👇👇 */
            ->actions([
                ViewAction::make()
                    ->color('info')
                    ->icon('heroicon-o-eye'),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Subscription $record): bool => $record->status === Subscription::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        SubscriptionService::activate($record);
                        Notification::make()->title('Subscription activated')->success()->send();
                    }),
                Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (Subscription $record): bool => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        SubscriptionService::pause($record);
                        Notification::make()->title('Subscription paused')->warning()->send();
                    }),
                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (Subscription $record): bool => $record->status === Subscription::STATUS_PAUSED)
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        SubscriptionService::resume($record);
                        Notification::make()->title('Subscription resumed')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Subscription $record): bool => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Subscription $record, array $data): void {
                        $result = SubscriptionService::cancel($record, $data['reason'] ?? null);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('Subscription cancelled'))
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('Cancellation failed'))
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('charge_now')
                    ->label('Charge Now')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('primary')
                    ->visible(
                        fn (Subscription $record): bool => config('officeguy.subscriptions.enabled', true) &&
                        $record->canBeCharged()
                    )
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        $result = SubscriptionService::processRecurringCharge($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Charge successful')
                                ->body('Payment processed successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Charge failed')
                                ->body($result['message'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            /* 👆 סוף הבלוק שתוקן 👆 */

            ->bulkActions([
                BulkActionGroup::make([
                    // Native Filament v5 bulk action with Bus::batch()
                    BulkAction::make('cancel_selected')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('officeguy::messages.bulk_cancel_confirm'))
                        ->modalDescription(__('officeguy::messages.bulk_cancel_desc'))
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $batch = Bus::batch(
                                $records
                                    ->filter(fn ($record) => $record->canBeCancelled())
                                    ->map(fn ($record) => new BulkSubscriptionCancelJob($record))
                            )->dispatch();

                            Notification::make()
                                ->title('Bulk cancellation started')
                                ->body("Batch ID: {$batch->id}. Processing {$records->count()} subscriptions.")
                                ->info()
                                ->send();
                        }),

                    // Legacy synchronous bulk action (for backwards compatibility)
                    BulkAction::make('cancel_selected_sync')
                        ->label('Cancel Selected (Sync)')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => ! config('officeguy.bulk_actions.enabled', false) || config('officeguy.bulk_actions.enable_legacy_actions', false))
                        ->action(function ($records): void {
                            $successCount = 0;
                            $failCount = 0;

                            foreach ($records as $record) {
                                if ($record->canBeCancelled()) {
                                    $result = SubscriptionService::cancel($record, 'Bulk cancellation');
                                    if ($result['success']) {
                                        $successCount++;
                                    } else {
                                        $failCount++;
                                    }
                                }
                            }

                            if ($successCount > 0 || $failCount > 0) {
                                Notification::make()
                                    ->title(__('Cancelled') . ": {$successCount}, " . __('Failed') . ": {$failCount}")->success()->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('No subscriptions to cancel'))
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('next_charge_at', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::due()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
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

    // duplicate navigation badge removed
}
