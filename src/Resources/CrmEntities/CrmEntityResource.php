<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\CreateCrmEntity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\EditCrmEntity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\ListCrmEntities;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\RelationManagers\ActivitiesRelationManager;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Schemas\CrmEntityForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Tables\CrmEntitiesTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;

class CrmEntityResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = CrmEntity::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    public static function form(Schema $schema): Schema
    {
        return CrmEntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmEntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmEntities::route('/'),
            'create' => CreateCrmEntity::route('/create'),
            'edit' => EditCrmEntity::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        // Can only create if there are folders synced
        return \OfficeGuy\LaravelSumitGateway\Models\CrmFolder::count() > 0;
    }
}
