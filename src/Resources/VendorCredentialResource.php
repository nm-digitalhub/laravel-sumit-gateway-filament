<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource\Pages;
use OfficeGuy\LaravelSumitGateway\Models\VendorCredential;

class VendorCredentialResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = VendorCredential::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Vendor Information')
                    ->schema([
                        Forms\Components\TextInput::make('vendor_type')
                            ->label('Vendor Type')
                            ->required()
                            ->helperText('The model class (e.g., App\\Models\\Vendor)'),
                        Forms\Components\TextInput::make('vendor_id')
                            ->label('Vendor ID')
                            ->required()
                            ->numeric(),
                    ])->columns(2),

                Schemas\Components\Section::make('SUMIT API Credentials')
                    ->schema([
                        Forms\Components\TextInput::make('company_id')
                            ->label('Company ID')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Key')
                            ->required()
                            ->password()
                            ->revealable(),
                        Forms\Components\TextInput::make('public_key')
                            ->label('Public Key'),
                        Forms\Components\TextInput::make('merchant_number')
                            ->label('Merchant Number'),
                    ])->columns(2),

                Schemas\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('validation_status')
                            ->label('Validation Status')
                            ->disabled(),
                        Forms\Components\Textarea::make('validation_message')
                            ->label('Validation Message')
                            ->disabled()
                            ->rows(2),
                        Forms\Components\DateTimePicker::make('validated_at')
                            ->label('Last Validated')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data'),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor_type')
                    ->label('Vendor Type')
                    ->formatStateUsing(fn ($state): string => class_basename($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor_id')
                    ->label('Vendor ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('company_id')
                    ->label('Company ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('validation_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'valid' => 'success',
                        'invalid' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('validated_at')
                    ->label('Validated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('validation_status')
                    ->options([
                        'valid' => 'Valid',
                        'invalid' => 'Invalid',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('validate')
                    ->label('Validate')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $isValid = $record->validateCredentials();

                        if ($isValid) {
                            Notification::make()
                                ->title('Credentials valid')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Credentials invalid')
                                ->body($record->validation_message)
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('toggle_active')
                    ->label(fn ($record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record): string => $record->is_active ? 'heroicon-o-x-mark' : 'heroicon-o-check')
                    ->color(fn ($record): string => $record->is_active ? 'danger' : 'success')
                    ->action(function ($record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Vendor activated' : 'Vendor deactivated')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('validate_all')
                        ->label('Validate Selected')
                        ->icon('heroicon-o-check-badge')
                        ->action(function ($records): void {
                            $valid = 0;
                            $invalid = 0;

                            foreach ($records as $record) {
                                if ($record->validateCredentials()) {
                                    $valid++;
                                } else {
                                    $invalid++;
                                }
                            }

                            Notification::make()
                                ->title('Validation complete')
                                ->body("Valid: {$valid}, Invalid: {$invalid}")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorCredentials::route('/'),
            'create' => Pages\CreateVendorCredential::route('/create'),
            'view' => Pages\ViewVendorCredential::route('/{record}'),
            'edit' => Pages\EditVendorCredential::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $invalidCount = static::getModel()::where('validation_status', 'invalid')->count();

        return $invalidCount > 0 ? (string) $invalidCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
