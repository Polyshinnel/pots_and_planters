<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Models\Attribute;
use App\Services\CategoryFilterConfiguration;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FiltersRelationManager extends RelationManager
{
    protected static string $relationship = 'filterAttributes';

    protected static ?string $title = 'Filters';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category filter')->schema([
                Select::make('display_type')->label('Filter display style')->placeholder('Select an Attribute first')->options(fn (Get $get): array => $this->displayOptions($get('recordId')))->helperText('Controls how this filter will be shown in the future catalog UI.')->required(),
                TextInput::make('sort_order')->label('Order')->numeric()->integer()->minValue(0)->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->inverseRelationship('filterCategories')->columns([
            TextColumn::make('name')->label('Attribute')->searchable()->sortable(),
            TextColumn::make('code')->label('Code')->searchable(),
            TextColumn::make('type')->label('Type')->badge(),
            TextColumn::make('unit')->label('Unit')->placeholder('—'),
            TextColumn::make('is_active')->label('State')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
            TextColumn::make('is_filterable')->label('Filterable')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
            TextColumn::make('pivot.display_type')->label('Filter display style')->badge()->formatStateUsing(fn (?string $state): string => $this->displayLabel($state)),
            TextColumn::make('pivot.sort_order')->label('Order')->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('category_attribute_filters.sort_order', $direction)),
        ])->defaultSort('pivot.sort_order')->headerActions([
            AttachAction::make()
                ->label('Add filter')
                ->recordTitleAttribute('name')
                ->recordSelectSearchColumns(['name', 'code'])
                ->preloadRecordSelect()
                ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                    ->where('attributes.is_active', true)
                    ->where('attributes.is_filterable', true)
                    ->whereHas('productTypes'))
                ->schema(fn (AttachAction $action): array => [
                    $action->getRecordSelect()->label('Attribute')->live()->helperText('Only active, filterable Attributes assigned to at least one Product Type are available. Set Filterable on the Attribute record.'),
                    Select::make('display_type')
                        ->label('Filter display style')
                        ->placeholder('Select an Attribute first')
                        ->options(fn (Get $get): array => $this->displayOptions($get('recordId')))
                        ->helperText('Controls how this filter will be shown in the future catalog UI. It does not change product values. Available styles depend on the selected Attribute type.')
                        ->required(),
                    TextInput::make('sort_order')->label('Order')->numeric()->integer()->minValue(0)->default(0)->required(),
                ])
                ->action(function (array $data, RelationManager $livewire, CategoryFilterConfiguration $configuration): void {
                    $configuration->attach(
                        $livewire->getOwnerRecord(),
                        (int) ($data['recordId'] ?? 0),
                        (string) ($data['display_type'] ?? ''),
                        (int) ($data['sort_order'] ?? 0),
                    );
                }),
            Action::make('reorderFilters')
                ->label('Set filter order')
                ->visible(fn (): bool => $this->getOwnerRecord()->filterAttributes()->exists())
                ->schema(fn (): array => $this->getOwnerRecord()->filterAttributes()->get()->map(fn (Attribute $attribute) => TextInput::make('order_'.$attribute->id)
                    ->label($attribute->name.' · '.$attribute->type.($attribute->unit ? ' ('.$attribute->unit.')' : ''))
                    ->numeric()->integer()->minValue(0)->default($attribute->pivot->sort_order)->required())->all())
                ->action(fn (array $data, RelationManager $livewire, CategoryFilterConfiguration $configuration) => $configuration->reorder($livewire->getOwnerRecord(), $data)),
        ])->recordActions([
            EditAction::make()->label('Edit filter')
                ->fillForm(fn (Attribute $record): array => ['display_type' => $record->pivot->display_type, 'sort_order' => $record->pivot->sort_order])
                ->schema(fn (): array => [
                    Select::make('display_type')->label('Filter display style')->options(fn (Attribute $record): array => $this->displayOptionsForType($record->type))->helperText('Controls the future catalog UI only; product values are unchanged.')->required(),
                    TextInput::make('sort_order')->label('Order')->numeric()->integer()->minValue(0)->required(),
                ])
                ->action(function (array $data, Attribute $record, RelationManager $livewire, CategoryFilterConfiguration $configuration): void {
                    $configuration->update($livewire->getOwnerRecord(), $record->id, (string) $data['display_type'], (int) $data['sort_order']);
                }),
            DetachAction::make()->label('Remove filter')->modalDescription('This removes only this category filter configuration. The Attribute and all product values remain unchanged.')
                ->action(fn (Attribute $record, RelationManager $livewire, CategoryFilterConfiguration $configuration) => $configuration->detach($livewire->getOwnerRecord(), $record->id)),
        ])->defaultSort('pivot.sort_order')
            ->emptyStateHeading('No filters configured')
            ->emptyStateDescription('Add existing active, filterable Attributes. Their values continue to come from products.');
    }

    private function displayOptions(mixed $attributeId): array
    {
        $type = is_numeric($attributeId) ? Attribute::query()->whereKey($attributeId)->value('type') : null;

        return $type ? $this->displayOptionsForType($type) : [];
    }

    private function displayOptionsForType(string $type): array
    {
        return CategoryFilterConfiguration::DISPLAY_TYPES[$type] ?? [];
    }

    private function displayLabel(?string $value): string
    {
        foreach (CategoryFilterConfiguration::DISPLAY_TYPES as $options) {
            if (isset($options[$value])) {
                return $options[$value];
            }
        }

        return $value ?? '—';
    }
}
