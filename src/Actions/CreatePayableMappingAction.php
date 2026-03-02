<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;
use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;

/**
 * CreatePayableMappingAction
 *
 * Filament v4 wizard action for creating custom Payable field mappings.
 * Provides a 3-step interface:
 * 1. Model Selection - Choose and validate the model class
 * 2. Field Mapping - Map 16 Payable fields to model fields
 * 3. Review - Preview and confirm the mapping
 *
 * Uses advanced Filament v4 features:
 * - Wizard steps with icons and descriptions
 * - Live validation (live(onBlur: true))
 * - Reactive fields (afterStateUpdated)
 * - Dynamic options (createOptionForm)
 * - Collapsible sections
 * - Suffix icons for visual feedback
 * - Client-side performance optimizations
 */
class CreatePayableMappingAction
{
    /**
     * Create the wizard action instance.
     */
    public static function make(): Action
    {
        return Action::make('create_payable_mapping')
            ->label('הוסף מיפוי Payable חדש')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->modalHeading('מיפוי שדות Payable למודל')
            ->modalDescription('צור מיפוי מותאם אישית בין המודל שלך לממשק Payable')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('שמור מיפוי')
            ->modalCancelActionLabel('ביטול')
            ->steps([
                static::getModelSelectionStep(),
                static::getFieldMappingStep(),
                static::getReviewStep(),
            ])
            ->action(static::handleSubmit(...));
    }

