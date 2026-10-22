<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeployWebhookController extends Controller
{
    /**
     * Handle GitHub / GitLab Auto-Deploy Webhook
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.deploy.webhook_secret', env('DEPLOY_WEBHOOK_SECRET'));

        // If secret is set, verify GitHub HMAC SHA256 signature
        if (!empty($secret)) {
            $signature = $request->header('X-Hub-Signature-256');
            if (!$signature) {
                return response()->json(['error' => 'Signature missing'], 403);
            }

            $computedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($computedSignature, $signature)) {
                Log::warning('Deploy webhook signature mismatch');
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        // Check if event is push to main
        $payload = $request->json()->all();
        $ref = $payload['ref'] ?? '';
        if ($ref && !in_array($ref, ['refs/heads/main', 'refs/heads/master'], true)) {
            return response()->json(['message' => 'Not target branch, ignored', 'ref' => $ref], 200);
        }

        Log::info('Deploy webhook triggered: executing deployment script...');

        $scriptPath = base_path('deploy.sh');
        if (file_exists($scriptPath)) {
            $process = new Process(['bash', $scriptPath], base_path());
            $process->setTimeout(300);
            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            Log::info('Deploy webhook output', [
                'output' => $output,
                'error' => $errorOutput,
                'exit_code' => $process->getExitCode(),
            ]);

            return response()->json([
                'success' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'message' => 'Deployment executed successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'deploy.sh script not found',
        ], 404);
    }
}
