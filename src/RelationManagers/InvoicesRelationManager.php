<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
use OfficeGuy\LaravelSumitGateway\Contracts\Invoiceable;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

/**
 * Invoices Relation Manager for displaying customer invoices/documents from SUMIT.
 *
 * This relation manager can be used with any model that has a relationship
 * to invoices that implement the Invoiceable interface.
 *
 * Usage:
 * Add to your Resource's getRelations() method:
 * public static function getRelations(): array
 * {
 *     return [
 *         InvoicesRelationManager::class,
 *     ];
 * }
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?string $title = 'Invoices & Documents';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Invoices & Documents')
            ->description('All invoices and credit notes from SUMIT')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Document Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => '#' . $state),

                TextColumn::make('sumit_document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Invoiceable $record): string => $record->getSumitDocumentTypeName())
                    ->color(fn (Invoiceable $record): string => match ($record->getSumitDocumentId()) {
                        null => 'gray',
                        default => match ($record->isCreditNote()) {
                            true => 'warning',
                            false => match ($record->isClosed()) {
                                true => 'success',
                                false => 'info',
                            },
                        },
                    }),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn (Invoiceable $record): string => $record->getCurrency())
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (Invoiceable $record): string => $record->isCreditNote()
                            ? 'Credit Note'
                            : match ($record->isClosed()) {
                                true => 'Paid',
                                false => 'Pending Payment',
                            }
                    )
                    ->color(
                        fn (Invoiceable $record): string => $record->isCreditNote()
                            ? 'info'
                            : match ($record->isClosed()) {
                                true => 'success',
                                false => 'warning',
                            }
                    ),
            ])
            ->actions([
                Action::make('view_pdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->visible(fn (Invoiceable $record): bool => ! in_array($record->getSumitDownloadUrl(), [null, '', '0'], true))
                    ->url(fn (Invoiceable $record): ?string => $record->getSumitDownloadUrl())
                    ->openUrlInNewTab(),

                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(
                        fn (Invoiceable $record): bool => ! in_array($record->getSumitPaymentUrl(), [null, '', '0'], true) &&
                        ! $record->isClosed() &&
                        ! $record->isCreditNote()
                    )
                    ->url(fn (Invoiceable $record): ?string => $record->getSumitPaymentUrl())
                    ->openUrlInNewTab(),

                Action::make('send_email')
                    ->label('Send by Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->default(fn (): string => auth()->user()->email ?? ''),
                    ])
                    ->action(function (Invoiceable $record, array $data): void {
                        try {
                            $result = DocumentService::sendByEmail(
                                (int) $record->getSumitDocumentId(),
                                $data['email']
                            );

                            Notification::make()
                                ->title('Document sent successfully')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error sending document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('create_credit')
                    ->label('Credit/Refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->visible(
                        fn (Invoiceable $record): bool => ! $record->isCreditNote() &&
                        $record->getTotalAmount() > 0 &&
                        ! in_array($record->getSumitDocumentId(), [null, 0], true)
                    )
                    ->form([
                        Forms\Components\Radio::make('refund_type')
                            ->label('Refund Type')
                            ->options([
                                'credit_note' => 'Credit Note (Document Only)',
                                'refund' => 'Direct Refund to Credit Card',
                            ])
                            ->descriptions([
                                'credit_note' => 'Create accounting credit note without money movement',
                                'refund' => 'Refund money directly to original payment method',
                            ])
                            ->default('credit_note')
                            ->required()
                            ->reactive()
                            ->inline(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount to Credit')
                            ->numeric()
                            ->required()
                            ->default(fn (Invoiceable $record): float => $record->getTotalAmount())
                            ->minValue(0.01)
                            ->maxValue(fn (Invoiceable $record): float => $record->getTotalAmount())
                            ->suffix(fn (Invoiceable $record): string => $record->getCurrency()),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->default(fn (Invoiceable $record): string => 'Credit for invoice #' . $record->getInvoiceNumber())
                            ->rows(3),
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Original Transaction ID')
                            ->helperText('Required for refund - original payment transaction ID')
                            ->visible(fn (callable $get): bool => $get('refund_type') === 'refund')
                            ->required(fn (callable $get): bool => $get('refund_type') === 'refund'),
                    ])
                    ->action(function (Invoiceable $record, array $data): void {
                        try {
                            $client = $record->getClient();

                            if (! $client instanceof HasSumitCustomer) {
                                throw new \Exception('Client does not implement HasSumitCustomer');
                            }

                            if ($data['refund_type'] === 'refund') {
                                // Process direct refund to credit card
                                $result = PaymentService::processRefund(
                                    $client,
                                    $data['transaction_id'],
                                    (float) $data['amount'],
                                    $data['description']
                                );

                                if ($result['success'] ?? false) {
                                    // Update client balance from SUMIT
                                    $debtService = app(DebtService::class);
                                    $updatedBalance = $debtService->getCustomerBalance($client);

                                    Notification::make()
                                        ->title('Refund processed successfully')
                                        ->body(
                                            'Transaction ID: ' . ($result['transaction_id'] ?? 'N/A') . "\n" .
                                            'Auth Number: ' . ($result['auth_number'] ?? 'N/A') . "\n" .
                                            'Updated Balance: ' . ($updatedBalance['formatted'] ?? 'Not available')
                                        )
                                        ->success()
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Unknown error');
                                }
                            } else {
                                // Create credit note document
                                $result = DocumentService::createCreditNote(
                                    $client,
                                    (float) $data['amount'],
                                    $data['description'],
                                    (int) $record->getSumitDocumentId()
                                );

                                if ($result['success'] ?? false) {
                                    // Refresh documents from SUMIT
                                    $this->refreshDocuments($client);

                                    // Update client balance
                                    $debtService = app(DebtService::class);
                                    $updatedDebt = $debtService->getCustomerDebt($client);

                                    Notification::make()
                                        ->title('Credit note created successfully')
                                        ->body(
                                            'Document Number: ' . ($result['document_number'] ?? 'N/A') . "\n" .
                                            'Linked to Invoice: #' . $record->getInvoiceNumber() . "\n" .
                                            'Updated Balance: ' . ($updatedDebt['formatted'] ?? 'Not available')
                                        )
                                        ->success()
                                        ->send();
                                } else {
                                    throw new \Exception($result['error'] ?? 'Unknown error');
                                }
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error processing credit/refund')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Override to get data from SUMIT API and sync to DB.
     *
     * This method fetches documents from SUMIT and syncs them to the local database.
     * The Invoice model (or any model implementing Invoiceable) should have a
     * syncFromSumit() static method to handle the sync logic.
     */
    public function getTableRecords(): Collection
    {
        $owner = $this->getOwnerRecord();

        // Owner must implement HasSumitCustomer
        if (! $owner instanceof HasSumitCustomer || ! $owner->getSumitCustomerId()) {
            return collect([]);
        }

        try {
            // Fetch documents from SUMIT
            $documents = DocumentService::fetchFromSumit(
                $owner->getSumitCustomerId(),
                now()->subYears(5),
                now()
            );

            // Sync all documents to local DB
            $this->syncDocuments($owner, $documents);

            // Return Invoice models from DB (Filament v4 requires Eloquent Models)
            return $this->getInvoicesFromDatabase($owner);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch SUMIT documents', [
                'owner_class' => $owner::class,
                'owner_id' => $owner->id,
                'error' => $e->getMessage(),
            ]);

            return collect([]);
        }
    }

    /**
     * Sync documents from SUMIT to local database.
     *
     * This method should be overridden in your application to use your
     * specific Invoice model's syncFromSumit() method.
     */
    protected function syncDocuments(HasSumitCustomer $owner, array $documents): void
    {
        // Get the Invoice model class from the relationship
        $relationshipClass = $this->getRelationship()->getRelated()::class;

        // Check if model has syncFromSumit method
        if (! method_exists($relationshipClass, 'syncFromSumit')) {
            Log::warning('Invoice model does not have syncFromSumit method', [
                'model' => $relationshipClass,
            ]);

            return;
        }

        // Sync each document
        collect($documents)->each(function (array $doc) use ($owner, $relationshipClass): void {
            try {
                $relationshipClass::syncFromSumit($owner, $doc);
            } catch (\Throwable $e) {
                Log::error('Failed to sync document', [
                    'document_id' => $doc['DocumentID'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Get invoices from database.
     *
     * Override this method to customize query.
     */
    protected function getInvoicesFromDatabase(HasSumitCustomer $owner): Collection
    {
        return $this->getRelationship()
            ->whereNotNull('sumit_document_id')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Refresh documents from SUMIT for a customer.
     */
    protected function refreshDocuments(HasSumitCustomer $client): void
    {
        try {
            $documents = DocumentService::fetchFromSumit(
                $client->getSumitCustomerId(),
                now()->subYears(5),
                now()
            );

            $this->syncDocuments($client, $documents);
        } catch (\Throwable $e) {
            Log::error('Failed to refresh documents', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
