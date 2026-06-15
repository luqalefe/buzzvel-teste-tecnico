<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Employee',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'country' => 'Brazil',
            'currency' => 'BRL',
        ], $overrides);
    }

    // ── US-A1: Registration ────────────────────────────────────────────────

    public function test_a_visitor_can_register_and_receives_a_token_and_their_data(): void
    {
        $response = $this->postJson('/api/register', $this->validRegistrationPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'country', 'currency', 'role', 'created_at'],
            ])
            ->assertJsonPath('user.email', 'alice@example.com')
            ->assertJsonPath('user.role', Role::Employee->value)
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'country' => 'Brazil',
            'currency' => 'BRL',
            'role' => Role::Employee->value,
        ]);
    }

    public function test_the_token_returned_at_registration_authenticates_protected_routes(): void
    {
        $token = $this->postJson('/api/register', $this->validRegistrationPayload())
            ->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'alice@example.com');
    }

    public function test_registration_rejects_a_duplicate_email_with_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', $this->validRegistrationPayload(['email' => 'taken@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_an_invalid_currency_with_422(): void
    {
        $this->postJson('/api/register', $this->validRegistrationPayload(['currency' => 'XYZ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    public function test_registration_does_not_let_a_visitor_self_assign_the_finance_role(): void
    {
        $this->postJson('/api/register', $this->validRegistrationPayload(['role' => 'finance']))
            ->assertCreated()
            ->assertJsonPath('user.role', Role::Employee->value);
    }

    // ── US-A2: Login ───────────────────────────────────────────────────────

    public function test_a_registered_user_can_log_in_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', ['email' => 'bob@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']]);
    }

    public function test_login_with_wrong_credentials_returns_401_with_a_generic_message(): void
    {
        User::factory()->create([
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', ['email' => 'bob@example.com', 'password' => 'wrong-password']);

        $response->assertStatus(401)
            ->assertJsonStructure(['message'])
            // generic message must not disclose which field was wrong
            ->assertJsonMissingPath('errors');
    }

    public function test_login_is_rate_limited_after_repeated_attempts(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);

        // The limiter allows 5/min per email+IP; the 6th attempt is throttled.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'bob@example.com', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'bob@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    // ── US-A3: Logout ──────────────────────────────────────────────────────

    public function test_a_user_can_log_out_and_the_token_is_revoked(): void
    {
        $token = $this->postJson('/api/register', $this->validRegistrationPayload())->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_revoked_token_can_no_longer_access_protected_routes(): void
    {
        $token = $this->postJson('/api/register', $this->validRegistrationPayload())->json('token');
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        // The test container reuses one resolved guard across requests; flushing
        // it models the fresh guard a real, separate HTTP request would build.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_protected_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
