<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas;

use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class CrmActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Activity Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('activity_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'note' => 'info',
                                'call' => 'success',
                                'email' => 'primary',
                                'meeting' => 'warning',
                                'task' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('subject')
                            ->label('Subject')
                            ->weight('semibold'),

                        Infolists\Components\TextEntry::make('activity_date')
                            ->label('Activity Date')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('entity.name')
                            ->label('Related Entity')
                            ->url(fn ($record): ?string => $record->entity
                                ? route('filament.admin.resources.crm-entities.edit', $record->entity)
                                : null),

                        Infolists\Components\TextEntry::make('client.name')
                            ->label('Client')
                            ->visible(fn ($record) => $record->client)
                            ->url(fn ($record): ?string => $record->client ? route('filament.admin.resources.clients.edit', $record->client_id) : null),

                        Infolists\Components\TextEntry::make('createdBy.name')
                            ->label('Created By'),

                        Infolists\Components\TextEntry::make('related_ticket_id')
                            ->label('Related Ticket')
                            ->visible(fn ($record): bool => $record->related_ticket_id !== null),

                        Infolists\Components\TextEntry::make('related_document_id')
                            ->label('Related Document')
                            ->visible(fn ($record): bool => $record->related_document_id !== null),
                    ])->columns(2),

                Schemas\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => ! empty($record->description)),

                Schemas\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->label('Additional Data'),
                    ])
                    ->collapsed()
                    ->visible(fn ($record): bool => ! empty($record->metadata)),

                Schemas\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }
}
