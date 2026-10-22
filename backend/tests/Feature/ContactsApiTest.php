<?php

namespace Tests\Feature;

use App\Models\Showroom;
use App\Models\SiteContactSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContactsApiTest extends TestCase
{
    use RefreshDatabase;

    private function showroom(string $id, array $overrides = []): Showroom
    {
        return Showroom::query()->create(array_merge([
            'public_id' => $id, 'name' => ucfirst($id), 'address' => 'Address '.$id,
            'city' => 'Dubai', 'country' => 'UAE', 'phones' => [['label' => '+971 1', 'href' => 'tel:+9711']],
            'emails' => [], 'status' => 'published', 'sort_order' => 0,
        ], $overrides));
    }

    public function test_api_returns_published_showrooms_in_stable_order_and_primary_as_full_public_object(): void
    {
        $later = $this->showroom('later', ['sort_order' => 1]);
        $primary = $this->showroom('primary', ['name' => 'Main', 'sort_order' => 0, 'emails' => ['main@example.com']]);
        $this->showroom('draft', ['status' => 'draft', 'sort_order' => 0]);
        SiteContactSetting::query()->findOrFail(1)->update(['primary_showroom_id' => $primary->id, 'whatsapp_url' => null]);

        $response = $this->getJson('/api/v1/contacts')->assertOk()
            ->assertJsonPath('data.primary.id', 'primary')
            ->assertJsonPath('data.primary.emails.0', 'main@example.com')
            ->assertJsonPath('data.whatsappUrl', null)
            ->assertJsonPath('data.showrooms.0.id', 'primary')
            ->assertJsonPath('data.showrooms.1.id', 'later')
            ->assertJsonCount(2, 'data.showrooms');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['status', 'sort_order', 'primary_showroom_id', 'public_id', 'storage_path', 'created_at'] as $internal) {
            $this->assertStringNotContainsString($internal, $response->getContent());
        }
        $this->assertNotSame($later->public_id, $primary->public_id);
    }

    public function test_hidden_or_deleted_primary_is_null_and_empty_database_returns_empty_contract(): void
    {
        $response = $this->getJson('/api/v1/contacts')->assertOk()
            ->assertJsonPath('data.primary', null)->assertJsonPath('data.whatsappUrl', null)->assertJsonPath('data.showrooms', []);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $primary = $this->showroom('primary');
        SiteContactSetting::query()->findOrFail(1)->update(['primary_showroom_id' => $primary->id]);
        $primary->update(['status' => 'draft']);
        $this->getJson('/api/v1/contacts')->assertOk()->assertJsonPath('data.primary', null)->assertJsonCount(0, 'data.showrooms');
        $primary->update(['status' => 'published']);
        $primary->delete();
        $this->getJson('/api/v1/contacts')->assertOk()->assertJsonPath('data.primary', null);
    }

    public function test_server_rejects_invalid_publication_and_invalid_settings(): void
    {
        $draft = Showroom::query()->create(['public_id' => 'draft']);
        try {
            $draft->update(['status' => 'published']);
            $this->fail('Incomplete showroom must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
            $this->assertArrayHasKey('address', $exception->errors());
        }

        $draft->update(['name' => 'Complete', 'address' => 'Address', 'city' => 'Dubai', 'country' => 'UAE', 'phones' => []]);
        $draft->update(['status' => 'published']);
        try {
            SiteContactSetting::query()->findOrFail(1)->update(['primary_showroom_id' => null, 'whatsapp_url' => 'javascript:alert(1)']);
            $this->fail('Invalid WhatsApp URL must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('whatsapp_url', $exception->errors());
        }
        try {
            SiteContactSetting::query()->findOrFail(1)->update(['primary_showroom_id' => $draft->id]);
        } catch (ValidationException $exception) {
            $this->fail('Published showroom should be a valid primary: '.json_encode($exception->errors()));
        }
    }

    public function test_published_instagram_contact_requires_both_handle_and_url(): void
    {
        try {
            $this->showroom('partial-instagram', ['instagram_handle' => '@example']);
            $this->fail('An Instagram handle without its URL must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('instagram_url', $exception->errors());
        }

        try {
            $this->showroom('partial-instagram-url', ['instagram_url' => 'https://www.instagram.com/example']);
            $this->fail('An Instagram URL without its handle must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('instagram_handle', $exception->errors());
        }
    }
}
