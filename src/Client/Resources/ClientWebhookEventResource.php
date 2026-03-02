<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientWebhookEventResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyClientPlugin;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

class ClientWebhookEventResource extends Resource
{
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = WebhookEvent::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyClientPlugin
    {
        return OfficeGuyClientPlugin::get();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Show only webhooks related to the current client's SUMIT customer ID transactions
        if (auth()->check()) {
            $client = auth()->user()->client;

            if (! $client) {
                // No client found - return empty query
                return parent::getEloquentQuery()->whereRaw('1 = 0');
            }

            $query->whereHas('transaction', function ($q) use ($client): void {
                $q->where('customer_id', $client->getSumitCustomerId());
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('פרטי Webhook')
                    ->schema([
                        Forms\Components\TextInput::make('event_type')
                            ->label('סוג אירוע')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('סטטוס')
                            ->disabled(),
                        Forms\Components\TextInput::make('url')
                            ->label('URL')
                            ->disabled(),
                        Forms\Components\TextInput::make('http_status')
                            ->label('HTTP Status')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Payload')
                    ->schema([
                        Forms\Components\Textarea::make('payload')
                            ->label('נתונים')
                            ->rows(10)
                            ->disabled()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state),
                    ]),

                Schemas\Components\Section::make('תגובה')
                    ->schema([
                        Forms\Components\Textarea::make('response')
                            ->label('Response')
                            ->rows(5)
                            ->disabled(),
                    ]),

                Schemas\Components\Section::make('ניסיונות חוזרים')
                    ->schema([
                        Forms\Components\TextInput::make('retry_count')
                            ->label('מספר ניסיונות')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_attempted_at')
                            ->label('ניסיון אחרון')
                            ->disabled(),
                        Forms\Components\TextInput::make('succeeded_at')
                            ->label('הצליח בתאריך')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('סוג אירוע')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'הצליח',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('http_status')
                    ->label('HTTP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('retry_count')
                    ->label('ניסיונות')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('succeeded_at')
                    ->label('הצליח')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'success' => 'הצליח',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                    ]),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('סוג אירוע')
                    ->options([
                        'payment.completed' => 'תשלום הושלם',
                        'payment.failed' => 'תשלום נכשל',
                        'subscription.created' => 'מנוי נוצר',
                        'subscription.renewed' => 'מנוי חודש',
                        'subscription.cancelled' => 'מנוי בוטל',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->label('צפייה'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('אין Webhook Logs')
            ->emptyStateDescription('לא נמצאו webhooks יוצאים');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientWebhookEvents::route('/'),
            'view' => Pages\ViewClientWebhookEvent::route('/{record}'),
        ];
    }
}
