<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\TokenService;

class CreateClientPaymentMethod extends CreateRecord
{
    protected static string $resource = ClientPaymentMethodResource::class;

    /**
     * טופס יצירת כרטיס – נפרד מה-Form של ה-Resource (שמשמש רק ל-View)
     */
    public function form(Schema $schema): Schema
    {
        $pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));

        \Log::info('[CreateClientPaymentMethod] form() called', [
            'pci_mode' => $pciMode,
            'company_id' => config('officeguy.company_id'),
            'public_key' => substr((string) config('officeguy.public_key'), 0, 10) . '...',
        ]);

        $components = [];

        if ($pciMode === 'yes') {
            \Log::info('[CreateClientPaymentMethod] Using PCI mode');

            // מצב PCI – שולחים פרטי כרטיס מלאים לשרת (השירות מייצר טוקן)
            $components[] = TextInput::make('og-ccnum')
                ->label('Card Number')
                ->required()
                ->numeric()
                ->rule('digits_between:12,19');

            $components[] = TextInput::make('og-expmonth')
                ->label('Expiry Month')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12);

            $components[] = TextInput::make('og-expyear')
                ->label('Expiry Year')
                ->required()
                ->numeric()
                ->minValue((int) date('Y'))
                ->maxValue((int) date('Y') + 20);

            $components[] = TextInput::make('og-cvv')
                ->label('CVV')
                ->password()
                ->required()
                ->rule('digits_between:3,4');

            $components[] = TextInput::make('og-citizenid')
                ->label('ID Number')
                ->required();

            // אפשרות לסמן ככרטיס ברירת מחדל – ממוקם גבוה יותר בטופס
            $components[] = Toggle::make('set_as_default')
                ->label('Set as default payment method')
                ->default(true);
        } else {
            \Log::info('[CreateClientPaymentMethod] Using Direct API mode (no SDK)');

            // מצב Direct API – שדות Filament רגילים שנשלח ישירות ל-SUMIT API
            $components[] = TextInput::make('og-ccnum')
                ->label('Card Number')
                ->placeholder('0000 0000 0000 0000')
                ->required()
                ->numeric()
                ->minLength(12)
                ->maxLength(19)
                ->rule('digits_between:12,19')
                ->helperText('Enter your 16-digit card number');

            $components[] = TextInput::make('og-cvv')
                ->label('CVV')
                ->placeholder('123')
                ->password()
                ->required()
                ->numeric()
                ->minLength(3)
                ->maxLength(4)
                ->rule('digits_between:3,4')
                ->helperText('3-4 digits on the back of your card');

            $components[] = TextInput::make('og-expmonth')
                ->label('Expiry Month')
                ->placeholder('MM')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->rule('digits:2')
                ->helperText('2 digits (01-12)');

            $components[] = TextInput::make('og-expyear')
                ->label('Expiry Year')
                ->placeholder('YYYY')
                ->required()
                ->numeric()
                ->minValue((int) date('Y'))
                ->maxValue((int) date('Y') + 20)
                ->rule('digits:4')
                ->helperText('4 digits (e.g. 2025)');

            $components[] = TextInput::make('og-citizenid')
                ->label('ID Number')
                ->placeholder('123456789')
                ->required()
                ->numeric()
                ->minLength(9)
                ->maxLength(9)
                ->rule('digits:9')
                ->helperText('9-digit Israeli ID number');

            // Toggle קרוב לראש הטופס כדי שיהיה גלוי מיידית
            $components[] = Toggle::make('set_as_default')
                ->label('Set as default payment method')
                ->default(true);
        }

        return $schema->schema($components)->columns(2);
    }

    /**
     * כאן אנחנו "עוקפים" את יצירת המודל הרגילה של Filament
     * ומעבירים את הנתונים ל-TokenService שמדבר עם SUMIT ומייצר טוקן.
     */
    protected function handleRecordCreation(array $data): OfficeGuyToken
    {
        // תמיד נשתמש ב-PCI mode כי אנחנו שולחים שדות ישירים
        // (לא משתמשים ב-SDK Hosted Fields)
        $pciMode = 'yes';

        \Log::info('[CreateClientPaymentMethod] handleRecordCreation called', [
            'pci_mode' => $pciMode,
            'has_ccnum' => ! empty($data['og-ccnum']),
            'has_cvv' => ! empty($data['og-cvv']),
            'has_expmonth' => ! empty($data['og-expmonth']),
            'has_expyear' => ! empty($data['og-expyear']),
            'has_citizenid' => ! empty($data['og-citizenid']),
        ]);

        // TokenService משתמש ב-RequestHelpers::post, אז נזריק את הדאטה ל-request
        request()->merge($data);

        // Get client (token owner)
        $client = auth()->user()->client;
        if (! $client) {
            Notification::make()
                ->danger()
                ->title('Client not found')
                ->body('Cannot add payment method - no client associated with user')
                ->send();
            $this->halt();
        }

        $result = TokenService::processToken($client, $pciMode);

        if (! ($result['success'] ?? false)) {
            Notification::make()
                ->danger()
                ->title('Failed to add payment method')
                ->body($result['message'] ?? 'Unknown error')
                ->send();

            // עוצר את תהליך ה-Create של Filament
            $this->halt();
        }

        /** @var OfficeGuyToken $token */
        $token = $result['token'];

        // אם המשתמש בחר "Set as default" – נסמן את הטוקן כברירת מחדל
        if (! empty($data['set_as_default'])) {
            $token->setAsDefault();
        }

        Notification::make()
            ->success()
            ->title('Payment method added')
            ->body('Your card has been saved successfully.')
            ->send();

        return $token;
    }

    /**
     * אחרי יצירה – נחזור לרשימת אמצעי התשלום
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * לא משתמשים ב-Model::create() אלא ב-handleRecordCreation בלבד
     * אבל צריך להחזיר את ה-data כדי ש-Filament יקרא ל-handleRecordCreation
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // מחזירים את הדאטה כפי שהוא - handleRecordCreation יטפל ביצירה
        return $data;
    }

    /**
     * Override Filament's default validation to allow our custom flow
     */
    protected function getFormModel(): string
    {
        return OfficeGuyToken::class;
    }
}
