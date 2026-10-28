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

        // 1. Run git pull origin main (find git directory or initialize it)
        $workDir = base_path();

        if (! is_dir($workDir . '/.git')) {
            // Initialize git repo if missing so it can pull directly from GitHub
            try {
                $initCmd = 'git init && git remote add origin https://github.com/smsonaylatr/social.git && git fetch origin main && git reset --hard origin/main';
                $initProcess = Process::fromShellCommandline($initCmd, $workDir);
                $initProcess->setTimeout(180);
                $initProcess->run();
                $outputs[] = "GIT INIT & SYNC: " . trim($initProcess->getOutput());
                if ($initProcess->getErrorOutput()) {
                    $outputs[] = "GIT INIT MSG: " . trim($initProcess->getErrorOutput());
                }
            } catch (\Throwable $e) {
                $errors[] = "GIT INIT EXCEPTION: " . $e->getMessage();
            }
        } else {
            try {
                $pullCmd = 'git fetch origin main && git reset --hard origin/main';
                $gitProcess = Process::fromShellCommandline($pullCmd, $workDir);
                $gitProcess->setTimeout(120);
                $gitProcess->run();
                $outputs[] = "GIT SYNC: " . trim($gitProcess->getOutput());
                if ($gitProcess->getErrorOutput()) {
                    $outputs[] = "GIT SYNC MSG: " . trim($gitProcess->getErrorOutput());
                }
            } catch (\Throwable $e) {
                $errors[] = "GIT PULL EXCEPTION: " . $e->getMessage();
            }
        }

        // 2. Clear caches cleanly
        try {
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $outputs[] = "ARTISAN: optimize:clear OK";
        } catch (\Throwable $e) {
            $errors[] = "OPTIMIZE ERR: " . $e->getMessage();
        }

        // Ensure framework view storage directories exist
        $viewPath = storage_path('framework/views');
        if (! is_dir($viewPath)) {
            @mkdir($viewPath, 0775, true);
        }

        // 3. Migrate if needed
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $outputs[] = "ARTISAN: migrate OK";
        } catch (\Throwable $e) {
            $errors[] = "MIGRATE ERR: " . $e->getMessage();
        }

        // 4. SQLite Optimization (WAL & Busy Timeout to prevent database locked errors)
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 60000;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous = NORMAL;');
                $outputs[] = "SQLITE: WAL mode & busy_timeout=60000 set OK";
            } catch (\Throwable $e) {
                $errors[] = "SQLITE PRAGMA ERR: " . $e->getMessage();
            }
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
