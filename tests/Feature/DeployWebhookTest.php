<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeployWebhookTest extends TestCase
{
    public function test_deploy_webhook_rejects_unauthorized_when_secret_set(): void
    {
        Config::set('services.deploy.webhook_secret', 'test-secret');

        $response = $this->postJson('/webhooks/deploy', [
            'ref' => 'refs/heads/main',
        ]);

        $response->assertStatus(403);
    }

    public function test_deploy_webhook_accepts_valid_signature_or_empty_secret(): void
    {
        Config::set('services.deploy.webhook_secret', 'test-secret');
        $payload = json_encode(['ref' => 'refs/heads/other-branch']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-secret');

        $response = $this->call(
            'POST',
            '/webhooks/deploy',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Not target branch, ignored']);
    }
}
