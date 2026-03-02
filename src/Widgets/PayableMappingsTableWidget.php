<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Widgets;

use Bytexr\QueueableBulkActions\Filament\Actions\QueueableBulkAction;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BulkPayableMappingActivateJob;
use OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BulkPayableMappingDeactivateJob;
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * PayableMappingsTableWidget
 *
 * Displays advanced Payable field mappings from the payable_field_mappings table.
 * Note: Basic field mapping from OfficeGuy Settings is shown in the form above.
 * Shown at the bottom of the OfficeGuySettings page.
 */
class PayableMappingsTableWidget extends BaseWidget
{
    /**
     * Widget heading.
     */
    protected static ?string $heading = 'מיפויי Payable מתקדמים (טבלה)';

    /**
     * Widget column span (full width).
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * Configure the table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(PayableFieldMapping::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('תווית')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->icon('heroicon-o-tag')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('model_class')
                    ->label('מחלקת מודל')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => class_basename($state))
                    ->description(fn ($record) => $record->model_class)
                    ->copyable()
                    ->copyMessage('הועתק!')
                    ->copyMessageDuration(1500)
                    ->tooltip('לחץ להעתקת המחלקה המלאה'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('פעיל')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('mapped_fields_count')
                    ->label('שדות ממופים')
                    ->state(fn ($record): int => count(array_filter($record->field_mappings ?? [])))
                    ->badge()
                    ->color('info')
                    ->suffix(' / 16')
                    ->icon('heroicon-o-arrows-right-left'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר ב')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('עודכן ב')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\Action::make('view')
                    ->label('צפה')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record): string => "מיפוי: {$record->label}")
                    ->modalDescription(fn ($record) => $record->model_class)
                    ->modalContent(fn ($record): \Illuminate\Contracts\View\Factory | \Illuminate\Contracts\View\View => view('officeguy::components.mapping-details', [
                        'mapping' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('סגור')
                    ->modalWidth('5xl'),

                Actions\Action::make('toggle_active')
                    ->label(fn ($record): string => $record->is_active ? 'השבת' : 'הפעל')
                    ->icon(fn ($record): string => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn ($record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record): string => $record->is_active ? 'השבתת מיפוי' : 'הפעלת מיפוי')
                    ->modalDescription(
                        fn ($record): string => $record->is_active
                            ? 'המיפוי יושבת ולא ישמש עבור יצירת Payable wrappers'
                            : 'המיפוי יופעל וישמש עבור יצירת Payable wrappers'
                    )
                    ->action(function ($record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'המיפוי הופעל' : 'המיפוי הושבת')
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->label('מחק')
                    ->modalHeading('מחיקת מיפוי')
                    ->modalDescription('האם אתה בטוח שברצונך למחוק את המיפוי? פעולה זו בלתי הפיכה.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('המיפוי נמחק')
                            ->body('המיפוי נמחק בהצלחה מהמערכת')
                    ),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    // Queueable bulk action (asynchronous, disabled by default)
                    QueueableBulkAction::make('activate')
                        ->label('הפעל נבחרים')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->job(BulkPayableMappingActivateJob::class)
                        ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
                        ->successNotificationTitle(__('officeguy::messages.bulk_mapping_activate_success'))
                        ->failureNotificationTitle(__('officeguy::messages.bulk_mapping_partial'))
                        ->modalHeading(__('officeguy::messages.bulk_mapping_activate_confirm'))
                        ->modalDescription(__('officeguy::messages.bulk_mapping_activate_desc')),

                    // Legacy synchronous bulk action (for backwards compatibility)
                    Actions\BulkAction::make('activate_sync')
                        ->label('הפעל נבחרים')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => ! config('officeguy.bulk_actions.enabled', false) || config('officeguy.bulk_actions.enable_legacy_actions', false))
                        ->action(function ($records): void {
                            $records->each->update(['is_active' => true]);

                            Notification::make()
                                ->title('המיפויים הופעלו')
                                ->success()
                                ->send();
                        }),

                    // Queueable bulk action (asynchronous, disabled by default)
                    QueueableBulkAction::make('deactivate')
                        ->label('השבת נבחרים')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->job(BulkPayableMappingDeactivateJob::class)
                        ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
                        ->successNotificationTitle(__('officeguy::messages.bulk_mapping_deactivate_success'))
                        ->failureNotificationTitle(__('officeguy::messages.bulk_mapping_partial'))
                        ->modalHeading(__('officeguy::messages.bulk_mapping_deactivate_confirm'))
                        ->modalDescription(__('officeguy::messages.bulk_mapping_deactivate_desc')),

                    // Legacy synchronous bulk action (for backwards compatibility)
                    Actions\BulkAction::make('deactivate_sync')
                        ->label('השבת נבחרים')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => ! config('officeguy.bulk_actions.enabled', false) || config('officeguy.bulk_actions.enable_legacy_actions', false))
                        ->action(function ($records): void {
                            $records->each->update(['is_active' => false]);

                            Notification::make()
                                ->title('המיפויים הושבתו')
                                ->success()
                                ->send();
                        }),

                    Actions\DeleteBulkAction::make()
                        ->modalHeading('מחיקת מיפויים')
                        ->modalDescription('האם אתה בטוח שברצונך למחוק את המיפויים הנבחרים? פעולה זו בלתי הפיכה.')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('המיפויים נמחקו')
                                ->body('כל המיפויים הנבחרים נמחקו בהצלחה')
                        ),
                ]),
            ])
            ->emptyStateHeading('אין מיפויים מתקדמים')
            ->emptyStateDescription('מיפוי בסיסי מוגדר בהגדרות המערכת למעלה. טבלה זו מציגה רק מיפויים מתקדמים מהטבלה.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
