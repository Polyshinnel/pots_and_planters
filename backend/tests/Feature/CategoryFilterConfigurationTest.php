<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\RelationManagers\FiltersRelationManager;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\User;
use App\Services\CategoryFilterConfiguration;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryFilterConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_configure_and_edit_filters_for_an_empty_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->category('Empty category');
        $attribute = $this->filterableAttribute('Height', 'height', 'number', 'cm');

        Livewire::actingAs($admin)->test(FiltersRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->mountTableAction('attach')
            ->set('mountedActions.0.data.recordId', $attribute->id)
            ->assertFormFieldExists('display_type')
            ->set('mountedActions.0.data.display_type', 'range')
            ->set('mountedActions.0.data.sort_order', 8)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('category_attribute_filters', [
            'category_id' => $category->id,
            'attribute_id' => $attribute->id,
            'display_type' => 'range',
            'sort_order' => 8,
        ]);
        $this->assertSame(0, $category->products()->count());

        Livewire::actingAs($admin)->test(FiltersRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->mountTableAction('edit', $attribute)
            ->set('mountedActions.0.data.display_type', 'range')
            ->set('mountedActions.0.data.sort_order', 2)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $attribute->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'unit' => 'cm']);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'is_filterable' => true]);
    }

    public function test_categories_keep_independent_filter_configuration_for_shared_attributes(): void
    {
        $first = $this->category('First');
        $second = $this->category('Second');
        $attribute = $this->filterableAttribute('Colour', 'colour', 'select');
        $configuration = app(CategoryFilterConfiguration::class);

        $configuration->attach($first, $attribute->id, 'options', 3);
        $configuration->attach($second, $attribute->id, 'options', 9);

        $this->assertSame([3, 9], [
            $first->fresh()->filterAttributes->first()->pivot->sort_order,
            $second->fresh()->filterAttributes->first()->pivot->sort_order,
        ]);
    }

    public function test_duplicate_pair_and_invalid_presentation_or_order_are_rejected_without_partial_writes(): void
    {
        $category = $this->category('Planters');
        $attribute = $this->filterableAttribute('Height', 'height', 'number');
        $configuration = app(CategoryFilterConfiguration::class);
        $configuration->attach($category, $attribute->id, 'range', 0);

        try {
            $configuration->attach($category, $attribute->id, 'range', 1);
            $this->fail('Duplicate category and Attribute configuration must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('category_attribute_filters', 1);
        }

        try {
            $configuration->update($category, $attribute->id, 'options', 4);
            $this->fail('Presentation incompatible with the Attribute type must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $attribute->id, 'display_type' => 'range', 'sort_order' => 0]);
        }

        try {
            $configuration->update($category, $attribute->id, 'range', -1);
            $this->fail('Negative order must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $attribute->id, 'sort_order' => 0]);
        }
    }

    public function test_reordering_multiple_filters_is_atomic_and_preserves_every_assignment(): void
    {
        $category = $this->category('Planters');
        $height = $this->filterableAttribute('Height', 'height', 'number');
        $colour = $this->filterableAttribute('Colour', 'colour', 'select');
        $configuration = app(CategoryFilterConfiguration::class);
        $configuration->attach($category, $height->id, 'range', 0);
        $configuration->attach($category, $colour->id, 'options', 1);

        try {
            $configuration->reorder($category, ['order_'.$height->id => 4, 'order_'.$colour->id => -1]);
            $this->fail('Invalid multi-filter order should roll back all changes.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $height->id, 'sort_order' => 0]);
            $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $colour->id, 'sort_order' => 1]);
        }

        $configuration->reorder($category, ['order_'.$height->id => 3, 'order_'.$colour->id => 0]);

        $this->assertSame([$colour->id, $height->id], $category->fresh()->filterAttributes->pluck('id')->all());
    }

    public function test_admin_can_reorder_the_category_filters_as_one_filament_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $this->category('Planters');
        $height = $this->filterableAttribute('Height', 'height', 'number');
        $colour = $this->filterableAttribute('Colour', 'colour', 'select');
        $configuration = app(CategoryFilterConfiguration::class);
        $configuration->attach($category, $height->id, 'range', 0);
        $configuration->attach($category, $colour->id, 'options', 1);

        Livewire::actingAs($admin)->test(FiltersRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->mountTableAction('reorderFilters')
            ->assertSet('mountedActions.0.data.order_'.$height->id, 0)
            ->assertSet('mountedActions.0.data.order_'.$colour->id, 1)
            ->set('mountedActions.0.data.order_'.$height->id, 4)
            ->set('mountedActions.0.data.order_'.$colour->id, 2)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $height->id, 'sort_order' => 4]);
        $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $colour->id, 'sort_order' => 2]);
    }

    public function test_only_active_globally_filterable_attributes_can_be_added_but_existing_assignments_remain_manageable(): void
    {
        $category = $this->category('Planters');
        $attribute = $this->filterableAttribute('Colour', 'colour', 'select');
        $configuration = app(CategoryFilterConfiguration::class);
        $configuration->attach($category, $attribute->id, 'options', 0);

        $attribute->update(['is_filterable' => false]);
        $attribute->update(['is_active' => false]);

        $this->assertSame([$attribute->id], $category->fresh()->filterAttributes->pluck('id')->all());
        $configuration->update($category, $attribute->id, 'options', 5);
        $this->assertDatabaseHas('category_attribute_filters', ['category_id' => $category->id, 'attribute_id' => $attribute->id, 'sort_order' => 5]);

        $newAttribute = Attribute::query()->create(['name' => 'Size', 'code' => 'size', 'type' => 'text']);
        $this->expectException(ValidationException::class);
        $configuration->attach($category, $newAttribute->id, 'values', 0);
    }

    public function test_attribute_type_and_referenced_record_deletion_are_protected(): void
    {
        $category = $this->category('Planters');
        $attribute = $this->filterableAttribute('Height', 'height', 'number');
        app(CategoryFilterConfiguration::class)->attach($category, $attribute->id, 'range', 0);

        try {
            $attribute->update(['type' => 'text', 'unit' => null]);
            $this->fail('Changing the type of a configured filter must be blocked.');
        } catch (ValidationException) {
            $this->assertSame('number', $attribute->fresh()->type);
        }

        foreach ([$category, $attribute] as $record) {
            try {
                $record->delete();
                $this->fail('Referenced category and Attribute records must be protected.');
            } catch (QueryException|ValidationException) {
                $this->assertTrue($record->exists);
            }
        }
    }

    public function test_non_admin_cannot_access_category_filter_manager(): void
    {
        $category = $this->category('Planters');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/categories/'.$category->id.'/edit')
            ->assertForbidden();
    }

    private function filterableAttribute(string $name, string $code, string $type, ?string $unit = null): Attribute
    {
        $attribute = Attribute::query()->create(['name' => $name, 'code' => $code, 'type' => $type, 'unit' => $unit, 'is_filterable' => true]);
        $productType = ProductType::query()->create(['name' => 'Planter']);
        $productType->attributes()->attach($attribute->id);

        return $attribute;
    }

    private function category(string $name): Category
    {
        $slug = str($name)->slug().'-'.str()->random(5);

        return Category::query()->create([
            'public_id' => str()->uuid()->toString(),
            'slug' => $slug,
            'name' => $name,
            'heading' => $name,
            'description' => 'Description',
            'tile_description' => 'Tile',
            'seo_title' => $name,
            'seo_description' => 'Description',
            'status' => 'draft',
            'sort_order' => 0,
        ]);
    }
}
