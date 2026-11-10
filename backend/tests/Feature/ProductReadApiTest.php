<?php

namespace Tests\Feature;

use App\Data\ProductListData;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_detail_return_contract_dtos_and_hide_private_product_data(): void
    {
        Storage::fake('public');
        [$product, $category] = $this->product();
        $product->update(['price_aed' => '12.3400', 'description' => '<p>Safe</p><script>bad()</script>']);
        $this->getJson('/api/v1/products?category='.$category->slug)
            ->assertOk()->assertHeader('Cache-Control')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $product->public_id)->assertJsonPath('data.0.priceAed', '12.3400')
            ->assertJsonPath('data.0.canonicalPath', '/shop/'.$category->slug.'/'.$product->slug)
            ->assertJsonMissingPath('data.0.supplier_id')->assertJsonMissingPath('data.0.id_internal');
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertHeader('Cache-Control')
            ->assertJsonPath('data.descriptionHtml', '<p>Safe</p>')->assertJsonPath('data.seo.robots', 'noindex,follow')
            ->assertJsonPath('data.seo.title', $product->name)->assertJsonPath('data.seo.description', null);
    }

    public function test_resolve_returns_public_dto_in_input_order_and_unavailable_is_private(): void
    {
        [$first] = $this->product(['sku' => 'SAME', 'price_aed' => '12.3400']);
        [$second] = $this->product(['sku' => 'SAME', 'price_aed' => null]);
        $hidden = $this->product(['name' => 'Secret Name'])[0];
        $hidden->update(['status' => 'draft']);
        $unknown = (string) Str::uuid();

        $response = $this->postJson('/api/v1/products/resolve', ['ids' => [$second->public_id, $unknown, $first->public_id, $hidden->public_id]])
            ->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertExactJson(['data' => [
            ['id' => $second->public_id, 'status' => 'available', 'product' => (new ProductListData($second->load(['brand', 'primaryCategory', 'categories', 'productType.attributes.options', 'attributeValues.selections.option', 'images.mediaAsset'])))->toArray()],
            ['id' => $unknown, 'status' => 'unavailable'],
            ['id' => $first->public_id, 'status' => 'available', 'product' => (new ProductListData($first->load(['brand', 'primaryCategory', 'categories', 'productType.attributes.options', 'attributeValues.selections.option', 'images.mediaAsset'])))->toArray()],
            ['id' => $hidden->public_id, 'status' => 'unavailable'],
        ]])->assertJsonMissing(['name' => 'Secret Name']);
    }

    public function test_resolve_hides_archived_products_inactive_suppliers_and_unpublished_primary_categories(): void
    {
        [$archived] = $this->product(['name' => 'Archived Secret']);
        $archived->update(['status' => 'archived']);
        [$inactiveSupplier] = $this->product(['name' => 'Supplier Secret']);
        $inactiveSupplier->supplier()->update(['is_active' => false]);
        [$unpublishedCategory, $category] = $this->product(['name' => 'Category Secret']);
        $category->update(['status' => 'draft']);

        $this->postJson('/api/v1/products/resolve', ['ids' => [$archived->public_id, $inactiveSupplier->public_id, $unpublishedCategory->public_id]])
            ->assertOk()->assertExactJson(['data' => [
                ['id' => $archived->public_id, 'status' => 'unavailable'],
                ['id' => $inactiveSupplier->public_id, 'status' => 'unavailable'],
                ['id' => $unpublishedCategory->public_id, 'status' => 'unavailable'],
            ]])->assertJsonMissing(['name' => 'Archived Secret'])
            ->assertJsonMissing(['name' => 'Supplier Secret'])->assertJsonMissing(['name' => 'Category Secret']);
    }

    public function test_resolve_rejects_invalid_batches_and_preserves_existing_product_routes(): void
    {
        $valid = (string) Str::uuid();
        foreach ([[], array_fill(0, 101, $valid), [$valid, $valid], ['bad'], ['x' => $valid]] as $ids) {
            $this->postJson('/api/v1/products/resolve', ['ids' => $ids])->assertUnprocessable()
                ->assertJsonStructure(['error' => ['code', 'message', 'requestId', 'errors']]);
        }
        $this->postJson('/api/v1/products/resolve', ['ids' => [$valid], 'extra' => true])->assertUnprocessable();
        [$product] = $this->product();
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk();
        $this->getJson('/api/v1/products/resolve')->assertNotFound();
    }

    public function test_resolve_accepts_one_hundred_ids_without_per_product_queries(): void
    {
        $products = [];
        for ($index = 0; $index < 100; $index++) {
            $products[] = $this->product(['name' => 'Batch '.$index])[0];
        }
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });
        $this->postJson('/api/v1/products/resolve', ['ids' => array_map(fn (Product $product) => $product->public_id, $products)])
            ->assertOk()->assertJsonCount(100, 'data')->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.99.id', $products[99]->public_id);
        $batchQueryCount = count($queries);
        $this->assertLessThanOrEqual(12, $batchQueryCount, 'Batch resolution should eager-load relations rather than query per product.');
    }

    public function test_resolve_returns_standard_503_envelope_when_database_is_unavailable(): void
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}");
        config(["database.connections.{$connection}.host" => '127.0.0.1', "database.connections.{$connection}.port" => 1]);
        DB::purge($connection);
        try {
            $this->postJson('/api/v1/products/resolve', ['ids' => [(string) Str::uuid()]])
                ->assertServiceUnavailable()->assertJsonStructure(['error' => ['code', 'message', 'requestId']])
                ->assertJsonPath('error.code', 'service_unavailable');
        } finally {
            config(["database.connections.{$connection}" => $settings]);
            DB::purge($connection);
        }
    }

    public function test_visibility_secondary_category_sort_and_unknown_responses(): void
    {
        [$first, $primary] = $this->product(['name' => 'Zulu', 'price_aed' => null]);
        $secondary = $this->category('secondary');
        $first->categories()->attach($secondary->id);
        [$second] = $this->product(['name' => 'Alpha', 'price_aed' => '20.0000']);
        $this->getJson('/api/v1/products?category=secondary')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/products?sort=name-asc')->assertOk()->assertJsonPath('data.0.name', 'Alpha');
        $this->getJson('/api/v1/products?category=absent')->assertNotFound()->assertJsonPath('error.code', 'category_not_found')->assertJsonStructure(['error' => ['requestId']]);
        $this->getJson('/api/v1/products/absent')->assertNotFound()->assertJsonPath('error.code', 'product_not_found');
        $first->supplier()->update(['is_active' => false]);
        $this->getJson('/api/v1/products/'.$first->slug)->assertNotFound();
        $this->getJson('/api/v1/products?perPage=49')->assertUnprocessable()->assertJsonStructure(['error' => ['errors']]);
        $this->assertSame(2, Product::query()->count());
    }

    public function test_typed_decimal_filter_and_legacy_keys_fail_closed(): void
    {
        [$product, $category, $type] = $this->product();
        $attribute = Attribute::query()->create(['name' => 'Height', 'code' => 'height', 'type' => 'number', 'is_active' => true, 'is_filterable' => true]);
        $type->attributes()->attach($attribute->id);
        $category->filterAttributes()->attach($attribute->id, ['display_type' => 'range', 'sort_order' => 0]);
        $product->attributeValues()->create(['product_type_id' => $type->id, 'attribute_id' => $attribute->id, 'number_value' => '0.000001']);
        $this->getJson('/api/v1/products?category='.$category->slug.'&filter[height][min]=0.000001&filter[height][max]=0.000001')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('facets.0.code', 'height');
        $this->getJson('/api/v1/products?category='.$category->slug.'&filter[height][min]=2&filter[height][max]=1')
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->getJson('/api/v1/products?category='.$category->slug.'&material=ceramic')
            ->assertUnprocessable()->assertJsonPath('error.errors.material.0', 'This legacy filter has no confirmed mapping for the requested category.');
    }

    public function test_global_filters_and_facets_use_visible_products_and_conjunctive_counts(): void
    {
        [$ceramicLow, $category, $type] = $this->product(['name' => 'Ceramic Low']);
        [$ceramicHigh, , $ceramicHighType] = $this->product(['name' => 'Ceramic High']);
        [$metalHigh, , $metalHighType] = $this->product(['name' => 'Metal High']);

        $material = Attribute::query()->create(['name' => 'Material', 'code' => 'material_type', 'type' => 'select', 'is_active' => true, 'is_filterable' => true]);
        $height = Attribute::query()->create(['name' => 'Height', 'code' => 'height', 'type' => 'number', 'is_active' => true, 'is_filterable' => true]);
        foreach ([$type, $ceramicHighType, $metalHighType] as $productType) {
            $productType->attributes()->attach([$material->id, $height->id]);
        }
        $ceramic = AttributeOption::query()->create(['attribute_id' => $material->id, 'label' => 'Ceramic', 'value' => 'ceramic']);
        $metal = AttributeOption::query()->create(['attribute_id' => $material->id, 'label' => 'Metal', 'value' => 'metal']);

        foreach ([[$ceramicLow, $ceramic, '0.000000'], [$ceramicHigh, $ceramic, '10.000000'], [$metalHigh, $metal, '20.000000']] as [$product, $option, $number]) {
            $value = $product->attributeValues()->create(['product_type_id' => $product->product_type_id, 'attribute_id' => $material->id]);
            $value->selections()->create(['attribute_id' => $material->id, 'attribute_option_id' => $option->id]);
            $product->attributeValues()->create(['product_type_id' => $product->product_type_id, 'attribute_id' => $height->id, 'number_value' => $number]);
        }
        $metalHigh->supplier()->update(['is_active' => false]);

        $this->getJson('/api/v1/products?filter[material_type][]=ceramic&filter[material_type][]=metal')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('facets.0.code', 'height');

        $this->getJson('/api/v1/products?filter[height]=0.000000')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ceramic Low');

        $response = $this->getJson('/api/v1/products?filter[material_type][]=ceramic&filter[height][min]=10&sort=name-asc&page=1&perPage=1');
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.sort', 'name-asc')
            ->assertJsonPath('meta.page', 1)->assertJsonPath('meta.perPage', 1)
            ->assertJsonPath('data.0.name', 'Ceramic High')
            ->assertJsonPath('facets.1.options.0.count', 1)
            ->assertJsonPath('facets.0.min', '10.000000')->assertJsonPath('facets.0.max', '10.000000')
            ->assertJsonMissingPath('facets.0.options');

        $this->getJson('/api/v1/products?category='.$category->slug.'&filter[material_type]=ceramic')
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_global_facets_do_not_include_inactive_or_unfilterable_attributes(): void
    {
        [$product, , $type] = $this->product();
        $active = Attribute::query()->create(['name' => 'Colour', 'code' => 'colour', 'type' => 'text', 'is_active' => true, 'is_filterable' => true]);
        $inactive = Attribute::query()->create(['name' => 'Hidden', 'code' => 'hidden', 'type' => 'text', 'is_active' => false, 'is_filterable' => true]);
        $notFilterable = Attribute::query()->create(['name' => 'Internal', 'code' => 'internal', 'type' => 'text', 'is_active' => true, 'is_filterable' => false]);
        $type->attributes()->attach([$active->id, $inactive->id, $notFilterable->id]);
        $product->attributeValues()->create(['product_type_id' => $type->id, 'attribute_id' => $active->id, 'text_value' => 'Green']);
        $product->attributeValues()->create(['product_type_id' => $type->id, 'attribute_id' => $inactive->id, 'text_value' => 'Secret']);
        $product->attributeValues()->create(['product_type_id' => $type->id, 'attribute_id' => $notFilterable->id, 'text_value' => 'Private']);

        $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('facets.0.code', 'colour')->assertJsonPath('facets.0.options.0.value', 'Green')
            ->assertJsonMissing(['code' => 'hidden'])->assertJsonMissing(['code' => 'internal']);
    }

    public function test_empty_list_is_successful_and_list_query_count_does_not_grow_per_product(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });
        $this->getJson('/api/v1/products')->assertOk()->assertExactJson(['data' => [], 'meta' => ['page' => 1, 'perPage' => 12, 'total' => 0, 'lastPage' => 1, 'sort' => 'newest'], 'facets' => []]);
        $emptyCount = count($queries);
        $this->product();
        $queries = [];
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.total', 1);
        $oneCount = count($queries);
        $this->product();
        $this->product();
        $queries = [];
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertSame($oneCount, count($queries));
        $this->assertGreaterThan(0, $emptyCount);
    }

    private function product(array $overrides = []): array
    {
        $brand = Brand::query()->create(['name' => 'Maker']);
        $supplier = Supplier::query()->create(['name' => 'Supplier']);
        $type = ProductType::query()->create(['name' => 'Pots', 'is_active' => false]);
        $category = $this->category('primary-'.uniqid());
        $product = Product::query()->create(array_merge(['brand_id' => $brand->id, 'supplier_id' => $supplier->id, 'product_type_id' => $type->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Planter', 'status' => 'draft', 'slug' => 'planter-'.uniqid()], $overrides, ['status' => 'draft', 'primary_category_id' => null]));
        $product->categories()->attach($category->id);
        DB::table('products')->where('id', $product->id)->update(['status' => 'published', 'primary_category_id' => $category->id]);
        $product->refresh();

        return [$product, $category, $type];
    }

    private function category(string $slug): Category
    {
        return Category::query()->create(['public_id' => 'cat-'.substr(md5($slug), 0, 12), 'slug' => $slug, 'name' => ucfirst($slug), 'heading' => ucfirst($slug), 'description' => 'Description', 'tile_description' => 'Tile', 'seo_title' => 'Title', 'seo_description' => 'Description', 'status' => 'published']);
    }
}