    /**
     * Step 1: Model Selection
     *
     * User selects a model class and the system validates it exists.
     */
    protected static function getModelSelectionStep(): Step
    {
        return Step::make('model_selection')
            ->label('בחירת מודל')
            ->description('בחר את המודל שברצונך למפות לממשק Payable')
            ->icon('heroicon-o-cube')
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('model_class')
                            ->label('מחלקת המודל (Class)')
                            ->placeholder('App\\Models\\MayaNetEsimProduct')
                            ->helperText('הזן את השם המלא של המודל כולל namespace')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                // Validate model exists
                                if (! $state) {
                                    $set('model_valid', false);
                                    $set('model_error', null);
                                    $set('available_fields', []);

                                    return;
                                }

                                if (class_exists($state)) {
                                    $set('model_valid', true);
                                    $set('model_error', null);

                                    // Load model fields
                                    $fields = static::getModelFields($state);
                                    $set('available_fields', $fields);
                                } else {
                                    $set('model_valid', false);
                                    $set('model_error', 'המודל לא קיים במערכת. ודא שה-namespace נכון.');
                                    $set('available_fields', []);
                                }
                            })
                            ->suffixIcon(
                                fn (Get $get): string => $get('model_valid')
                                    ? 'heroicon-o-check-circle'
                                    : 'heroicon-o-x-circle'
                            )
                            ->suffixIconColor(
                                fn (Get $get): string => $get('model_valid') ? 'success' : 'danger'
                            ),

                        Hidden::make('model_valid')
                            ->default(false),

                        Hidden::make('available_fields')
                            ->default([]),

                        TextEntry::make('model_error')
                            ->state(fn (Get $get): mixed => $get('model_error'))
                            ->visible(fn (Get $get): bool => ! $get('model_valid') && $get('model_class'))
                            ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400']),

                        TextEntry::make('model_info')
                            ->label('מידע על המודל')
                            ->state(function (Get $get): null | \Illuminate\Contracts\View\Factory | \Illuminate\Contracts\View\View | string {
                                $modelClass = $get('model_class');
                                if (! $modelClass || ! class_exists($modelClass)) {
                                    return null;
                                }

                                try {
                                    $reflection = new \ReflectionClass($modelClass);
                                    $model = new $modelClass;
                                    $fillable = method_exists($model, 'getFillable')
                                        ? $model->getFillable()
                                        : [];

                                    return view('officeguy::components.model-info', [
                                        'file' => $reflection->getFileName(),
                                        'fillable' => $fillable,
                                        'modelClass' => $modelClass,
                                    ]);
                                } catch (\Exception $e) {
                                    return 'שגיאה בטעינת מידע המודל: ' . $e->getMessage();
                                }
                            })
                            ->visible(fn (Get $get): mixed => $get('model_valid')),

                        TextInput::make('mapping_label')
                            ->label('תווית למיפוי (אופציונלי)')
                            ->placeholder('מיפוי eSIM Product')
                            ->helperText('תווית תיאורית שתעזור לזהות את המיפוי בעתיד')
                            ->visible(fn (Get $get): mixed => $get('model_valid')),
                    ])
                    ->columnSpanFull(),
            ])
            ->afterValidation(function (Get $get): void {
                if (! $get('model_valid')) {
                    throw new \Exception('יש לבחור מודל תקין לפני המשך');
                }
            });
    }

    /**
     * Step 2: Field Mapping
     *
     * User maps each of the 16 Payable fields to their model's actual fields.
     */
    protected static function getFieldMappingStep(): Step
    {
        $mappingService = app(PayableMappingService::class);

        return Step::make('field_mapping')
            ->label('מיפוי שדות')
            ->description('מפה כל שדה מממשק Payable לשדה המתאים במודל שלך')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Section::make('שדות חובה')
                    ->description('שדות אלו חייבים להיות ממופים כדי ש-Payable יעבוד כראוי')
                    ->icon('heroicon-o-exclamation-circle')
                    ->collapsible()
                    ->columns(1)
                    ->schema(
                        static::buildMappingFieldsForCategory('required', $mappingService->getRequiredPayableFields())
                    )
                    ->columnSpanFull(),

                Section::make('שדות אופציונליים')
                    ->description('שדות אלו ניתנים להשאיר ריקים אם אינם רלוונטיים למודל שלך')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->collapsed()
                    ->columns(1)
                    ->schema(
                        static::buildMappingFieldsForCategory('optional', $mappingService->getOptionalPayableFields())
                    )
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Step 3: Review
     *
     * User reviews the complete mapping before saving.
     */
    protected static function getReviewStep(): Step
    {
        return Step::make('review')
            ->label('סקירה')
            ->description('סקור את המיפוי לפני שמירה')
            ->icon('heroicon-o-eye')
            ->schema([
                Section::make('סיכום המיפוי')
                    ->schema([
                        TextEntry::make('review_summary')
                            ->label('')
                            ->state(function (Get $get): \Illuminate\Contracts\View\Factory | \Illuminate\Contracts\View\View {
                                $modelClass = $get('model_class');
                                $label = $get('mapping_label');
                                $mappings = static::collectMappings($get);

                                return view('officeguy::components.mapping-review', [
                                    'modelClass' => $modelClass,
                                    'label' => $label,
                                    'mappings' => $mappings,
                                ]);
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Build mapping fields for a specific category (required/optional).
     *
     * @param  string  $category  'required' or 'optional'
     * @param  array  $fields  Payable fields to generate inputs for
     */
    protected static function buildMappingFieldsForCategory(string $category, array $fields): array
    {
        $components = [];

        foreach ($fields as $field) {
            $components[] = Grid::make(2)
                ->schema([
                    // Right column: Payable field info
                    Fieldset::make()
                        ->label($field['label_he'])
                        ->schema([
                            TextEntry::make("info_method_{$field['key']}")
                                ->label('מתודה')
                                ->state($field['method'])
                                ->extraAttributes(['class' => 'font-mono text-sm']),

                            TextEntry::make("info_return_{$field['key']}")
                                ->label('סוג החזרה')
                                ->state($field['return_type'])
                                ->extraAttributes(['class' => 'font-mono text-xs text-gray-500 dark:text-gray-400']),

                            TextEntry::make("info_desc_{$field['key']}")
                                ->label('תיאור')
                                ->state($field['description_he'])
                                ->extraAttributes(['class' => 'text-xs text-gray-600 dark:text-gray-300']),
                        ]),

                    // Left column: Model field mapping
                    Select::make("mapping_{$field['key']}")
                        ->label('ממופה לשדה במודל')
                        ->placeholder('בחר שדה או הזן ערך מותאם אישית')
                        ->options(fn (Get $get): mixed => $get('available_fields') ?? [])
                        ->searchable()
                        ->allowHtml()
                        ->suffixIcon('heroicon-o-arrow-left')
                        ->helperText("דוגמה: {$field['example']}")
                        ->required($category === 'required')
                        ->live()
                        ->createOptionForm([
                            TextInput::make('custom_value')
                                ->label('ערך מותאם אישית')
                                ->placeholder('order.client.name, "ILS", 0.17, [], null')
                                ->helperText('ניתן להזין: נתיב מקונן (dot notation), ערך קבוע במרכאות, מספר, מערך JSON, או null')
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data, Get $get) {
                            // Add the custom value to available fields
                            $available = $get('available_fields') ?? [];

                            // Don't add HTML markup for custom values
                            return $data['custom_value'];
                        }),
                ])
                ->columnSpanFull();
        }

        return $components;
    }

    /**
     * Get model fields with proper formatting.
     *
     * @return array<string, string>
     */
    protected static function getModelFields(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
            $fields = [];

            // Get fillable fields
            if (method_exists($model, 'getFillable')) {
                $fillable = $model->getFillable();
                foreach ($fillable as $field) {
                    $fields[$field] = "<span class='font-mono text-blue-600 dark:text-blue-400'>{$field}</span> <span class='text-xs text-gray-500'>(שדה ישיר)</span>";
                }
            }

            // Add common relationship paths
            $commonRelations = ['user', 'client', 'customer', 'order', 'package'];
            foreach ($commonRelations as $relation) {
                if (method_exists($model, $relation)) {
                    $fields["{$relation}.name"] = "<span class='font-mono text-purple-600 dark:text-purple-400'>{$relation}.name</span> <span class='text-xs text-purple-500'>(קשר מקונן)</span>";
                    $fields["{$relation}.email"] = "<span class='font-mono text-purple-600 dark:text-purple-400'>{$relation}.email</span> <span class='text-xs text-purple-500'>(קשר מקונן)</span>";
                    $fields["{$relation}.id"] = "<span class='font-mono text-purple-600 dark:text-purple-400'>{$relation}.id</span> <span class='text-xs text-purple-500'>(קשר מקונן)</span>";
                    $fields["{$relation}.phone"] = "<span class='font-mono text-purple-600 dark:text-purple-400'>{$relation}.phone</span> <span class='text-xs text-purple-500'>(קשר מקונן)</span>";
                }
            }

            // Add common constant values
            $fields['_constant_ils'] = "<span class='font-mono text-green-600 dark:text-green-400'>\"ILS\"</span> <span class='text-xs text-green-500'>(ערך קבוע)</span>";
            $fields['_constant_0'] = "<span class='font-mono text-green-600 dark:text-green-400'>0</span> <span class='text-xs text-green-500'>(אפס)</span>";
            $fields['_constant_0.17'] = "<span class='font-mono text-green-600 dark:text-green-400'>0.17</span> <span class='text-xs text-green-500'>(17% מע\"מ)</span>";
            $fields['_constant_true'] = "<span class='font-mono text-green-600 dark:text-green-400'>true</span> <span class='text-xs text-green-500'>(אמת)</span>";
            $fields['_constant_false'] = "<span class='font-mono text-green-600 dark:text-green-400'>false</span> <span class='text-xs text-green-500'>(שקר)</span>";
            $fields['_constant_empty_array'] = "<span class='font-mono text-green-600 dark:text-green-400'>[]</span> <span class='text-xs text-green-500'>(מערך ריק)</span>";
            $fields['_constant_null'] = "<span class='font-mono text-gray-500'>null</span> <span class='text-xs text-gray-400'>(ללא ערך)</span>";

            return $fields;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Collect all mappings from form data.
     *
     * @return array<string, string|null>
     */
    protected static function collectMappings(Get $get): array
    {
        $mappingService = app(PayableMappingService::class);
        $payableFields = $mappingService->getPayableFields();
        $mappings = [];

        foreach ($payableFields as $field) {
            $key = "mapping_{$field['key']}";
            $value = $get($key);

            // Handle special constant values
            $value = match ($value) {
                '_constant_ils' => 'ILS',
                '_constant_0' => '0',
                '_constant_0.17' => '0.17',
                '_constant_true' => 'true',
                '_constant_false' => 'false',
                '_constant_empty_array' => '[]',
                '_constant_null' => null,
                default => $value,
            };

            $mappings[$field['key']] = $value;
        }

        return $mappings;
    }

    /**
     * Handle form submission - save the mapping.
     */
    protected static function handleSubmit(array $data): void
    {
        $mappingService = app(PayableMappingService::class);

        $fieldMappings = [];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, 'mapping_')) {
                $fieldKey = Str::after($key, 'mapping_');

                // Handle constant values
                $value = match ($value) {
                    '_constant_ils' => 'ILS',
                    '_constant_0' => '0',
                    '_constant_0.17' => '0.17',
                    '_constant_true' => 'true',
                    '_constant_false' => 'false',
                    '_constant_empty_array' => '[]',
                    '_constant_null' => null,
                    default => $value,
                };

                $fieldMappings[$fieldKey] = $value;
            }
        }

        $mappingService->upsertMapping(
            modelClass: $data['model_class'],
            fieldMappings: $fieldMappings,
            label: $data['mapping_label'] ?? null
        );

        Notification::make()
            ->title('המיפוי נשמר בהצלחה')
            ->body("מעכשיו המודל {$data['model_class']} יכול לשמש כ-Payable")
            ->success()
            ->icon('heroicon-o-check-circle')
            ->send();
    }
}
