<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The browser and PHP reach Reverb at different addresses under Docker, and
 * getting that wrong fails silently: the browser connects, the websocket looks
 * healthy, and nothing is ever published. This pins the split so a
 * `config:publish broadcasting` — which would restore Laravel's single-address
 * default — cannot quietly take live updates down again.
 */
class BroadcastAddressTest extends TestCase
{
    public function test_the_project_owns_its_broadcasting_config(): void
    {
        $this->assertFileExists(
            base_path('config/broadcasting.php'),
            'Sem esse arquivo o Laravel cai no default do framework, que publica no endereço do navegador.',
        );
    }

    public function test_the_server_publishes_to_the_backend_address_not_the_browser_one(): void
    {
        $this->assertSame('reverb-service', $this->hostWith(
            backend: 'reverb-service',
            browser: 'localhost',
        ));
    }

    public function test_it_falls_back_to_the_browser_address_when_there_is_only_one(): void
    {
        // Single host, no Compose network: both sides dial the same name.
        $this->assertSame('ws.example.com', $this->hostWith(
            backend: null,
            browser: 'ws.example.com',
        ));
    }

    private function hostWith(?string $backend, ?string $browser): string
    {
        $previous = [
            'REVERB_BACKEND_HOST' => getenv('REVERB_BACKEND_HOST'),
            'REVERB_HOST' => getenv('REVERB_HOST'),
        ];

        $this->putenv('REVERB_BACKEND_HOST', $backend);
        $this->putenv('REVERB_HOST', $browser);

        try {
            $config = require base_path('config/broadcasting.php');

            return (string) $config['connections']['reverb']['options']['host'];
        } finally {
            foreach ($previous as $key => $value) {
                $this->putenv($key, $value === false ? null : $value);
            }
        }
    }

    private function putenv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
