<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\CreateCrmActivity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\EditCrmActivity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\ListCrmActivities;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\ViewCrmActivity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas\CrmActivityForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas\CrmActivityInfolist;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Tables\CrmActivitiesTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmActivity;

class CrmActivityResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = CrmActivity::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    // Keep dynamic navigationLabel method - it overrides plugin default
    public static function getNavigationLabel(): string
    {
        return __('crm_activities.nav_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CrmActivityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CrmActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmActivitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmActivities::route('/'),
            'create' => CreateCrmActivity::route('/create'),
            'view' => ViewCrmActivity::route('/{record}'),
            'edit' => EditCrmActivity::route('/{record}/edit'),
        ];
    }
}
