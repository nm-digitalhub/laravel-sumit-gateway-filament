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
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

/**
 * Filament Resource for managing incoming webhooks from SUMIT.
 *
 * This resource displays webhooks received from the SUMIT system when
 * cards (customers, documents, transactions, etc.) are created, updated,
 * deleted, or archived.
 */
class SumitWebhookResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = SumitWebhook::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'event_type';

    protected static ?string $modelLabel = 'SUMIT Webhook';

    protected static ?string $pluralModelLabel = 'SUMIT Webhooks';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                InfolistSection::make('Webhook Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Webhook ID'),
                        TextEntry::make('event_type')
                            ->label('Event Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'card_created' => 'success',
                                'card_updated' => 'info',
                                'card_deleted' => 'danger',
                                'card_archived' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($record) => $record->getEventTypeLabel()),
                        TextEntry::make('card_type')
                            ->label('Card Type')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn ($record) => $record->getCardTypeLabel()),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'processed' => 'success',
                                'received' => 'warning',
                                'failed' => 'danger',
                                'ignored' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')
                            ->label('Received At')
                            ->dateTime(),
                        TextEntry::make('processed_at')
                            ->label('Processed At')
                            ->dateTime()
                            ->placeholder('Not processed yet'),
                    ])->columns(3),

                InfolistSection::make('Request Information')
                    ->schema([
                        TextEntry::make('source_ip')
                            ->label('Source IP')
                            ->copyable(),
                        TextEntry::make('content_type')
                            ->label('Content Type'),
                        TextEntry::make('card_id')
                            ->label('Card ID (SUMIT)')
                            ->copyable(),
                    ])->columns(3),

                InfolistSection::make('Card Data')
                    ->schema([
                        TextEntry::make('customer_id')
                            ->label('Customer ID')
                            ->copyable(),
                        TextEntry::make('customer_name')
                            ->label('Customer Name'),
                        TextEntry::make('customer_email')
                            ->label('Customer Email')
                            ->copyable()
                            ->icon('heroicon-o-envelope'),
                        TextEntry::make('client.name')
                            ->label('Client')
                            ->placeholder('—')
                            ->url(fn ($record): ?string => $record->client ? route('filament.admin.resources.clients.edit', $record->client_id) : null)
                            ->color('primary'),
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money(fn ($record) => $record->currency ?? 'ILS')
                            ->placeholder('N/A'),
                    ])->columns(4),

                InfolistSection::make('CRM Data')
                    ->schema([
                        TextEntry::make('crm_folder_id')
                            ->label('Folder ID')
                            ->state(fn ($record) => $record->getCrmFolderId())
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('crm_entity_id')
                            ->label('Entity ID')
                            ->state(fn ($record) => $record->getCrmEntityId())
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('crm_action')
                            ->label('Action')
                            ->state(fn ($record) => $record->getCrmAction())
                            ->placeholder('—'),
                        TextEntry::make('crm_properties')
                            ->label('Properties')
                            ->state(fn ($record) => $record->getCrmProperties())
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->copyable(),
                    ])
                    ->visible(fn ($record): bool => $record->event_type === 'crm')
                    ->columns(3),

                InfolistSection::make('Connected Resources')
                    ->description('Local resources linked to this webhook')
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Client')
                            ->placeholder('Not linked')
                            ->url(fn ($record): ?string => $record->client_id ? route('filament.admin.resources.clients.edit', $record->client_id) : null)
                            ->color('primary'),
                        TextEntry::make('transaction.payment_id')
                            ->label('Transaction')
                            ->placeholder('Not linked')
                            ->url(fn ($record): ?string => $record->transaction_id
                                ? TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                                : null)
                            ->color('primary'),
                        TextEntry::make('document.document_number')
                            ->label('Document')
                            ->placeholder('Not linked')
                            ->url(fn ($record): ?string => $record->document_id
                                ? DocumentResource::getUrl('view', ['record' => $record->document_id])
                                : null)
                            ->color('primary'),
                        TextEntry::make('token.last_digits')
                            ->label('Token')
                            ->formatStateUsing(fn ($state) => $state ? '****' . $state : null)
                            ->placeholder('Not linked')
                            ->url(fn ($record): ?string => $record->token_id
                                ? TokenResource::getUrl('view', ['record' => $record->token_id])
                                : null)
                            ->color('primary'),
                        TextEntry::make('subscription.name')
                            ->label('Subscription')
                            ->placeholder('Not linked')
                            ->url(fn ($record): ?string => $record->subscription_id
                                ? SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                                : null)
                            ->color('primary'),
                    ])->columns(4),

                InfolistSection::make('Processing Notes')
                    ->schema([
                        TextEntry::make('processing_notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => ! empty($record->processing_notes)),

                InfolistSection::make('Error Information')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Error Message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => ! empty($record->error_message)),

                InfolistSection::make('נתוני Payload גולמיים')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        ViewEntry::make('payload')
                            ->label('Payload Data')
                            ->view('officeguy::filament.components.api-payload'),
                    ]),

                InfolistSection::make('Request Headers')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        ViewEntry::make('headers')
                            ->label('HTTP Headers')
                            ->view('officeguy::filament.components.api-payload'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->filtersLayout(FiltersLayout::AboveContent) // מציג מסננים במלוא הרוחב גם במובייל
            ->filtersFormColumns(1)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'card_created' => 'success',
                        'card_updated' => 'info',
                        'card_deleted' => 'danger',
                        'card_archived' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record) => $record->getEventTypeLabel())
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('endpoint')
                    ->label('Endpoint')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('card_type')
                    ->label('Card Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($record) => $record->getCardTypeLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'received' => 'warning',
                        'failed' => 'danger',
                        'ignored' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'processed' => 'heroicon-o-check-circle',
                        'received' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'ignored' => 'heroicon-o-minus-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_id')
                    ->label('Card ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'ILS')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source_ip')
                    ->label('Source IP')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Endpoint / Event Type')
                    ->options(SumitWebhook::getEventTypes())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('card_type')
                    ->label('Card Type')
                    ->options(SumitWebhook::getCardTypes())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(SumitWebhook::getStatuses())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('unprocessed')
                    ->label('Unprocessed')
                    ->query(fn ($query) => $query->where('status', 'received'))
                    ->toggle(),
                Tables\Filters\Filter::make('failed')
                    ->label('Failed')
                    ->query(fn ($query) => $query->where('status', 'failed'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_transaction')
                    ->label('Has Transaction')
                    ->query(fn ($query) => $query->whereNotNull('transaction_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_document')
                    ->label('Has Document')
                    ->query(fn ($query) => $query->whereNotNull('document_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('received_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('received_until')
                            ->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['received_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['received_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
                Tables\Filters\SelectFilter::make('endpoint')
                    ->label('Endpoint')
                    ->options(fn (): array => SumitWebhook::getKnownEndpoints())
                    ->preload()
                    ->searchable(false)
                    ->placeholder('כל נקודות הקצה'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // Dispatch the event again for processing
                        event(new \OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived($record));

                        Notification::make()
                            ->title('Webhook dispatched for processing')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_processed')
                    ->label('Mark Processed')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Processing Notes')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->markAsProcessed($data['notes'] ?? null);

                        Notification::make()
                            ->title('Webhook marked as processed')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_ignored')
                    ->label('Ignore')
                    ->icon('heroicon-o-minus-circle')
                    ->color('gray')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for ignoring')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->markAsIgnored($data['reason'] ?? null);

                        Notification::make()
                            ->title('Webhook marked as ignored')
                            ->success()
                            ->send();
                    }),
                Action::make('copy_payload')
                    ->label('Copy Payload')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function ($record): void {
                        Notification::make()
                            ->title('Payload copied to clipboard')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_all_processed')
                        ->label('Mark Processed')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $record->markAsProcessed('Bulk processed');
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} webhooks marked as processed")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('mark_all_ignored')
                        ->label('Mark Ignored')
                        ->icon('heroicon-o-minus-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $record->markAsIgnored('Bulk ignored');
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} webhooks marked as ignored")
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSumitWebhooks::route('/'),
            'view' => Pages\ViewSumitWebhook::route('/{record}'),
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'received')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $failedCount = static::getModel()::where('status', 'failed')->count();

        return $failedCount > 0 ? 'danger' : 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['card_id', 'customer_id', 'customer_email', 'customer_name', 'event_type'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Event' => $record->getEventTypeLabel(),
            'Card Type' => $record->getCardTypeLabel(),
            'Status' => ucfirst((string) $record->status),
        ];
    }
}
