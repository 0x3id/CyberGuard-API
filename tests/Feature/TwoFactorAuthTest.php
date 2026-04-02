<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 2FA setup returns QR code
     */
    public function test_2fa_setup_returns_qr_code(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_enabled' => false,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'qr_code',
                    'secret'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => '2FA setup initiated'
            ]);
    }

    /**
     * Test enabling 2FA with valid code
     */
    public function test_enable_2fa_with_valid_code(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_enabled' => false,
        ]);

        // Setup first
        $setupResponse = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/setup');

        $secret = $setupResponse->json('data.secret');

        // Generate a valid TOTP code
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $code = $google2fa->getCurrentOtp($secret);

        // Enable 2FA
        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/enable', [
                'code' => $code
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => '2FA enabled successfully'
            ]);

        // Verify user has 2FA enabled
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_secret);
    }

    /**
     * Test login returns 2FA required when 2FA is enabled
     */
    public function test_login_returns_2fa_required(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'two_factor_enabled' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'requires_2fa',
                    'email'
                ]
            ])
            ->assertJson([
                'data' => [
                    'requires_2fa' => true,
                    'email' => 'test@example.com'
                ]
            ]);
    }

    /**
     * Test login without 2FA returns token
     */
    public function test_login_without_2fa_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'two_factor_enabled' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'requires_2fa',
                    'user',
                    'token'
                ]
            ])
            ->assertJson([
                'data' => [
                    'requires_2fa' => false
                ]
            ]);
    }

    /**
     * Test verifying 2FA code during login
     */
    public function test_verify_2fa_code(): void
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'email_verified_at' => now(),
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'email' => 'test@example.com',
            'code' => $code
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user',
                    'token'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => '2FA verified successfully'
            ]);
    }

    /**
     * Test verify 2FA with invalid code
     */
    public function test_verify_2fa_with_invalid_code(): void
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'email' => 'test@example.com',
            'code' => '000000'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid verification code'
            ]);
    }

    /**
     * Test getting 2FA status
     */
    public function test_get_2fa_status(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/auth/2fa/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'two_factor_enabled'
                ]
            ])
            ->assertJson([
                'data' => [
                    'two_factor_enabled' => true
                ]
            ]);
    }

    /**
     * Test disabling 2FA
     */
    public function test_disable_2fa(): void
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'email_verified_at' => now(),
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/auth/2fa/disable', [
                'code' => $code
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => '2FA disabled successfully'
            ]);

        // Verify 2FA is disabled
        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
    }
}
