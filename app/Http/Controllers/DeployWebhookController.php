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

        $outputs = [];
        $errors = [];

        // 1. Run git pull origin main
        try {
            $gitProcess = Process::fromShellCommandline('git pull origin main', base_path());
            $gitProcess->setTimeout(120);
            $gitProcess->run();
            $outputs[] = "GIT: " . trim($gitProcess->getOutput());
            if ($gitProcess->getErrorOutput()) {
                $errors[] = "GIT ERR: " . trim($gitProcess->getErrorOutput());
            }
        } catch (\Throwable $e) {
            $errors[] = "GIT EXCEPTION: " . $e->getMessage();
        }

        // 2. Clear & optimize Laravel caches
        try {
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $outputs[] = "ARTISAN: optimize:clear OK";
        } catch (\Throwable $e) {
            $errors[] = "OPTIMIZE ERR: " . $e->getMessage();
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            $outputs[] = "ARTISAN: view:clear OK";
        } catch (\Throwable $e) {
            $errors[] = "VIEW ERR: " . $e->getMessage();
        }

        // 3. Migrate if needed
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $outputs[] = "ARTISAN: migrate OK";
        } catch (\Throwable $e) {
            $errors[] = "MIGRATE ERR: " . $e->getMessage();
        }

        Log::info('Deploy webhook executed', [
            'outputs' => $outputs,
            'errors' => $errors,
        ]);

        return response()->json([
            'success' => empty($errors),
            'message' => 'Deployment executed',
            'outputs' => $outputs,
            'errors' => $errors,
        ]);
    }
}
