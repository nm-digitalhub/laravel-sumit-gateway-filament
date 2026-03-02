<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;
use OfficeGuy\LaravelSumitGateway\Services\CrmSchemaService;

class CrmFoldersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sumit_folder_id')
                    ->label('Folder ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'contact' => 'success',
                        'lead' => 'warning',
                        'company' => 'info',
                        'deal' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('entities_count')
                    ->label('Entities')
                    ->counts('entities')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fields_count')
                    ->label('Fields')
                    ->counts('fields')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_type')
                    ->label('Entity Type')
                    ->options([
                        'contact' => 'Contact',
                        'lead' => 'Lead',
                        'company' => 'Company',
                        'deal' => 'Deal',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('sync_schema')
                    ->label('Sync Schema')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Folder Schema')
                    ->modalDescription('This will sync field definitions from SUMIT CRM. Existing fields will be updated.')
                    ->action(function ($record): void {
                        try {
                            $result = CrmSchemaService::syncFolderSchema((int) $record->sumit_folder_id);

                            Notification::make()
                                ->title('Schema synced successfully')
                                ->body("Synced {$result['fields_synced']} fields for folder: {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('sync_entities')
                    ->label('Sync Entities')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync All Entities')
                    ->modalDescription('This will fetch and sync all entities from this folder. This may take several minutes.')
                    ->action(function ($record): void {
                        try {
                            $result = CrmDataService::syncAllEntities((int) $record->id);

                            Notification::make()
                                ->title('Entities synced successfully')
                                ->body("Synced {$result['entities_synced']} entities from folder: {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
