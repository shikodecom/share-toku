<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(array $attributes = []): void
    {
        Socialite::fake('google', GoogleUser::fake(array_merge([
            'id' => 'google-001', 'name' => 'Test User', 'email' => 'test@example.com',
        ], $attributes)));
    }

    public function test_login_page_and_removed_password_routes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Googleでログイン')->assertDontSee('type="password"', false)->assertDontSee('/register');
        $this->get('/')->assertOk()->assertDontSee('/register');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'password'])->assertStatus(405);
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/dashboard');
    }

    public function test_google_redirect(): void
    {
        Socialite::fake('google');
        $this->get('/auth/google/redirect')->assertRedirect();
    }

    public function test_real_provider_uses_session_state_and_identity_scopes(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost:8000/auth/google/callback']);
        $response = $this->get('/auth/google/redirect')->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('http://localhost:8000/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame(session('state'), $query['state']);
        $this->get('/auth/google/callback?state=incorrect&code=unused')->assertRedirect('/login')
            ->assertSessionHas('auth_error', 'Googleログインの確認に失敗しました。もう一度お試しください。');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_migration_can_rollback_with_null_password_and_reapply(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_10_02_000002_add_google_auth_to_users.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'google_id'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'password' => null]);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'password' => null, 'google_id' => null]);
    }

    public function test_first_login_creates_personal_workspace_and_preserves_intended_url(): void
    {
        $this->fakeGoogle();
        $session = app('session.store');
        $session->start();
        $oldId = $session->getId();
        $this->withSession(['url.intended' => '/site-connections/authorize?site=example'])
            ->get('/auth/google/callback')->assertRedirect('/site-connections/authorize?site=example');
        $this->assertNotSame($oldId, $session->getId());
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['google_id' => 'google-001', 'email' => 'test@example.com', 'password' => null]);
        $this->assertNotNull($user->email_verified_at);
        $workspace = Workspace::sole();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'type' => 'personal', 'owner_user_id' => $user->id, 'name' => 'Test User のWorkspace']);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);
        $this->assertArrayNotHasKey('token', $user->getAttributes());
        $this->get('/dashboard')->assertOk();
    }

    public function test_repeat_login_updates_profile_without_duplicates(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $user = User::sole();
        $verifiedAt = $user->email_verified_at;
        $this->post('/logout');
        $this->fakeGoogle(['name' => ' New Name ', 'email' => ' NEW@EXAMPLE.COM ']);
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_members', 1);
    }

    public function test_name_fallback_and_verification_of_existing_user(): void
    {
        $user = User::factory()->unverified()->create(['google_id' => 'google-001']);
        $this->fakeGoogle(['name' => '']);
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertSame('test', $user->fresh()->name);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_email_collision_does_not_link_google_identity(): void
    {
        foreach (['google-old', null] as $googleId) {
            $existing = User::factory()->create(['google_id' => $googleId, 'email' => 'test@example.com']);
            $this->fakeGoogle();
            $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('auth_error');
            $this->assertGuest();
            $this->assertSame($googleId, $existing->fresh()->google_id);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('workspaces', 0);
            $existing->delete();
        }
    }

    public function test_existing_identity_cannot_take_another_users_email(): void
    {
        $user = User::factory()->create(['google_id' => 'google-001', 'email' => 'old@example.com']);
        User::factory()->create(['email' => 'test@example.com']);
        $this->fakeGoogle();
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_required_google_attributes(): void
    {
        foreach ([['email' => null], ['id' => ' ']] as $attributes) {
            $this->fakeGoogle($attributes);
            $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('auth_error');
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('workspaces', 0);
        }
    }

    public function test_oauth_cancel_and_error_do_not_call_provider_or_change_login(): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/login')
            ->assertSessionHas('auth_error', 'Googleログインをキャンセルしました。');
        $this->assertGuest();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/auth/google/callback?error=server_error')->assertRedirect('/login');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_invalid_state(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->get('/auth/google/callback')->assertRedirect('/login')
            ->assertSessionHas('auth_error', 'Googleログインの確認に失敗しました。もう一度お試しください。');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provider_exception_is_logged_without_secrets(): void
    {
        Log::shouldReceive('warning')->once()->with('Google login provider failed.', ['exception_class' => RuntimeException::class]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('secret-token-code-state'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->get('/auth/google/callback?code=secret-code&state=secret-state')->assertRedirect('/login');
        $this->get('/login')->assertDontSee('secret')->assertSee('Googleログインに失敗しました。');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_value' => 'secret']);
        $session = app('session.store');
        $session->regenerateToken();
        $oldToken = $session->token();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_value');
        $this->assertGuest();
        $this->assertNotSame($oldToken, $session->token());
    }
}
