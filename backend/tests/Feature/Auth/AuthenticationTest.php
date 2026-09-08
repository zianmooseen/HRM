<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_read_current_user_and_logout(): void
    {
        User::query()->create([
            'name' => 'Admin User',
            'username' => 'sys.admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/login', [
                'login' => 'sys.admin',
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'sys.admin')
            ->assertJsonPath('data.user.email', 'admin@example.com');

        $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com');

        $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_json_login_without_stateful_session_returns_user_payload(): void
    {
        User::query()->create([
            'name' => 'Admin User',
            'username' => 'sys.admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this
            ->postJson('/api/login', [
                'login' => 'sys.admin',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'sys.admin');
    }
}
