<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Tables;

use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('מזהה')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_id')
                    ->label('מזהה תשלום')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'refunded' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('סכום')
                    ->formatStateUsing(function ($record): string {
                        $currency = $record?->currency ?: 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };

                        return $symbol . ' ' . number_format((float) $record?->amount, 2);
                    })
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('אמצעי')
                    ->badge()
                    ->sortable(),
                TextColumn::make('last_digits')
                    ->label('כרטיס')
                    ->formatStateUsing(fn ($state): string => $state ? '****' . $state : '-'),
                TextColumn::make('vendor_id')
                    ->label('ספק')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('subscription_id')
                    ->label('מנוי')
                    ->formatStateUsing(function ($record) {
                        if (! $record->subscription_id) {
                            return null;
                        }
                        $sub = Subscription::find($record->subscription_id);

                        return $sub?->name ?? $record->subscription_id;
                    })
                    ->url(function ($record): ?string {
                        if (! $record->subscription_id) {
                            return null;
                        }
                        $sub = Subscription::find($record->subscription_id);

                        return $sub ? SubscriptionResource::getUrl('view', ['record' => $sub->id]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('customer_id')
                    ->label('לקוח')
                    ->formatStateUsing(function ($record) {
                        if (! $record->customer_id) {
                            return null;
                        }
                        $client = Client::query()->where('sumit_customer_id', $record->customer_id)->first();

                        return $client?->name ?? $record->customer_id;
                    })
                    ->url(function ($record): ?string {
                        if (! $record->customer_id) {
                            return null;
                        }
                        $client = Client::query()->where('sumit_customer_id', $record->customer_id)->first();

                        return $client ? route('filament.admin.resources.clients.view', ['record' => $client->id]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('document_id')
                    ->label('מסמך')
                    ->formatStateUsing(function ($record) {
                        if (! $record->document_id) {
                            return null;
                        }
                        $docId = OfficeGuyDocument::query()
                            ->where('document_id', $record->document_id)
                            ->value('id');

                        return $docId ? $record->document_id : $record->document_id;
                    })
                    ->url(function ($record): ?string {
                        if (! $record->document_id) {
                            return null;
                        }
                        $docId = OfficeGuyDocument::query()
                            ->where('document_id', $record->document_id)
                            ->value('id');

                        return $docId ? DocumentResource::getUrl('view', ['record' => $docId]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(),
                IconColumn::make('is_donation')
                    ->label('תרומה')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_upsell')
                    ->label('Upsell')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('auth_number')
                    ->label('מס\' אישור')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('transaction_type')
                    ->label('סוג')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'charge' => 'success',
                        'refund' => 'warning',
                        'void' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'charge' => 'חיוב',
                        'refund' => 'זיכוי',
                        'void' => 'בוטל',
                        default => $state,
                    })
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('parent_transaction_id')
                    ->label('חיוב מקורי')
                    ->formatStateUsing(fn ($state) => $state ? "#$state" : null)
                    ->url(fn ($record): ?string => $record->parent_transaction_id
                        ? route('filament.admin.resources.transactions.view', ['record' => $record->parent_transaction_id])
                        : null)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->visible(fn ($record) => $record?->isRefund()),
                TextColumn::make('refund_transaction_id')
                    ->label('עסקת זיכוי')
                    ->formatStateUsing(fn ($state) => $state ? "#$state" : null)
                    ->url(fn ($record): ?string => $record->refund_transaction_id
                        ? route('filament.admin.resources.transactions.view', ['record' => $record->refund_transaction_id])
                        : null)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->visible(fn ($record) => $record?->hasBeenRefunded()),
                TextColumn::make('payment_token')
                    ->label('Token')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                IconColumn::make('is_test')
                    ->label('בדיקות')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('תאריך')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'completed' => 'הושלם',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                        'refunded' => 'הוחזר',
                    ])
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->label('אמצעי תשלום')
                    ->options([
                        'card' => 'כרטיס אשראי',
                        'bit' => 'ביט',
                    ])
                    ->multiple(),
                SelectFilter::make('currency')
                    ->label('מטבע')
                    ->options([
                        'ILS' => '₪ ILS',
                        'USD' => '$ USD',
                        'EUR' => '€ EUR',
                        'GBP' => '£ GBP',
                    ])
                    ->multiple(),
                TernaryFilter::make('is_donation')
                    ->label('תרומות'),
                TernaryFilter::make('is_upsell')
                    ->label('Upsell Transactions'),
                Filter::make('has_vendor')
                    ->label('עסקאות ספק')
                    ->query(fn ($query) => $query->whereNotNull('vendor_id')),
                Filter::make('has_subscription')
                    ->label('עסקאות מנוי')
                    ->query(fn ($query) => $query->whereNotNull('subscription_id')),
                Filter::make('amount')
                    ->form([
                        TextInput::make('amount_from')
                            ->numeric()
                            ->label('סכום מינימלי'),
                        TextInput::make('amount_to')
                            ->numeric()
                            ->label('סכום מקסימלי'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['amount_from'],
                            fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                        )
                        ->when(
                            $data['amount_to'],
                            fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                        )),
                TernaryFilter::make('is_test')
                    ->label('עסקאות בדיקה'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('צפייה'),
                Action::make('create_donation_receipt')
                    ->label('צור קבלת תרומה')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->is_donation && $record->status === 'completed')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // Trigger donation receipt creation
                        Notification::make()
                            ->title('Donation receipt requested')
                            ->body('Check documents for the new receipt.')
                            ->success()
                            ->send();
                    }),
                Action::make('resend_receipt')
                    ->label('שליחה מחדש של קבלה')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn ($record): bool => $record->status === 'completed' && $record->document_id)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        Notification::make()
                            ->title('Receipt resend requested')
                            ->body('The receipt will be sent to the customer.')
                            ->success()
                            ->send();
                    }),
                Action::make('send_payment_link')
                    ->label('שליחת לינק תשלום')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => $record->customer_id)
                    ->form([
                        TextInput::make('email')
                            ->label('אימייל יעד')
                            ->email()
                            ->default(fn ($record) => $record->client?->email)
                            ->required(false),
                        TextInput::make('phone')
                            ->label('מספר נייד (ל‑SMS)')
                            ->default(fn ($record) => $record->client?->phone)
                            ->tel()
                            ->required(false),
                    ])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data): void {
                        $result = app(DebtService::class)->sendPaymentLink(
                            (int) $record->customer_id,
                            $data['email'] ?? null,
                            $data['phone'] ?? null
                        );

                        if (! ($result['success'] ?? false)) {
                            throw new \Exception($result['error'] ?? 'שליחה נכשלה');
                        }

                        $sentTo = trim(($data['email'] ?? '') . ' ' . ($data['phone'] ?? ''));

                        Notification::make()
                            ->title('לינק תשלום נשלח')
                            ->body("נשלח אל: {$sentTo}")
                            ->success()
                            ->send();
                    }),
                Action::make('check_debt')
                    ->label('בדיקת חוב ב‑SUMIT')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => ! empty($record->customer_id))
                    ->action(function ($record): void {
                        try {
                            $balance = app(DebtService::class)
                                ->getCustomerBalanceById((int) $record->customer_id);

                            if (! $balance) {
                                throw new \Exception('לא התקבלה יתרה מה‑SUMIT');
                            }

                            Notification::make()
                                ->title('יתרה נוכחית')
                                ->body($balance['formatted'])
                                ->color($balance['debt'] > 0 ? 'danger' : ($balance['debt'] < 0 ? 'success' : 'gray'))
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('בדיקת חוב נכשלה')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
