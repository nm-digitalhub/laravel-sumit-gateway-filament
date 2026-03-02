<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Schemas;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;

class CrmFolderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Folder Information')
                    ->schema([
                        Forms\Components\TextInput::make('sumit_folder_id')
                            ->label('SUMIT Folder ID')
                            ->disabled()
                            ->helperText('Automatically synced from SUMIT CRM'),

                        Forms\Components\TextInput::make('name')
                            ->label('Folder Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('entity_type')
                            ->label('Entity Type')
                            ->disabled()
                            ->helperText('contacts, leads, companies, deals, etc.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2),

                Schemas\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('Folder Settings')
                            ->disabled(),
                    ])
                    ->collapsed(),

                Schemas\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created At')
                            ->content(fn ($record) => $record?->created_at?->format('Y-m-d H:i:s')),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated At')
                            ->content(fn ($record) => $record?->updated_at?->format('Y-m-d H:i:s')),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }
}
