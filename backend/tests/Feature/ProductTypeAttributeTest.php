<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductTypeResource\Pages\EditProductType;
use App\Filament\Resources\ProductTypeResource\RelationManagers\AttributesRelationManager;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTypeAttributeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_attributes_can_be_shared_and_assignment_defaults_and_settings_are_independent(): void
    {
        $this->assertTrue(Schema::hasColumn('attributes', 'is_filterable'));
        $this->assertFalse(Schema::hasColumn('product_type_attribute', 'is_filterable'));

        $firstType = ProductType::query()->create(['name' => 'Planter']);
        $secondType = ProductType::query()->create(['name' => 'Table']);
        $height = Attribute::query()->create(['name' => 'Height', 'code' => 'height', 'type' => 'number', 'unit' => 'cm']);
        $colour = Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'select', 'is_filterable' => true]);

        $firstType->attributes()->attach($height->id);
        $firstType->attributes()->attach($colour->id, ['is_required' => true, 'sort_order' => 8]);
        $secondType->attributes()->attach($height->id, ['sort_order' => 2]);

        $this->assertDatabaseHas('product_type_attribute', [
            'product_type_id' => $firstType->id,
            'attribute_id' => $height->id,
            'is_required' => false,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('product_type_attribute', [
            'product_type_id' => $firstType->id,
            'attribute_id' => $colour->id,
            'is_required' => true,
            'sort_order' => 8,
        ]);
        $this->assertFalse($height->fresh()->is_filterable);
        $this->assertTrue($colour->fresh()->is_filterable);
        $this->assertSame([$height->id, $colour->id], $firstType->fresh()->attributes->pluck('id')->all());
        $this->assertSame([$firstType->id, $secondType->id], $height->fresh()->productTypes->sortBy('id')->pluck('id')->all());
        $this->assertSame(2, $secondType->fresh()->attributes->first()->pivot->sort_order);
        $this->assertFalse((bool) $secondType->fresh()->attributes->first()->pivot->is_required);
    }

    public function test_database_rejects_duplicate_and_restricts_deleting_related_records(): void
    {
        $type = ProductType::query()->create(['name' => 'Planter']);
        $attribute = Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'select']);
        $option = AttributeOption::query()->create(['attribute_id' => $attribute->id, 'label' => 'Red', 'value' => 'red']);
        $type->attributes()->attach($attribute->id);

        try {
            $type->attributes()->attach($attribute->id);
            $this->fail('The database must reject duplicate assignments.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_type_attribute', 1);
        }

        foreach ([$type, $attribute] as $record) {
            try {
                $record->delete();
                $this->fail('The database must restrict deleting referenced records.');
            } catch (QueryException) {
                $this->assertTrue($record->exists);
            }
        }

        $this->assertDatabaseHas('attribute_options', ['id' => $option->id]);
        $type->attributes()->detach($attribute->id);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
        $this->assertDatabaseHas('attribute_options', ['id' => $option->id]);
    }

    public function test_admin_can_attach_edit_and_explicitly_detach_attributes_without_changing_attribute(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $type = ProductType::query()->create(['name' => 'Planter']);
        $attribute = Attribute::query()->create(['name' => 'Height', 'code' => 'height', 'type' => 'number', 'unit' => 'cm']);

        $relationManager = Livewire::actingAs($admin)->test(AttributesRelationManager::class, ['ownerRecord' => $type, 'pageClass' => EditProductType::class])
            ->mountTableAction('attach');

        $this->assertTrue($relationManager->instance()->getMountedTableAction()->isRecordSelectPreloaded());

        $relationManager
            ->set('mountedActions.0.data.recordId', $attribute->id)
            ->set('mountedActions.0.data.is_required', true)
            ->set('mountedActions.0.data.sort_order', 5)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_type_attribute', [
            'product_type_id' => $type->id,
            'attribute_id' => $attribute->id,
            'is_required' => true,
            'sort_order' => 5,
        ]);
        $this->assertFalse($attribute->fresh()->is_filterable);

        Livewire::actingAs($admin)->test(AttributesRelationManager::class, ['ownerRecord' => $type, 'pageClass' => EditProductType::class])
            ->mountTableAction('edit', $attribute)
            ->set('mountedActions.0.data.is_required', false)
            ->set('mountedActions.0.data.sort_order', 1)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_type_attribute', [
            'product_type_id' => $type->id,
            'attribute_id' => $attribute->id,
            'is_required' => false,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($admin)->test(AttributesRelationManager::class, ['ownerRecord' => $type, 'pageClass' => EditProductType::class])
            ->callTableAction('detach', $attribute)
            ->assertSuccessful();

        $this->assertDatabaseMissing('product_type_attribute', ['product_type_id' => $type->id, 'attribute_id' => $attribute->id]);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'Height', 'unit' => 'cm']);
    }

    public function test_archived_assignments_and_inactive_product_types_remain_manageable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $type = ProductType::query()->create(['name' => 'Archived Type']);
        $attribute = Attribute::query()->create(['name' => 'Old Colour', 'code' => 'old_colour', 'type' => 'text', 'is_active' => false]);
        $type->attributes()->attach($attribute->id);
        $type->update(['is_active' => false]);

        Livewire::actingAs($admin)->test(AttributesRelationManager::class, ['ownerRecord' => $type->fresh(), 'pageClass' => EditProductType::class])
            ->assertCanSeeTableRecords([$attribute]);

        $this->assertDatabaseHas('product_type_attribute', ['product_type_id' => $type->id, 'attribute_id' => $attribute->id]);
    }

    public function test_non_admin_cannot_access_product_type_or_assignment_manager(): void
    {
        $type = ProductType::query()->create(['name' => 'Planter']);
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/product-types/'.$type->id.'/edit')
            ->assertForbidden();
    }
}
