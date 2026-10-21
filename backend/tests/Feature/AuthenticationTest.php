<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        RateLimiterFacade::clearResolvedInstance();
        app()->forgetInstance(RateLimiter::class);
        Cache::store('array')->flush();
        auth()->forgetGuards();
        session()->flush();
        Filament::setCurrentPanel('admin');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.random_int(1, 254)]);
    }

    protected function tearDown(): void
    {
        auth()->guard('web')->logout();
        session()->flush();
        Filament::setCurrentPanel(null);
        Cache::store('array')->flush();
        RateLimiterFacade::clearResolvedInstance();
        app()->forgetInstance(RateLimiter::class);

        parent::tearDown();
    }

    public function test_created_administrator_can_authenticate_through_filament(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk();
    }

    public function test_regular_user_cannot_authenticate_through_filament(): void
    {
        $user = User::factory()->create();
        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.email']);
        $this->assertGuest();
    }
}
