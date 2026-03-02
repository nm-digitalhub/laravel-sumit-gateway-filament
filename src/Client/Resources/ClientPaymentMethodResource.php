<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyClientPlugin;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class ClientPaymentMethodResource extends Resource
{
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = OfficeGuyToken::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyClientPlugin
    {
        return OfficeGuyClientPlugin::get();
    }

    /**
     * מציג רק כרטיסים של הלקוח (Client) המחובר
     */
    public static function getEloquentQuery(): Builder
    {
        $client = auth()->user()?->client;

        if (! $client) {
            // אם אין Client, החזר query ריק
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('owner_type', $client::class)
            ->where('owner_id', $client->id);
    }

    /**
     * משיכת אמצעי תשלום מספק SUMIT ושמירה מקומית ללקוח (Client) הנוכחי.
     *
     * @return array{0: bool, 1: string|null, 2?: int}
     */
    protected static function syncTokensFromSumit(): array
    {
        $user = auth()->user();
        $client = $user?->client;

        if (! $client?->sumit_customer_id) {
            return [false, 'לא נמצא מזהה SUMIT ללקוח הנוכחי'];
        }

        $result = PaymentService::getPaymentMethodsForCustomer($client->sumit_customer_id, true);

        if (! $result['success']) {
            return [false, $result['error'] ?? ''];
        }

        $methods = $result['payment_methods'] ?? [];
        $active = $result['active_method'] ?? null;
        $ownerType = $client::class;  // Fixed: Client instead of User
        $ownerId = $client->getKey();      // Fixed: Client ID instead of User ID
        $kept = [];
        $candidateDefault = null;
        $candidateExpiry = null;
        $usage = [];

        foreach ($methods as $method) {
            $token = $method['CreditCard_Token'] ?? null;
            if (! $token) {
                continue;
            }

            $lastFour = $method['CreditCard_LastDigits']
                ?? substr((string) ($method['CreditCard_CardMask'] ?? ''), -4);

            $record = OfficeGuyToken::updateOrCreate(
                [
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'token' => $token,
                ],
                [
                    'gateway_id' => 'officeguy',
                    'card_type' => (string) Arr::get($method, 'Type', '1'),
                    'last_four' => $lastFour,
                    'citizen_id' => $method['CreditCard_CitizenID'] ?? null,
                    'expiry_month' => str_pad((string) ($method['CreditCard_ExpirationMonth'] ?? '1'), 2, '0', STR_PAD_LEFT),
                    'expiry_year' => (string) ($method['CreditCard_ExpirationYear'] ?? date('Y')),
                    'is_default' => false,
                    'metadata' => $method,
                ]
            );

            $kept[] = $record->token;

            // נאתר כרטיס לא פג תוקף ונבחר את בעל התוקף הרחוק ביותר כברירת מחדל מוצעת
            $exp = Carbon::createFromDate(
                (int) ($method['CreditCard_ExpirationYear'] ?? date('Y')),
                (int) ($method['CreditCard_ExpirationMonth'] ?? 1),
                1
            )->endOfMonth();

            if ($exp->isPast()) {
                continue;
            }

            if ($candidateExpiry === null || $exp->greaterThan($candidateExpiry)) {
                $candidateExpiry = $exp;
                $candidateDefault = $record;
            }
        }

        // משיכת היסטוריית תשלומים וחישוב שימוש לכל Token
        $payments = PaymentService::listPayments(['Valid' => true]);
        if ($payments['success'] ?? false) {
            foreach ($payments['payments'] ?? [] as $payment) {
                $token = $payment['PaymentMethod']['CreditCard_Token'] ?? null;
                if (! $token) {
                    continue;
                }

                $usage[$token]['total'] = ($usage[$token]['total'] ?? 0) + 1;
                $usage[$token]['amount'] = ($usage[$token]['amount'] ?? 0) + (float) ($payment['Amount'] ?? 0);

                $date = $payment['Date'] ?? null;
                if ($date && (! isset($usage[$token]['last_at']) || $date > $usage[$token]['last_at'])) {
                    $usage[$token]['last_at'] = $date;
                    $usage[$token]['last_amount'] = (float) ($payment['Amount'] ?? 0);
                    $usage[$token]['payment_id'] = $payment['ID'] ?? null;
                    $usage[$token]['status_desc'] = $payment['StatusDescription'] ?? null;
                    $usage[$token]['valid_payment'] = $payment['ValidPayment'] ?? null;
                }
            }
        }

        if ($kept !== []) {
            OfficeGuyToken::where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->whereNotIn('token', $kept)
                ->delete();
        }

        // העשרת מטאדטה בסטטיסטיקות שימוש
        foreach ($kept as $token) {
            $record = OfficeGuyToken::where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->where('token', $token)
                ->first();

            if (! $record) {
                continue;
            }

            $meta = $record->metadata ?? [];
            if (isset($usage[$token])) {
                $meta['Usage_Total'] = $usage[$token]['total'] ?? null;
                $meta['Usage_TotalAmount'] = $usage[$token]['amount'] ?? null;
                $meta['Usage_LastAt'] = $usage[$token]['last_at'] ?? null;
                $meta['Usage_LastAmount'] = $usage[$token]['last_amount'] ?? null;
                $meta['PaymentID'] = $usage[$token]['payment_id'] ?? ($meta['PaymentID'] ?? null);
                $meta['StatusDescription'] = $usage[$token]['status_desc'] ?? ($meta['StatusDescription'] ?? null);
                $meta['ValidPayment'] = $usage[$token]['valid_payment'] ?? ($meta['ValidPayment'] ?? null);
            }

            $record->metadata = $meta;
            $record->save();
        }

        // אם חזר כרטיס פעיל מפורש, נסמן אותו כברירת מחדל
        if ($active && ($token = $active['CreditCard_Token'] ?? null)) {
            $activeRecord = OfficeGuyToken::where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->where('token', $token)
                ->first();

            if ($activeRecord) {
                $activeRecord->setAsDefault();
            }
        } elseif ($candidateDefault) {
            // אחרת, בחר את הכרטיס הלא פג תוקף עם התוקף הרחוק ביותר
            $candidateDefault->setAsDefault();
        }

        return [true, null, count($kept)];
    }

    /**
     * Infolist משודרג לתצוגת כרטיס - חוויית לקוח מעולה
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // כרטיס ויזואלי מרכזי
                Section::make('אמצעי התשלום שלך')
                    ->description('כרטיס זה שמור בצורה מאובטחת במערכת')
                    ->icon('heroicon-o-credit-card')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                // סוג כרטיס עם צבעים ייחודיים
                                TextEntry::make('card_type')
                                    ->label('חברת אשראי')
                                    ->badge()
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color(fn ($record): string => match ((string) ($record->metadata['Type'] ?? $record->card_type ?? '')) {
                                        '1' => 'info',      // Visa - כחול
                                        '2' => 'warning',   // MasterCard - כתום
                                        '6' => 'success',   // Amex - ירוק
                                        '22' => 'primary',  // Cal - סגול
                                        default => 'gray',
                                    })
                                    ->icon(fn ($record): string => match ((string) ($record->metadata['Type'] ?? $record->card_type ?? '')) {
                                        '1' => 'heroicon-o-credit-card',
                                        '2' => 'heroicon-o-credit-card',
                                        '6' => 'heroicon-o-credit-card',
                                        '22' => 'heroicon-o-building-library',
                                        default => 'heroicon-o-credit-card',
                                    })
                                    ->formatStateUsing(fn ($record): string => match ((string) ($record->metadata['Type'] ?? $record->card_type ?? '')) {
                                        '1' => 'Visa',
                                        '2' => 'MasterCard',
                                        '6' => 'American Express',
                                        '22' => 'CAL / כאל',
                                        default => 'כרטיס אשראי',
                                    }),

                                TextEntry::make('metadata.ID')
                                    ->label('מזהה כרטיס בספק')
                                    ->icon('heroicon-o-hashtag')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state): string => $state ? '#' . $state : 'לא זמין'),

                                // מספר כרטיס מוסתר
                                TextEntry::make('last_four')
                                    ->label('מספר כרטיס')
                                    ->icon('heroicon-o-shield-check')
                                    ->iconColor('success')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(
                                        fn ($record): string => substr((string) ($record->metadata['CreditCard_CardMask'] ?? '************' . $record->last_four), -4)
                                    )
                                    ->formatStateUsing(fn ($state): string => '•••• •••• •••• ' . $state)
                                    ->copyable()
                                    ->copyMessage('הועתק בהצלחה!')
                                    ->copyMessageDuration(1500)
                                    ->helperText('לחץ להעתקה'),

                                // תוקף
                                TextEntry::make('expiry')
                                    ->label('תוקף')
                                    ->icon('heroicon-o-calendar-days')
                                    ->badge()
                                    ->size('lg')
                                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success')
                                    ->formatStateUsing(
                                        fn ($record): string => ($record->metadata['CreditCard_ExpirationMonth'] ?? $record->expiry_month)
                                        . '/' . substr((string) ($record->metadata['CreditCard_ExpirationYear'] ?? $record->expiry_year), -2)
                                    )
                                    ->helperText(
                                        fn ($record): string => $record->isExpired()
                                        ? 'הכרטיס פג תוקף'
                                        : 'בתוקף עד ' . $record->expiry_month . '/' . $record->expiry_year
                                    ),

                                // סטטוס וברירת מחדל
                                TextEntry::make('status')
                                    ->label('סטטוס')
                                    ->badge()
                                    ->size('lg')
                                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success')
                                    ->icon(fn ($record): string => $record->isExpired() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                                    ->formatStateUsing(
                                        fn ($record): string => ($record->isExpired() ? 'פג תוקף' : 'פעיל') .
                                        ($record->is_default ? ' (ברירת מחדל)' : '')
                                    )
                                    ->helperText(
                                        fn ($record): string => $record->is_default ? 'כרטיס ברירת מחדל לתשלומים' : ''
                                    ),

                                TextEntry::make('metadata.CreditCard_CitizenID')
                                    ->label('ת.ז. דיווחה לספק')
                                    ->icon('heroicon-o-identification')
                                    ->placeholder('לא זמין'),

                                TextEntry::make('metadata.CustomerID')
                                    ->label('CustomerID ב‑SUMIT')
                                    ->icon('heroicon-o-user')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state): string => $state ? '#' . $state : 'לא זמין'),
                            ]),
                    ]),

                // פרטי אבטחה
                Section::make('פרטי אבטחה')
                    ->description('מידע מאובטח על הכרטיס')
                    ->icon('heroicon-o-lock-closed')
                    ->collapsible()
                    ->collapsed(false)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('gateway_id')
                                    ->label('שער תשלום')
                                    ->icon('heroicon-o-server')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'officeguy' => 'SUMIT Gateway',
                                        'officeguybit' => 'Bit Payment',
                                        default => strtoupper((string) $state),
                                    }),

                                TextEntry::make('token')
                                    ->label('טוקן אבטחה')
                                    ->icon('heroicon-o-key')
                                    ->iconColor('warning')
                                    ->copyable()
                                    ->copyMessage('טוקן הועתק בצורה מאובטחת!')
                                    ->copyMessageDuration(2000)
                                    ->limit(24)
                                    ->tooltip(fn ($state): string => 'טוקן מלא: ' . $state)
                                    ->helperText('לחץ להעתקת הטוקן המלא'),

                                TextEntry::make('citizen_id')
                                    ->label('ת.ז. מקושרת')
                                    ->icon('heroicon-o-identification')
                                    ->iconColor('primary')
                                    ->placeholder('לא זמין')
                                    ->copyable()
                                    ->helperText('ניתן להעתקה'),
                            ]),
                    ]),

                // מידע מתקדם מ-SUMIT
                Section::make('מידע מתקדם מ-SUMIT')
                    ->description('פרטים טכניים מספק התשלום')
                    ->icon('heroicon-o-cloud')
                    ->collapsible()
                    ->collapsed(true)
                    ->columnSpanFull()
                    ->visible(fn ($record) => collect([
                        $record->metadata['TransactionID'] ?? null,
                        $record->metadata['AuthNumber'] ?? null,
                        $record->metadata['ResultCode'] ?? null,
                        $record->metadata['ResultDescription'] ?? null,
                        $record->metadata['PaymentID'] ?? null,
                    ])->filter()->isNotEmpty())
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('transaction_id')
                                    ->label('מזהה טרנזקציה')
                                    ->icon('heroicon-o-document-text')
                                    ->iconColor('primary')
                                    ->badge()
                                    ->color('primary')
                                    ->state(fn ($record) => $record->metadata['TransactionID'] ?? null)
                                    ->formatStateUsing(fn ($state): string => $state ? '#' . $state : 'לא זמין')
                                    ->copyable()
                                    ->copyMessage('מזהה טרנזקציה הועתק!')
                                    ->helperText('מזהה בסיסטם SUMIT'),

                                TextEntry::make('auth_number')
                                    ->label('מספר אישור')
                                    ->icon('heroicon-o-check-badge')
                                    ->iconColor('success')
                                    ->badge()
                                    ->color('success')
                                    ->state(fn ($record) => $record->metadata['AuthNumber'] ?? null)
                                    ->formatStateUsing(fn ($state): string => $state ? trim((string) $state) : 'לא זמין')
                                    ->copyable()
                                    ->helperText('מספר אישור מהבנק'),

                                TextEntry::make('acquirer')
                                    ->label('מפעיל')
                                    ->icon('heroicon-o-building-office')
                                    ->iconColor('warning')
                                    ->badge()
                                    ->color('warning')
                                    ->state(fn ($record) => $record->metadata['Acquirer'] ?? null)
                                    ->formatStateUsing(fn ($state): string => match ($state) {
                                        1 => 'ישראכרט',
                                        2 => 'לאומי קארד',
                                        3 => 'כאל',
                                        4 => 'דיינרס',
                                        5 => 'אמקס',
                                        6 => 'לאומי קארד (חיוב מיידי)',
                                        default => $state ? "קוד: {$state}" : 'לא זמין',
                                    })
                                    ->helperText('חברת הסליקה'),

                                TextEntry::make('issuer')
                                    ->label('מנפיק')
                                    ->icon('heroicon-o-credit-card')
                                    ->iconColor('info')
                                    ->badge()
                                    ->color('info')
                                    ->state(fn ($record) => $record->metadata['Issuer'] ?? null)
                                    ->formatStateUsing(fn ($state): string => match ($state) {
                                        1 => 'ישראכרט',
                                        2 => 'לאומי קארד',
                                        6 => 'לאומי קארד',
                                        15 => 'אמקס',
                                        16 => 'כאל',
                                        default => $state ? "קוד: {$state}" : 'לא זמין',
                                    })
                                    ->helperText('הבנק שהנפיק את הכרטיס'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('result_code')
                                    ->label('קוד תוצאה')
                                    ->icon('heroicon-o-signal')
                                    ->state(fn ($record) => $record->metadata['ResultCode'] ?? null)
                                    ->badge()
                                    ->color(fn ($state): string => $state === '000' ? 'success' : 'danger')
                                    ->formatStateUsing(fn ($state) => $state ?: 'לא זמין'),

                                TextEntry::make('result_description')
                                    ->label('תיאור תוצאה')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->state(fn ($record) => $record->metadata['ResultDescription'] ?? null)
                                    ->badge()
                                    ->color(fn ($state): string => $state === 'Approved' ? 'success' : 'warning')
                                    ->formatStateUsing(fn ($state) => $state ?: 'לא זמין'),

                                TextEntry::make('checkout_index')
                                    ->label('אינדקס תשלום')
                                    ->icon('heroicon-o-queue-list')
                                    ->state(
                                        fn ($record) => isset($record->metadata['FileNumber'], $record->metadata['CheckoutIndex'])
                                        ? $record->metadata['FileNumber'] . '-' . $record->metadata['CheckoutIndex']
                                        : null
                                    )
                                    ->placeholder('לא זמין')
                                    ->helperText('מזהה בקובץ התשלומים'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('metadata.PaymentID')
                                    ->label('PaymentID')
                                    ->icon('heroicon-o-hashtag')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state): string => $state ? '#' . $state : 'לא זמין')
                                    ->helperText('מזהה עסקה ב‑SUMIT'),

                                TextEntry::make('metadata.StatusDescription')
                                    ->label('סטטוס עסקה')
                                    ->icon('heroicon-o-information-circle')
                                    ->formatStateUsing(
                                        fn ($state) => match (trim((string) $state)) {
                                            'Approved', '(קוד 000)', 'קוד 000', '000', '' => 'הצלחה',
                                            default => $state ?: 'לא זמין',
                                        }
                                    ),

                                TextEntry::make('metadata.ValidPayment')
                                    ->label('עסקה תקינה')
                                    ->icon('heroicon-o-check-circle')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
                            ]),
                    ]),

                // סטטיסטיקות שימוש
                Section::make('סטטיסטיקות שימוש')
                    ->description('היסטוריית שימוש בכרטיס')
                    ->icon('heroicon-o-chart-bar-square')
                    ->collapsible()
                    ->collapsed(false)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('transactions_count')
                                    ->label('מספר תשלומים')
                                    ->icon('heroicon-o-shopping-cart')
                                    ->iconColor('primary')
                                    ->badge()
                                    ->color('primary')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->metadata['Usage_Total'] ?? null)
                                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((int) $state) : 'טרם בוצע')
                                    ->helperText('נתון מספק SUMIT (Usage_Total)'),

                                TextEntry::make('total_amount')
                                    ->label('סכום כולל')
                                    ->icon('heroicon-o-banknotes')
                                    ->iconColor('success')
                                    ->badge()
                                    ->color('success')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->metadata['Usage_TotalAmount'] ?? null)
                                    ->formatStateUsing(fn ($state): string => $state !== null ? '₪' . number_format((float) $state, 2) : 'אין נתונים')
                                    ->helperText('Usage_TotalAmount מספק SUMIT'),

                                TextEntry::make('last_transaction')
                                    ->label('תשלום אחרון')
                                    ->icon('heroicon-o-clock')
                                    ->iconColor('warning')
                                    ->badge()
                                    ->color('warning')
                                    ->state(fn ($record) => $record->metadata['Usage_LastAt'] ?? null)
                                    ->formatStateUsing(function ($state) {
                                        if (! $state) {
                                            return 'אין נתונים';
                                        }

                                        try {
                                            return Carbon::parse($state)->timezone('Asia/Jerusalem')->format('d/m/Y H:i');
                                        } catch (\Throwable) {
                                            return $state;
                                        }
                                    })
                                    ->placeholder('טרם בוצע')
                                    ->helperText(function ($record): string {
                                        $state = $record->metadata['Usage_LastAt'] ?? null;
                                        if (! $state) {
                                            return 'לא נמצאו תשלומים';
                                        }

                                        try {
                                            return Carbon::parse($state)->timezone('Asia/Jerusalem')->diffForHumans();
                                        } catch (\Throwable) {
                                            return 'מתוך נתוני SUMIT';
                                        }
                                    }),

                                TextEntry::make('usage_frequency')
                                    ->label('תדירות שימוש')
                                    ->icon('heroicon-o-arrow-trending-up')
                                    ->iconColor('info')
                                    ->badge()
                                    ->color('info')
                                    ->state(function ($record): string {
                                        $count = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction::where('last_digits', $record->last_four)
                                            ->where('card_type', $record->card_type)
                                            ->whereIn('status', ['completed', 'success', 'approved'])
                                            ->count();
                                        $days = $record->created_at->diffInDays(now());
                                        if ($days === 0 || $count === 0) {
                                            return 'חדש';
                                        }
                                        $frequency = $count / max($days, 1);
                                        if ($frequency >= 1) {
                                            return 'יומי';
                                        }
                                        if ($frequency >= 0.25) {
                                            return 'שבועי';
                                        }
                                        if ($frequency >= 0.1) {
                                            return 'חודשי';
                                        }

                                        return 'נדיר';
                                    })
                                    ->helperText('על בסיס היסטוריית שימוש'),
                            ]),
                    ]),

                // היסטוריה ומידע מערכת
                Section::make('היסטוריה ומידע מערכת')
                    ->description('פרטי מערכת ותאריכים')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('תאריך הוספה')
                                    ->icon('heroicon-o-plus-circle')
                                    ->iconColor('success')
                                    ->dateTime('d/m/Y בשעה H:i')
                                    ->timezone('Asia/Jerusalem')
                                    ->helperText(
                                        fn ($record): string => 'לפני ' . $record->created_at->diffForHumans()
                                    ),

                                TextEntry::make('updated_at')
                                    ->label('עדכון אחרון')
                                    ->icon('heroicon-o-arrow-path')
                                    ->iconColor('warning')
                                    ->dateTime('d/m/Y בשעה H:i')
                                    ->timezone('Asia/Jerusalem')
                                    ->helperText(
                                        fn ($record): string => 'לפני ' . $record->updated_at->diffForHumans()
                                    ),

                                TextEntry::make('id')
                                    ->label('מזהה מערכת')
                                    ->icon('heroicon-o-hashtag')
                                    ->iconColor('gray')
                                    ->formatStateUsing(fn ($state): string => '#' . str_pad((string) $state, 6, '0', STR_PAD_LEFT))
                                    ->copyable()
                                    ->copyMessage('מזהה הועתק!')
                                    ->helperText('מזהה פנימי למעקב'),
                            ]),
                    ]),

                // Metadata - מוצג בצורה מסודרת עם תרגום לעברית
                Section::make('נתונים טכניים מלאים')
                    ->description('מידע מפורט על התהליך שנשמר מ-SUMIT')
                    ->icon('heroicon-o-code-bracket')
                    ->collapsible()
                    ->collapsed(true)
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => ! empty($record->metadata))
                    ->schema([
                        // קטגוריה 1: סטטוס העסקה
                        Section::make('סטטוס העסקה')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('meta_success')
                                        ->label('סטטוס הצלחה')
                                        ->state(fn ($record) => $record->metadata['Success'] ?? null)
                                        ->formatStateUsing(fn ($state): string => $state ? 'הצלחה' : 'כשלון')
                                        ->badge()
                                        ->color(fn ($state): string => $state ? 'success' : 'danger'),

                                    TextEntry::make('meta_result_code')
                                        ->label('קוד תוצאה')
                                        ->state(fn ($record) => $record->metadata['ResultCode'] ?? null)
                                        ->formatStateUsing(fn ($state) => $state === '000' ? '000 - מאושר' : ($state ?? 'לא זמין'))
                                        ->badge()
                                        ->color(fn ($state): string => $state === '000' ? 'success' : 'warning'),

                                    TextEntry::make('meta_result_description')
                                        ->label('תיאור התוצאה')
                                        ->state(fn ($record) => $record->metadata['ResultDescription'] ?? 'לא זמין')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),

                        // קטגוריה 2: מזהי עסקה
                        Section::make('מזהי עסקה')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('meta_transaction_id')
                                        ->label('מזהה עסקה')
                                        ->state(fn ($record) => $record->metadata['TransactionID'] ?? 'לא זמין')
                                        ->copyable()
                                        ->badge()
                                        ->color('primary'),

                                    TextEntry::make('meta_auth_number')
                                        ->label('מספר אישור')
                                        ->state(fn ($record) => $record->metadata['AuthNumber'] ?? 'לא זמין')
                                        ->copyable()
                                        ->badge()
                                        ->color('primary'),

                                    TextEntry::make('meta_file_number')
                                        ->label('מספר תיק')
                                        ->state(fn ($record) => $record->metadata['FileNumber'] ?? 'לא זמין')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),

                        // קטגוריה 3: פרטי כרטיס וסליקה
                        Section::make('פרטי כרטיס וסליקה')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('meta_brand')
                                        ->label('מותג כרטיס')
                                        ->state(function ($record): string {
                                            $brand = $record->metadata['Brand'] ?? null;

                                            return match ((string) $brand) {
                                                '1' => 'Visa',
                                                '2' => 'MasterCard',
                                                '6' => 'American Express',
                                                '22' => 'CAL / כאל',
                                                default => $brand ? "מותג {$brand}" : 'לא זמין',
                                            };
                                        })
                                        ->badge()
                                        ->color('info'),

                                    TextEntry::make('meta_acquirer')
                                        ->label('חברת סליקה')
                                        ->state(function ($record): string {
                                            $acquirer = $record->metadata['Acquirer'] ?? null;

                                            return match ((string) $acquirer) {
                                                '1' => 'ישראכרט',
                                                '2' => 'לאומי קארד',
                                                '3' => 'כ.א.ל',
                                                '4' => 'דיינרס',
                                                '5' => 'אמריקן אקספרס',
                                                '6' => 'כ.א.ל (חו"ל)',
                                                default => $acquirer ? "חברת סליקה {$acquirer}" : 'לא זמין',
                                            };
                                        })
                                        ->badge()
                                        ->color('gray'),

                                    TextEntry::make('meta_issuer')
                                        ->label('מנפיק הכרטיס')
                                        ->state(function ($record): string {
                                            $issuer = $record->metadata['Issuer'] ?? null;

                                            return match ((string) $issuer) {
                                                '1' => 'בנק לאומי',
                                                '2' => 'בנק הפועלים',
                                                '3' => 'בנק דיסקונט',
                                                '4' => 'בנק מזרחי',
                                                '5' => 'בנק מרכנתיל',
                                                '6' => 'בנק יהב',
                                                '7' => 'בנק איגוד',
                                                '8' => 'בנק ערבי ישראלי',
                                                '9' => 'בנק פועלי אגודת ישראל',
                                                '10' => 'בנק ירושלים',
                                                '11' => 'בנק אוצר החייל',
                                                '12' => 'בנק הבינלאומי',
                                                '13' => 'בנק מסד',
                                                '14' => 'בנק דקסיה',
                                                '15' => 'SBI סטייט בנק',
                                                '16' => 'כ.א.ל (חו"ל)',
                                                '17' => 'בנק ערבי',
                                                '20' => 'בנק פועלי',
                                                '31' => 'הבנק הבינלאומי הראשון',
                                                '46' => 'בנק מסד',
                                                default => $issuer ? "בנק {$issuer}" : 'לא זמין',
                                            };
                                        })
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),

                        // קטגוריה 4: פרטי תוקף ואבטחה
                        Section::make('פרטי תוקף ואבטחה')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('meta_expiration_month')
                                        ->label('חודש תפוגה')
                                        ->state(fn ($record): string => isset($record->metadata['ExpirationMonth'])
                                            ? str_pad((string) $record->metadata['ExpirationMonth'], 2, '0', STR_PAD_LEFT)
                                            : 'לא זמין')
                                        ->badge()
                                        ->color('gray'),

                                    TextEntry::make('meta_expiration_year')
                                        ->label('שנת תפוגה')
                                        ->state(function ($record) {
                                            $year = $record->metadata['ExpirationYear'] ?? null;
                                            if (! $year) {
                                                return 'לא זמין';
                                            }

                                            return strlen((string) $year) === 2 ? '20' . $year : $year;
                                        })
                                        ->badge()
                                        ->color('gray'),

                                    TextEntry::make('meta_citizen_id')
                                        ->label('תעודת זהות')
                                        ->state(fn ($record) => $record->metadata['CitizenID'] ?? 'לא זמין')
                                        ->copyable()
                                        ->badge()
                                        ->color('warning'),

                                    TextEntry::make('meta_card_pattern')
                                        ->label('תבנית כרטיס')
                                        ->state(fn ($record) => $record->metadata['CardPattern'] ?? 'לא זמין')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),

                        // קטגוריה 5: מידע טכני מתקדם
                        Section::make('מידע טכני מתקדם')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->collapsed()
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('meta_card_token')
                                        ->label('טוקן כרטיס')
                                        ->state(fn ($record) => $record->metadata['CardToken'] ?? 'לא זמין')
                                        ->copyable()
                                        ->badge()
                                        ->color('primary'),

                                    TextEntry::make('meta_checkout_index')
                                        ->label('אינדקס תשלום')
                                        ->state(fn ($record) => $record->metadata['CheckoutIndex'] ?? 'לא זמין')
                                        ->badge()
                                        ->color('gray'),

                                    TextEntry::make('meta_checkout_record_index')
                                        ->label('אינדקס רשומת תשלום')
                                        ->state(fn ($record) => $record->metadata['CheckoutRecordIndex'] ?? 'לא זמין')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * משמש רק להצגת דף View (לא Create) - deprecated, השתמש ב-infolist
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('פרטי כרטיס')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('card_type')
                        ->label('סוג כרטיס')
                        ->disabled(),

                    TextInput::make('last_four')
                        ->label('מספר כרטיס')
                        ->formatStateUsing(fn ($state): string => '**** **** **** ' . $state)
                        ->disabled(),

                    Placeholder::make('expiry')
                        ->label('תוקף')
                        ->content(
                            fn ($record): string => $record?->expiry_month . '/' . $record?->expiry_year
                        ),

                    Checkbox::make('is_default')
                        ->label('ברירת מחדל')
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('סטטוס')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('status')
                        ->label('סטטוס כרטיס')
                        ->content(
                            fn ($record): string => $record?->isExpired() ? '⚠️ פג תוקף' : '✓ פעיל'
                        ),

                    Placeholder::make('created_at')
                        ->label('נוסף בתאריך')
                        ->content(
                            fn ($record) => $record?->created_at?->format('d/m/Y')
                        ),
                ])
                ->columns(2),
        ]);
    }

    /**
     * טבלת אמצעי תשלום משודרגת
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_default')
                    ->label('ברירת מחדל')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn ($record): string => $record->is_default ? 'כרטיס ברירת מחדל' : 'לא ברירת מחדל'),

                Tables\Columns\TextColumn::make('card_type')
                    ->label('סוג כרטיס')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-credit-card')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        '1' => 'Visa',
                        '2' => 'MasterCard',
                        '6' => 'American Express',
                        '22' => 'CAL / כאל',
                        default => 'כרטיס אשראי',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_four')
                    ->label('מספר כרטיס')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('success')
                    ->formatStateUsing(fn ($state): string => '**** **** **** ' . $state)
                    ->copyable()
                    ->copyMessage('הועתק!')
                    ->copyMessageDuration(1500)
                    ->searchable(),

                Tables\Columns\TextColumn::make('expiry_month')
                    ->label('תוקף')
                    ->icon('heroicon-o-calendar')
                    ->formatStateUsing(
                        fn ($record): string => $record->expiry_month . '/' . substr((string) $record->expiry_year, -2)
                    )
                    ->badge()
                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success')
                    ->tooltip(
                        fn ($record): string => $record->isExpired()
                        ? 'הכרטיס פג תוקף!'
                        : 'כרטיס פעיל עד ' . $record->expiry_month . '/' . $record->expiry_year
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success')
                    ->icon(fn ($record): string => $record->isExpired() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->formatStateUsing(fn ($record): string => $record->isExpired() ? 'פג תוקף' : 'פעיל'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוסף בתאריך')
                    ->icon('heroicon-o-clock')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Asia/Jerusalem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('ברירת מחדל')
                    ->trueLabel('רק ברירת מחדל')
                    ->falseLabel('ללא ברירת מחדל')
                    ->placeholder('הכל')
                    ->indicator('כרטיס ברירת מחדל'),

                Tables\Filters\Filter::make('expired')
                    ->label('כרטיסים שפג תוקפם')
                    ->query(
                        fn (Builder $query) => $query->whereRaw("STR_TO_DATE(CONCAT(expiry_year, '-', expiry_month, '-01'), '%Y-%m-%d') < CURDATE()")
                    )
                    ->toggle()
                    ->indicator('פג תוקף'),

                Tables\Filters\Filter::make('active')
                    ->label('כרטיסים פעילים')
                    ->query(
                        fn (Builder $query) => $query->whereRaw("STR_TO_DATE(CONCAT(expiry_year, '-', expiry_month, '-01'), '%Y-%m-%d') >= CURDATE()")
                    )
                    ->toggle()
                    ->default()
                    ->indicator('פעיל'),
            ])
            ->headerActions([
                Action::make('refresh_from_sumit')
                    ->label('רענן מ‑SUMIT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        [$ok, $error, $count] = self::syncTokensFromSumit();

                        if (! $ok) {
                            Notification::make()
                                ->title('הריענון נכשל')
                                ->body($error ?: 'שגיאה לא ידועה')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('הריענון הצליח')
                            ->body("עודכנו {$count} אמצעי תשלום מספק SUMIT")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('צפייה')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (OfficeGuyToken $record): string => static::getUrl('view', ['record' => $record])),

                Action::make('set_default')
                    ->label('הגדר כברירת מחדל')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record): bool => ! $record->is_default && ! $record->isExpired())
                    ->requiresConfirmation()
                    ->modalHeading('הגדרת כרטיס ברירת מחדל')
                    ->modalDescription('האם להגדיר כרטיס זה כאמצעי התשלום המועדף שלך?')
                    ->modalSubmitActionLabel('כן, הגדר כברירת מחדל')
                    ->modalCancelActionLabel('ביטול')
                    ->action(function (OfficeGuyToken $record): void {
                        $user = auth()->user();
                        $client = $user?->client;

                        if (! $client?->sumit_customer_id) {
                            Notification::make()
                                ->title('לא נמצא מזהה SUMIT ללקוח')
                                ->danger()
                                ->send();

                            return;
                        }

                        $push = PaymentService::setPaymentMethodForCustomer(
                            $client->sumit_customer_id,
                            $record->token,
                            $record->metadata ?? []
                        );

                        if (! $push['success']) {
                            Notification::make()
                                ->title('העדכון בספק נכשל')
                                ->body($push['error'] ?? 'שגיאה לא ידועה')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->setAsDefault();

                        Notification::make()
                            ->title('הכרטיס הוגדר כברירת מחדל')
                            ->body('עודכן גם ב‑SUMIT ובמערכת המקומית')
                            ->success()
                            ->icon('heroicon-o-check-circle')
                            ->send();
                    }),

                Action::make('remove_default')
                    ->label('הסר ברירת מחדל')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn ($record) => $record->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('הסרת ברירת מחדל')
                    ->modalDescription('האם להסיר את הכרטיס מרשימת ברירת המחדל?')
                    ->modalSubmitActionLabel('כן, הסר')
                    ->modalCancelActionLabel('ביטול')
                    ->action(function (OfficeGuyToken $record): void {
                        $record->update(['is_default' => false]);

                        Notification::make()
                            ->title('ברירת המחדל הוסרה')
                            ->body('הכרטיס כבר לא מוגדר כברירת מחדל')
                            ->success()
                            ->icon('heroicon-o-check-circle')
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('מחיקה')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('מחיקת אמצעי תשלום')
                    ->modalDescription('האם אתה בטוח שברצונך למחוק אמצעי תשלום זה? פעולה זו אינה ניתנת לביטול.')
                    ->modalSubmitActionLabel('כן, מחק')
                    ->modalCancelActionLabel('ביטול')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('אמצעי התשלום נמחק')
                            ->body('הכרטיס הוסר בהצלחה מהמערכת')
                            ->icon('heroicon-o-check-circle')
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('מחיקת כרטיסים מסומנים')
                        ->requiresConfirmation()
                        ->modalHeading('מחיקה המונית')
                        ->modalDescription('האם למחוק את כל הכרטיסים המסומנים?')
                        ->modalSubmitActionLabel('כן, מחק הכל')
                        ->modalCancelActionLabel('ביטול')
                        ->action(fn (Collection $records) => $records->each->delete())
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('כרטיסים נמחקו')
                                ->body('כל הכרטיסים המסומנים נמחקו בהצלחה')
                        )
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('אין אמצעי תשלום שמורים')
            ->emptyStateDescription('טרם שמרת אמצעי תשלום. תוכל לשמור כרטיס בעת ביצוע תשלום או דרך טופס הוספת כרטיס.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateActions([
                Action::make('add_card')
                    ->label('הוסף כרטיס חדש')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => static::getUrl('create'))
                    ->button(),
            ])
            ->striped()
            ->defaultSort('is_default', 'desc')
            ->defaultSort('created_at', 'desc');
    }

    /**
     * עמודים המשויכים ל-Resource
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientPaymentMethods::route('/'),
            'create' => Pages\CreateClientPaymentMethod::route('/create'),
            'view' => Pages\ViewClientPaymentMethod::route('/{record}'),
        ];
    }

    /**
     * אין עריכת כרטיס קיים
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * מספר הכרטיסים שפג תוקפם (badge בתפריט)
     */
    public static function getNavigationBadge(): ?string
    {
        $expiredCount = static::getEloquentQuery()
            ->get()
            ->filter(fn (OfficeGuyToken $token): bool => $token->isExpired())
            ->count();

        return $expiredCount > 0 ? (string) $expiredCount : null;
    }

    /**
     * צבע ה-badge (אדום לכרטיסים שפג תוקפם)
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'danger' : null;
    }

    /**
     * Tooltip for navigation badge
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        $count = static::getNavigationBadge();
        if (! $count) {
            return null;
        }

        return $count == 1
            ? 'כרטיס אחד שפג תוקפו'
            : "{$count} כרטיסים שפג תוקפם";
    }

    /**
     * כותרת עמוד מותאמת
     */
    public static function getNavigationLabel(): string
    {
        return 'אמצעי תשלום';
    }

    /**
     * תיאור ה-Resource
     */
    public static function getModelLabel(): string
    {
        return 'אמצעי תשלום';
    }

    public static function getPluralModelLabel(): string
    {
        return 'אמצעי תשלום';
    }
}
