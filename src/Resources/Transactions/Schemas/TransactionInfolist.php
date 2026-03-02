<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // =========================
            // פרטי עסקה
            // =========================
            Section::make('פרטי עסקה')
                ->schema([
                    TextEntry::make('payment_id')
                        ->label('מזהה תשלום')
                        ->copyable()
                        ->icon('heroicon-o-credit-card'),

                    TextEntry::make('auth_number')
                        ->label('מספר אישור')
                        ->copyable()
                        ->icon('heroicon-o-check-badge'),

                    TextEntry::make('amount')
                        ->label('סכום')
                        ->money(fn ($record) => $record->currency ?: 'ILS')
                        ->icon('heroicon-o-banknotes'),

                    TextEntry::make('currency')
                        ->label('מטבע')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => match (strtoupper((string) $state)) {
                            '', '0', 'ILS' => '₪ ILS',
                            'USD' => '$ USD',
                            'EUR' => '€ EUR',
                            'GBP' => '£ GBP',
                            default => strtoupper((string) $state),
                        }),

                    TextEntry::make('status')
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
                        }),

                    TextEntry::make('payment_method')
                        ->label('אמצעי תשלום')
                        ->badge()
                        ->icon('heroicon-o-credit-card'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // =========================
            // פרטי כרטיס
            // =========================
            Section::make('פרטי כרטיס')
                ->schema([
                    TextEntry::make('payment_method_type')
                        ->label('אמצעי תשלום')
                        ->badge()
                        ->icon('heroicon-o-credit-card')
                        ->state(function ($record) {
                            // Try to get from API response first
                            $type = data_get($record->raw_response, 'Data.Payment.PaymentMethod.Type');
                            if ($type !== null) {
                                return match ((int) $type) {
                                    0 => 'אחר',
                                    1 => 'כרטיס אשראי',
                                    2 => 'הוראת קבע',
                                    default => 'לא ידוע',
                                };
                            }

                            // Fallback to payment_method field
                            return match ($record->payment_method) {
                                'card' => 'כרטיס אשראי',
                                'bit' => 'ביט',
                                default => $record->payment_method ?: 'לא ידוע',
                            };
                        })
                        ->color(fn ($state): string => match ($state) {
                            'כרטיס אשראי' => 'success',
                            'הוראת קבע' => 'info',
                            'ביט' => 'warning',
                            default => 'gray',
                        }),

                    TextEntry::make('card_mask')
                        ->label('מספר כרטיס')
                        ->state(function ($record) {
                            // Try to get CardMask from API response
                            $mask = data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_CardMask');
                            if ($mask) {
                                return $mask;
                            }

                            // Fallback to last_digits with manual masking
                            return $record->last_digits ? 'XXXXXXXXXXXX' . $record->last_digits : '-';
                        })
                        ->copyable()
                        ->icon('heroicon-o-hashtag'),

                    TextEntry::make('card_type')
                        ->label('סוג כרטיס')
                        ->badge()
                        ->icon('heroicon-o-credit-card')
                        ->visible(fn ($record): bool => ! empty($record->card_type)),

                    TextEntry::make('expiration')
                        ->label('תוקף')
                        ->icon('heroicon-o-calendar')
                        ->state(function ($record): string {
                            // Try to get from API response first
                            $month = data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_ExpirationMonth')
                                ?? $record->expiration_month;
                            $year = data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_ExpirationYear')
                                ?? $record->expiration_year;

                            if ($month && $year) {
                                return sprintf('%02d/%04d', $month, $year);
                            }

                            return '-';
                        }),

                    TextEntry::make('citizen_id')
                        ->label('ת.ז. מחזיק הכרטיס')
                        ->icon('heroicon-o-identification')
                        ->state(
                            fn ($record) => data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_CitizenID') ?: '-'
                        )
                        ->visible(function ($record): bool {
                            $citizenId = data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_CitizenID');

                            return ! empty($citizenId);
                        }),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->visible(
                    fn ($record): bool => ! empty($record->last_digits) ||
                    ! empty(data_get($record->raw_response, 'Data.Payment.PaymentMethod.CreditCard_LastDigits'))
                ),

            // =========================
            // תשלומים
            // =========================
            Section::make('תשלומים')
                ->schema([
                    TextEntry::make('payments_count')
                        ->label('מספר תשלומים')
                        ->badge()
                        ->color('primary')
                        ->icon('heroicon-o-calculator'),

                    TextEntry::make('first_payment_amount')
                        ->label('תשלום ראשון')
                        ->money(fn ($record) => $record->currency ?: 'ILS'),

                    TextEntry::make('non_first_payment_amount')
                        ->label('תשלומים נוספים')
                        ->money(fn ($record) => $record->currency ?: 'ILS'),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn ($record): bool => ! empty($record->payments_count) && $record->payments_count > 1),

            // =========================
            // מידע נוסף
            // =========================
            Section::make('מידע נוסף')
                ->schema([
                    TextEntry::make('document_id')
                        ->label('מזהה מסמך')
                        ->icon('heroicon-o-document-text')
                        ->copyable(),

                    TextEntry::make('customer_id')
                        ->label('מזהה לקוח')
                        ->icon('heroicon-o-user')
                        ->copyable(),

                    TextEntry::make('subscription_id')
                        ->label('מזהה מנוי')
                        ->icon('heroicon-o-arrow-path')
                        ->copyable()
                        ->visible(fn ($record): bool => ! empty($record->subscription_id)),

                    TextEntry::make('vendor_id')
                        ->label('מזהה ספק')
                        ->icon('heroicon-o-building-storefront')
                        ->copyable()
                        ->visible(fn ($record): bool => ! empty($record->vendor_id)),

                    TextEntry::make('environment')
                        ->label('סביבה')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'www' => 'success',
                            'dev' => 'warning',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'www' => 'ייצור (Production)',
                            'dev' => 'פיתוח (Development)',
                            default => $state,
                        }),

                    IconEntry::make('is_test')
                        ->label('מצב בדיקות')
                        ->boolean()
                        ->trueIcon('heroicon-o-beaker')
                        ->falseIcon('heroicon-o-check-circle')
                        ->trueColor('warning')
                        ->falseColor('success'),

                    IconEntry::make('is_donation')
                        ->label('תרומה')
                        ->boolean()
                        ->visible(fn ($record): bool => ! empty($record->is_donation)),

                    IconEntry::make('is_upsell')
                        ->label('Upsell')
                        ->boolean()
                        ->visible(fn ($record): bool => ! empty($record->is_upsell)),

                    TextEntry::make('status_description')
                        ->label('תיאור סטטוס')
                        ->icon('heroicon-o-information-circle')
                        ->columnSpanFull()
                        ->visible(fn ($record): bool => filled($record->status_description)),

                    TextEntry::make('error_message')
                        ->label('הודעת שגיאה')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn ($record): bool => filled($record->error_message)),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // =========================
            // מסמך להורדה - כרטיס אינטראקטיבי ⭐⭐⭐
            // =========================
            Section::make('מסמך להורדה')
                ->schema([
                    ViewEntry::make('document_card')
                        ->view('officeguy::filament.components.document-download-card')
                        ->label(null),
                ])
                ->columnSpanFull()
                ->icon('heroicon-o-document-text')
                ->visible(
                    fn ($record): bool => filled(data_get($record->raw_response, 'Data.DocumentDownloadURL'))
                ),

            // =========================
            // נתוני API גולמיים
            // =========================
            Section::make('נתוני API גולמיים')
                ->schema([
                    ViewEntry::make('raw_request')
                        ->view('officeguy::filament.components.api-payload')
                        ->label('נתוני בקשה (Request)'),

                    ViewEntry::make('raw_response')
                        ->view('officeguy::filament.components.api-payload')
                        ->label('נתוני תגובה (Response)'),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),

            // =========================
            // השוואת Request ל-Response
            // =========================
            Section::make('השוואת Request ל-Response')
                ->schema([
                    ViewEntry::make('api_diff')
                        ->view('officeguy::filament.components.api-payload-diff')
                        ->label(null),
                ])
                ->collapsible()
                ->collapsed()
                ->description('השוואה מפורטת בין נתוני ה-Request לנתוני ה-Response')
                ->columnSpanFull(),
        ]);
    }
}
