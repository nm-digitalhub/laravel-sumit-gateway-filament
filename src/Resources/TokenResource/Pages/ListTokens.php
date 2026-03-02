<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_new_card')
                ->label('Add New Card')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('owner_id')
                        ->label('Select Customer')
                        ->searchable()
                        ->required()
                        ->options(function (): array {
                            // Get all unique owners from tokens
                            $tokens = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken::query()
                                ->with('owner')
                                ->get()
                                ->groupBy('owner_type');

                            $options = [];
                            foreach ($tokens as $ownerType => $ownerTokens) {
                                foreach ($ownerTokens->unique('owner_id') as $token) {
                                    if ($token->owner) {
                                        $label = method_exists($token->owner, 'getName')
                                            ? $token->owner->getName()
                                            : ($token->owner->name ?? $token->owner->email ?? "ID: {$token->owner_id}");

                                        $options["{$ownerType}:{$token->owner_id}"] = $label;
                                    }
                                }
                            }

                            return $options;
                        })
                        ->helperText('Select the customer to add a new payment card for'),
                ])
                ->action(function (array $data) {
                    // Parse owner_type and owner_id
                    [$ownerType, $ownerId] = explode(':', (string) $data['owner_id']);

                    // URL encode the owner type to handle backslashes
                    $encodedOwnerType = urlencode($ownerType);

                    // Redirect to add-card page with route parameters
                    return redirect()->to(
                        TokenResource::getUrl('add-card', [
                            'ownerType' => $encodedOwnerType,
                            'ownerId' => $ownerId,
                        ])
                    );
                }),
        ];
    }
}
