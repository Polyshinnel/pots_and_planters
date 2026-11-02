<?php

namespace Tests\Feature;

use App\Filament\Resources\AttributeResource;
use App\Filament\Resources\AttributeResource\Pages\CreateAttribute;
use App\Filament\Resources\AttributeResource\Pages\EditAttribute;
use App\Filament\Resources\AttributeResource\Pages\ListAttributes;
use App\Filament\Resources\AttributeResource\RelationManagers\OptionsRelationManager;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttributeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_all_supported_types_generate_immutable_public_ids_and_default_to_active(): void
    {
        foreach (Attribute::TYPES as $type) {
            $attribute = Attribute::query()->create(['name' => "  {$type}  ", 'code' => "  {$type}  ", 'type' => $type]);
            $this->assertSame($type, $attribute->type);
            $this->assertSame($type, $attribute->code);
            $this->assertSame($type, $attribute->name);
            $this->assertTrue($attribute->is_active);
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $attribute->public_id);
            $this->assertNotNull($attribute->created_at);
            $this->assertNotNull($attribute->updated_at);

            $attribute->public_id = (string) Str::uuid();
            try {
                $attribute->save();
                $this->fail('Public identifiers must be immutable.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('public_id', $exception->errors());
            }
        }
    }

    public function test_name_and_code_are_trimmed_validated_and_code_does_not_follow_name(): void
    {
        $attribute = Attribute::query()->create(['name' => '  Colour  ', 'code' => '  COLOR Name  ', 'type' => 'text']);
        $this->assertSame('color_name', $attribute->code);

        $attribute->update(['name' => 'Finish']);
        $this->assertSame('color_name', $attribute->fresh()->code);

        foreach ([['name' => " \t ", 'code' => 'valid'], ['name' => str_repeat('x', 256), 'code' => 'valid'], ['name' => 'Valid', 'code' => 'not valid!']] as $input) {
            try {
                Attribute::query()->create($input + ['type' => 'text']);
                $this->fail('Invalid attributes must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        Attribute::query()->create(['name' => 'First', 'code' => 'duplicate', 'type' => 'text']);
        $this->expectException(ValidationException::class);
        Attribute::query()->create(['name' => 'Second', 'code' => 'duplicate', 'type' => 'text']);
    }

    public function test_unit_is_allowed_only_for_number_and_database_rejects_invalid_type_and_unit(): void
    {
        $number = Attribute::query()->create(['name' => 'Height', 'code' => 'height', 'type' => 'number', 'unit' => ' cm ']);
        $this->assertSame('cm', $number->unit);

        try {
            Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'select', 'unit' => 'cm']);
            $this->fail('Units must not be allowed for non-number types.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit', $exception->errors());
        }

        foreach ([['type' => 'bogus', 'unit' => null], ['type' => 'text', 'unit' => 'cm']] as $invalid) {
            try {
                DB::table('attributes')->insert($invalid + [
                    'public_id' => (string) Str::uuid(),
                    'name' => 'Invalid',
                    'code' => Str::random(10),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail('Database checks must reject invalid attribute data.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_options_are_only_allowed_for_select_types_and_values_are_unique_per_attribute(): void
    {
        $select = Attribute::query()->create(['name' => 'Finish', 'code' => 'finish', 'type' => 'select']);
        $multi = Attribute::query()->create(['name' => 'Use', 'code' => 'use', 'type' => 'multiselect']);
        $first = AttributeOption::query()->create(['attribute_id' => $select->id, 'label' => '  Bronze Patina ', 'value' => 'Bronze Patina']);
        $second = AttributeOption::query()->create(['attribute_id' => $multi->id, 'label' => 'Bronze Patina', 'value' => 'bronze patina']);

        $this->assertSame('Bronze Patina', $first->label);
        $this->assertSame('bronze_patina', $first->value);
        $this->assertSame($first->value, $second->value);
        $this->assertSame(0, $first->sort_order);
        $this->assertTrue($first->is_active);
        $this->assertNotSame($first->public_id, $second->public_id);
        $this->assertCount(1, $select->options);

        foreach (['text', 'number', 'boolean'] as $type) {
            $scalar = Attribute::query()->create(['name' => $type, 'code' => $type, 'type' => $type]);
            try {
                AttributeOption::query()->create(['attribute_id' => $scalar->id, 'label' => 'Invalid', 'value' => 'invalid']);
                $this->fail('Scalar attributes cannot have options.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('attribute_id', $exception->errors());
            }
        }

        $this->expectException(ValidationException::class);
        AttributeOption::query()->create(['attribute_id' => $select->id, 'label' => 'Duplicate', 'value' => 'BRONZE-PATINA']);
    }

    public function test_options_keep_stable_value_sort_and_archive_and_attribute_type_cannot_change(): void
    {
        $attribute = Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'select']);
        $later = AttributeOption::query()->create(['attribute_id' => $attribute->id, 'label' => 'Blue', 'value' => 'blue', 'sort_order' => 2]);
        $first = AttributeOption::query()->create(['attribute_id' => $attribute->id, 'label' => 'Red', 'value' => 'red', 'sort_order' => 1]);
        $first->update(['label' => 'Ruby Red', 'is_active' => false]);

        $this->assertSame('red', $first->fresh()->value);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame([$first->id, $later->id], $attribute->fresh()->options->pluck('id')->all());

        $this->expectException(ValidationException::class);
        $attribute->update(['type' => 'text']);
    }

    public function test_foreign_key_restricts_attribute_delete_and_resources_disable_delete_actions(): void
    {
        $attribute = Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'select']);
        AttributeOption::query()->create(['attribute_id' => $attribute->id, 'label' => 'Red', 'value' => 'red']);

        try {
            $attribute->delete();
            $this->fail('The database must restrict deleting an attribute with options.');
        } catch (QueryException) {
            $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
        }

        $option = $attribute->options()->firstOrFail();
        $this->assertFalse(AttributeResource::canDelete($attribute));
        $this->assertFalse(AttributeResource::canDeleteAny());
        $this->assertTrue($option->exists);
        $this->assertDatabaseHas('attribute_options', ['id' => $option->id]);
    }

    public function test_admin_can_manage_attributes_and_options_while_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(CreateAttribute::class)
            ->set('data.name', '  Product Height  ')
            ->set('data.code', 'product height')
            ->set('data.type', 'number')
            ->set('data.unit', 'cm')
            ->call('create')
            ->assertHasNoFormErrors();

        $attribute = Attribute::query()->where('code', 'product_height')->firstOrFail();
        $originalId = $attribute->public_id;
        $attribute->update(['type' => 'select', 'unit' => null]);

        Livewire::actingAs($admin)->test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->mountTableAction('create')
            ->set('mountedActions.0.data.label', '  Tall  ')
            ->set('mountedActions.0.data.value', 'Tall')
            ->set('mountedActions.0.data.sort_order', 4)
            ->set('mountedActions.0.data.is_active', true)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $option = $attribute->options()->firstOrFail();
        $this->assertSame('Tall', $option->label);
        $this->assertSame('tall', $option->value);
        $this->assertSame(4, $option->sort_order);

        $scalar = Attribute::query()->create(['name' => 'Material', 'code' => 'material', 'type' => 'text']);
        $this->assertFalse(OptionsRelationManager::canViewForRecord($scalar, EditAttribute::class));
        $this->assertTrue(OptionsRelationManager::canViewForRecord($attribute, EditAttribute::class));

        Livewire::actingAs($admin)->test(EditAttribute::class, ['record' => $attribute->getKey()])
            ->set('data.name', 'Product Height in cm')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $attribute->refresh();
        $this->assertSame($originalId, $attribute->public_id);
        $this->assertSame('product_height', $attribute->code);
        $this->assertFalse($attribute->is_active);

        Livewire::actingAs($admin)->test(ListAttributes::class)
            ->searchTable('Product Height in cm')
            ->assertCanSeeTableRecords([$attribute]);
        Livewire::actingAs($admin)->test(ListAttributes::class)
            ->filterTable('type', 'select')
            ->assertCanSeeTableRecords([$attribute]);
        Livewire::actingAs($admin)->test(ListAttributes::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$attribute]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/attributes')->assertForbidden();
    }
}
