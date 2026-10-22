<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SocialWebhookController extends Controller
{
    /**
     * Handle Official Partner Webhook Verification (GET) & Event Notifications (POST)
     */
    public function handle(Request $request, string $platform): Response|JsonResponse
    {
        // 1. Meta (Instagram / Facebook / WhatsApp) & YouTube PubSubHubbub Verification Challenge
        if ($request->isMethod('GET')) {
            if ($request->has('hub_challenge') || $request->has('hub.challenge')) {
                $challenge = $request->query('hub_challenge') ?? $request->query('hub_challenge', $request->input('hub.challenge'));
                return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
            }

            // X (Twitter) Account Activity API CRC Challenge
            if ($platform === 'x' && $request->has('crc_token')) {
                $secret = config('services.x.webhook_secret', 'socialflow_x_verify');
                $hash = base64_encode(hash_hmac('sha256', (string) $request->query('crc_token'), $secret, true));
                return response()->json([
                    'response_token' => 'sha256=' . $hash,
                ]);
            }

            return response()->json([
                'status' => 'ok',
                'platform' => $platform,
                'webhook' => 'active',
            ]);
        }

        // 2. Incoming Webhook Event Payload (POST)
        $payload = $request->all();
        Log::info("SocialWebhook [{$platform}] event received", [
            'platform' => $platform,
            'payload' => $payload,
        ]);

        // Handle deauthorization / token revocation events automatically
        if (isset($payload['event']) && in_array($payload['event'], ['authorization.removed', 'deauthorize', 'token_revoked'], true)) {
            $externalId = $payload['open_id'] ?? $payload['user_id'] ?? $payload['account_id'] ?? null;
            if ($externalId) {
                SocialAccount::where('account_id', $externalId)->update(['status' => 'revoked']);
            }
        }

        return response()->json([
            'received' => true,
            'platform' => $platform,
        ]);
    }
}
