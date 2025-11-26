<?php

namespace App\Filament\Superadmin\Resources\UserResource\Pages;

use App\Filament\Superadmin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()),
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()->is_superadmin),
        ];
    }
}
