<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductTypeResource\Pages\EditProductType;
use App\Filament\Resources\ProductTypeResource\RelationManagers\AttributesRelationManager;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCategoryManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ProductAttributeValuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_all_five_types_round_trip_with_false_zero_decimal_precision_and_selections(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$text, $number, $boolean, $select, $multi] = $this->attributes($type);
        $red = AttributeOption::query()->create(['attribute_id' => $select->id, 'label' => 'Red', 'value' => 'red']);
        $blue = AttributeOption::query()->create(['attribute_id' => $multi->id, 'label' => 'Blue', 'value' => 'blue']);
        $white = AttributeOption::query()->create(['attribute_id' => $multi->id, 'label' => 'White', 'value' => 'white']);
        $product = $this->save($brand, $supplier, $type, [
            $text->id => 'Warm white', $number->id => '0.123456', $boolean->id => false,
            $select->id => (string) $red->id, $multi->id => [$blue->id, $white->id],
        ]);

        $rows = $product->attributeValues()->with('selections')->get()->keyBy('attribute_id');
        $this->assertSame('Warm white', $rows[$text->id]->text_value);
        $this->assertSame('0.123456', $rows[$number->id]->number_value);
        $this->assertFalse($rows[$boolean->id]->boolean_value);
        $this->assertSame([$red->id], $rows[$select->id]->selections->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertEqualsCanonicalizing([$blue->id, $white->id], $rows[$multi->id]->selections->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->all());

        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet([
                "attribute_values.{$text->id}" => 'Warm white', "attribute_values.{$number->id}" => '0.123456',
                "attribute_values.{$boolean->id}" => '0', "attribute_values.{$select->id}" => $red->id,
                "attribute_values.{$multi->id}" => [$blue->id, $white->id],
            ]);
    }

    public function test_required_is_enforced_only_when_publishing_and_primary_category_remains_required(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$required] = $this->attributes($type, ['text'], true);
        $product = $this->save($brand, $supplier, $type, []);
        $category = $this->category();

        try {
            app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, [
                'status' => 'published', 'category_ids' => [$category->id], 'primary_category_id' => $category->id,
            ]), $product);
            $this->fail('Publishing without the required value must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("attribute_values.{$required->id}", $exception->errors());
        }
        $this->assertSame('draft', $product->fresh()->status);

        $updated = app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, [
            'status' => 'published', 'category_ids' => [$category->id], 'primary_category_id' => $category->id,
            'attribute_values' => [$required->id => '  12 cm  '],
        ]), $product);
        $this->assertSame('published', $updated->status);
        $this->assertSame($category->id, $updated->primary_category_id);
        $this->assertSame('12 cm', $updated->attributeValues()->first()->text_value);
    }

    public function test_failure_during_value_persistence_rolls_back_product_and_earlier_values(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$first, $second] = $this->attributes($type, ['text', 'text']);
        try {
            ProductAttributeValue::creating(function (ProductAttributeValue $value) use ($second): void {
                if ((int) $value->attribute_id === $second->id) {
                    throw new RuntimeException('Simulated storage failure.');
                }
            });

            try {
                $this->save($brand, $supplier, $type, [$first->id => 'Saved first', $second->id => 'Fail second']);
                $this->fail('The simulated storage failure must escape the transaction.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated storage failure.', $exception->getMessage());
            }
        } finally {
            ProductAttributeValue::flushEventListeners();
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_attribute_values', 0);
    }

    public function test_foreign_unassigned_duplicate_and_inactive_options_are_rejected(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$select, $multi] = $this->attributes($type, ['select', 'multiselect']);
        $other = Attribute::query()->create(['name' => 'Other', 'code' => 'other', 'type' => 'select']);
        $wrongOption = AttributeOption::query()->create(['attribute_id' => $other->id, 'label' => 'Wrong', 'value' => 'wrong']);
        $active = AttributeOption::query()->create(['attribute_id' => $multi->id, 'label' => 'Active', 'value' => 'active']);
        $inactive = AttributeOption::query()->create(['attribute_id' => $multi->id, 'label' => 'Inactive', 'value' => 'inactive']);
        $product = $this->save($brand, $supplier, $type, []);

        $inactive->update(['is_active' => false]);
        foreach ([[$select->id => $wrongOption->id], [$other->id => 'value'], [$multi->id => [$active->id, $active->id]], [$multi->id => [$inactive->id]]] as $values) {
            try {
                app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, ['attribute_values' => $values]), $product);
                $this->fail('Invalid values must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(0, $product->fresh()->attributeValues()->count());
            }
        }

        app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, ['attribute_values' => [$multi->id => [$active->id]]]), $product);
        $inactive->update(['is_active' => true]);
        app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, ['attribute_values' => [$multi->id => [$active->id, $inactive->id]]]), $product);
        $inactive->update(['is_active' => false]);
        app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, ['attribute_values' => [$multi->id => [$active->id]]]), $product);
        $this->assertSame([$active->id], $product->fresh()->attributeValues()->where('attribute_id', $multi->id)->first()->selections()->pluck('attribute_option_id')->all());
    }

    public function test_product_type_attribute_type_and_assignment_changes_preserve_existing_values(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$attribute] = $this->attributes($type, ['text']);
        $product = $this->save($brand, $supplier, $type, [$attribute->id => 'Retained']);
        $otherType = ProductType::query()->create(['name' => 'Other Type']);

        foreach ([
            fn () => app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $otherType), $product),
            fn () => $product->update(['product_type_id' => $otherType->id]),
            fn () => $attribute->update(['type' => 'number']),
        ] as $change) {
            try {
                $change();
                $this->fail('A potentially destructive schema change must be blocked.');
            } catch (ValidationException) {
                $this->assertSame('Retained', $product->fresh()->attributeValues()->first()->text_value);
            }
        }

        try {
            DB::table('products')->where('id', $product->id)->update(['product_type_id' => $otherType->id]);
            $this->fail('MariaDB must prevent changing a Product Type with values.');
        } catch (QueryException) {
            $this->assertSame($type->id, $product->fresh()->product_type_id);
        }

        Livewire::actingAs(User::factory()->create(['is_admin' => true]))
            ->test(AttributesRelationManager::class, ['ownerRecord' => $type, 'pageClass' => EditProductType::class])
            ->callTableAction('detach', $attribute);
        $this->assertDatabaseHas('product_type_attribute', ['product_type_id' => $type->id, 'attribute_id' => $attribute->id]);

        $this->expectException(QueryException::class);
        $type->attributes()->detach($attribute->id);
    }

    public function test_database_enforces_unique_value_rows_and_matching_attribute_option_pairs(): void
    {
        [$brand, $supplier, $type] = $this->references();
        [$first, $second] = $this->attributes($type, ['select', 'select']);
        $firstOption = AttributeOption::query()->create(['attribute_id' => $first->id, 'label' => 'First', 'value' => 'first']);
        $secondOption = AttributeOption::query()->create(['attribute_id' => $second->id, 'label' => 'Second', 'value' => 'second']);
        $product = $this->save($brand, $supplier, $type, [$first->id => $firstOption->id]);
        $value = $product->attributeValues()->where('attribute_id', $first->id)->firstOrFail();

        try {
            $value->replicate()->save();
            $this->fail('A second value row for a product and attribute must fail.');
        } catch (QueryException) {
            $this->assertSame(1, $product->attributeValues()->where('attribute_id', $first->id)->count());
        }

        try {
            $value->selections()->create(['attribute_id' => $first->id, 'attribute_option_id' => $firstOption->id]);
            $this->fail('Duplicate selections must fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_attribute_selections', 1);
        }

        $this->expectException(QueryException::class);
        $value->selections()->create(['attribute_id' => $first->id, 'attribute_option_id' => $secondOption->id]);
    }

    public function test_filament_create_and_edit_persist_values_and_non_admin_cannot_edit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$brand, $supplier, $type] = $this->references();
        [$text, $bool] = $this->attributes($type, ['text', 'boolean']);
        Livewire::actingAs($admin)->test(CreateProduct::class)
            ->set('data.brand_id', $brand->id)->set('data.supplier_id', $supplier->id)->set('data.product_type_id', $type->id)
            ->set('data.sku', 'ATTRIBUTE-1')->set('data.name', 'Attribute Product')
            ->set("data.attribute_values.{$text->id}", 'Initial')->set("data.attribute_values.{$bool->id}", '0')
            ->call('create')->assertHasNoFormErrors();
        $product = Product::query()->firstOrFail();
        $this->assertSame('Initial', $product->attributeValues()->where('attribute_id', $text->id)->value('text_value'));
        $this->assertFalse($product->attributeValues()->where('attribute_id', $bool->id)->value('boolean_value'));

        Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->id])
            ->set("data.attribute_values.{$text->id}", 'Updated')->set("data.attribute_values.{$bool->id}", null)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated', $product->fresh()->attributeValues()->where('attribute_id', $text->id)->value('text_value'));
        $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $bool->id]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/products')->assertForbidden();
    }

    private function references(): array
    {
        return [Brand::query()->create(['name' => 'Brand']), Supplier::query()->create(['name' => 'Supplier']), ProductType::query()->create(['name' => 'Planter'])];
    }

    /** @return list<Attribute> */
    private function attributes(ProductType $type, array $types = ['text', 'number', 'boolean', 'select', 'multiselect'], bool $required = false): array
    {
        $attributes = [];
        foreach ($types as $index => $attributeType) {
            $attribute = Attribute::query()->create([
                'name' => ucfirst($attributeType).' '.$index,
                'code' => $attributeType.'_'.$index,
                'type' => $attributeType,
                'unit' => $attributeType === 'number' ? 'cm' : null,
            ]);
            $type->attributes()->attach($attribute->id, ['is_required' => $required, 'sort_order' => $index]);
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    private function save(Brand $brand, Supplier $supplier, ProductType $type, array $values): Product
    {
        return app(ProductCategoryManager::class)->save($this->data($brand, $supplier, $type, ['attribute_values' => $values]));
    }

    private function data(Brand $brand, Supplier $supplier, ProductType $type, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $brand->id, 'supplier_id' => $supplier->id, 'product_type_id' => $type->id,
            'sku' => 'SKU-'.Str::random(10), 'name' => 'Product',
        ], $overrides);
    }

    private function category(): Category
    {
        return Category::query()->create([
            'public_id' => 'planters', 'slug' => 'planters', 'name' => 'Planters', 'heading' => 'Planters',
            'description' => 'Description', 'tile_description' => 'Tile', 'seo_title' => 'Planters',
            'seo_description' => 'Description', 'status' => 'published',
        ]);
    }
}
