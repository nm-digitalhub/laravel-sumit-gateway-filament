<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use BezhanSalleh\PluginEssentials\Concerns\Resource\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Resource\HasNavigation;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientDocumentResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\OfficeGuyClientPlugin;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

class ClientDocumentResource extends Resource
{
    use HasLabels;
    use HasNavigation;

    protected static ?string $model = OfficeGuyDocument::class;

    /**
     * Link this resource to its plugin
     */
    public static function getEssentialsPlugin(): ?OfficeGuyClientPlugin
    {
        return OfficeGuyClientPlugin::get();
    }

    public static function getEloquentQuery(): Builder
    {
        // Filter to only show documents for the authenticated client's SUMIT customer ID
        $client = auth()->user()?->client;

        if (! $client) {
            // No client found - return empty query
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('customer_id', $client->getSumitCustomerId());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Document Information')
                    ->schema([
                        Forms\Components\TextInput::make('document_id')
                            ->label('Document ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('document_type')
                            ->label('Document Type')
                            ->formatStateUsing(fn ($record) => $record?->getDocumentTypeName())
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('Date')
                            ->formatStateUsing(fn ($record) => $record?->created_at?->format('M d, Y'))
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Document Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->disabled()
                            ->rows(3),
                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(
                                fn ($record): string => $record?->is_draft ? '📝 Draft' : '✅ Final'
                            ),
                        Forms\Components\Placeholder::make('email_status')
                            ->label('Email Status')
                            ->content(
                                fn ($record): string => $record?->emailed ? '✉️ Sent' : '📧 Not Sent'
                            ),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_id')
                    ->label('Document ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->getDocumentTypeName())
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->isInvoice() => 'success',
                        $record->isOrder() => 'info',
                        $record->isDonationReceipt() => 'warning',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->label('Draft')
                    ->boolean(),
                Tables\Columns\IconColumn::make('emailed')
                    ->label('Emailed')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Document Type')
                    ->options([
                        '1' => 'Invoice',
                        '8' => 'Order',
                        'DonationReceipt' => 'Donation Receipt',
                    ]),
                Tables\Filters\TernaryFilter::make('is_draft')
                    ->label('Draft Documents'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record): bool => $record->document_id && ! $record->is_draft)
                    ->url(fn ($record): string => route('officeguy.document.download', ['document' => $record->document_id]), true),
            ])
            ->emptyStateHeading('No documents found')
            ->emptyStateDescription('You don\'t have any invoices or receipts yet. Documents will appear here after you make a purchase.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListClientDocuments::route('/'),
            'view' => Pages\ViewClientDocument::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $draftCount = static::getEloquentQuery()
            ->where('is_draft', true)
            ->count();

        return $draftCount > 0 ? (string) $draftCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'info' : null;
    }
}
