<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteContactSettingResource\Pages\EditSiteContactSettings;
use App\Models\Showroom;
use App\Models\SiteContactSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SiteContactSettingResource extends Resource
{
    protected static ?string $model = SiteContactSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Contacts';

    protected static ?string $navigationLabel = 'Primary & WhatsApp';

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact settings')->description('The primary contact is read from its published showroom record.')->schema([
                Select::make('primary_showroom_id')->label('Primary showroom')->options(fn (): array => Showroom::query()->published()->publicOrder()->pluck('name', 'id')->all())->searchable()->nullable(),
                TextInput::make('whatsapp_url')->label('Global WhatsApp URL')->url()->maxLength(2048)->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return ['index' => EditSiteContactSettings::route('/')];
    }
}
