<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class CrmEntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sumit_entity_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('crm_folder_id')
                    ->label('Folder ID')
                    ->state(fn ($record) => $record->folder?->sumit_folder_id ?? $record->crm_folder_id)
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record->folder?->name),

                Tables\Columns\TextColumn::make('folder.name')
                    ->label('Folder')
                    ->badge()
                    ->color(fn ($record): string => match ($record->folder?->entity_type) {
                        'contact' => 'success',
                        'lead' => 'warning',
                        'company' => 'info',
                        'deal' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('-')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sumit_balance')
                    ->label('יתרה')
                    ->state(function ($record) {
                        if (! $record->sumit_customer_id) {
                            return null;
                        }

                        return Cache::remember(
                            'sumit_balance_' . $record->sumit_customer_id,
                            300,
                            function () use ($record) {
                                $service = app(DebtService::class);

                                return $service->getCustomerBalanceById((int) $record->sumit_customer_id);
                            }
                        );
                    })
                    ->formatStateUsing(fn ($state) => $state['formatted'] ?? null)
                    ->badge()
                    ->color(function (array $state): string {
                        $debt = $state['debt'] ?? 0;

                        return $debt > 0 ? 'danger' : ($debt < 0 ? 'success' : 'gray');
                    })
                    ->icon('heroicon-o-scale')
                    ->tooltip('Automated SUMIT debt/credit balance (cached 5 minutes)')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assigned.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('activities_count')
                    ->label('Activities')
                    ->counts('activities')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('deleted_at')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record): bool => $record->deleted_at === null)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('crm_folder_id')
                    ->label('Folder')
                    ->relationship('folder', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('owner_user_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Assigned To')
                    ->relationship('assigned', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                // Sync Entity from SUMIT
                Action::make('sync_from_sumit')
                    ->label('Sync from SUMIT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Entity from SUMIT')
                    ->modalDescription('This will fetch the latest data from SUMIT CRM and update this entity.')
                    ->action(function ($record): void {
                        try {
                            $result = CrmDataService::syncEntityFromSumit((int) $record->sumit_entity_id);

                            Notification::make()
                                ->title('Entity synced successfully')
                                ->body("Updated: {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->sumit_entity_id !== null),

                Action::make('sync_to_sumit')
                    ->label('Sync to SUMIT (push)')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Push entity to SUMIT')
                    ->modalDescription('Creates/updates the customer in SUMIT and stores the returned SUMIT ID locally.')
                    ->action(function (CrmEntity $record): void {
                        $result = $record->syncToSumit();

                        if ($result['success'] ?? false) {
                            Notification::make()
                                ->title('Pushed to SUMIT')
                                ->body('SUMIT ID: ' . ($result['sumit_customer_id'] ?? 'unknown'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Push failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),

                // Archive Entity (soft delete alternative)
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Entity')
                    ->modalDescription('This will archive the entity in SUMIT. You can restore it later.')
                    ->action(function ($record): void {
                        try {
                            $result = CrmDataService::archiveEntity((int) $record->sumit_entity_id);

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Entity archived')
                                    ->body("Archived: {$record->name}")
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception($result['error']);
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Archive failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->sumit_entity_id !== null && ! $record->trashed()),

                // Export as PDF
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function ($record) {
                        try {
                            $result = CrmDataService::getEntityPrintHTML(
                                (int) $record->sumit_entity_id,
                                $record->crm_folder_id,
                                true // PDF format
                            );

                            if ($result['success']) {
                                // Decode base64 PDF and download
                                $pdf = base64_decode($result['pdf']);
                                $filename = "entity-{$record->sumit_entity_id}.pdf";

                                return response()->streamDownload(function () use ($pdf): void {
                                    echo $pdf;
                                }, $filename, ['Content-Type' => 'application/pdf']);
                            }

                            throw new \Exception($result['error']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Export failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->sumit_entity_id !== null),

                // Check Usage Count (before deletion)
                Action::make('check_usage')
                    ->label('Check Usage')
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->color('gray')
                    ->modalHeading('Entity Usage Count')
                    ->modalWidth('md')
                    ->action(function ($record): void {
                        try {
                            $result = CrmDataService::countEntityUsage((int) $record->sumit_entity_id);

                            if ($result['success']) {
                                $count = $result['usage_count'];
                                Notification::make()
                                    ->title('Usage Count')
                                    ->body("This entity is referenced {$count} times in the system.")
                                    ->info()
                                    ->persistent()
                                    ->send();
                            } else {
                                throw new \Exception($result['error']);
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Check failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->sumit_entity_id !== null),

                Action::make('check_debt')
                    ->label('Check Debt')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        try {
                            if (! $record->sumit_customer_id) {
                                throw new \Exception('Missing SUMIT customer ID on this entity.');
                            }

                            $balance = app(DebtService::class)->getCustomerBalanceById((int) $record->sumit_customer_id);

                            if (! $balance) {
                                throw new \Exception('Failed to retrieve balance from SUMIT.');
                            }

                            Notification::make()
                                ->title('Balance')
                                ->body($balance['formatted'])
                                ->color($balance['debt'] > 0 ? 'danger' : ($balance['debt'] < 0 ? 'success' : 'gray'))
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Debt check failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => $record->sumit_customer_id !== null),

                // Add Activity
                Action::make('add_activity')
                    ->label('Add Activity')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn ($record): string => route('filament.admin.resources.crm-activities.create', [
                        'crm_entity_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(false),
            ])
            ->toolbarActions([
                // Header Actions
                Action::make('export_all')
                    ->label('Export All as PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Export All Entities')
                    ->modalDescription('This will generate a PDF with all entities in the selected view.')
                    ->action(function (Table $table) {
                        try {
                            // Get current filters/search
                            $query = $table->getQuery();
                            $folder = $query->first()?->folder;

                            if (! $folder) {
                                throw new \Exception('Please select a folder filter first');
                            }

                            // Get first view for this folder
                            $view = $folder->views()->first();

                            if (! $view || ! $view->sumit_view_id) {
                                throw new \Exception('No view available for this folder');
                            }

                            $result = CrmDataService::getEntitiesHTML(
                                $folder->sumit_folder_id,
                                $view->sumit_view_id,
                                true // PDF
                            );

                            if ($result['success']) {
                                $pdf = base64_decode($result['pdf']);
                                $filename = "crm-entities-{$folder->name}.pdf";

                                return response()->streamDownload(function () use ($pdf): void {
                                    echo $pdf;
                                }, $filename, ['Content-Type' => 'application/pdf']);
                            }

                            throw new \Exception($result['error']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Export failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('sync_all')
                    ->label('Sync All from SUMIT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('folder_id')
                            ->label('Select Folder')
                            ->options(\OfficeGuy\LaravelSumitGateway\Models\CrmFolder::pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->helperText('Choose which folder to sync entities from'),
                    ])
                    ->modalHeading('Sync All Entities from SUMIT')
                    ->modalDescription('This will sync all entities from the selected folder. This may take a while.')
                    ->modalSubmitActionLabel('Sync Entities')
                    ->action(function (array $data): void {
                        try {
                            $folder = \OfficeGuy\LaravelSumitGateway\Models\CrmFolder::find($data['folder_id']);

                            if (! $folder) {
                                throw new \Exception('Folder not found');
                            }

                            $result = CrmDataService::syncAllEntities($folder->id);

                            if (! $result['success']) {
                                throw new \Exception($result['error'] ?? 'Sync failed');
                            }

                            $syncedCount = $result['entities_synced'] ?? 0;

                            Notification::make()
                                ->title('Sync completed')
                                ->body("Synced {$syncedCount} entities from {$folder->name}")
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

                // Bulk Actions
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    Action::make('bulk_archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $success = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->sumit_entity_id) {
                                    $result = CrmDataService::archiveEntity((int) $record->sumit_entity_id);
                                    $result['success'] ? $success++ : $failed++;
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Archive Complete')
                                ->body("Archived: {$success}, Failed: {$failed}")
                                ->success()
                                ->send();
                        }),

                    Action::make('bulk_sync')
                        ->label('Sync Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $success = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->sumit_entity_id) {
                                    try {
                                        $result = CrmDataService::syncEntityFromSumit((int) $record->sumit_entity_id);
                                        $success++;
                                    } catch (\Exception) {
                                        $failed++;
                                    }
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Sync Complete')
                                ->body("Synced: {$success}, Failed: {$failed}")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->deferFilters(false); // Instant filtering
    }
}
