<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        TextInput::make('payment_id')
                            ->label('מזהה תשלום')
                            ->disabled(),
                        TextInput::make('auth_number')
                            ->label('מספר אישור')
                            ->disabled(),
                        TextInput::make('amount')
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
                            ->disabled(),
                        TextInput::make('currency')
                            ->label('מטבע')
                            ->formatStateUsing(fn ($state): string => match (strtoupper((string) $state)) {
                                '', '0', 'ILS' => '₪ ILS',
                                'USD' => '$ USD',
                                'EUR' => '€ EUR',
                                'GBP' => '£ GBP',
                                default => strtoupper((string) $state),
                            })
                            ->disabled(),
                        TextInput::make('status')
                            ->label('סטטוס')
                            ->disabled(),
                        TextInput::make('payment_method')
                            ->label('אמצעי תשלום')
                            ->disabled(),
                    ])->columns(2),

                Section::make('פרטי כרטיס')
                    ->schema([
                        TextInput::make('card_type')
                            ->label('סוג כרטיס')
                            ->disabled(),
                        TextInput::make('last_digits')
                            ->label('4 ספרות אחרונות')
                            ->disabled(),
                        TextInput::make('expiration_month')
                            ->label('חודש תפוגה')
                            ->disabled(),
                        TextInput::make('expiration_year')
                            ->label('שנת תפוגה')
                            ->disabled(),
                    ])->columns(4),

                Section::make('תשלומים')
                    ->schema([
                        TextInput::make('payments_count')
                            ->label('מספר תשלומים')
                            ->disabled(),
                        TextInput::make('first_payment_amount')
                            ->label('תשלום ראשון')
                            ->disabled(),
                        TextInput::make('non_first_payment_amount')
                            ->label('תשלומים נוספים')
                            ->disabled(),
                    ])->columns(3),

                Section::make('מידע נוסף')
                    ->schema([
                        TextInput::make('document_id')
                            ->label('מזהה מסמך')
                            ->disabled(),
                        TextInput::make('customer_id')
                            ->label('מזהה לקוח')
                            ->disabled(),
                        TextInput::make('environment')
                            ->label('סביבה')
                            ->disabled(),
                        Checkbox::make('is_test')
                            ->label('מצב בדיקות')
                            ->disabled(),
                        Textarea::make('status_description')
                            ->label('תיאור סטטוס')
                            ->disabled()
                            ->rows(2),
                        Textarea::make('error_message')
                            ->label('הודעת שגיאה')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2),

                Section::make('נתוני API גולמיים')
                    ->schema([
                        ViewField::make('raw_request')
                            ->view('officeguy::filament.components.api-payload')
                            ->label('נתוני בקשה (Request)'),
                        ViewField::make('raw_response')
                            ->view('officeguy::filament.components.api-payload')
                            ->label('נתוני תגובה (Response)'),
                    ])
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('השוואת Request ל-Response')
                    ->schema([
                        ViewField::make('api_diff')
                            ->view('officeguy::filament.components.api-payload-diff')
                            ->label(null),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->description('השוואה מפורטת בין נתוני ה-Request לנתוני ה-Response'),
            ]);
    }
}
