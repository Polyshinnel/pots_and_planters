<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_health_checks_database_and_redis(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);
        Redis::shouldReceive('connection->ping')->once()->andReturn(true);
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_health_reports_dependency_failure_without_details(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);
        Redis::shouldReceive('connection->ping')->once()->andThrow(new \RuntimeException('internal connection details'));
        $this->getJson('/api/health')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_admin_requires_login_and_serves_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Pots &amp; Planters', false);
    }

    public static function environments(): array
    {
        return [['local'], ['production']];
    }

    #[DataProvider('environments')]
    public function test_horizon_requires_admin_in_every_environment(string $environment): void
    {
        $this->app['env'] = $environment;
        $this->get('/horizon')->assertRedirect('/admin/login');
        $user = User::factory()->make(['id' => 1]);
        $this->actingAs($user)->get('/horizon')->assertForbidden();
        $user->forceFill(['is_admin' => true]);
        $this->actingAs($user)->get('/horizon')->assertOk();
    }
}
