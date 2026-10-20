<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every request reaches the app through nginx. If the proxy's address is not
 * trusted, $request->ip() is nginx for every visitor, and every per-IP
 * throttle in the app — login lockout, the public AI chat — becomes a single
 * bucket shared by the whole site.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__ip-probe', fn () => request()->ip());
    }

    public function test_the_client_address_comes_through_a_private_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->get('/__ip-probe', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertSeeText('203.0.113.9');
    }

    public function test_a_public_address_cannot_claim_to_be_a_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/__ip-probe', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertSeeText('198.51.100.7');
    }
}
