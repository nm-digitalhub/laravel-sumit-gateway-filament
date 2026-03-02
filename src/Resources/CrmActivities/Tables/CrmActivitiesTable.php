<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class CrmActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('activity_type')
                    ->label(__('crm_activities.columns.activity_type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'note' => 'info',
                        'call' => 'success',
                        'email' => 'primary',
                        'meeting' => 'warning',
                        'task' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label(__('crm_activities.columns.subject'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->limit(50),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label(__('crm_activities.columns.related_to'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->placeholder('-')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('crm_activities.columns.created_by'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('crm_activities.columns.assigned_to'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_at')
                    ->label(__('crm_activities.columns.activity_date'))
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('crm_activities.columns.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('crm_activities.columns.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('reminder_at')
                    ->label(__('crm_activities.columns.reminder'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('overdue')
                    ->label(__('crm_activities.columns.overdue'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isOverdue())
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('activity_type')
                    ->label(__('crm_activities.filters.activity_type'))
                    ->options(__('crm_activities.options.activity_type'))
                    ->multiple(),

                Tables\Filters\SelectFilter::make('crm_entity_id')
                    ->label(__('crm_activities.filters.related_entity'))
                    ->relationship('entity', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('crm_activities.filters.status'))
                    ->options(__('crm_activities.options.status'))
                    ->multiple(),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('crm_activities.filters.priority'))
                    ->options(__('crm_activities.options.priority'))
                    ->multiple(),

                Tables\Filters\Filter::make('upcoming')
                    ->label(__('crm_activities.filters.upcoming'))
                    ->query(fn ($query) => $query->upcoming()),

                Tables\Filters\Filter::make('overdue')
                    ->label(__('crm_activities.filters.overdue'))
                    ->query(fn ($query) => $query->overdue()),

                Tables\Filters\Filter::make('has_reminder')
                    ->label(__('crm_activities.filters.has_reminder'))
                    ->query(fn ($query) => $query->whereNotNull('reminder_at')),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('crm_activities.filters.from')),
                        Forms\Components\DatePicker::make('to')->label(__('crm_activities.filters.to')),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('start_at', '>=', $date))
                        ->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('start_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()->requiresConfirmation(),

                Action::make('mark_in_progress')
                    ->label(__('crm_activities.actions.mark_in_progress'))
                    ->icon('heroicon-o-play-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->iconButton()
                    ->visible(fn ($record): bool => $record->status !== 'in_progress')
                    ->action(fn ($record) => $record->update(['status' => 'in_progress'])),

                Action::make('mark_completed')
                    ->label(__('crm_activities.actions.mark_completed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->iconButton()
                    ->visible(fn ($record): bool => $record->status !== 'completed')
                    ->action(function ($record): void {
                        $record->update([
                            'status' => 'completed',
                            'end_at' => $record->end_at ?? now(),
                        ]);
                    }),

                Action::make('cancel')
                    ->label(__('crm_activities.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->iconButton()
                    ->visible(fn ($record): bool => $record->status !== 'cancelled')
                    ->action(fn ($record) => $record->update(['status' => 'cancelled'])),
            ])
            ->bulkActions([
                BulkAction::make('bulk_mark_completed')
                    ->label(__('crm_activities.actions.mark_completed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update([
                        'status' => 'completed',
                        'end_at' => now(),
                    ])),

                BulkAction::make('bulk_mark_in_progress')
                    ->label(__('crm_activities.actions.mark_in_progress'))
                    ->icon('heroicon-o-play-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => 'in_progress'])),
            ])
            ->defaultSort('start_at', 'desc')
            ->deferFilters(false);
    }
}
