<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCardSpecificationsFormatter;
use App\Services\ProductCategoryManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCardDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_display_attribute_order_round_trips_and_formats_real_values_without_float_conversion(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $height = $this->attribute($type, 'Height', 'height', 'number', 'cm', 'H');
        $finish = $this->attribute($type, 'Finish', 'finish', 'select', null, '');
        $red = AttributeOption::query()->create(['attribute_id' => $finish->id, 'label' => 'Silver leaf', 'value' => 'silver_leaf']);

        $product = $this->save($brand, $supplier, $type, [
            'attribute_values' => [$height->id => '125.123456', $finish->id => $red->id],
            'selected_attribute_ids' => [$height->id, $finish->id],
            'card_display_attribute_ids' => [['attribute_id' => $finish->id], ['attribute_id' => $height->id]],
        ]);

        $this->assertSame([$finish->id, $height->id], $product->fresh()->card_display_attribute_ids);
        $this->assertSame('Silver leaf · H 125.123456 cm', app(ProductCardSpecificationsFormatter::class)->format(
            $type->id,
            [$height->id => '125.123456', $finish->id => $red->id],
            [$finish->id, $height->id],
        ));
    }

    public function test_display_order_rejects_duplicates_foreign_type_and_unselected_attributes(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $optional = $this->attribute($type, 'Length', 'length', 'number');
        $foreign = Attribute::query()->create(['name' => 'Foreign', 'code' => 'foreign', 'type' => 'text']);

        foreach ([
            [['attribute_id' => $optional->id], ['attribute_id' => $optional->id]],
            [['attribute_id' => $foreign->id]],
            [['attribute_id' => $optional->id]],
        ] as $rows) {
            try {
                $this->save($brand, $supplier, $type, ['card_display_attribute_ids' => $rows]);
                $this->fail('Invalid card attribute selections must fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('card_display_attribute_ids', $exception->errors());
            }
        }
    }

    public function test_formatter_omits_empty_values_and_keeps_false_zero_and_option_label(): void
    {
        [, , $type] = $this->references();
        $count = $this->attribute($type, 'Count', 'count', 'number', null, '');
        $available = $this->attribute($type, 'Available', 'available', 'boolean', null, '');
        $finish = $this->attribute($type, 'Finish', 'finish', 'select', null, '');
        $option = AttributeOption::query()->create(['attribute_id' => $finish->id, 'label' => 'Warm white', 'value' => 'warm_white']);

        $formatted = app(ProductCardSpecificationsFormatter::class)->format($type->id, [
            $count->id => '0.000001', $available->id => '0', $finish->id => $option->id,
        ], [$count->id, $available->id, $finish->id]);

        $this->assertSame('Count 0.000001 cm · Available No · Warm white', $formatted);
        $this->assertSame('', app(ProductCardSpecificationsFormatter::class)->format($type->id, [], [$count->id]));
    }

    public function test_required_attribute_can_be_displayed_and_edit_reloads_saved_order(): void
    {
        [$brand, $supplier, $type] = $this->references();
        $required = $this->attribute($type, 'Height', 'height', 'number', 'cm', 'H');
        $type->attributes()->updateExistingPivot($required->id, ['is_required' => true]);
        $optional = $this->attribute($type, 'Finish', 'finish', 'text');
        $product = $this->save($brand, $supplier, $type, [
            'attribute_values' => [$optional->id => 'Silver leaf'],
            'selected_attribute_ids' => [$optional->id],
            'card_display_attribute_ids' => [['attribute_id' => $required->id], ['attribute_id' => $optional->id]],
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $displayRows = Livewire::actingAs($admin)->test(EditProduct::class, ['record' => $product->id])
            ->get('data.card_display_attribute_ids');
        $this->assertSame([$required->id, $optional->id], array_map(
            fn (array $row): int => (int) $row['attribute_id'],
            array_values($displayRows),
        ));

        $label = Attribute::query()->create(['name' => 'Diameter', 'code' => 'diameter', 'type' => 'number', 'short_label' => '  <b>Ø</b>  ']);
        $this->assertSame('Ø', $label->fresh()->short_label);
    }

    private function references(): array
    {
        return [Brand::query()->create(['name' => 'Brand']), Supplier::query()->create(['name' => 'Supplier']), ProductType::query()->create(['name' => 'Planter'])];
    }

    private function attribute(ProductType $type, string $name, string $code, string $attributeType, ?string $unit = null, ?string $shortLabel = null): Attribute
    {
        $attribute = Attribute::query()->create(['name' => $name, 'code' => $code, 'type' => $attributeType, 'unit' => $unit, 'short_label' => $shortLabel]);
        $type->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);

        return $attribute;
    }

    private function save(Brand $brand, Supplier $supplier, ProductType $type, array $overrides = []): Product
    {
        return app(ProductCategoryManager::class)->save(array_merge([
            'brand_id' => $brand->id, 'supplier_id' => $supplier->id, 'product_type_id' => $type->id,
            'sku' => 'SKU-'.Str::random(10), 'name' => 'Planter', 'attribute_values' => [], 'selected_attribute_ids' => [],
        ], $overrides));
    }
}
