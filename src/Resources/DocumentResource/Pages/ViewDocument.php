<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource\Pages;

use App\Models\Client;
use App\Models\Order;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

            Actions\Action::make('open_order')
                ->label('פתח הזמנה')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->visible(fn ($record): bool => $record->order_id && Order::find($record->order_id))
                ->url(function ($record): ?string {
                    $order = Order::find($record->order_id);

                    return $order ? route('filament.admin.resources.orders.view', ['record' => $order->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('open_subscription')
                ->label('פתח מנוי')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record): bool => $record->subscription_id && Subscription::find($record->subscription_id))
                ->url(function ($record): ?string {
                    $sub = Subscription::find($record->subscription_id);

                    return $sub ? route('filament.admin.resources.subscriptions.view', ['record' => $sub->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('check_debt')
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

            Actions\Action::make('send_payment_link')
                ->label('שליחת לינק תשלום')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn ($record): bool => ! empty($record->customer_id))
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('אימייל יעד')
                        ->email()
                        ->default(fn ($record) => $record->client?->email)
                        ->required(false),
                    \Filament\Forms\Components\TextInput::make('phone')
                        ->label('מספר נייד (ל‑SMS)')
                        ->default(fn ($record) => $record->client?->phone)
                        ->tel()
                        ->required(false),
                ])
                ->requiresConfirmation()
                ->action(function ($record, array $data): void {
                    $result = app(\OfficeGuy\LaravelSumitGateway\Services\DebtService::class)->sendPaymentLink(
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

            Actions\DeleteAction::make(),
        ];
    }
}
