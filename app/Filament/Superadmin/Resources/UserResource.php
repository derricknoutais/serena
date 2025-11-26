<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Utilisateurs';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Mot de passe')
                ->password()
                ->revealable()
                ->required(fn (?User $record) => $record === null)
                ->dehydrated(fn ($state) => filled($state)),
            Select::make('tenant_id')
                ->label('Tenant')
                ->required()
                ->searchable()
                ->preload()
                ->options(fn () => Tenant::query()
                    ->get()
                    ->mapWithKeys(fn (Tenant $tenant) => [
                        $tenant->id => $tenant->name ?? $tenant->slug ?? $tenant->id,
                    ])
                    ->toArray())
                ->getOptionLabelUsing(function ($value): ?string {
                    $tenant = Tenant::query()->find($value);

                    if ($tenant === null) {
                        return $value;
                    }

                    return $tenant->name ?? $tenant->slug ?? $tenant->id;
                }),
            Toggle::make('is_superadmin')
                ->label('Superadmin'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_superadmin')
                    ->label('Superadmin')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Créé le')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn () => Tenant::query()
                        ->get()
                        ->mapWithKeys(fn (Tenant $tenant) => [
                            $tenant->id => $tenant->name ?? $tenant->slug ?? $tenant->id,
                        ])
                        ->toArray()),
                TernaryFilter::make('is_superadmin')
                    ->label('Superadmin'),
            ])
            ->actions([
                Impersonate::make()
                    ->guard('web')
                    ->redirectTo(function (User $record): string {
                        $tenant = $record->tenant;

                        if ($tenant === null) {
                            return url('/');
                        }

                        $centralDomain = config('tenancy.central_domains')[0] ?? 'saas-template.test';
                        $domain = $tenant->domains()->first()?->domain ?? "{$tenant->slug}.{$centralDomain}";

                        return sprintf('%s://%s/dashboard', request()->getScheme(), $domain);
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => ! $record->is_superadmin),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn () => false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
