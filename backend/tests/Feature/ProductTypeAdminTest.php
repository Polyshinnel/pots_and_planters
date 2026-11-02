<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductTypeResource;
use App\Filament\Resources\ProductTypeResource\Pages\CreateProductType;
use App\Filament\Resources\ProductTypeResource\Pages\EditProductType;
use App\Filament\Resources\ProductTypeResource\Pages\ListProductTypes;
use App\Models\ProductType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_product_type_generates_an_immutable_public_id_and_defaults_to_active(): void
    {
        $type = ProductType::query()->create(['name' => '  Planter  ']);

        $this->assertSame('Planter', $type->name);
        $this->assertTrue($type->is_active);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $type->public_id);
        $this->assertNotNull($type->created_at);
        $this->assertNotNull($type->updated_at);

        $type->public_id = (string) Str::uuid();
        $this->expectException(ValidationException::class);
        $type->save();
    }

    public function test_name_is_trimmed_and_must_be_nonblank_and_within_limit(): void
    {
        foreach ([" \t ", str_repeat('x', 256)] as $name) {
            try {
                ProductType::query()->create(['name' => $name]);
                $this->fail('Invalid names must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }
    }

    public function test_admin_can_create_edit_search_sort_filter_and_archive_without_deleting(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(CreateProductType::class)
            ->set('data.name', '  Outdoor Planter  ')
            ->call('create')
            ->assertHasNoFormErrors();

        $type = ProductType::query()->where('name', 'Outdoor Planter')->firstOrFail();
        $originalPublicId = $type->public_id;

        Livewire::actingAs($admin)->test(EditProductType::class, ['record' => $type->getKey()])
            ->set('data.name', 'Garden Pot')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $type->refresh();
        $this->assertSame('Garden Pot', $type->name);
        $this->assertSame($originalPublicId, $type->public_id);
        $this->assertFalse($type->is_active);
        $this->assertDatabaseHas('product_types', ['id' => $type->id, 'is_active' => false]);

        Livewire::actingAs($admin)->test(ListProductTypes::class)
            ->searchTable('Garden Pot')
            ->assertCanSeeTableRecords([$type]);
        Livewire::actingAs($admin)->test(ListProductTypes::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$type]);
        Livewire::actingAs($admin)->test(ListProductTypes::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$type]);

        $this->assertFalse(ProductTypeResource::canDelete($type));
        $this->assertFalse(ProductTypeResource::canDeleteAny());
        $this->assertDatabaseHas('product_types', ['id' => $type->id]);
    }

    public function test_non_admin_cannot_access_product_type_resource(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/product-types')->assertForbidden();
    }
}
