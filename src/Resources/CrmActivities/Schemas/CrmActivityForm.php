<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

class CrmActivityForm
{
    /**
     * SUMIT folder ID for CRM entities used to populate the related-entity select.
     * Default: Customers folder.
     */
    private const SUMIT_FOLDER_ID = 1076734599;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Activity Details')
                    ->schema([
                        Forms\Components\Select::make('crm_entity_id')
                            ->label(__('crm_activities.fields.related_entity'))
                            ->options(function () {
                                $folder = CrmFolder::where('sumit_folder_id', self::SUMIT_FOLDER_ID)->first();
                                if (! $folder) {
                                    return [];
                                }

                                // Ensure local cache is fresh; if empty, pull from SUMIT once.
                                $cacheKey = "crm_entities_folder_local_{$folder->id}";

                                return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($folder) {
                                    // If no local entities, try a fresh sync from SUMIT
                                    if (! $folder->entities()->exists()) {
                                        CrmDataService::syncAllEntities($folder->id, [
                                            'LoadProperties' => true,
                                            'Paging' => ['StartIndex' => 0, 'PageSize' => 500],
                                        ]);
                                    }

                                    return $folder->entities()
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(function ($entity): array {
                                            $label = $entity->name;
                                            if ($entity->client?->name) {
                                                $label = $entity->client->name . ' — ' . $entity->name;
                                            }

                                            return [$entity->id => $label];
                                        })
                                        ->toArray();
                                });
                            })
                            ->placeholder(__('crm_activities.fields.related_entity'))
                            ->preload()
                            ->searchable()
                            ->required()
                            ->helperText(__('crm_activities.help.related_entity'))
                            ->disablePlaceholderSelection(),

                        Forms\Components\Select::make('activity_type')
                            ->label(__('crm_activities.fields.activity_type'))
                            ->options(__('crm_activities.options.activity_type'))
                            ->required()
                            ->default('note')
                            ->native(false),

                        Forms\Components\TextInput::make('subject')
                            ->label(__('crm_activities.fields.subject'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label(__('crm_activities.fields.status'))
                            ->options(__('crm_activities.options.status'))
                            ->required()
                            ->default('planned')
                            ->native(false),

                        Forms\Components\Select::make('priority')
                            ->label(__('crm_activities.fields.priority'))
                            ->options(__('crm_activities.options.priority'))
                            ->required()
                            ->default('normal')
                            ->native(false),

                        Forms\Components\Select::make('user_id')
                            ->label(__('crm_activities.fields.assigned_to'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('crm_activities.help.assigned_to')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Schemas\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('description')
                            ->label(__('crm_activities.fields.description'))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Schemas\Components\Section::make('Timing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label(__('crm_activities.fields.start_at'))
                            ->native(false)
                            ->seconds(false)
                            ->helperText(__('crm_activities.help.start_at')),

                        Forms\Components\DateTimePicker::make('end_at')
                            ->label(__('crm_activities.fields.end_at'))
                            ->native(false)
                            ->seconds(false)
                            ->after('start_at')
                            ->helperText(__('crm_activities.help.end_at')),

                        Forms\Components\DateTimePicker::make('reminder_at')
                            ->label(__('crm_activities.fields.reminder_at'))
                            ->native(false)
                            ->seconds(false)
                            ->before('start_at')
                            ->helperText(__('crm_activities.help.reminder_at')),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsible(),

                Schemas\Components\Section::make('Related Items')
                    ->schema([
                        Forms\Components\Select::make('related_document_id')
                            ->label(__('crm_activities.fields.related_document_id'))
                            ->relationship('document', 'document_number')
                            ->searchable()
                            ->preload()
                            ->helperText(__('crm_activities.help.related_document')),

                        Forms\Components\TextInput::make('related_ticket_id')
                            ->label(__('crm_activities.fields.related_ticket_id'))
                            ->numeric()
                            ->helperText(__('crm_activities.help.related_ticket')),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
