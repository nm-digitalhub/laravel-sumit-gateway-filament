<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;

use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas;
use Filament\Schemas\Schema;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

class ViewClientSubscription extends ViewRecord
{
    protected static string $resource = ClientSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_documents')
                ->label('סנכרן חשבוניות')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    try {
                        $synced = DocumentService::syncForSubscription($this->record);

                        \Filament\Notifications\Notification::make()
                            ->title('סנכרון חשבוניות הושלם')
                            ->body("סונכרנו {$synced} חשבוניות מ-SUMIT")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('שגיאה בסנכרון חשבוניות')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Auto-sync documents from SUMIT before loading the view
        try {
            DocumentService::syncForSubscription($this->record);
        } catch (\Exception $e) {
            // Log error but don't fail the page load
            \Illuminate\Support\Facades\Log::error('Failed to auto-sync subscription documents', [
                'subscription_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
        }

        return parent::mutateFormDataBeforeFill($data);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('פרטי מנוי')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('שם המנוי')
                            ->icon('heroicon-o-tag'),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('סכום')
                            ->money(fn ($record) => $record->currency ?? 'ILS')
                            ->icon('heroicon-o-banknotes'),

                        Infolists\Components\TextEntry::make('currency')
                            ->label('מטבע')
                            ->badge(),

                        Infolists\Components\TextEntry::make('status')
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
                    ])->columns(4),

                Schemas\Components\Section::make('מחזור חיוב')
                    ->schema([
                        Infolists\Components\TextEntry::make('interval_months')
                            ->label('מחזור (חודשים)')
                            ->icon('heroicon-o-calendar-days'),

                        Infolists\Components\TextEntry::make('total_cycles')
                            ->label('סה"כ מחזורים')
                            ->default('ללא הגבלה')
                            ->icon('heroicon-o-arrow-path'),

                        Infolists\Components\TextEntry::make('completed_cycles')
                            ->label('מחזורים שולמו')
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->formatStateUsing(fn ($state, $record) => $record->documentsMany()->wherePivot('amount', '>', 0)->count()),

                        Infolists\Components\TextEntry::make('recurring_id')
                            ->label('מזהה SUMIT')
                            ->copyable()
                            ->copyMessage('מזהה SUMIT הועתק')
                            ->icon('heroicon-o-identification'),
                    ])->columns(4),

                Schemas\Components\Section::make('לוח זמנים')
                    ->schema([
                        Infolists\Components\TextEntry::make('next_charge_at')
                            ->label('חיוב הבא')
                            ->dateTime('d/m/Y H:i')
                            ->icon('heroicon-o-clock'),

                        Infolists\Components\TextEntry::make('last_charged_at')
                            ->label('חיוב אחרון')
                            ->dateTime('d/m/Y H:i')
                            ->icon('heroicon-o-check-badge'),

                        Infolists\Components\TextEntry::make('trial_ends_at')
                            ->label('סיום תקופת ניסיון')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('אין תקופת ניסיון')
                            ->icon('heroicon-o-gift'),

                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('תאריך תפוגה')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('אין תפוגה')
                            ->icon('heroicon-o-calendar-date-range'),
                    ])->columns(4),

                Schemas\Components\Section::make('חשבוניות ותשלומים')
                    ->description('רשימת כל החשבוניות שהונפקו עבור מנוי זה')
                    ->schema([
                        Schemas\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('invoices_count')
                                    ->label('סה"כ חשבוניות')
                                    ->icon('heroicon-o-document-text')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn ($state, $record) => $record->documentsMany()->count()),

                                Infolists\Components\TextEntry::make('total_billed')
                                    ->label('סה"כ חויב')
                                    ->icon('heroicon-o-banknotes')
                                    ->money(fn ($record) => $record->currency ?? 'ILS')
                                    ->badge()
                                    ->color('warning')
                                    ->formatStateUsing(fn ($state, $record) => $record->documentsMany()->get()->sum('pivot.amount')),

                                Infolists\Components\TextEntry::make('total_paid')
                                    ->label('סה"כ שולם')
                                    ->icon('heroicon-o-check-circle')
                                    ->money(fn ($record) => $record->currency ?? 'ILS')
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn ($state, $record) => $record->documentsMany()
                                        ->wherePivot('amount', '>', 0)
                                        ->get()
                                        ->sum('pivot.amount')),
                            ]),

                        Infolists\Components\RepeatableEntry::make('documentsMany')
                            ->label('חשבוניות')
                            ->schema([
                                Schemas\Components\Grid::make(7)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('document_number')
                                            ->label('מספר חשבונית')
                                            ->icon('heroicon-o-hashtag')
                                            ->copyable()
                                            ->copyMessage('מספר החשבונית הועתק'),

                                        Infolists\Components\TextEntry::make('document_type')
                                            ->label('סוג')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                '1' => 'חשבונית',
                                                '2' => 'קבלה',
                                                '3' => 'זיכוי',
                                                '8' => 'הזמנה',
                                                '320' => 'תרומה',
                                                default => 'מסמך',
                                            })
                                            ->color(fn (string $state): string => match ($state) {
                                                '1' => 'primary',
                                                '2' => 'success',
                                                '3' => 'danger',
                                                '320' => 'warning',
                                                default => 'secondary',
                                            }),

                                        Infolists\Components\TextEntry::make('document_date')
                                            ->label('תאריך')
                                            ->icon('heroicon-o-calendar')
                                            ->dateTime('d/m/Y'),

                                        Infolists\Components\TextEntry::make('pivot.amount')
                                            ->label('סכום מנוי זה')
                                            ->money(fn ($record) => $record->currency ?? 'ILS')
                                            ->icon('heroicon-o-banknotes')
                                            ->badge()
                                            ->color('info'),

                                        Infolists\Components\TextEntry::make('amount')
                                            ->label('סה"כ חשבונית')
                                            ->money(fn ($record) => $record->currency ?? 'ILS')
                                            ->icon('heroicon-o-calculator')
                                            ->color('secondary'),

                                        Infolists\Components\TextEntry::make('is_closed')
                                            ->label('סטטוס תשלום')
                                            ->badge()
                                            ->formatStateUsing(fn ($state): string => $state ? 'שולם' : 'ממתין')
                                            ->color(fn ($state): string => $state ? 'success' : 'warning')
                                            ->icon(fn ($state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),

                                        Infolists\Components\TextEntry::make('document_download_url')
                                            ->label('פעולות')
                                            ->formatStateUsing(function ($state, $record): string {
                                                $buttons = [];

                                                if (! empty($record->document_download_url)) {
                                                    $buttons[] = sprintf(
                                                        '<a href="%s" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                            </svg>
                                                            הורדה
                                                        </a>',
                                                        $record->document_download_url
                                                    );
                                                }

                                                if (! empty($record->document_payment_url) && ! $record->is_closed) {
                                                    $buttons[] = sprintf(
                                                        '<a href="%s" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-success-600 hover:bg-success-700 rounded-lg transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                                            </svg>
                                                            תשלום
                                                        </a>',
                                                        $record->document_payment_url
                                                    );
                                                }

                                                return empty($buttons) ? '<span class="text-gray-500 dark:text-gray-400">אין פעולות</span>' : implode(' ', $buttons);
                                            })
                                            ->html(),
                                    ]),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record): bool => $record->documentsMany()->count() > 0),

                        Infolists\Components\TextEntry::make('no_documents')
                            ->label('')
                            ->default('אין חשבוניות עבור מנוי זה')
                            ->icon('heroicon-o-information-circle')
                            ->color('secondary')
                            ->visible(fn ($record): bool => $record->documentsMany()->count() === 0),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
