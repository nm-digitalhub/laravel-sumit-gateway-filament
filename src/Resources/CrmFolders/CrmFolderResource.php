<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyPlugin;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\CreateCrmFolder;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\EditCrmFolder;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\ListCrmFolders;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Schemas\CrmFolderForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Tables\CrmFoldersTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;

class CrmFolderResource extends Resource
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = CrmFolder::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyPlugin
    {
        return OfficeGuyPlugin::get();
    }

    public static function form(Schema $schema): Schema
    {
        return CrmFolderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmFoldersTable::configure($table);
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
            'index' => ListCrmFolders::route('/'),
            'create' => CreateCrmFolder::route('/create'),
            'edit' => EditCrmFolder::route('/{record}/edit'),
        ];
    }
}
