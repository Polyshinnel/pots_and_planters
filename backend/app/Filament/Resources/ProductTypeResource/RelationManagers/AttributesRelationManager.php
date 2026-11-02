<?php

namespace App\Filament\Resources\ProductTypeResource\RelationManagers;

use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';

    protected static ?string $title = 'Attributes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attribute assignment')->schema([
                Toggle::make('is_required')->label('Required')->default(false)->required(),
                TextInput::make('sort_order')->label('Order')->numeric()->integer()->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('code')->label('Code')->searchable()->sortable(),
            TextColumn::make('type')->label('Type')->badge()->sortable(),
            TextColumn::make('unit')->label('Unit')->placeholder('—'),
            IconColumn::make('is_active')->label('Active')->boolean(),
            IconColumn::make('is_required')->label('Required')->boolean()->state(fn (Attribute $record): bool => (bool) $record->pivot->is_required),
            TextColumn::make('sort_order')->label('Order')->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('product_type_attribute.sort_order', $direction)),
        ])->defaultSort('sort_order')->headerActions([
            AttachAction::make()
                ->label('Add Attribute')
                ->recordTitleAttribute('name')
                ->recordSelectSearchColumns(['name', 'code'])
                ->preloadRecordSelect()
                ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('attributes.is_active', true))
                ->schema(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Toggle::make('is_required')->label('Required')->default(false)->required(),
                    TextInput::make('sort_order')->label('Order')->numeric()->integer()->default(0)->required(),
                ])
                ->action(function (array $data, RelationManager $livewire): void {
                    $attribute = Attribute::query()
                        ->whereKey($data['recordId'] ?? null)
                        ->where('is_active', true)
                        ->first();

                    if (! $attribute) {
                        throw ValidationException::withMessages([
                            'recordId' => 'Choose an active Attribute that is not already assigned.',
                        ]);
                    }

                    $relationship = $livewire->getOwnerRecord()->attributes();

                    if ($relationship->whereKey($attribute->getKey())->exists()) {
                        throw ValidationException::withMessages([
                            'recordId' => 'This attribute has already been assigned to this product type.',
                        ]);
                    }

                    try {
                        DB::transaction(fn () => $relationship->attach($attribute, [
                            'is_required' => (bool) ($data['is_required'] ?? false),
                            'sort_order' => (int) ($data['sort_order'] ?? 0),
                        ]));
                    } catch (QueryException $exception) {
                        if (! str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                            throw $exception;
                        }

                        throw ValidationException::withMessages([
                            'recordId' => 'This attribute has already been assigned to this product type. Refresh the page and try again.',
                        ]);
                    }
                }),
        ])->recordActions([
            EditAction::make()->label('Edit assignment'),
            DetachAction::make()->label('Remove Attribute')->modalDescription('This only removes the assignment. Existing product values must be cleared or moved first.')
                ->action(function (Attribute $record, RelationManager $livewire): void {
                    $hasValues = ProductAttributeValue::query()->where('attribute_id', $record->id)
                        ->whereHas('product', fn (Builder $query) => $query->where('product_type_id', $livewire->getOwnerRecord()->getKey()))
                        ->exists();
                    if ($hasValues) {
                        throw ValidationException::withMessages(['recordId' => 'This attribute has saved Product values. Move or clear those values explicitly before removing the assignment.']);
                    }

                    $livewire->getOwnerRecord()->attributes()->detach($record->id);
                }),
        ]);
    }
}
