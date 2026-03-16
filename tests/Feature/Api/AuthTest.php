<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Role $customerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerRole = Role::factory()->customer()->create();
    }

    // ---------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token']);

        $this->assertDatabaseHas('users', ['phone' => '+24177000001']);
    }

    public function test_register_requires_phone(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_register_requires_first_name_and_last_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'phone' => '+24177000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_register_requires_minimum_8_char_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+24177000001']);

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_register_allows_optional_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'phone' => '+24177000001',
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_assigns_customer_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+24177000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('phone', '+24177000001')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->customerRole->id, $user->role_id);
    }

    // ---------------------------------------------------------------
    // Login
    // ---------------------------------------------------------------

    public function test_login_with_phone_returns_token(): void
    {
        User::factory()->create([
            'phone' => '+24177000002',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '+24177000002',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_with_email_returns_token(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+24177000003',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '+24177000003',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Identifiants incorrects');
    }

    public function test_login_fails_for_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => '+24177999999',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'phone' => '+24177000004',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => '+24177000004',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Votre compte a été désactivé');
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'phone' => '+24177000005',
            'password' => bcrypt('password123'),
            'last_login_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'login' => '+24177000005',
            'password' => 'password123',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    // ---------------------------------------------------------------
    // Logout
    // ---------------------------------------------------------------

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Déconnexion réussie');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Me (current user)
    // ---------------------------------------------------------------

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user' => ['id', 'first_name', 'last_name', 'email', 'phone', 'role']]);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Change Password
    // ---------------------------------------------------------------

    public function test_change_password_succeeds_with_correct_current(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword123'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Mot de passe modifié avec succès');
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword123'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Mot de passe actuel incorrect');
    }
}
