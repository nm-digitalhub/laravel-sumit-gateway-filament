<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource\Pages;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

class WebhookEventResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = WebhookEvent::class;

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
            ->schema([
                Schemas\Components\Section::make('Event Information')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Select::make('event_type')
                            ->label('Event Type')
                            ->options(WebhookEvent::getEventTypes())
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options(WebhookEvent::getStatuses())
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->disabled(),
                        Forms\Components\TextInput::make('http_status_code')
                            ->label('HTTP Status')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Related Resources')
                    ->columnSpanFull()
                    ->description('Connected resources for automation workflows')
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('document_id')
                            ->label('Document ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('token_id')
                            ->label('Token ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('subscription_id')
                            ->label('Subscription ID')
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('Customer & Amount')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('Currency')
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('Retry Information')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('retry_count')
                            ->label('Retry Count')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('next_retry_at')
                            ->label('Next Retry At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),
                    ])->columns(3),

                Schemas\Components\Section::make('Error Details')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->disabled(),
                    ])
                    ->visible(fn (?Model $record): bool => ! empty($record?->error_message)),

                Schemas\Components\Section::make('נתוני Webhook')
                    ->columnSpanFull()
                    ->schema([
                        ViewField::make('payload')
                            ->view('officeguy::filament.components.api-payload')
                            ->label('Request Payload'),
                        ViewField::make('response')
                            ->view('officeguy::filament.components.api-payload')
                            ->label('Response Data'),
                    ])->collapsed(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            InfolistSection::make('Event Details')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('event_type')
                        ->label('Event Type')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'payment_completed', 'bit_payment_completed' => 'success',
                            'payment_failed' => 'danger',
                            'document_created' => 'info',
                            'subscription_created', 'subscription_charged' => 'warning',
                            'stock_synced' => 'gray',
                            default => 'gray',
                        }),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'sent' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            'retrying' => 'info',
                            default => 'gray',
                        }),
                    TextEntry::make('webhook_url')
                        ->label('Webhook URL')
                        ->copyable()
                        ->url(fn (Model $record): ?string => $record->webhook_url, shouldOpenInNewTab: true),
                    TextEntry::make('http_status_code')
                        ->label('HTTP Status')
                        ->badge()
                        ->color(fn (?int $state): string => match (true) {
                            $state !== null && $state >= 200 && $state < 300 => 'success',
                            $state !== null && $state >= 400 && $state < 500 => 'warning',
                            $state !== null && $state >= 500 => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                    TextEntry::make('sent_at')
                        ->label('Sent At')
                        ->dateTime()
                        ->placeholder('Not sent yet'),
                ])->columns(3),

            InfolistSection::make('Connected Resources')
                ->columnSpanFull()
                ->description('Click to navigate to related records')
                ->schema([
                    TextEntry::make('transaction.payment_id')
                        ->label('Transaction')
                        ->placeholder('No transaction')
                        ->url(fn (Model $record): ?string => $record->transaction_id
                            ? TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                            : null)
                        ->color('primary'),
                    TextEntry::make('document.document_number')
                        ->label('Document')
                        ->placeholder('No document')
                        ->url(fn (Model $record): ?string => $record->document_id
                            ? DocumentResource::getUrl('view', ['record' => $record->document_id])
                            : null)
                        ->color('primary'),
                    TextEntry::make('token.last_digits')
                        ->label('Token')
                        ->formatStateUsing(fn (?string $state): ?string => $state ? '****' . $state : null)
                        ->placeholder('No token')
                        ->url(fn (Model $record): ?string => $record->token_id
                            ? TokenResource::getUrl('view', ['record' => $record->token_id])
                            : null)
                        ->color('primary'),
                    TextEntry::make('subscription.name')
                        ->label('Subscription')
                        ->placeholder('No subscription')
                        ->url(fn (Model $record): ?string => $record->subscription_id
                            ? SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                            : null)
                        ->color('primary'),
                ])->columns(4),

            InfolistSection::make('Customer & Payment')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('customer_email')
                        ->label('Customer Email')
                        ->copyable()
                        ->icon('heroicon-o-envelope'),
                    TextEntry::make('customer_id')
                        ->label('Customer ID')
                        ->copyable(),
                    TextEntry::make('amount')
                        ->label('Amount')
                        ->money(fn (Model $record): string => $record->currency ?? 'ILS'),
                    TextEntry::make('currency')
                        ->badge(),
                ])->columns(4),

            InfolistSection::make('Retry Status')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('retry_count')
                        ->label('Retry Attempts')
                        ->badge()
                        ->color(fn (int $state): string => match (true) {
                            $state === 0 => 'success',
                            $state < 3 => 'warning',
                            default => 'danger',
                        }),
                    TextEntry::make('next_retry_at')
                        ->label('Next Retry')
                        ->dateTime()
                        ->placeholder('No retry scheduled'),
                ])->columns(2)
                ->visible(fn (Model $record): bool => $record->retry_count > 0 || $record->next_retry_at !== null),

            InfolistSection::make('Error Information')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('error_message')
                        ->label('Error Message')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Model $record): bool => ! empty($record->error_message)),

            InfolistSection::make('נתוני Webhook גולמיים')
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    ViewEntry::make('payload')
                        ->label('Request Payload')
                        ->view('officeguy::filament.components.api-payload'),
                ]),

            InfolistSection::make('תגובת Webhook')
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    ViewEntry::make('response')
                        ->label('Response Data')
                        ->view('officeguy::filament.components.api-payload'),
                ])
                ->visible(fn (Model $record): bool => ! empty($record->response)),
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
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'payment_completed', 'bit_payment_completed' => 'success',
                        'payment_failed' => 'danger',
                        'document_created' => 'info',
                        'subscription_created', 'subscription_charged' => 'warning',
                        'stock_synced' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (Model $record): string => $record->getEventTypeLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'retrying' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'retrying' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_status_code')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state !== null && $state >= 200 && $state < 300 => 'success',
                        $state !== null && $state >= 400 && $state < 500 => 'warning',
                        $state !== null && $state >= 500 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (Model $record): string => $record->currency ?? 'ILS')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction.payment_id')
                    ->label('Transaction')
                    ->url(fn (Model $record): ?string => $record->transaction_id
                        ? TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                        : null)
                    ->color('primary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscription.name')
                    ->label('Subscription')
                    ->url(fn (Model $record): ?string => $record->subscription_id
                        ? SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                        : null)
                    ->color('primary')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(WebhookEvent::getEventTypes())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(WebhookEvent::getStatuses())
                    ->multiple(),
                Tables\Filters\Filter::make('has_transaction')
                    ->label('Has Transaction')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('transaction_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('Has Subscription')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('subscription_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_document')
                    ->label('Has Document')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('document_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('failed_deliveries')
                    ->label('Failed Deliveries')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'failed'))
                    ->toggle(),
                Tables\Filters\Filter::make('pending_retry')
                    ->label('Pending Retry')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'retrying')
                        ->where('next_retry_at', '<=', now()))
                    ->toggle(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Model $record): bool => $record->canRetry())
                    ->requiresConfirmation()
                    ->action(function (Model $record): void {
                        $webhookService = app(WebhookService::class);
                        $success = $webhookService->send($record->event_type, $record->payload ?? []);

                        if ($success) {
                            $record->markAsSent(200);
                            Notification::make()
                                ->title('Webhook resent successfully')
                                ->success()
                                ->send();
                        } else {
                            $record->scheduleRetry();
                            Notification::make()
                                ->title('Webhook retry failed')
                                ->body('Scheduled for automatic retry.')
                                ->warning()
                                ->send();
                        }
                    }),
                Action::make('copy_payload')
                    ->label('Copy Payload')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function (Model $record): void {
                        Notification::make()
                            ->title('Payload copied to clipboard')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => $record->status !== 'pending'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('retry_all')
                        ->label('Retry Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $webhookService = app(WebhookService::class);
                            $successCount = 0;
                            $failCount = 0;

                            foreach ($records as $record) {
                                if ($record->canRetry()) {
                                    $success = $webhookService->send($record->event_type, $record->payload ?? []);
                                    if ($success) {
                                        $record->markAsSent(200);
                                        $successCount++;
                                    } else {
                                        $record->scheduleRetry();
                                        $failCount++;
                                    }
                                }
                            }

                            Notification::make()
                                ->title('Bulk retry completed')
                                ->body("Success: {$successCount}, Scheduled for retry: {$failCount}")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('mark_as_failed')
                        ->label('Mark as Failed')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->markAsFailed('Manually marked as failed');
                            }

                            Notification::make()
                                ->title('Events marked as failed')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookEvents::route('/'),
            'view' => Pages\ViewWebhookEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['pending', 'retrying', 'failed'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        $failedCount = static::getModel()::where('status', 'failed')->count();

        return $failedCount > 0 ? 'danger' : 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer_email', 'customer_id', 'event_type'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Event' => $record->getEventTypeLabel(),
            'Status' => ucfirst($record->status),
            'Customer' => $record->customer_email,
        ];
    }
}
