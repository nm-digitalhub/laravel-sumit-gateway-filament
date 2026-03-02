<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Schemas;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolderField;

class CrmEntityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Folder Selection')
                    ->schema([
                        Forms\Components\Select::make('crm_folder_id')
                            ->label('CRM Folder')
                            ->options(CrmFolder::pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('folder_changed', true))
                            ->helperText('Select the folder type for this entity'),
                    ])
                    ->visible(fn ($record): bool => $record === null), // Only show on create

                Schemas\Components\Section::make('Standard Fields')
                    ->schema([
                        Forms\Components\Hidden::make('sumit_entity_id')
                            ->label('SUMIT Entity ID')
                            ->disabled(),

                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Optional link to local client')
                            ->native(false),

                        Forms\Components\TextInput::make('name')
                            ->label('Entity Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Main identifier for this entity'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(65535),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('mobile')
                            ->label('Mobile')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('tax_id')
                            ->label('VAT / Company Number')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('address')
                            ->label('Address')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City'),

                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code'),

                        Forms\Components\TextInput::make('country')
                            ->label('Country')
                            ->default('Israel'),

                        Forms\Components\Select::make('owner_user_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('User responsible for this entity'),

                        Forms\Components\Select::make('assigned_user_id')
                            ->label('Assigned To')
                            ->relationship('assigned', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                // Dynamic fields based on folder
                Schemas\Components\Section::make('Custom Fields')
                    ->schema(function ($record, $get): array {
                        $folderId = $record?->crm_folder_id ?? $get('crm_folder_id');

                        if (! $folderId) {
                            return [
                                Forms\Components\Placeholder::make('no_folder')
                                    ->label('')
                                    ->content('Please select a folder first to see custom fields'),
                            ];
                        }

                        $fields = CrmFolderField::where('crm_folder_id', $folderId)
                            // Migration defines 'display_order'; fall back to id if column missing
                            ->orderBy('display_order')
                            ->orderBy('id')
                            ->get();

                        if ($fields->isEmpty()) {
                            return [
                                Forms\Components\Placeholder::make('no_fields')
                                    ->label('')
                                    ->content('No custom fields defined for this folder'),
                            ];
                        }

                        $components = [];

                        foreach ($fields as $field) {
                            $components[] = self::makeFieldComponent($field, $record);
                        }

                        return $components;
                    })
                    ->columns(2)
                    ->collapsed(fn ($record): bool => $record !== null),

                Schemas\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_data')
                            ->label('Raw SUMIT Data')
                            ->disabled(),
                    ])
                    ->collapsed()
                    ->visible(fn ($record): bool => $record !== null),
            ]);
    }

    /**
     * Create a dynamic form component based on field type.
     */
    protected static function makeFieldComponent(CrmFolderField $field, $record): Forms\Components\Field
    {
        $fieldKey = "custom_field_{$field->id}";

        // Get current value from entity's custom fields
        $defaultValue = $record?->getCustomField($field->field_name);

        return match ($field->field_type) {
            'text', 'string' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->maxLength(255)
                ->helperText($field->description),

            'textarea', 'longtext' => Forms\Components\Textarea::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->rows(3)
                ->maxLength(65535)
                ->helperText($field->description),

            'number', 'integer' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->numeric()
                ->helperText($field->description),

            'decimal', 'float' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->numeric()
                ->step(0.01)
                ->helperText($field->description),

            'date' => Forms\Components\DatePicker::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->helperText($field->description),

            'datetime' => Forms\Components\DateTimePicker::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->helperText($field->description),

            'boolean', 'checkbox' => Forms\Components\Toggle::make($fieldKey)
                ->label($field->label)
                ->default((bool) $defaultValue)
                ->required((bool) $field->is_required)
                ->helperText($field->description),

            'select', 'dropdown' => Forms\Components\Select::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->options(self::parseOptions($field->options))
                ->searchable()
                ->helperText($field->description),

            'multiselect' => Forms\Components\Select::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue ? json_decode((string) $defaultValue, true) : [])
                ->required((bool) $field->is_required)
                ->options(self::parseOptions($field->options))
                ->multiple()
                ->searchable()
                ->helperText($field->description),

            'email' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->email()
                ->maxLength(255)
                ->helperText($field->description),

            'phone' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->tel()
                ->maxLength(20)
                ->helperText($field->description),

            'url' => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->url()
                ->maxLength(255)
                ->helperText($field->description),

            default => Forms\Components\TextInput::make($fieldKey)
                ->label($field->label)
                ->default($defaultValue)
                ->required((bool) $field->is_required)
                ->helperText($field->description ?? "Unknown field type: {$field->field_type}"),
        };
    }

    /**
     * Parse field options from JSON or array.
     */
    protected static function parseOptions($options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($options) ? $options : [];
    }
}
