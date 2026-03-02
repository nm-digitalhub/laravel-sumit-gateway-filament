<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Pages;

use App\Models\Client;
use Filament\Actions;
use Filament\Actions\Action as NotificationAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Schemas\TransactionInfolist;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_document')
                ->label('צפה במסמך')
                ->icon('heroicon-o-document-text')
                ->visible(fn ($record): bool => ! empty($record->document_id))
                ->url(function ($record): ?string {
                    $docId = OfficeGuyDocument::query()
                        ->where('document_id', $record->document_id)
                        ->value('id');

                    return $docId ? route('officeguy.document.download', ['document' => $docId]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('refresh_status')
                ->label('רענן סטטוס')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function ($record): void {
                    // TODO: Implement status refresh via API
                    Notification::make()
                        ->title('עדכון סטטוס טרם יושם')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('open_subscription')
                ->label('פתח מנוי')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record): bool => $record->subscription_id && Subscription::find($record->subscription_id))
                ->url(function ($record): ?string {
                    $sub = Subscription::find($record->subscription_id);

                    return $sub ? SubscriptionResource::getUrl('view', ['record' => $sub->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('open_client')
                ->label('פתח לקוח')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->visible(fn ($record) => Client::query()
                    ->where('sumit_customer_id', $record->customer_id)
                    ->exists())
                ->url(function ($record): ?string {
                    $client = Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->first();

                    return $client ? route('filament.admin.resources.clients.view', ['record' => $client->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('process_refund')
                ->label('זיכוי כספי')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(
                    fn ($record): bool => $record->status === 'completed' &&
                    $record->amount > 0 &&
                    $record->refund_transaction_id === null  // Prevent duplicate refunds
                )
                ->form([
                    Schemas\Components\Section::make('סוג הזיכוי')
                        ->description('בחר את סוג הזיכוי שברצונך לבצע')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Radio::make('refund_type')
                                ->label('סוג זיכוי')
                                ->options(fn ($record): array => [
                                    'full_refund' => 'החזר כספי מלא לכרטיס אשראי (₪' . number_format((float) ($record->amount ?? 0), 2) . ')',
                                    'partial_refund' => 'החזר כספי חלקי לכרטיס אשראי',
                                    'credit_note' => 'תעודת זיכוי חשבונאית (ללא החזר פיזי)',
                                ])
                                ->default('full_refund')
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('amount')
                                ->label('סכום זיכוי')
                                ->numeric()
                                ->prefix('₪')
                                ->minValue(0.01)
                                ->maxValue(fn ($get, $record): float => (float) $record->amount)
                                ->default(fn ($record): float => (float) $record->amount)
                                ->visible(fn (Get $get): bool => $get('refund_type') !== 'full_refund')
                                ->required(fn (Get $get): bool => $get('refund_type') !== 'full_refund')
                                ->helperText(fn ($record): string => 'סכום מקורי: ₪' . number_format((float) $record->amount, 2)),

                            Forms\Components\Textarea::make('reason')
                                ->label('סיבת זיכוי')
                                ->default('החזר כספי ללקוח')
                                ->required()
                                ->rows(2),
                        ]),
                ])
                ->requiresConfirmation()
                ->modalHeading('זיכוי כספי')
                ->modalDescription(fn ($record): string => 'זיכוי עבור תשלום #' . $record->id . ' - מזהה תשלום: ' . $record->payment_id)
                ->modalSubmitActionLabel('בצע זיכוי')
                ->action(function ($record, array $data): void {
                    \Log::info('Refund action started', [
                        'record_id' => $record->id,
                        'customer_id' => $record->customer_id,
                        'data' => $data,
                    ]);

                    $client = Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->first();

                    if (! $client) {
                        \Log::warning('Client not found', ['customer_id' => $record->customer_id]);
                        Notification::make()
                            ->title('שגיאה')
                            ->body('לקוח לא נמצא במערכת')
                            ->danger()
                            ->send();

                        return;
                    }

                    $refundType = $data['refund_type'];
                    $amount = (float) ($refundType === 'full_refund' ? $record->amount : $data['amount']);
                    $reason = $data['reason'];

                    \Log::info('Processing refund', [
                        'type' => $refundType,
                        'amount' => $amount,
                        'reason' => $reason,
                    ]);

                    try {
                        if ($refundType === 'credit_note') {
                            // תעודת זיכוי חשבונאית
                            $result = DocumentService::createCreditNote(
                                $client,
                                $amount,
                                $reason,
                                $record->document_id
                            );
                        } else {
                            // החזר כספי פיזי
                            $result = PaymentService::processRefund(
                                $client,
                                $record->payment_id,
                                $amount,
                                $reason
                            );
                        }

                        \Log::info('Refund result', ['result' => $result]);

                        if (isset($result['success']) && $result['success']) {
                            // ProcessRefund already updated the original transaction status
                            // and created the refund transaction record with proper links

                            $refundRecordId = $result['refund_record']->id ?? null;
                            $refundUrl = $refundRecordId
                                ? route('filament.admin.resources.transactions.view', ['record' => $refundRecordId])
                                : null;

                            Notification::make()
                                ->title('זיכוי בוצע בהצלחה')
                                ->body(match ($refundType) {
                                    'credit_note' => 'תעודת זיכוי נוצרה: ' . ($result['document_id'] ?? 'N/A'),
                                    default => 'החזר כספי בוצע: ₪' . number_format($amount, 2) .
                                        (
                                            $refundRecordId
                                            ? ' | מזהה עסקה: ' . $refundRecordId
                                            : ''
                                        ) .
                                        (
                                            $result['transaction_id'] !== '' && $result['transaction_id'] !== '0'
                                            ? ' | מזהה SUMIT: ' . $result['transaction_id']
                                            : ''
                                        ),
                                })
                                ->success()
                                ->persistent()
                                ->actions($refundUrl ? [
                                    NotificationAction::make('view_refund')
                                        ->label('צפה בעסקת הזיכוי')
                                        ->url($refundUrl)
                                        ->openUrlInNewTab(),
                                ] : [])
                                ->send();

                            // Refresh the current record to show updated status
                            $record->refresh();
                        } else {
                            \Log::error('Refund failed', ['error' => $result['error'] ?? 'Unknown']);
                            Notification::make()
                                ->title('שגיאה בביצוע זיכוי')
                                ->body($result['error'] ?? 'שגיאה לא ידועה')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        \Log::error('Refund exception', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        Notification::make()
                            ->title('שגיאה')
                            ->body('שגיאה בביצוע הזיכוי: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
